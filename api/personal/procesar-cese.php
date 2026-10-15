<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Procesar cese: devolucion MASIVA de equipos + bloqueo
// de cuentas + acta TI-FOR-001 + personal->cesado
// Recibe: condiciones por equipo (JSON), verificacion_accesos (JSON)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('personal', 'cese');
Actas::asegurar();

 $id = (int)($_POST['id'] ?? 0);

 $p = Database::getOne(
    "SELECT p.*, a.nombre AS area FROM personal p
     LEFT JOIN areas a ON a.id = p.area_id WHERE p.id = ?", [$id]);
if (!$p) Response::error('La persona no existe.');
if (!in_array($p['estado'], ['cese_programado','en_proceso_cese','activo'], true)) {
    Response::error('El estado actual (' . $p['estado'] . ') no permite procesar el cese.');
}

// ---- equipos asignados ----
 $asignaciones = Database::get(
    "SELECT a.id AS asig_id, a.tipo, a.condicion_entrega, e.*, t.nombre AS tipo_eq, t.familia
     FROM asignaciones a
     INNER JOIN equipos e ON e.id = a.equipo_id
     INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
     WHERE a.personal_id = ? AND a.estado IN ('activa','vencida')", [$id]);

// ---- condiciones por equipo (JSON: [{asig_id, condicion, obs}]) ----
 $condiciones = json_decode((string)($_POST['condiciones'] ?? '[]'), true) ?: [];
 $mapaCond = [];
 $checksPorAsig = [];
foreach ($condiciones as $c) {
    $mapaCond[(int)($c['asig_id'] ?? 0)] = [
        'condicion' => in_array($c['condicion'] ?? '', ['nuevo','bueno','regular','danado','faltante'], true)
                       ? $c['condicion'] : 'bueno',
        'obs' => mb_substr(trim((string)($c['obs'] ?? '')), 0, 200),
    ];
    $checksPorAsig[(int)($c['asig_id'] ?? 0)] = is_array($c['checks'] ?? null) ? $c['checks'] : [];
}

// ---- equipos NO devueltos (sin condicion informada = faltante) ----
 $faltantes = [];
foreach ($asignaciones as $a) {
    if (!isset($mapaCond[(int)$a['asig_id']])) {
        $faltantes[] = $a['codigo'];
    }
}

Database::begin();
try {
    $equiposDevueltos = [];
    $asigCerradas = [];

    // ---- 1. cerrar TODAS las asignaciones ----
    foreach ($asignaciones as $a) {
        $aid = (int)$a['asig_id'];
        $cond = $mapaCond[$aid]['condicion'] ?? 'faltante';
        $obs  = $mapaCond[$aid]['obs'] ?? '';

        Database::update('asignaciones', [
            'estado'               => 'devuelta',
            'fecha_devolucion_real'=> date('Y-m-d H:i:s'),
            'condicion_devolucion' => $cond,
            'obs_devolucion'       => $obs ?: null,
        ], 'id = ?', [$aid]);

        // estado del equipo segun condicion
        $estadoEq = 'en_stock';
        $condEq = $a['condicion'];
        if ($cond === 'danado')   { $estadoEq = 'en_revision'; }
        if ($cond === 'faltante') { $estadoEq = 'en_revision'; $condEq = 'danado'; }
        if ($cond === 'regular')  { $condEq = 'regular'; }

        Database::update('equipos', ['estado' => $estadoEq, 'condicion' => $condEq], 'id = ?', [$a['id']]);

        Database::insert('equipo_historial', [
            'equipo_id' => $a['id'], 'usuario_id' => Auth::userId(),
            'evento' => 'devuelto',
            'detalle' => 'Cese de personal · condición: ' . $cond . ($obs ? ' · ' . $obs : ''),
        ]);

        // para el acta (el contenido de cada equipo depende de su tipo)
        $equiposDevueltos[] = Actas::equipo(
            array_merge($a, ['tipo' => $a['tipo_eq']]), true,
            ['condicion' => $cond, 'obs' => $obs, 'checks' => $checksPorAsig[$aid] ?? []]);
        $asigCerradas[] = $aid;
    }

    // ---- 2. bloquear TODAS las cuentas del sistema ----
    $cuentas = Database::get("SELECT id, usuario FROM usuarios WHERE personal_id = ? AND estado = 'activo'", [$id]);
    foreach ($cuentas as $cu) {
        Database::update('usuarios', ['estado' => 'bloqueado'], 'id = ?', [$cu['id']]);
        Auditoria::registrar('cambiar_estado', 'usuarios', (int)$cu['id'],
            ['estado' => 'activo'], ['estado' => 'bloqueado (cese)']);
    }

    // ---- 3. checklist de salida: marcar verificacion de accesos ----
    $verifAccesos = json_decode((string)($_POST['verificacion_accesos'] ?? '[]'), true) ?: [];
    $checksCese = Database::get(
        "SELECT pc.id, ci.item FROM personal_checklist pc
         INNER JOIN checklist_items ci ON ci.id = pc.item_id
         INNER JOIN checklist_plantillas cp ON cp.id = ci.plantilla_id AND cp.tipo = 'cese'
         WHERE pc.personal_id = ?", [$id]);

    $mapearItem = [
        'credenciales'  => 'credencial',
        'bloqueo'       => 'cuenta',
        'token'         => 'mfa',
        'vpn'           => 'vpn',
        'sistemas'      => 'sistema',
        'sesiones'      => 'sesion',
        'lineas'        => 'linea',
        'equipos'       => 'equipo',
        'accesorios'    => 'accesorio',
    ];
    foreach ($checksCese as $ch) {
        $itemLower = mb_strtolower($ch['item']);
        foreach ($mapearItem as $clave => $palabra) {
            if (in_array($clave, $verifAccesos, true) && mb_strpos($itemLower, $palabra) !== false) {
                Database::update('personal_checklist', [
                    'completado' => 1,
                    'responsable_usuario_id' => Auth::userId(),
                    'fecha_completado' => date('Y-m-d H:i:s'),
                    'observacion' => 'Marcado durante el proceso de cese',
                ], 'id = ?', [$ch['id']]);
                break;
            }
        }
    }

    // ---- 4. ACTA DE DEVOLUCION (cese): por equipo segun su tipo ----
    $contenido = [
        'colaborador' => [
            'nombre' => $p['nombres'] . ' ' . $p['apellidos'],
            'dni' => $p['dni'],
            'cargo' => $p['cargo'] ?: '—',
            'area' => $p['area'] ?: '—',
            'jefe' => '—',
            'fecha_cese' => $p['fecha_cese'] ?: date('Y-m-d'),
        ],
        'motivo' => 'cese',
        'equipos_entregados' => [],
        'equipos_devueltos' => $equiposDevueltos,
        'verificacion_accesos' => $verifAccesos,
        'observaciones' => trim((string)($_POST['observaciones'] ?? '')) .
            ($faltantes ? ' | EQUIPOS NO ENTREGADOS: ' . implode(', ', $faltantes) : ''),
    ];
    $acta = Actas::crear('devolucion', $contenido, $id, null);
    $codigoActa = $acta['codigo'];
    $token = $acta['token'];
    foreach ($asigCerradas as $aidCerrada) Actas::vincular($aidCerrada, 'devolucion', $acta['id']);

    // ---- 5. personal -> cesado ----
    Database::update('personal', ['estado' => 'cesado'], 'id = ?', [$id]);

    Database::commit();
    Auditoria::registrar('cambiar_estado', 'personal', $id,
        ['estado' => $p['estado']], ['estado' => 'cesado']);

    $msg = 'Cese PROCESADO: ' . count($equiposDevueltos) . ' equipo(s) recibido(s), ' .
           count($cuentas) . ' cuenta(s) bloqueada(s). Acta ' . $codigoActa . ' generada.';
    if ($faltantes) {
        $msg .= ' ATENCIÓN: ' . count($faltantes) . ' equipo(s) NO entregado(s): ' . implode(', ', $faltantes);
    }

    Response::ok([
        'acta' => $codigoActa,
        'acta_id' => $acta['id'],
        'acta_url' => $acta['url'],
        'equipos' => count($equiposDevueltos),
        'cuentas_bloqueadas' => count($cuentas),
        'faltantes' => $faltantes,
    ], $msg);

} catch (Throwable $e) {
    Database::rollback();
    Response::error('Error al procesar el cese: ' . $e->getMessage());
}
<?php
// ============================================================
// SIGTI - Generar acta (guarda contenido JSON + token publico)
// POST: tipo, personal_id, motivo, motivo_texto, equipos_ent[] (ids),
//       equipos_dev[] (ids), validacion_ti[] (json), verif_accesos[] (json),
//       obs_devoluciones (json id=>texto), observaciones
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('actas', 'generar');

 $tipo = $_POST['tipo'] ?? '';
if (!in_array($tipo, ['entrega','cambio','devolucion'], true)) {
    Response::validation(['tipo' => 'Tipo de acta inválido.']);
}

 $personalId = (int)($_POST['personal_id'] ?? 0);
 $p = Database::getOne(
    "SELECT p.*, a.nombre AS area, CONCAT(j.nombres,' ',j.apellidos) AS jefe
     FROM personal p
     LEFT JOIN areas a ON a.id = p.area_id
     LEFT JOIN personal j ON j.id = p.jefe_id
     WHERE p.id = ?", [$personalId]);
if (!$p) Response::validation(['personal_id' => 'El colaborador no existe.']);

// ---- helper: armar estructura de equipo ----
 $armarEquipo = function (int $eqId, string $esquema): ?array {
    $e = Database::getOne(
        "SELECT e.*, t.nombre AS tipo, t.familia
         FROM equipos e INNER JOIN tipo_equipos t ON t.id = e.tipo_equipo_id
         WHERE e.id = ?", [$eqId]);
    if (!$e) return null;
    $acc = Database::get('SELECT nombre FROM equipo_accesorios WHERE equipo_id = ? ORDER BY id', [$eqId]);
    return [
        'id'          => (int)$e['id'],
        'codigo'      => $e['codigo'],
        'tipo'        => $e['tipo'],
        'familia'     => $e['familia'],
        'marca'       => $e['marca'],
        'modelo'      => $e['modelo'] ?: '',
        'serie'       => $e['nro_serie'] ?: '',
        'imei'        => $e['imei'] ?: '',
        'specs'       => $e['especificaciones'] ? json_decode($e['especificaciones'], true) : [],
        'accesorios'  => array_column($acc, 'nombre'),
        'condicion'   => ucfirst($e['condicion']),
        'condicion_devolucion' => $esquema === 'dev' ? ($_POST['cond_dev_' . $eqId] ?? '') : '',
        'obs_devolucion'       => $esquema === 'dev' ? ($_POST['obs_dev_' . $eqId] ?? '') : '',
    ];
};

 $idsEnt = json_decode((string)($_POST['equipos_ent'] ?? '[]'), true) ?: [];
 $idsDev = json_decode((string)($_POST['equipos_dev'] ?? '[]'), true) ?: [];

if ($tipo === 'entrega' && !$idsEnt) {
    Response::validation(['equipos_ent' => 'Seleccione al menos un equipo a entregar.']);
}
if ($tipo === 'devolucion' && !$idsDev) {
    Response::validation(['equipos_dev' => 'Seleccione al menos un equipo devuelto (o use "sin equipos").']);
}

 $equiposEnt = [];
foreach ($idsEnt as $id) { $eq = $armarEquipo((int)$id, 'ent'); if ($eq) $equiposEnt[] = $eq; }
 $equiposDev = [];
foreach ($idsDev as $id) { $eq = $armarEquipo((int)$id, 'dev'); if ($eq) $equiposDev[] = $eq; }

// formato segun tipo/familia
 $familia = $equiposEnt[0]['familia'] ?? ($equiposDev[0]['familia'] ?? '');
 $formato = $tipo === 'cambio'     ? Database::getValue("SELECT valor FROM configuracion WHERE clave='acta_formato_cambio'")
        : ($tipo === 'devolucion' ? Database::getValue("SELECT valor FROM configuracion WHERE clave='acta_formato_devolucion'")
        : ($familia === 'movil'   ? Database::getValue("SELECT valor FROM configuracion WHERE clave='acta_formato_entrega_movil'")
                                  : Database::getValue("SELECT valor FROM configuracion WHERE clave='acta_formato_entrega'")));
 $formato = $formato ?: 'LUMAT-TI-FOR-001';

 $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio',
          'Agosto','Setiembre','Octubre','Noviembre','Diciembre'];

 $contenido = [
    'tipo'            => $tipo,
    'formato_codigo'  => $formato,
    'familia'         => $familia,
    'colaborador'     => [
        'nombre'      => $p['nombres'] . ' ' . $p['apellidos'],
        'dni'         => $p['dni'],
        'cargo'       => $p['cargo'] ?: '—',
        'area'        => $p['area'] ?: '—',
        'jefe'        => $p['jefe'] ?: '—',
        'fecha_cese'  => $p['fecha_cese'] ?: '—',
    ],
    'motivo'          => $_POST['motivo'] ?? '',
    'motivo_texto'    => trim((string)($_POST['motivo_texto'] ?? '')),
    'equipos_entregados' => $equiposEnt,
    'equipos_devueltos'  => $equiposDev,
    'validacion_ti'   => json_decode((string)($_POST['validacion_ti'] ?? '[]'), true) ?: [],
    'verificacion_accesos' => json_decode((string)($_POST['verif_accesos'] ?? '[]'), true) ?: [],
    'observaciones'   => trim((string)($_POST['observaciones'] ?? '')),
    'fecha'           => date('Y-m-d H:i:s'),
    'fecha_formal'    => date('d') . ' / ' . date('m') . ' / ' . date('Y'),
];

Database::begin();
try {
    $codigo = Correlativo::generar('acta');
    $token  = bin2hex(random_bytes(32));

    $actaId = Database::insert('actas', [
        'codigo'                => $codigo,
        'tipo'                  => $tipo,
        'personal_id'           => $personalId,
        'responsable_usuario_id'=> Auth::userId(),
        'fecha'                 => date('Y-m-d H:i:s'),
        'contenido'             => json_encode($contenido, JSON_UNESCAPED_UNICODE),
        'token'                 => $token,
        'firmado'               => 0,
    ]);

    Auditoria::registrar('crear', 'actas', $actaId, null,
        ['codigo' => $codigo, 'tipo' => $tipo]);

    Database::commit();

    Response::ok([
        'id'    => $actaId,
        'codigo'=> $codigo,
        'token' => $token,
        'url'   => BASE_URL . 'acta.php?token=' . $token,
    ], "Acta $codigo generada correctamente.");

} catch (Throwable $e) {
    Database::rollback();
    Response::error('Error al generar el acta: ' . $e->getMessage());
}
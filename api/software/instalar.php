<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Software: registrar / editar / retirar programas en los equipos   (software.registrar)
// POST accion =
//   instalar    equipo_id, software_id, version, licencia ('' | 'auto' | id), fecha, observaciones
//   masivo      software_id, equipo_ids (1,2,3), version, licencia, fecha
//   desinstalar id, motivo
//   editar      id, version, licencia, observaciones
//   verificar   equipo_ids (1,2,3)  -> confirma que el inventario de software del equipo es correcto
//   importar    lineas (CODIGO;Programa;Version;Licencia), solo_validar (0|1), crear_faltantes (0|1)
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

function sw_ids(string $s): array
{
    return array_values(array_unique(array_filter(array_map('intval', preg_split('/[,\s]+/', $s)), fn($i) => $i > 0)));
}

try {
    Auth::check();
    Csrf::validate();
    Auth::requirePermission('software', 'registrar');
    Software::asegurar();

    $accion = (string)($_POST['accion'] ?? '');
    $uid = Auth::userId();
    $lic = fn() => ($_POST['licencia'] ?? '') === 'auto' ? 'auto' : (int)($_POST['licencia'] ?? 0);

    try {
        if ($accion === 'instalar') {
            if ((int)($_POST['equipo_id'] ?? 0) <= 0) Response::validation(['equipo_id' => 'Elija el equipo.']);
            if ((int)($_POST['software_id'] ?? 0) <= 0) Response::validation(['software_id' => 'Elija el programa.']);
            $r = Software::instalar((int)$_POST['equipo_id'], (int)$_POST['software_id'], (string)($_POST['version'] ?? ''),
                $lic(), $_POST['fecha'] ?? null, (string)($_POST['observaciones'] ?? ''), $uid);
            $msg = "«{$r['software']}» registrado en {$r['equipo']}.";
            if ($r['aviso']) $msg .= ' Atención: queda SIN licencia asignada' . ($r['aviso'] === 'sin_licencia_disponible' ? ' (no hay puestos libres)' : '') . '.';
            Response::ok(['id' => $r['id'], 'aviso' => $r['aviso'], 'mensaje' => $msg]);

        } elseif ($accion === 'masivo') {
            $swId = (int)($_POST['software_id'] ?? 0);
            $ids = sw_ids((string)($_POST['equipo_ids'] ?? ''));
            if ($swId <= 0) Response::validation(['software_id' => 'Elija el programa.']);
            if (!$ids) Response::validation(['equipo_ids' => 'Elija al menos un equipo.']);
            if (count($ids) > 500) Response::validation(['equipo_ids' => 'Máximo 500 equipos por vez.']);
            $ok = 0; $sinLic = 0; $omitidos = [];
            foreach ($ids as $eid) {
                try {
                    $r = Software::instalar($eid, $swId, (string)($_POST['version'] ?? ''), $lic(), $_POST['fecha'] ?? null, '', $uid);
                    $ok++;
                    if ($r['aviso']) $sinLic++;
                } catch (InvalidArgumentException $e) {
                    $cod = Database::getValue('SELECT codigo FROM equipos WHERE id = ?', [$eid]) ?: ('#' . $eid);
                    $omitidos[] = ['codigo' => $cod, 'motivo' => $e->getMessage()];
                }
            }
            $msg = "Instalado en $ok equipo(s)." . ($omitidos ? ' Omitidos: ' . count($omitidos) . '.' : '')
                 . ($sinLic ? " $sinLic quedaron sin licencia." : '');
            Response::ok(['instalados' => $ok, 'sin_licencia' => $sinLic, 'omitidos' => $omitidos, 'mensaje' => $msg]);

        } elseif ($accion === 'desinstalar') {
            $r = Software::desinstalar((int)($_POST['id'] ?? 0), (string)($_POST['motivo'] ?? ''), $uid);
            Response::ok(['mensaje' => "«{$r['software']}» retirado de {$r['equipo']}. Queda en el historial."]);

        } elseif ($accion === 'editar') {
            $r = Software::editar((int)($_POST['id'] ?? 0), (string)($_POST['version'] ?? ''),
                array_key_exists('licencia', $_POST) ? ($_POST['licencia'] === 'auto' ? 'auto' : (int)$_POST['licencia']) : null,   // ausente = no tocar
                (string)($_POST['observaciones'] ?? ''), $uid);
            Response::ok(['mensaje' => $r['cambios'] ? 'Cambios guardados.' : 'No había cambios que guardar.']);

        } elseif ($accion === 'verificar') {
            $ids = sw_ids((string)($_POST['equipo_ids'] ?? ($_POST['equipo_id'] ?? '')));
            if (!$ids) Response::validation(['equipo_ids' => 'Elija al menos un equipo.']);
            $n = 0;
            foreach ($ids as $eid) {
                $e = Database::getOne('SELECT id, codigo, estado FROM equipos WHERE id = ?', [$eid]);
                if (!$e || $e['estado'] === 'dado_de_baja') continue;
                Database::update('equipos', ['software_verificado_en' => date('Y-m-d H:i:s'), 'software_verificado_por' => $uid], 'id = ?', [$eid]);
                Database::insert('equipo_historial', ['equipo_id' => $eid, 'usuario_id' => $uid, 'evento' => 'software_verificado',
                    'detalle' => 'Inventario de software confirmado']);
                Auditoria::registrar('actualizar', 'equipo_software', $eid, null, ['equipo' => $e['codigo'], 'verificado' => date('Y-m-d')],
                    ['accion_detalle' => 'software_verificado', 'submodulo' => 'verificacion', 'equipo_id' => $eid]);
                $n++;
            }
            Response::ok(['verificados' => $n, 'mensaje' => $n === 1 ? 'Inventario de software confirmado.' : "Inventario confirmado en $n equipos."]);

        } elseif ($accion === 'importar') {
            $texto = (string)($_POST['lineas'] ?? '');
            $soloValidar = !empty($_POST['solo_validar']) && $_POST['solo_validar'] !== '0';
            $crear = !empty($_POST['crear_faltantes']) && $_POST['crear_faltantes'] !== '0' && Auth::can('software', 'gestionar');
            $lineas = preg_split('/\r\n|\r|\n/', $texto);
            if (count($lineas) > 2000) Response::validation(['lineas' => 'Máximo 2000 líneas por vez.']);

            $equipos = []; // codigo en minusculas => id
            foreach (Database::get("SELECT id, codigo, estado FROM equipos") as $e) $equipos[mb_strtolower($e['codigo'])] = $e;
            $catalogo = []; // nombre en minusculas => fila
            foreach (Database::get('SELECT id, nombre, activo FROM software_catalogo') as $s) $catalogo[mb_strtolower($s['nombre'])] = $s;

            $res = []; $ok = 0; $err = 0; $nuevosProg = [];
            foreach ($lineas as $i => $linea) {
                $linea = trim($linea);
                if ($linea === '') continue;
                $nroLinea = $i + 1;
                $sep = strpos($linea, ';') !== false ? ';' : (strpos($linea, "\t") !== false ? "\t" : ',');
                $c = array_map('trim', explode($sep, $linea));
                $cod = $c[0] ?? ''; $prog = $c[1] ?? ''; $ver = $c[2] ?? ''; $licTxt = mb_strtolower($c[3] ?? '');
                // encabezado
                if ($nroLinea === 1 && preg_match('/^(c[oó]digo|equipo)/iu', $cod) && preg_match('/^(programa|software)/iu', $prog)) continue;
                $fila = ['linea' => $nroLinea, 'codigo' => $cod, 'programa' => $prog, 'version' => $ver];
                if ($cod === '' || $prog === '') { $fila['estado'] = 'error'; $fila['detalle'] = 'Faltan datos (use CODIGO;Programa;Versión).'; $err++; $res[] = $fila; continue; }
                $eq = $equipos[mb_strtolower($cod)] ?? null;
                if (!$eq) { $fila['estado'] = 'error'; $fila['detalle'] = 'El equipo no existe.'; $err++; $res[] = $fila; continue; }
                if ($eq['estado'] === 'dado_de_baja') { $fila['estado'] = 'error'; $fila['detalle'] = 'Equipo dado de baja.'; $err++; $res[] = $fila; continue; }
                $sw = $catalogo[mb_strtolower($prog)] ?? null;
                if (!$sw) {
                    if (!$crear) { $fila['estado'] = 'error'; $fila['detalle'] = 'Programa no está en el catálogo.'; $err++; $res[] = $fila; continue; }
                    if ($soloValidar) {
                        $fila['estado'] = 'ok'; $fila['detalle'] = 'Se agregará al catálogo (en evaluación).'; $ok++; $res[] = $fila;
                        $catalogo[mb_strtolower($prog)] = ['id' => 0, 'nombre' => $prog, 'activo' => 1];
                        continue;
                    }
                    if (mb_strlen($prog) > 100) { $fila['estado'] = 'error'; $fila['detalle'] = 'Nombre demasiado largo.'; $err++; $res[] = $fila; continue; }
                    $nid = Database::insert('software_catalogo', ['nombre' => $prog, 'categoria' => 'otro', 'requiere_licencia' => 0,
                        'autorizacion' => 'evaluacion', 'creado_por' => $uid, 'descripcion' => 'Creado por importación: revisar categoría y licencia.']);
                    Auditoria::registrar('crear', 'software_catalogo', $nid, null, ['nombre' => $prog, 'origen' => 'importacion'],
                        ['accion_detalle' => 'programa_creado', 'submodulo' => 'catalogo']);
                    $sw = ['id' => $nid, 'nombre' => $prog, 'activo' => 1];
                    $catalogo[mb_strtolower($prog)] = $sw;
                    $nuevosProg[] = $prog;
                }
                if ((int)$sw['activo'] !== 1) { $fila['estado'] = 'error'; $fila['detalle'] = 'Programa desactivado en el catálogo.'; $err++; $res[] = $fila; continue; }

                $licOpt = ($licTxt === 'sin' || $licTxt === 'ninguna' || $licTxt === 'no') ? '' : 'auto';
                if ($soloValidar) {
                    $ya = (int)$sw['id'] > 0 && Database::getValue("SELECT id FROM equipo_software WHERE equipo_id = ? AND software_id = ? AND estado = 'instalado'", [(int)$eq['id'], (int)$sw['id']]);
                    if ($ya) { $fila['estado'] = 'omitido'; $fila['detalle'] = 'Ya estaba registrado.'; }
                    else { $fila['estado'] = 'ok'; $fila['detalle'] = $fila['detalle'] ?? ''; $ok++; }
                    $res[] = $fila; continue;
                }
                try {
                    $r = Software::instalar((int)$eq['id'], (int)$sw['id'], $ver, $licOpt, null, 'Carga masiva', $uid);
                    $fila['estado'] = 'ok'; $fila['detalle'] = $r['aviso'] ? 'Registrado sin licencia.' : 'Registrado.'; $ok++;
                } catch (InvalidArgumentException $e) {
                    $ya = mb_strpos($e->getMessage(), 'ya está registrado') !== false;
                    $fila['estado'] = $ya ? 'omitido' : 'error'; $fila['detalle'] = $e->getMessage();
                    if (!$ya) $err++;
                }
                $res[] = $fila;
            }
            if ($soloValidar) {
                $msg = "Revisadas " . count($res) . " línea(s): $ok correctas, $err con error.";
            } else {
                $msg = "Importación terminada: $ok registrado(s), $err con error." . ($nuevosProg ? ' Programas nuevos en el catálogo (en evaluación): ' . implode(', ', array_slice($nuevosProg, 0, 5)) . (count($nuevosProg) > 5 ? '…' : '') . '.' : '');
                Auditoria::registrar('crear', 'equipo_software', null, null, ['importadas' => $ok, 'errores' => $err],
                    ['accion_detalle' => 'software_importado', 'submodulo' => 'importacion']);
            }
            Response::ok(['validado' => $soloValidar ? 1 : 0, 'correctas' => $ok, 'errores' => $err, 'filas' => $res, 'mensaje' => $msg]);

        } else {
            Response::error('Acción inválida.');
        }
    } catch (InvalidArgumentException $e) {
        Response::error($e->getMessage());
    }

} catch (Throwable $e) {
    error_log('[SIGTI] software/instalar: ' . $e->getMessage());
    Response::error('No se pudo completar la operación de software.', null, 500);
}

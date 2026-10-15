<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Chat: conversaciones y mensajes
//
//  GET  ?listar=1                 -> mis conversaciones + directorio (personas y areas)
//  GET  ?sala_id=N&desde_id=M     -> mensajes nuevos de una sala (y datos de la sala)
//  POST accion=enviar             -> escribir en una sala (texto y/o archivo, responder a, menciones[])
//  POST accion=editar             -> corregir un mensaje propio
//  POST accion=eliminar           -> borrar un mensaje propio (o cualquiera si administra el grupo)
//  POST accion=reaccionar         -> poner/quitar una reaccion (emoji) a un mensaje
//  POST accion=iniciar_directo    -> abrir/crear chat 1 a 1 (usuario_id)
//  POST accion=iniciar_area       -> abrir canal del area (area_id): el propio o uno
//                                    entre mi area y la elegida
//
// Privacidad: un chat directo solo lo ven sus dos participantes; Sistemas
// (usuarios.gestionar) puede SUPERVISARLOS en solo lectura y la pantalla lo avisa.
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Chat::asegurar();

$yo = (int)Auth::userId();
$esSistemas = Chat::esSistemas();

/** Foto de la persona no hace falta: nombre, area y cargo bastan para ubicarla. */
function chat_directorio(int $yo): array
{
    $u = Database::get(
        "SELECT u.id, u.nombre_completo AS nombre, u.ultimo_acceso, r.nombre AS rol,
                a.id AS area_id, a.nombre AS area, p.cargo
         FROM usuarios u
         INNER JOIN roles r ON r.id = u.rol_id
         LEFT JOIN personal p ON p.id = u.personal_id
         LEFT JOIN areas a ON a.id = p.area_id
         WHERE u.estado = 'activo' AND u.id <> ?
         ORDER BY u.nombre_completo", [$yo]);
    $lim = time() - 300;
    foreach ($u as &$x) {
        $x['id'] = (int)$x['id'];
        $x['area_id'] = $x['area_id'] ? (int)$x['area_id'] : null;
        $x['en_linea'] = ($x['ultimo_acceso'] && strtotime((string)$x['ultimo_acceso']) >= $lim) ? 1 : 0;
        unset($x['ultimo_acceso']);
    }
    return $u;
}


/** Selecciona mensajes con autor y datos del mensaje al que responden. */
const CHAT_SEL = "SELECT m.id, m.usuario_id, m.mensaje, m.sistema, m.created_at, m.responde_a, m.editado, m.eliminado,
        m.archivo_nombre, m.archivo_tipo, m.archivo_size, m.menciones, m.act, m.sticker_id, u.nombre_completo AS autor,
        p.mensaje AS r_texto, p.eliminado AS r_elim, p.archivo_nombre AS r_archivo, p.archivo_tipo AS r_archivo_tipo,
        p.sticker_id AS r_sticker_id, pu.nombre_completo AS r_autor
     FROM chat_mensajes m
     LEFT JOIN usuarios u ON u.id = m.usuario_id
     LEFT JOIN chat_mensajes p ON p.id = m.responde_a
     LEFT JOIN usuarios pu ON pu.id = p.usuario_id";

/** Texto corto para mostrar cuando el mensaje no tiene texto propio (vista previa / cita de respuesta). */
function chat_previa_sin_texto(?string $archivoNombre, ?string $archivoTipo, ?int $stickerId): string
{
    if ($stickerId) return '🧩 Sticker';
    if ($archivoTipo && strpos($archivoTipo, 'audio/') === 0) return '🎤 Nota de voz';
    return $archivoNombre ? '📎 ' . $archivoNombre : '';
}

/** Deja cada mensaje listo para la pantalla (reacciones, respuesta, adjunto, sticker). */
function chat_formato(array $rows, int $yo): array
{
    if (!$rows) return [];
    $ids = array_map(fn($r) => (int)$r['id'], $rows);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $rea = [];
    foreach (Database::get(
        "SELECT mensaje_id, emoji, COUNT(*) AS n, SUM(CASE WHEN usuario_id = ? THEN 1 ELSE 0 END) AS yo
         FROM chat_reacciones WHERE mensaje_id IN ($in) GROUP BY mensaje_id, emoji ORDER BY MIN(id)",
        array_merge([$yo], $ids)) as $r) {
        $rea[(int)$r['mensaje_id']][] = ['e' => $r['emoji'], 'n' => (int)$r['n'], 'yo' => (int)$r['yo'] > 0];
    }
    $out = [];
    foreach ($rows as $r) {
        $elim = (int)$r['eliminado'] === 1;
        $m = [
            'id' => (int)$r['id'], 'usuario_id' => (int)$r['usuario_id'], 'autor' => $r['autor'],
            'mensaje' => $elim ? '' : (string)$r['mensaje'], 'sistema' => (int)$r['sistema'],
            'created_at' => $r['created_at'], 'editado' => (int)$r['editado'] === 1, 'eliminado' => $elim,
            'act' => (int)$r['act'], 'reacciones' => $elim ? [] : ($rea[(int)$r['id']] ?? []),
            'menciones' => $r['menciones'] ? array_map('intval', explode(',', $r['menciones'])) : [],
            'archivo' => null, 'resp' => null, 'sticker' => null,
        ];
        if (!$elim && $r['archivo_nombre']) {
            $m['archivo'] = [
                'nombre' => $r['archivo_nombre'], 'size' => (int)$r['archivo_size'],
                'imagen' => strpos((string)$r['archivo_tipo'], 'image/') === 0,
                'audio' => strpos((string)$r['archivo_tipo'], 'audio/') === 0,
            ];
        }
        if (!$elim && $r['sticker_id']) {
            $m['sticker'] = ['id' => (int)$r['sticker_id']];
        }
        if ($r['responde_a']) {
            $m['resp'] = [
                'id' => (int)$r['responde_a'], 'autor' => $r['r_autor'],
                'texto' => (int)$r['r_elim'] === 1 ? 'Mensaje eliminado' : mb_substr(
                    (string)($r['r_texto'] !== '' ? $r['r_texto'] : chat_previa_sin_texto($r['r_archivo'], $r['r_archivo_tipo'], $r['r_sticker_id'] ? (int)$r['r_sticker_id'] : null)),
                    0, 90
                ),
            ];
        }
        $out[] = $m;
    }
    return $out;
}

/** ¿Puede esa persona estar mencionada en la sala? (miembro, o del area si es canal de area) */
function chat_puede_estar(array $sala, int $uid): bool
{
    if ((int)$sala['id'] === 1) return true;
    if (Chat::esMiembro((int)$sala['id'], $uid)) return true;
    if (in_array($sala['tipo'], ['area', 'entre_areas'], true)) {
        $a = Chat::areaDe($uid);
        return $a !== null && in_array($a, [(int)$sala['area1_id'], (int)$sala['area2_id']], true);
    }
    return false;
}

/** Mensaje sobre el que se quiere actuar + su sala + acceso de escritura. */
function chat_mensaje_escribible(int $yo, bool $sis): array
{
    $m = Database::getOne('SELECT * FROM chat_mensajes WHERE id = ?', [(int)($_POST['mensaje_id'] ?? 0)]);
    if (!$m) Response::error('El mensaje no existe.');
    $sala = Chat::sala((int)$m['sala_id']);
    if (!$sala || Chat::acceso($sala, $yo, $sis) !== 'escribir') Response::forbidden('No tiene acceso a esa conversación.');
    if ((int)$m['sistema'] === 1) Response::error('Los avisos automáticos no se pueden cambiar.');
    return [$m, $sala];
}

// ============================ GET ============================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if (isset($_GET['listar'])) {
        Chat::sincronizar($yo);
        $dir = chat_directorio($yo);
        $nom = [];
        foreach ($dir as $d) $nom[$d['id']] = $d;

        $filas = Database::get(
            "SELECT s.id, s.nombre, s.tipo, s.descripcion, s.usuario1_id, s.usuario2_id, s.area1_id, s.area2_id, csu.rol,
                    (SELECT COUNT(*) FROM chat_mensajes m
                      WHERE m.sala_id = s.id AND m.id > csu.ultimo_leido_id AND m.usuario_id <> ?) AS no_leidos,
                    (SELECT MAX(m.id) FROM chat_mensajes m WHERE m.sala_id = s.id) AS ultimo_id
             FROM chat_sala_usuarios csu
             INNER JOIN chat_salas s ON s.id = csu.sala_id
             WHERE csu.usuario_id = ? AND s.activa = 1", [$yo, $yo]);

        $reu = [];
        foreach (Database::get('SELECT * FROM chat_reuniones') as $r) $reu[(int)$r['sala_id']] = $r;

        $conv = [];
        foreach ($filas as $s) {
            $sid = (int)$s['id'];
            $c = [
                'sala_id' => $sid, 'nombre' => $s['nombre'], 'tipo' => $s['tipo'], 'descripcion' => $s['descripcion'],
                'no_leidos' => (int)$s['no_leidos'], 'ultimo_id' => (int)($s['ultimo_id'] ?? 0),
                'soy_admin' => $s['rol'] === 'admin', 'area_id' => $s['area1_id'] ? (int)$s['area1_id'] : null,
            ];
            if ($s['tipo'] === 'directo') {
                $otro = (int)$s['usuario1_id'] === $yo ? (int)$s['usuario2_id'] : (int)$s['usuario1_id'];
                $c['otro_id'] = $otro;
                $c['nombre'] = $nom[$otro]['nombre'] ?? 'Usuario';
                $c['subtitulo'] = trim(($nom[$otro]['area'] ?? '') . (($nom[$otro]['cargo'] ?? '') ? ' · ' . $nom[$otro]['cargo'] : ''));
                $c['en_linea'] = $nom[$otro]['en_linea'] ?? 0;
            } elseif ($s['tipo'] === 'reunion' && isset($reu[$sid])) {
                $r = $reu[$sid];
                $c['reunion'] = [
                    'id' => (int)$r['id'], 'inicio' => $r['inicio'], 'duracion_min' => (int)$r['duracion_min'],
                    'estado' => Chat::estadoReunion($r),
                ];
            }
            if ($c['ultimo_id']) {
                $m = Database::getOne(
                    'SELECT m.mensaje, m.created_at, m.sistema, m.eliminado, m.archivo_nombre, m.archivo_tipo, m.sticker_id, u.nombre_completo AS autor
                     FROM chat_mensajes m LEFT JOIN usuarios u ON u.id = m.usuario_id WHERE m.id = ?', [$c['ultimo_id']]);
                if ($m) {
                    $c['ultimo_texto'] = (int)$m['eliminado'] === 1 ? 'Mensaje eliminado'
                        : mb_substr((string)($m['mensaje'] !== '' ? $m['mensaje'] : chat_previa_sin_texto($m['archivo_nombre'], $m['archivo_tipo'], $m['sticker_id'] ? (int)$m['sticker_id'] : null)), 0, 60);
                    $c['ultima_hora'] = $m['created_at'];
                    $c['ultimo_autor'] = $m['sistema'] ? '' : (string)strtok((string)$m['autor'], ' ');
                }
            }
            $conv[] = $c;
        }

        // Sistemas: conversaciones directas ajenas, solo para supervisar (lectura)
        $super = [];
        if ($esSistemas) {
            $mias = array_column($conv, 'sala_id');
            $todas = Database::get(
                "SELECT s.id, s.usuario1_id, s.usuario2_id,
                        (SELECT MAX(m.id) FROM chat_mensajes m WHERE m.sala_id = s.id) AS ultimo_id
                 FROM chat_salas s WHERE s.tipo = 'directo' AND s.activa = 1
                 ORDER BY ultimo_id DESC LIMIT 200");
            $todos = [];
            foreach (Database::get('SELECT id, nombre_completo FROM usuarios') as $x) $todos[(int)$x['id']] = $x['nombre_completo'];
            foreach ($todas as $t) {
                if (in_array((int)$t['id'], $mias, true) || !$t['ultimo_id']) continue;
                $super[] = [
                    'sala_id' => (int)$t['id'], 'tipo' => 'supervision', 'no_leidos' => 0, 'ultimo_id' => (int)$t['ultimo_id'],
                    'nombre' => ($todos[(int)$t['usuario1_id']] ?? '?') . ' ↔ ' . ($todos[(int)$t['usuario2_id']] ?? '?'),
                ];
            }
        }

        $miArea = Chat::areaDe($yo);
        $areas = Database::get("SELECT id, nombre FROM areas WHERE estado = 'activo' ORDER BY nombre");
        foreach ($areas as &$a) { $a['id'] = (int)$a['id']; $a['mia'] = $miArea === $a['id']; }

        Response::ok([
            'yo' => ['id' => $yo, 'nombre' => Auth::nombre(), 'area_id' => $miArea],
            'conversaciones' => $conv,
            'supervision' => $super,
            'usuarios' => $dir,
            'areas' => $areas,
            'es_sistemas' => $esSistemas,
            'no_leidos' => array_sum(array_column($conv, 'no_leidos')),
        ]);
    }

    // ---- mensajes de una sala ----
    $salaId = (int)($_GET['sala_id'] ?? 0);
    $desde = (int)($_GET['desde_id'] ?? 0);
    if ($salaId <= 0) Response::error('Sala no especificada.');

    $sala = Chat::sala($salaId);
    if (!$sala) Response::error('La conversación no existe.');
    $acceso = Chat::acceso($sala, $yo, $esSistemas);
    if (!$acceso) Response::forbidden('No tiene acceso a esta conversación.');

    $desdeAct = (int)($_GET['desde_act'] ?? 0);
    $cambios = [];
    if ($desde > 0) {
        $msgs = Database::get(CHAT_SEL . " WHERE m.sala_id = ? AND m.id > ? ORDER BY m.id ASC LIMIT 200", [$salaId, $desde]);
        // mensajes ya entregados que alguien edito, borro o reacciono
        $cambios = Database::get(CHAT_SEL . " WHERE m.sala_id = ? AND m.id <= ? AND m.act > ? ORDER BY m.act ASC LIMIT 100",
            [$salaId, $desde, $desdeAct]);
    } else {
        // primera carga: los ultimos 100
        $msgs = Database::get(CHAT_SEL . " WHERE m.sala_id = ? ORDER BY m.id DESC LIMIT 100", [$salaId]);
        $msgs = array_reverse($msgs);
    }
    $msgs = chat_formato($msgs, $yo);
    $cambios = chat_formato($cambios, $yo);
    $ultAct = (int)(Database::getValue('SELECT COALESCE(MAX(act),0) FROM chat_mensajes WHERE sala_id = ?', [$salaId]) ?? 0);
    $ultimo = (int)(Database::getValue('SELECT COALESCE(MAX(id),0) FROM chat_mensajes WHERE sala_id = ?', [$salaId]) ?? 0);
    if ($acceso === 'escribir') Chat::marcarLeido($salaId, $yo);

    $out = ['mensajes' => $msgs, 'cambios' => $cambios, 'ultimo_id' => $ultimo, 'ultimo_act' => $ultAct, 'acceso' => $acceso];

    // Datos de la sala solo en la primera carga (la pantalla los pide una vez)
    if ($desde === 0) {
        $info = [
            'id' => (int)$sala['id'], 'nombre' => $sala['nombre'], 'tipo' => $sala['tipo'],
            'descripcion' => $sala['descripcion'], 'soy_admin' => Chat::esAdmin($salaId, $yo),
            'area1_id' => $sala['area1_id'] ? (int)$sala['area1_id'] : null,
            'area2_id' => $sala['area2_id'] ? (int)$sala['area2_id'] : null,
        ];
        // Miembros (en canales de area se calculan por area: siempre al dia)
        $lim = time() - 300;
        if ($sala['tipo'] === 'area' || $sala['tipo'] === 'entre_areas') {
            $ids = Chat::usuariosDeArea((int)$sala['area1_id']);
            if ($sala['tipo'] === 'entre_areas') $ids = array_merge($ids, Chat::usuariosDeArea((int)$sala['area2_id']));
            $ids = array_values(array_unique($ids));
        } elseif ((int)$sala['id'] === 1) {
            $ids = array_map(fn($r) => (int)$r['id'], Database::get("SELECT id FROM usuarios WHERE estado = 'activo'"));
        } else {
            $ids = array_map(fn($r) => (int)$r['usuario_id'], Database::get('SELECT usuario_id FROM chat_sala_usuarios WHERE sala_id = ?', [$salaId]));
        }
        $miembros = [];
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $miembros = Database::get(
                "SELECT u.id, u.nombre_completo AS nombre, u.ultimo_acceso, a.nombre AS area, p.cargo,
                        (SELECT csu.rol FROM chat_sala_usuarios csu WHERE csu.sala_id = ? AND csu.usuario_id = u.id) AS rol
                 FROM usuarios u LEFT JOIN personal p ON p.id = u.personal_id LEFT JOIN areas a ON a.id = p.area_id
                 WHERE u.id IN ($in) ORDER BY u.nombre_completo", array_merge([$salaId], $ids));
            foreach ($miembros as &$m) {
                $m['id'] = (int)$m['id'];
                $m['en_linea'] = ($m['ultimo_acceso'] && strtotime((string)$m['ultimo_acceso']) >= $lim) ? 1 : 0;
                $m['admin'] = $m['rol'] === 'admin';
                unset($m['ultimo_acceso'], $m['rol']);
            }
        }
        $info['miembros'] = $miembros;
        if ($sala['tipo'] === 'directo') {
            $info['supervisable'] = true;      // la pantalla avisa que Sistemas puede supervisar
        }
        if ($sala['tipo'] === 'reunion') {
            $r = Database::getOne('SELECT r.*, u.nombre_completo AS organizador FROM chat_reuniones r
                                   LEFT JOIN usuarios u ON u.id = r.organizador_id WHERE r.sala_id = ?', [$salaId]);
            if ($r) {
                $info['reunion'] = [
                    'id' => (int)$r['id'], 'titulo' => $r['titulo'], 'descripcion' => $r['descripcion'],
                    'inicio' => $r['inicio'], 'duracion_min' => (int)$r['duracion_min'], 'enlace' => $r['enlace'],
                    'organizador' => $r['organizador'], 'organizador_id' => (int)$r['organizador_id'],
                    'estado' => Chat::estadoReunion($r),
                ];
            }
        }
        $out['sala'] = $info;
    }
    Response::ok($out);
}

// ============================ POST ===========================
Csrf::validate();
$accion = $_POST['accion'] ?? '';

if ($accion === 'enviar') {
    $salaId = (int)($_POST['sala_id'] ?? 0);
    $msg = trim((string)($_POST['mensaje'] ?? ''));
    $hayArchivo = isset($_FILES['archivo']) && (int)($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $stickerId = (int)($_POST['sticker_id'] ?? 0);

    if (mb_strlen($msg) > 1000) Response::validation(['mensaje' => 'Máximo 1000 caracteres.']);
    if ($msg === '' && !$hayArchivo && $stickerId <= 0) Response::validation(['mensaje' => 'Escriba un mensaje, adjunte un archivo o elija un sticker.']);
    if ($stickerId > 0 && !Database::getValue('SELECT id FROM chat_stickers WHERE id = ? AND activo = 1', [$stickerId])) {
        Response::error('Ese sticker ya no está disponible.');
    }

    $sala = Chat::sala($salaId);
    if (!$sala) Response::error('La conversación no existe.');
    if (Chat::acceso($sala, $yo, $esSistemas) !== 'escribir') Response::forbidden('No puede escribir en esta conversación.');
    if ($sala['tipo'] === 'reunion') {
        $r = Database::getOne('SELECT * FROM chat_reuniones WHERE sala_id = ?', [$salaId]);
        if ($r && $r['estado'] === 'cancelada') Response::error('La reunión fue cancelada.');
    }

    $resp = null;
    if (!empty($_POST['responde_a'])) {
        $resp = (int)$_POST['responde_a'];
        if (!Database::getValue('SELECT 1 FROM chat_mensajes WHERE id = ? AND sala_id = ?', [$resp, $salaId])) {
            Response::error('El mensaje al que responde ya no existe.');
        }
    }

    // menciones: solo personas que pueden estar en la sala
    $m = $_POST['menciones'] ?? [];
    if (!is_array($m)) $m = explode(',', (string)$m);
    $mencion = [];
    foreach (array_unique(array_filter(array_map('intval', $m), fn($n) => $n > 0)) as $uid) {
        if ($uid === $yo) continue;
        if (!Database::getValue("SELECT 1 FROM usuarios WHERE id = ? AND estado = 'activo'", [$uid])) continue;
        if (chat_puede_estar($sala, $uid)) $mencion[] = $uid;
    }

    $dato = [
        'sala_id' => $salaId, 'usuario_id' => $yo, 'mensaje' => $msg, 'responde_a' => $resp,
        'menciones' => $mencion ? implode(',', $mencion) : null,
        'sticker_id' => $stickerId > 0 ? $stickerId : null,
    ];
    $guardado = null;
    if ($hayArchivo) {
        try { $guardado = Chat::guardarArchivo($_FILES['archivo']); }
        catch (RuntimeException $e) { Response::error($e->getMessage()); }
        $dato += ['archivo' => $guardado['archivo'], 'archivo_nombre' => $guardado['nombre'],
                  'archivo_tipo' => $guardado['tipo'], 'archivo_size' => $guardado['size']];
    }
    $id = Database::insert('chat_mensajes', $dato);
    Chat::marcarLeido($salaId, $yo);

    $previa = $msg !== '' ? $msg : chat_previa_sin_texto($guardado['nombre'] ?? null, $guardado['tipo'] ?? null, $stickerId > 0 ? $stickerId : null);
    $donde = $sala['tipo'] === 'directo' ? Auth::nombre() : $sala['nombre'];
    foreach ($mencion as $uid) {
        Notificador::avisar($uid, 'sistema', 'Lo mencionaron en: ' . $donde,
            Auth::nombre() . ': ' . mb_substr($previa, 0, 120), 'chat/principal');
    }
    Auditoria::registrar('crear', 'chat_mensajes', $id, null,
        ['sala_id' => $salaId, 'mensaje' => mb_substr($msg, 0, 100), 'archivo' => $guardado['nombre'] ?? null, 'sticker_id' => $stickerId ?: null]);
    Response::ok(['id' => $id, 'mensaje' => 'Enviado.']);
}

if ($accion === 'editar') {
    [$m, $sala] = chat_mensaje_escribible($yo, $esSistemas);
    if ((int)$m['usuario_id'] !== $yo) Response::forbidden('Solo puede editar sus propios mensajes.');
    if ((int)$m['eliminado'] === 1) Response::error('El mensaje fue eliminado.');
    $txt = trim((string)($_POST['mensaje'] ?? ''));
    if ($txt === '' || mb_strlen($txt) > 1000) Response::validation(['mensaje' => 'Escriba el mensaje (máximo 1000 caracteres).']);
    if ($txt === (string)$m['mensaje']) Response::ok(['mensaje' => 'Sin cambios.']);
    Database::update('chat_mensajes', ['mensaje' => $txt, 'editado' => 1], 'id = ?', [(int)$m['id']]);
    Chat::marcarCambio((int)$m['sala_id'], (int)$m['id']);
    Auditoria::registrar('actualizar', 'chat_mensajes', (int)$m['id'], ['mensaje' => mb_substr((string)$m['mensaje'], 0, 100)], ['mensaje' => mb_substr($txt, 0, 100)]);
    Response::ok(['mensaje' => 'Mensaje corregido.']);
}

if ($accion === 'eliminar') {
    [$m, $sala] = chat_mensaje_escribible($yo, $esSistemas);
    if ((int)$m['eliminado'] === 1) Response::error('El mensaje ya fue eliminado.');
    $suyo = (int)$m['usuario_id'] === $yo;
    $admin = in_array($sala['tipo'], ['grupo', 'reunion'], true) && Chat::esAdmin((int)$sala['id'], $yo);
    if (!$suyo && !$admin) Response::forbidden('Solo puede eliminar sus mensajes (o cualquiera si administra el grupo).');
    if (!empty($m['archivo'])) @unlink(Chat::rutaArchivo((string)$m['archivo']));
    Database::run("UPDATE chat_mensajes SET eliminado = 1, mensaje = '', archivo = NULL, archivo_nombre = NULL, archivo_tipo = NULL, archivo_size = NULL WHERE id = ?", [(int)$m['id']]);
    Database::delete('chat_reacciones', 'mensaje_id = ?', [(int)$m['id']]);
    Chat::marcarCambio((int)$m['sala_id'], (int)$m['id']);
    Auditoria::registrar('eliminar', 'chat_mensajes', (int)$m['id'], ['sala_id' => (int)$m['sala_id'], 'autor' => (int)$m['usuario_id'], 'mensaje' => mb_substr((string)$m['mensaje'], 0, 100)], null);
    Response::ok(['mensaje' => 'Mensaje eliminado.']);
}

if ($accion === 'reaccionar') {
    [$m, $sala] = chat_mensaje_escribible($yo, $esSistemas);
    if ((int)$m['eliminado'] === 1) Response::error('El mensaje fue eliminado.');
    $norm = fn($e) => str_replace("\u{FE0F}", '', (string)$e);
    $emoji = null;
    foreach (Chat::EMOJIS as $e) if ($norm($e) === $norm($_POST['emoji'] ?? '')) $emoji = $e;
    if ($emoji === null) Response::error('Reacción no permitida.');
    $ya = Database::getValue('SELECT id FROM chat_reacciones WHERE mensaje_id = ? AND usuario_id = ? AND emoji = ?', [(int)$m['id'], $yo, $emoji]);
    if ($ya) Database::delete('chat_reacciones', 'id = ?', [(int)$ya]);
    else Database::insert('chat_reacciones', ['mensaje_id' => (int)$m['id'], 'usuario_id' => $yo, 'emoji' => $emoji]);
    Chat::marcarCambio((int)$m['sala_id'], (int)$m['id']);
    Response::ok(['puesta' => !$ya, 'mensaje' => $ya ? 'Reacción quitada.' : 'Reacción enviada.']);
}

if ($accion === 'iniciar_directo') {
    $otro = (int)($_POST['usuario_id'] ?? 0);
    if ($otro === $yo) Response::error('No puede chatear consigo mismo.');
    if (!Database::getValue("SELECT id FROM usuarios WHERE id = ? AND estado = 'activo'", [$otro])) Response::error('El usuario no existe o está inactivo.');

    $sid = Database::getValue(
        "SELECT id FROM chat_salas WHERE tipo = 'directo'
           AND ((usuario1_id = ? AND usuario2_id = ?) OR (usuario1_id = ? AND usuario2_id = ?))", [$yo, $otro, $otro, $yo]);
    if (!$sid) {
        $sid = Database::insert('chat_salas', [
            'nombre' => 'directo', 'tipo' => 'directo', 'usuario1_id' => $yo, 'usuario2_id' => $otro, 'creada_por' => $yo, 'activa' => 1,
        ]);
    }
    foreach ([$yo, $otro] as $u) Chat::unir((int)$sid, $u);
    Response::ok(['sala_id' => (int)$sid, 'mensaje' => 'Conversación lista.']);
}

if ($accion === 'iniciar_area') {
    $area = (int)($_POST['area_id'] ?? 0);
    if (!Database::getValue("SELECT id FROM areas WHERE id = ? AND estado = 'activo'", [$area])) Response::error('El área no existe o está inactiva.');
    $mia = Chat::areaDe($yo);

    if ($mia === $area) {
        $sid = Chat::canalArea($area);
    } elseif ($mia) {
        $sid = Chat::canalEntreAreas($mia, $area, $yo);
    } elseif ($esSistemas) {
        $sid = Chat::canalArea($area);          // Sistemas sin ficha de personal: entra al canal del area
    } else {
        Response::error('Su usuario no está vinculado a un área; pida a Sistemas que lo vincule a su ficha de personal.');
    }
    // miembros de las dos areas quedan dentro al sincronizarse; el solicitante entra ya
    Chat::unir((int)$sid, $yo);
    foreach (Chat::usuariosDeArea($area) as $u) Chat::unir((int)$sid, $u);
    if ($mia) foreach (Chat::usuariosDeArea($mia) as $u) Chat::unir((int)$sid, $u);
    Response::ok(['sala_id' => (int)$sid, 'mensaje' => 'Canal listo.']);
}

Response::error('Acción inválida.');

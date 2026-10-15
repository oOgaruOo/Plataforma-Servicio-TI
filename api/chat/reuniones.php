<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Chat: reuniones (una persona con varias personas / areas)
//  GET  -> mis reuniones (proximas y las ultimas ya realizadas)
//  POST accion=crear        titulo, descripcion, fecha, hora, duracion_min, enlace?, usuarios[], areas[]
//       accion=reprogramar  id, fecha, hora, duracion_min          (organizador o Sistemas)
//       accion=cancelar     id                                      (organizador o Sistemas)
// Cada reunion tiene su propio chat (sala tipo "reunion") y un enlace de
// videollamada (Jitsi Meet por defecto, o el que indique quien organiza).
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Chat::asegurar();
    $yo = (int)Auth::userId();
    $esSis = Chat::esSistemas();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        Chat::sincronizar($yo);
        $f = Database::get(
            "SELECT r.*, u.nombre_completo AS organizador,
                    (SELECT COUNT(*) FROM chat_sala_usuarios x WHERE x.sala_id = r.sala_id) AS invitados
             FROM chat_reuniones r
             INNER JOIN chat_sala_usuarios csu ON csu.sala_id = r.sala_id AND csu.usuario_id = ?
             LEFT JOIN usuarios u ON u.id = r.organizador_id
             ORDER BY r.inicio DESC LIMIT 60", [$yo]);
        $out = [];
        foreach ($f as $r) {
            $out[] = [
                'id' => (int)$r['id'], 'sala_id' => (int)$r['sala_id'], 'titulo' => $r['titulo'],
                'descripcion' => $r['descripcion'], 'inicio' => $r['inicio'], 'duracion_min' => (int)$r['duracion_min'],
                'enlace' => $r['enlace'], 'organizador' => $r['organizador'], 'organizador_id' => (int)$r['organizador_id'],
                'invitados' => (int)$r['invitados'], 'estado' => Chat::estadoReunion($r),
                'soy_organizador' => (int)$r['organizador_id'] === $yo,
            ];
        }
        // las proximas primero (de la mas cercana a la mas lejana), luego las pasadas (recientes primero)
        usort($out, function ($a, $b) {
            $pa = in_array($a['estado'], ['programada', 'en_curso'], true);
            $pb = in_array($b['estado'], ['programada', 'en_curso'], true);
            if ($pa !== $pb) return $pa ? -1 : 1;
            return $pa ? strcmp($a['inicio'], $b['inicio']) : strcmp($b['inicio'], $a['inicio']);
        });
        Response::ok($out);
    }

    Csrf::validate();
    $accion = $_POST['accion'] ?? '';

    $lista = function (string $k): array {
        $v = $_POST[$k] ?? [];
        if (!is_array($v)) $v = explode(',', (string)$v);
        return array_values(array_unique(array_filter(array_map('intval', $v), fn($n) => $n > 0)));
    };
    $momento = function () {
        $fecha = trim((string)($_POST['fecha'] ?? ''));
        $hora = trim((string)($_POST['hora'] ?? ''));
        $err = [];
        $d = DateTime::createFromFormat('Y-m-d H:i', $fecha . ' ' . $hora);
        if (!$d || $d->format('Y-m-d H:i') !== $fecha . ' ' . $hora) $err['fecha'] = 'Indique una fecha y hora válidas.';
        $dur = (int)($_POST['duracion_min'] ?? 60);
        if ($dur < 5 || $dur > 480) $err['duracion_min'] = 'La duración va de 5 minutos a 8 horas.';
        if (!$err && $d->getTimestamp() < time() - 300) $err['fecha'] = 'La reunión no puede empezar en el pasado.';
        if ($err) Response::validation($err);
        return [$d->format('Y-m-d H:i:s'), $dur];
    };
    $reunion = function () use ($yo, $esSis): array {
        $r = Database::getOne('SELECT * FROM chat_reuniones WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        if (!$r) Response::error('La reunión no existe.');
        if ((int)$r['organizador_id'] !== $yo && !$esSis) Response::forbidden('Solo quien organiza la reunión (o Sistemas) puede cambiarla.');
        if ($r['estado'] === 'cancelada') Response::error('La reunión ya fue cancelada.');
        return $r;
    };
    $cuando = fn(string $ini) => date('d/m/Y H:i', strtotime($ini));

    if ($accion === 'crear') {
        $titulo = trim((string)($_POST['titulo'] ?? ''));
        $desc = trim((string)($_POST['descripcion'] ?? ''));
        $enlace = trim((string)($_POST['enlace'] ?? ''));
        $err = [];
        if ($titulo === '' || mb_strlen($titulo) > 120) $err['titulo'] = 'Indique el motivo de la reunión (máximo 120 caracteres).';
        if ($enlace !== '' && (!preg_match('~^https://[^\s]+$~i', $enlace) || strlen($enlace) > 250)) {
            $err['enlace'] = 'El enlace debe comenzar con https:// (déjelo vacío para generar uno automático).';
        }
        if ($err) Response::validation($err);
        [$inicio, $dur] = $momento();

        $ids = $lista('usuarios');
        foreach ($lista('areas') as $a) $ids = array_merge($ids, Chat::usuariosDeArea($a));
        $ids = array_values(array_diff(array_unique($ids), [$yo]));
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $ids = array_map(fn($r) => (int)$r['id'], Database::get("SELECT id FROM usuarios WHERE estado = 'activo' AND id IN ($in)", $ids));
        }
        if (!$ids) Response::validation(['usuarios' => 'Invite al menos a una persona o a un área.']);
        if ($enlace === '') $enlace = Chat::enlaceNuevo();

        Database::begin();
        try {
            $sid = Database::insert('chat_salas', [
                'nombre' => $titulo, 'descripcion' => $desc !== '' ? mb_substr($desc, 0, 255) : null,
                'tipo' => 'reunion', 'creada_por' => $yo, 'activa' => 1,
            ]);
            Chat::unir($sid, $yo, 'admin');
            foreach ($ids as $u) Chat::unir($sid, $u);
            $rid = Database::insert('chat_reuniones', [
                'sala_id' => $sid, 'titulo' => $titulo, 'descripcion' => $desc !== '' ? mb_substr($desc, 0, 255) : null,
                'inicio' => $inicio, 'duracion_min' => $dur, 'enlace' => $enlace, 'organizador_id' => $yo, 'estado' => 'programada',
            ]);
            Chat::mensaje($sid, $yo, Auth::nombre() . ' convocó la reunión «' . $titulo . '» para el ' . $cuando($inicio) . ' (' . $dur . ' min).', true);
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            throw $e;
        }
        foreach ($ids as $u) {
            Notificador::avisar($u, 'sistema', 'Reunión: ' . $titulo, $cuando($inicio) . ' · organiza ' . Auth::nombre(), 'chat/principal');
        }
        Auditoria::registrar('crear', 'chat_reuniones', $rid, null, ['titulo' => $titulo, 'inicio' => $inicio, 'invitados' => count($ids) + 1]);
        Response::ok(['id' => $rid, 'sala_id' => $sid, 'enlace' => $enlace,
                      'mensaje' => 'Reunión programada. Se avisó a ' . count($ids) . ' persona(s).']);
    }

    if ($accion === 'reprogramar') {
        $r = $reunion();
        [$inicio, $dur] = $momento();
        Database::update('chat_reuniones', ['inicio' => $inicio, 'duracion_min' => $dur], 'id = ?', [(int)$r['id']]);
        Chat::mensaje((int)$r['sala_id'], $yo, Auth::nombre() . ' reprogramó la reunión para el ' . $cuando($inicio) . ' (' . $dur . ' min).', true);
        $q = Database::get('SELECT usuario_id FROM chat_sala_usuarios WHERE sala_id = ? AND usuario_id <> ?', [(int)$r['sala_id'], $yo]);
        foreach ($q as $m) {
            Notificador::avisar((int)$m['usuario_id'], 'sistema', 'Reunión reprogramada: ' . $r['titulo'], 'Nueva hora: ' . $cuando($inicio), 'chat/principal');
        }
        Auditoria::registrar('actualizar', 'chat_reuniones', (int)$r['id'], ['inicio' => $r['inicio']], ['inicio' => $inicio]);
        Response::ok(['mensaje' => 'Reunión reprogramada y avisada a los invitados.']);
    }

    if ($accion === 'cancelar') {
        $r = $reunion();
        Database::update('chat_reuniones', ['estado' => 'cancelada'], 'id = ?', [(int)$r['id']]);
        Chat::mensaje((int)$r['sala_id'], $yo, Auth::nombre() . ' canceló la reunión.', true);
        $q = Database::get('SELECT usuario_id FROM chat_sala_usuarios WHERE sala_id = ? AND usuario_id <> ?', [(int)$r['sala_id'], $yo]);
        foreach ($q as $m) {
            Notificador::avisar((int)$m['usuario_id'], 'sistema', 'Reunión cancelada: ' . $r['titulo'], 'Estaba prevista para el ' . $cuando($r['inicio']), 'chat/principal');
        }
        Auditoria::registrar('actualizar', 'chat_reuniones', (int)$r['id'], ['estado' => $r['estado']], ['estado' => 'cancelada']);
        Response::ok(['mensaje' => 'Reunión cancelada.']);
    }

    Response::error('Acción inválida.');

} catch (Throwable $e) {
    Response::error('chat/reuniones: ' . $e->getMessage());
}

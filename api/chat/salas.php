<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Chat: grupos
//  GET  -> mis salas
//  POST accion=crear    nombre, descripcion, usuarios[] , areas[]   (grupo; quien crea es admin)
//       accion=invitar  sala_id, usuarios[] , areas[]               (admin del grupo/reunion)
//       accion=quitar   sala_id, usuario_id                         (admin)
//       accion=admin    sala_id, usuario_id, valor=1|0              (admin: dar/quitar administracion)
//       accion=editar   sala_id, nombre, descripcion                (admin)
//       accion=salir    sala_id
//       accion=archivar sala_id                                     (admin: cierra el grupo)
// Los canales de area / entre areas y los chats directos no se editan a mano.
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

try {
    Auth::check();
    Chat::asegurar();
    $yo = (int)Auth::userId();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        Chat::sincronizar($yo);
        $salas = Database::get(
            "SELECT s.id, s.nombre, s.descripcion, s.tipo,
                    (SELECT COUNT(*) FROM chat_sala_usuarios x WHERE x.sala_id = s.id) AS miembros,
                    (SELECT MAX(m.id) FROM chat_mensajes m WHERE m.sala_id = s.id) AS ultimo_msg_id
             FROM chat_salas s INNER JOIN chat_sala_usuarios csu ON csu.sala_id = s.id
             WHERE csu.usuario_id = ? AND s.activa = 1
             ORDER BY ultimo_msg_id DESC", [$yo]);
        Response::ok($salas);
    }

    Csrf::validate();
    $accion = $_POST['accion'] ?? '';

    // lista de ids enviada como usuarios[]=1&usuarios[]=2 (o "1,2,3")
    $lista = function (string $k): array {
        $v = $_POST[$k] ?? [];
        if (!is_array($v)) $v = explode(',', (string)$v);
        return array_values(array_unique(array_filter(array_map('intval', $v), fn($n) => $n > 0)));
    };
    // usuarios activos que corresponden a usuarios[] + todos los de areas[]
    $destinatarios = function () use ($lista): array {
        $ids = $lista('usuarios');
        foreach ($lista('areas') as $a) $ids = array_merge($ids, Chat::usuariosDeArea($a));
        $ids = array_values(array_unique($ids));
        if (!$ids) return [];
        $in = implode(',', array_fill(0, count($ids), '?'));
        return array_map(fn($r) => (int)$r['id'],
            Database::get("SELECT id FROM usuarios WHERE estado = 'activo' AND id IN ($in)", $ids));
    };
    $sala = function () use ($yo): array {
        $id = (int)($_POST['sala_id'] ?? 0);
        $s = Chat::sala($id);
        if (!$s || (int)$s['activa'] !== 1) Response::error('La conversación no existe.');
        return $s;
    };
    $soloEditable = function (array $s): void {
        if (!in_array($s['tipo'], ['grupo', 'reunion'], true)) {
            Response::error('Los chats directos y los canales de área no se administran a mano.');
        }
    };

    if ($accion === 'crear') {
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $desc = trim((string)($_POST['descripcion'] ?? ''));
        if ($nombre === '') Response::validation(['nombre' => 'Indique el nombre del grupo.']);
        if (mb_strlen($nombre) > 120) Response::validation(['nombre' => 'Máximo 120 caracteres.']);
        $miembros = array_values(array_diff($destinatarios(), [$yo]));
        if (!$miembros) Response::validation(['usuarios' => 'Elija al menos una persona o un área para el grupo.']);

        Database::begin();
        try {
            $sid = Database::insert('chat_salas', [
                'nombre' => $nombre, 'descripcion' => $desc !== '' ? mb_substr($desc, 0, 255) : null,
                'tipo' => 'grupo', 'creada_por' => $yo, 'activa' => 1,
            ]);
            Chat::unir($sid, $yo, 'admin');
            foreach ($miembros as $u) Chat::unir($sid, $u);
            Chat::mensaje($sid, $yo, Auth::nombre() . ' creó el grupo «' . $nombre . '».', true);
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            throw $e;
        }
        foreach ($miembros as $u) {
            Notificador::avisar($u, 'sistema', 'Nuevo grupo de chat: ' . $nombre, Auth::nombre() . ' lo agregó al grupo.', 'chat/principal');
        }
        Auditoria::registrar('crear', 'chat_salas', $sid, null, ['nombre' => $nombre, 'miembros' => count($miembros) + 1]);
        Response::ok(['sala_id' => $sid, 'mensaje' => "Grupo «{$nombre}» creado con " . (count($miembros) + 1) . ' personas.']);
    }

    if ($accion === 'invitar') {
        $s = $sala(); $soloEditable($s);
        if (!Chat::esAdmin((int)$s['id'], $yo)) Response::forbidden('Solo quien administra el grupo puede agregar personas.');
        $nuevos = [];
        foreach ($destinatarios() as $u) {
            if (!Chat::esMiembro((int)$s['id'], $u)) { Chat::unir((int)$s['id'], $u); $nuevos[] = $u; }
        }
        if (!$nuevos) Response::error('Esas personas ya están en el grupo (o no hay nadie que agregar).');
        $n = Database::getValue('SELECT nombre_completo FROM usuarios WHERE id = ?', [$nuevos[0]]);
        Chat::mensaje((int)$s['id'], $yo, Auth::nombre() . ' agregó a ' . (count($nuevos) === 1 ? $n : count($nuevos) . ' personas') . '.', true);
        foreach ($nuevos as $u) {
            Notificador::avisar($u, 'sistema', 'Lo agregaron a: ' . $s['nombre'], Auth::nombre() . ' lo agregó.', 'chat/principal');
        }
        Auditoria::registrar('actualizar', 'chat_salas', (int)$s['id'], null, ['agregados' => count($nuevos)]);
        Response::ok(['agregados' => count($nuevos), 'mensaje' => count($nuevos) . ' persona(s) agregada(s).']);
    }

    if ($accion === 'quitar') {
        $s = $sala(); $soloEditable($s);
        $u = (int)($_POST['usuario_id'] ?? 0);
        if (!Chat::esAdmin((int)$s['id'], $yo)) Response::forbidden('Solo quien administra el grupo puede quitar personas.');
        if ($u === $yo) Response::error('Para irse del grupo use «Salir del grupo».');
        if (!Chat::esMiembro((int)$s['id'], $u)) Response::error('Esa persona no está en el grupo.');
        $n = Database::getValue('SELECT nombre_completo FROM usuarios WHERE id = ?', [$u]);
        Database::delete('chat_sala_usuarios', 'sala_id = ? AND usuario_id = ?', [(int)$s['id'], $u]);
        Chat::mensaje((int)$s['id'], $yo, Auth::nombre() . ' quitó a ' . $n . '.', true);
        Auditoria::registrar('actualizar', 'chat_salas', (int)$s['id'], null, ['quitado' => $u]);
        Response::ok(['mensaje' => $n . ' ya no está en el grupo.']);
    }

    if ($accion === 'admin') {
        $s = $sala(); $soloEditable($s);
        $u = (int)($_POST['usuario_id'] ?? 0);
        $val = (int)($_POST['valor'] ?? 0) === 1;
        if (!Chat::esAdmin((int)$s['id'], $yo)) Response::forbidden('Solo quien administra el grupo puede cambiar administradores.');
        if (!Chat::esMiembro((int)$s['id'], $u)) Response::error('Esa persona no está en el grupo.');
        if (!$val && $u === $yo && (int)Database::getValue("SELECT COUNT(*) FROM chat_sala_usuarios WHERE sala_id = ? AND rol = 'admin'", [(int)$s['id']]) <= 1) {
            Response::error('El grupo necesita al menos un administrador: nombre otro antes.');
        }
        Database::run('UPDATE chat_sala_usuarios SET rol = ? WHERE sala_id = ? AND usuario_id = ?', [$val ? 'admin' : 'miembro', (int)$s['id'], $u]);
        Response::ok(['mensaje' => $val ? 'Ahora administra el grupo.' : 'Ya no administra el grupo.']);
    }

    if ($accion === 'editar') {
        $s = $sala(); $soloEditable($s);
        if (!Chat::esAdmin((int)$s['id'], $yo)) Response::forbidden('Solo quien administra el grupo puede editarlo.');
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        if ($nombre === '' || mb_strlen($nombre) > 120) Response::validation(['nombre' => 'Indique el nombre (máximo 120 caracteres).']);
        $desc = trim((string)($_POST['descripcion'] ?? ''));
        Database::update('chat_salas', ['nombre' => $nombre, 'descripcion' => $desc !== '' ? mb_substr($desc, 0, 255) : null], 'id = ?', [(int)$s['id']]);
        Response::ok(['mensaje' => 'Grupo actualizado.']);
    }

    if ($accion === 'salir') {
        $s = $sala(); $soloEditable($s);
        if (!Chat::esMiembro((int)$s['id'], $yo)) Response::error('Usted no está en este grupo.');
        $sid = (int)$s['id'];
        Database::delete('chat_sala_usuarios', 'sala_id = ? AND usuario_id = ?', [$sid, $yo]);
        $quedan = (int)Database::getValue('SELECT COUNT(*) FROM chat_sala_usuarios WHERE sala_id = ?', [$sid]);
        if ($quedan === 0) {
            Database::update('chat_salas', ['activa' => 0], 'id = ?', [$sid]);
        } elseif (!Database::getValue("SELECT 1 FROM chat_sala_usuarios WHERE sala_id = ? AND rol = 'admin'", [$sid])) {
            $prox = Database::getValue('SELECT usuario_id FROM chat_sala_usuarios WHERE sala_id = ? ORDER BY usuario_id LIMIT 1', [$sid]);
            Database::run("UPDATE chat_sala_usuarios SET rol = 'admin' WHERE sala_id = ? AND usuario_id = ?", [$sid, (int)$prox]);
        }
        if ($quedan > 0) Chat::mensaje($sid, $yo, Auth::nombre() . ' salió del grupo.', true);
        Response::ok(['mensaje' => 'Salió de «' . $s['nombre'] . '».']);
    }

    if ($accion === 'archivar') {
        $s = $sala(); $soloEditable($s);
        if (!Chat::esAdmin((int)$s['id'], $yo)) Response::forbidden('Solo quien administra el grupo puede cerrarlo.');
        Database::update('chat_salas', ['activa' => 0], 'id = ?', [(int)$s['id']]);
        Auditoria::registrar('eliminar', 'chat_salas', (int)$s['id'], ['nombre' => $s['nombre']], null);
        Response::ok(['mensaje' => 'Grupo cerrado.']);
    }

    Response::error('Acción inválida.');

} catch (Throwable $e) {
    Response::error('chat/salas: ' . $e->getMessage());
}

<?php
// ============================================================
// SIGTI - Visor PUBLICO de acta (para WhatsApp / correo)
// URL: acta.php?token=xxxx  (sin login: el token es el secreto)
// ============================================================
require_once __DIR__ . '/core/bootstrap.php';

 $token = trim((string)($_GET['token'] ?? ''));
if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(404);
    echo '<div style="font-family:Arial;padding:40px;text-align:center;color:#b91c1c">
          <b>Acta no encontrada.</b><br>El enlace no es válido o fue revocado.</div>';
    exit;
}

 $acta = Database::getOne('SELECT * FROM actas WHERE token = ?', [$token]);
if (!$acta) {
    http_response_code(404);
    echo '<div style="font-family:Arial;padding:40px;text-align:center;color:#b91c1c">
          <b>Acta no encontrada.</b></div>';
    exit;
}

 $contenido = json_decode($acta['contenido'], true) ?: [];
 $contenido['codigo'] = $acta['codigo'];
 $contenido['token']  = $token;

echo ActaPlantilla::render($contenido);
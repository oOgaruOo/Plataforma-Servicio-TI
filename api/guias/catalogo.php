<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Centro de ayuda: guias visibles para quien consulta + preguntas frecuentes + glosario.
// El contenido esta en core/Guias.php
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
try {
    Response::ok([
        'categorias' => Guias::CATEGORIAS,
        'guias'      => Guias::visibles(),
        'faq'        => Guias::faq(),
        'glosario'   => Guias::glosario(),
    ]);
} catch (Throwable $e) {
    error_log('[SIGTI] guias/catalogo: ' . $e->getMessage() . ' ' . basename($e->getFile()) . ':' . $e->getLine());
    Response::error('No se pudieron cargar las guías.', null, 500);
}

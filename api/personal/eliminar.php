<?php
// ============================================================
// SIGTI - Borrado logico/real solo para pre-ingresos sin uso
// ============================================================
require_once __DIR__ . '/../../core/bootstrap.php';

Auth::check();
Csrf::validate();
Auth::requirePermission('personal', 'eliminar');

 $id = (int)($_POST['id'] ?? 0);
if ($id === 0) Response::error('Persona no especificada.');

 $p = Database::getOne('SELECT id, estado, dni FROM personal WHERE id = ?', [$id]);
if (!$p) Response::error('La persona no existe.');

if ($p['estado'] !== 'pre_ingreso') {
    Response::error('Solo se puede eliminar personal en estado «pre-ingreso». '
                  . 'El resto pasa a «cesado» (histórico, nunca se borra).');
}

// ¿tiene ya algo asociado? (checklist con items hechos = pasó a proceso real)
 $hechos = (int) Database::getValue(
    'SELECT COUNT(*) FROM personal_checklist WHERE personal_id = ? AND completado = 1', [$id]);
 $cuentas = (int) Database::getValue(
    'SELECT COUNT(*) FROM usuarios WHERE personal_id = ?', [$id]);

if ($hechos > 0 || $cuentas > 0) {
    Response::error('La persona ya tiene actividad registrada (checklist o cuentas). No puede eliminarse.');
}

Database::begin();
try {
    Database::delete('personal_checklist', 'personal_id = ?', [$id]);
    Database::delete('personal', 'id = ?', [$id]);
    Database::commit();
    Auditoria::registrar('eliminar', 'personal', $id, $p, null);
    Response::ok(null, 'Registro eliminado (pre-ingreso sin actividad).');
} catch (Throwable $e) {
    Database::rollback();
    Response::error('No se pudo eliminar: ' . $e->getMessage());
}
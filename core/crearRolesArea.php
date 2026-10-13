<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Crear roles automaticos (jefe + personal) por area
// ============================================================

function slugRolArea(string $nombre): string
{
    return preg_replace('/_+/', '_', strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $nombre)));
}

/**
 * Si el area cambia de nombre, sus roles automaticos (jefe_<area> / personal_<area>) la siguen.
 * Solo se tocan los que conservan el nombre automatico: uno renombrado a mano no se modifica.
 */
function renombrarRolesArea(int $areaId, string $viejo, string $nuevo): int
{
    $n = 0;
    try {
        $sv = slugRolArea($viejo);
        $sn = slugRolArea($nuevo);
        if ($sv === $sn) return 0;
        foreach (['jefe_' => 'Jefe de ', 'personal_' => 'Personal de '] as $pre => $desc) {
            $rol = Database::getOne('SELECT id FROM roles WHERE nombre = ? AND area_id = ? AND es_sistema = 0', [$pre . $sv, $areaId]);
            if (!$rol) continue;
            if (Database::getValue('SELECT id FROM roles WHERE nombre = ? AND id <> ?', [$pre . $sn, (int)$rol['id']])) continue;
            Database::update('roles', ['nombre' => $pre . $sn, 'descripcion' => $desc . $nuevo], 'id = ?', [(int)$rol['id']]);
            $n++;
        }
    } catch (Throwable $e) {
        error_log('[SIGTI] renombrarRolesArea: ' . $e->getMessage());
    }
    return $n;
}

/** Un rol automatico que quedo suelto (su area fue eliminada) se enlaza a la area nueva del mismo nombre. */
function enlazarRolArea(int $rolId, int $areaId): void
{
    $rol = Database::getOne('SELECT area_id FROM roles WHERE id = ?', [$rolId]);
    if ($rol && (int)$rol['area_id'] !== $areaId
        && (empty($rol['area_id']) || !Database::getValue('SELECT id FROM areas WHERE id = ?', [(int)$rol['area_id']]))) {
        Database::update('roles', ['area_id' => $areaId], 'id = ?', [$rolId]);
    }
}

function crearRolesArea(int $areaId, string $areaNombre): void
{
    try {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $areaNombre));
        $slug = preg_replace('/_+/', '_', $slug);   // sin dobles guiones

        // jefe del area
        $existeJefe = Database::getValue(
            'SELECT id FROM roles WHERE nombre = ?', ['jefe_' . $slug]);
        if ($existeJefe) {
            enlazarRolArea((int)$existeJefe, $areaId);
        } else {
            Database::insert('roles', [
                'nombre' => 'jefe_' . $slug,
                'descripcion' => 'Jefe de ' . $areaNombre,
                'es_sistema' => 0,
                'area_id' => $areaId,
            ]);
        }

        // personal del area
        $existePersonal = Database::getValue(
            'SELECT id FROM roles WHERE nombre = ?', ['personal_' . $slug]);
        if ($existePersonal) {
            enlazarRolArea((int)$existePersonal, $areaId);
        } else {
            Database::insert('roles', [
                'nombre' => 'personal_' . $slug,
                'descripcion' => 'Personal de ' . $areaNombre,
                'es_sistema' => 0,
                'area_id' => $areaId,
            ]);
        }
    } catch (Throwable $e) {
        error_log('[SIGTI] crearRolesArea: ' . $e->getMessage());
    }
}
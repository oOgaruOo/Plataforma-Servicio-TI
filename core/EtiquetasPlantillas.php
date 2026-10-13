<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Etiquetas: tipos de plantilla, variables y modelos base
//
//   objeto = 'equipo'   -> se imprime con datos de un equipo        (views/equipos/etiquetas.php)
//   objeto = 'personal' -> se imprime con datos de una persona      (views/personal/etiquetas.php)
//   objeto = 'locker'   -> etiqueta del locker de una persona       (views/personal/etiquetas.php)
// ============================================================

class EtiquetasPlantillas
{
    public const OBJETOS = ['equipo' => 'Equipos', 'personal' => 'Personal', 'locker' => 'Lockers'];

    /** Crea la columna 'objeto' si todavia no existe (las plantillas anteriores quedan como 'equipo'). */
    public static function asegurar(): void
    {
        Esquema::columna('etiquetas_plantillas', 'objeto', "VARCHAR(10) NOT NULL DEFAULT 'equipo'");
    }

    /** Variables disponibles por tipo (para mostrarlas en el diseñador). */
    public static function variables(string $objeto): array
    {
        if ($objeto === 'personal' || $objeto === 'locker') {
            return ['{codigo_usuario}', '{nombre_completo}', '{nombres}', '{apellidos}', '{apellido_paterno}',
                    '{apellido_materno}', '{dni}', '{area}', '{area_codigo}', '{cargo}', '{locker}', '{tipo_personal}'];
        }
        return ['{codigo}', '{serie}', '{tipo}', '{marca}', '{modelo}', '{activo_fijo}', '{ubicacion}', '{url_consulta}'];
    }

    /** Valores de las variables para una fila de personal (con area y codigo de area). */
    public static function valoresPersonal(array $p): array
    {
        $v = fn($k) => (isset($p[$k]) && trim((string)$p[$k]) !== '') ? trim((string)$p[$k]) : '—';
        return [
            '{codigo_usuario}'   => $v('codigo_usuario'),
            '{nombre_completo}'  => trim(($p['nombres'] ?? '') . ' ' . ($p['apellidos'] ?? '')),
            '{nombres}'          => $v('nombres'),
            '{apellidos}'        => $v('apellidos'),
            '{apellido_paterno}' => $v('apellido_paterno'),
            '{apellido_materno}' => $v('apellido_materno'),
            '{dni}'              => $v('dni'),
            '{area}'             => $v('area'),
            '{area_codigo}'      => $v('area_codigo'),
            '{cargo}'            => $v('cargo'),
            '{locker}'           => $v('locker'),
            '{tipo_personal}'    => ucfirst($v('tipo_personal')),
        ];
    }

    /** Siembra (una sola vez) las plantillas base de personal y locker. Si luego se borran, no vuelven. */
    public static function sembrar(): void
    {
        self::asegurar();
        $base = [
            'personal' => ['Credencial del personal', 60, 30, [
                ['tipo' => 'texto',   'contenido' => '{nombre_completo}',         'fontSize' => 10, 'x' => 2, 'y' => 2,  'w' => 56, 'h' => 6, 'negrita' => true,  'align' => 'center'],
                ['tipo' => 'texto',   'contenido' => '{area} · {cargo}',          'fontSize' => 7,  'x' => 2, 'y' => 9,  'w' => 56, 'h' => 4, 'negrita' => false, 'align' => 'center'],
                ['tipo' => 'texto',   'contenido' => 'Código: {codigo_usuario}',  'fontSize' => 9,  'x' => 2, 'y' => 14, 'w' => 56, 'h' => 5, 'negrita' => true,  'align' => 'center'],
                ['tipo' => 'barcode', 'contenido' => '{codigo_usuario}',          'fontSize' => 8,  'x' => 6, 'y' => 20, 'w' => 48, 'h' => 8, 'negrita' => false, 'align' => 'center'],
            ]],
            'locker' => ['Locker del personal', 50, 30, [
                ['tipo' => 'texto',   'contenido' => 'LOCKER {locker}',                    'fontSize' => 14, 'x' => 2, 'y' => 2,  'w' => 46, 'h' => 8, 'negrita' => true,  'align' => 'center'],
                ['tipo' => 'texto',   'contenido' => '{nombre_completo}',                  'fontSize' => 8,  'x' => 2, 'y' => 11, 'w' => 46, 'h' => 5, 'negrita' => true,  'align' => 'center'],
                ['tipo' => 'texto',   'contenido' => '{codigo_usuario} · {area}',          'fontSize' => 7,  'x' => 2, 'y' => 16, 'w' => 46, 'h' => 4, 'negrita' => false, 'align' => 'center'],
                ['tipo' => 'barcode', 'contenido' => '{codigo_usuario}',                   'fontSize' => 8,  'x' => 5, 'y' => 21, 'w' => 40, 'h' => 7, 'negrita' => false, 'align' => 'center'],
            ]],
        ];
        foreach ($base as $obj => [$nombre, $ancho, $alto, $els]) {
            $clave = 'etiquetas_base_' . $obj;
            if (Database::getValue('SELECT valor FROM configuracion WHERE clave = ?', [$clave]) !== null) continue;
            Database::insert('etiquetas_plantillas', [
                'nombre' => $nombre, 'ancho_mm' => $ancho, 'alto_mm' => $alto, 'objeto' => $obj,
                'elementos' => json_encode($els, JSON_UNESCAPED_UNICODE),
            ]);
            Database::insert('configuracion', ['clave' => $clave, 'valor' => '1',
                'descripcion' => 'Plantilla base de etiquetas (' . $obj . ') ya creada']);
        }
    }
}

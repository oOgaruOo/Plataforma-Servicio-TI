<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Lector generico de archivos .csv / .xlsx para cargas masivas (sin composer ni librerias externas).
// Devuelve cada fila como arreglo asociativo usando la primera fila como encabezado,
// con las claves normalizadas (minusculas, sin tildes, espacios -> "_").
// Usa solo extensiones que ya trae PHP: zip (ZipArchive) y SimpleXML, ambas estandar en XAMPP.
// ============================================================

class ImportadorArchivo
{
    /** $nombreOriginal se usa solo para saber la extension (el archivo real esta en $ruta, p.ej. tmp_name de $_FILES). */
    public static function leer(string $ruta, string $nombreOriginal): array
    {
        $ext = strtolower((string) pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        if ($ext === 'csv' || $ext === 'txt') return self::leerCsv($ruta);
        if ($ext === 'xlsx') return self::leerXlsx($ruta);
        throw new RuntimeException('Formato no soportado. Use un archivo .csv o .xlsx.');
    }

    public static function normalizar(string $s): string
    {
        $s = trim(mb_strtolower($s));
        $s = strtr($s, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n', 'Ü' => 'u',
        ]);
        $s = (string) preg_replace('/[^a-z0-9]+/', '_', $s);
        return trim($s, '_');
    }

    private static function leerCsv(string $ruta): array
    {
        $fh = fopen($ruta, 'r');
        if (!$fh) throw new RuntimeException('No se pudo abrir el archivo.');

        // quita el BOM de UTF-8 si lo tiene, y detecta el separador mirando la primera linea
        $primera = fgets($fh);
        if ($primera === false) { fclose($fh); return []; }
        $primera = preg_replace('/^\xEF\xBB\xBF/', '', $primera);
        $delim = substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ',';
        rewind($fh);
        $linea0 = fgetcsv($fh, 0, $delim);
        if (!$linea0) { fclose($fh); return []; }
        $linea0[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $linea0[0]);
        $header = array_map([self::class, 'normalizar'], $linea0);

        $filas = [];
        while (($linea = fgetcsv($fh, 0, $delim)) !== false) {
            if (count($linea) === 1 && trim((string) ($linea[0] ?? '')) === '') continue;
            $r = [];
            foreach ($header as $i => $h) { if ($h !== '') $r[$h] = isset($linea[$i]) ? trim((string) $linea[$i]) : ''; }
            $filas[] = $r;
        }
        fclose($fh);
        return $filas;
    }

    private static function leerXlsx(string $ruta): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('El servidor no tiene la extensión ZIP de PHP habilitada; suba el archivo como .csv en su lugar.');
        }
        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) throw new RuntimeException('El archivo .xlsx está dañado o no es un Excel válido.');

        // ---- cadenas compartidas (textos) ----
        $shared = [];
        $xmlSS = $zip->getFromName('xl/sharedStrings.xml');
        if ($xmlSS !== false) {
            $ss = @simplexml_load_string($xmlSS);
            if ($ss) {
                foreach ($ss->si as $si) {
                    if (isset($si->t)) { $shared[] = (string) $si->t; continue; }
                    $texto = '';
                    if (isset($si->r)) foreach ($si->r as $r) $texto .= (string) $r->t;
                    $shared[] = $texto;
                }
            }
        }

        // ---- primera hoja del libro ----
        $xmlHoja = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($xmlHoja === false) throw new RuntimeException('No se encontró ninguna hoja dentro del archivo .xlsx.');
        $hoja = @simplexml_load_string($xmlHoja);
        if (!$hoja || !isset($hoja->sheetData)) throw new RuntimeException('No se pudo leer el contenido del archivo .xlsx.');

        $filasRaw = [];
        foreach ($hoja->sheetData->row as $row) {
            $colMax = -1; $vals = [];
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                preg_match('/^([A-Z]+)/', $ref, $m);
                $col = self::colLetraANum($m[1] ?? 'A');
                $tipo = (string) $c['t'];
                $valor = '';
                if ($tipo === 's') { $valor = $shared[(int) (string) ($c->v ?? '')] ?? ''; }
                elseif ($tipo === 'inlineStr') { $valor = isset($c->is->t) ? (string) $c->is->t : ''; }
                else { $valor = isset($c->v) ? (string) $c->v : ''; }
                $vals[$col] = $valor;
                if ($col > $colMax) $colMax = $col;
            }
            $linea = [];
            for ($i = 0; $i <= $colMax; $i++) $linea[] = $vals[$i] ?? '';
            $filasRaw[] = $linea;
        }
        if (!$filasRaw) return [];

        $header = array_map([self::class, 'normalizar'], array_shift($filasRaw));
        $filas = [];
        foreach ($filasRaw as $linea) {
            $vacia = true;
            foreach ($linea as $v) { if (trim((string) $v) !== '') { $vacia = false; break; } }
            if ($vacia) continue;
            $r = [];
            foreach ($header as $i => $h) { if ($h !== '') $r[$h] = isset($linea[$i]) ? trim((string) $linea[$i]) : ''; }
            $filas[] = $r;
        }
        return $filas;
    }

    /** "A" -> 0, "B" -> 1, ..., "Z" -> 25, "AA" -> 26, etc. */
    private static function colLetraANum(string $letras): int
    {
        $n = 0;
        foreach (str_split($letras) as $c) $n = $n * 26 + (ord($c) - 64);
        return max(0, $n - 1);
    }
}

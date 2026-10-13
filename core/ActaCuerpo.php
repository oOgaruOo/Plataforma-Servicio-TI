<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Cuerpo de las actas v2 (entrega / devolucion / cambio)
//
// El marco del documento (logo, encabezado, pie, botones de imprimir)
// lo dibuja ActaPlantilla::render(); aqui se arma el contenido, que
// cambia segun la FAMILIA del equipo (ver ActaPerfiles).
// ============================================================

class ActaCuerpo
{
    private static function h($s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }

    /** Reemplaza {emp} por el nombre de la empresa. */
    private static function t(string $texto): string
    {
        return str_replace('{emp}', Actas::empresa(), $texto);
    }

    private static function fam(array $e): string
    {
        return ActaPerfiles::claveFamilia($e['familia'] ?? 'otro');
    }

    private static function familias(array $lista): array
    {
        $f = [];
        foreach ($lista as $e) $f[] = self::fam($e);
        return array_values(array_unique($f));
    }

    // ---------------------------------------------------------
    // Titulo y cuerpo
    // ---------------------------------------------------------

    public static function titulo(array $a): string
    {
        $tipo = $a['tipo'] ?? 'entrega';
        $eq = $tipo === 'devolucion'
            ? ($a['equipos_devueltos'] ?? [])
            : array_merge($a['equipos_entregados'] ?? [], $tipo === 'cambio' ? ($a['equipos_devueltos'] ?? []) : []);
        return ActaPerfiles::titulo($tipo, self::familias($eq));
    }

    public static function cuerpo(array $a): string
    {
        $tipo = $a['tipo'] ?? 'entrega';
        if ($tipo === 'devolucion') return self::devolucion($a);
        if ($tipo === 'cambio')     return self::cambio($a);
        return self::entrega($a);
    }

    // ---------------------------------------------------------
    // Piezas comunes
    // ---------------------------------------------------------

    private static function h2(int $n, string $texto): string
    {
        return '<h2>' . $n . '. ' . self::h($texto) . '</h2>';
    }

    private static function fila(string $lbl, string $val): string
    {
        return '<tr><td class="lbl">' . self::h($lbl) . '</td><td>' . $val . '</td></tr>';
    }

    private static function esPersona(array $a): bool
    {
        return (($a['destino']['tipo'] ?? 'persona') === 'persona');
    }

    /** Datos del colaborador o del destino (area, proyecto, obra) y de quien lo recibe / devuelve. */
    private static function secDestino(array $a, int $n, bool $conCese, bool $devuelve): string
    {
        $col = $a['colaborador'] ?? [];
        $filasCol =
            self::fila('Nombre Completo', self::h($col['nombre'] ?? '—')) .
            self::fila('DNI', self::h($col['dni'] ?? '—')) .
            self::fila('Cargo', self::h($col['cargo'] ?? '—')) .
            self::fila('Área', self::h($col['area'] ?? '—')) .
            ($conCese ? self::fila('Fecha de Cese', self::h($col['fecha_cese'] ?? '—')) : '') .
            self::fila('Jefe Inmediato', self::h($col['jefe'] ?? '—'));

        $dest = $a['destino'] ?? null;
        if ($dest && ($dest['tipo'] ?? 'persona') !== 'persona') {
            $d = self::fila($devuelve ? 'Origen de los equipos' : 'Destino de los equipos',
                    self::h(($dest['tipo_texto'] ?? '') . ': ' . ($dest['nombre'] ?? '')))
               . (!empty($dest['codigo'])    ? self::fila('Código', self::h($dest['codigo'])) : '')
               . (!empty($dest['cliente'])   ? self::fila('Cliente', self::h($dest['cliente'])) : '')
               . (!empty($dest['ubicacion']) ? self::fila('Ubicación', self::h($dest['ubicacion'])) : '')
               . (!empty($dest['detalle'])   ? self::fila('Frente / zona', self::h($dest['detalle'])) : '');
            $h = self::h2($n, 'Destino y responsable') . '<table>' . $d . '</table>'
               . '<p style="margin:6px 0 4px"><b>' . ($devuelve
                    ? 'Responsable que devuelve los equipos:'
                    : 'Responsable que recibe y custodia los equipos:') . '</b></p><table>' . $filasCol . '</table>';
        } else {
            $h = self::h2($n, 'Datos del colaborador') . '<table>' . $filasCol . '</table>';
        }

        // modalidad (solo al entregar)
        $m = $a['modalidad'] ?? null;
        if (!$devuelve && $m && ($m['tipo'] ?? '') === 'prestamo') {
            $ret = !empty($m['retorno']) ? date('d/m/Y', strtotime((string)$m['retorno'])) : '—';
            $h .= '<table style="margin-top:6px">' .
                self::fila('Modalidad', '<b>Préstamo temporal</b>') .
                self::fila('Fecha de devolución estipulada', '<b>' . self::h($ret) . '</b>') . '</table>';
        } elseif (!$devuelve && $m) {
            $h .= '<table style="margin-top:6px">' . self::fila('Modalidad', 'Entrega permanente') . '</table>';
        }
        return $h;
    }

    private static function checkboxes(array $items, array $marcados): string
    {
        $h = '<div class="chks">';
        foreach ($items as $val => $lbl) {
            $marca = in_array((string)$val, array_map('strval', $marcados), true);
            $h .= ($marca ? '<b>&#9745; ' : '&#9744; ') . self::h($lbl) . ($marca ? ' ✔</b>' : '') . '<br>';
        }
        return $h . '</div>';
    }

    /** Condicion al recibir: casillas con la elegida marcada. */
    private static function estadoFisicoDev(array $e): string
    {
        $c = (string)($e['condicion_devolucion'] ?? '');
        $grupos = ['Bueno' => ['nuevo', 'bueno'], 'Regular' => ['regular'], 'Dañado' => ['danado', 'irreparable'], 'No entregado' => ['faltante']];
        $h = '';
        foreach ($grupos as $lbl => $claves) {
            $sel = in_array($c, $claves, true);
            $h .= ($sel ? '<b>&#9745; ' . $lbl . '</b>' : '&#9744; ' . $lbl) . ' &nbsp; ';
        }
        return $h;
    }

    /** Ficha del equipo segun su familia + lista de verificacion de TI. $modo: 'ent' | 'dev'. */
    private static function bloqueEquipo(array $e, string $modo, int $i, int $total): string
    {
        $fam = self::fam($e);
        $perfil = ActaPerfiles::de($fam);
        $dev = $modo === 'dev';

        $filas = '';
        if ($total > 1) {
            $filas .= '<tr><th colspan="2">Equipo ' . $i . ' de ' . $total . ' — ' . self::h($perfil['nombre']) . '</th></tr>';
        }
        $filas .= self::fila('Tipo de Equipo', self::h($e['tipo'] ?? '') . ($total > 1 ? '' : ' <span style="color:#666">(' . self::h($perfil['nombre']) . ')</span>'));
        $filas .= self::fila('Marca / Modelo', self::h(trim(($e['marca'] ?? '') . ' ' . ($e['modelo'] ?? ''))));
        $filas .= self::fila('Código Patrimonial', self::h($e['codigo'] ?? ''));
        $filas .= self::fila('Número de Serie', self::h(($e['serie'] ?? '') !== '' ? $e['serie'] : '—'));
        if (!empty($e['activo_fijo'])) $filas .= self::fila('Activo fijo (contable)', self::h($e['activo_fijo']));
        if (!empty($e['imei']))       $filas .= self::fila('IMEI', self::h($e['imei']));
        if (!empty($e['mac']))        $filas .= self::fila('Dirección MAC', self::h($e['mac']));

        $specs = $e['specs'] ?? [];
        foreach ($perfil['specs'] as $k) {
            $v = trim((string)($specs[$k] ?? ''));
            if ($v !== '') $filas .= self::fila(ActaPerfiles::etiquetaSpec($k), self::h($v));
        }

        $acc = $e['accesorios'] ?? [];
        $filas .= self::fila($dev ? 'Accesorios devueltos' : 'Accesorios entregados',
            $acc ? self::h(implode(', ', $acc)) : '—');
        if ($dev && !empty($e['acc_faltantes'])) {
            $filas .= self::fila('Accesorios faltantes', '<b>' . self::h($e['acc_faltantes']) . '</b>');
        }

        if ($dev) {
            $filas .= self::fila('Estado físico al recibir', self::estadoFisicoDev($e));
            $filas .= self::fila('Estado funcional', '&#9744; Operativo &nbsp; &#9744; Con fallas');
            if (!empty($e['obs_devolucion'])) $filas .= self::fila('Observaciones del equipo', self::h($e['obs_devolucion']));
        } else {
            $filas .= self::fila('Estado físico', self::h($e['condicion'] ?? '—'));
        }

        $tituloChk = $dev ? 'Inspección y verificación de TI al recibir el equipo' : 'Verificación técnica realizada por TI al entregar';
        return '<table>' . $filas . '</table>' .
            '<p style="margin:6px 0 2px"><b>' . self::h($tituloChk) . ':</b></p>' .
            self::checkboxes(ActaPerfiles::checks($modo, $fam), $e['checks'] ?? []);
    }

    private static function bloques(array $lista, string $modo): string
    {
        if (!$lista) return '<p style="text-align:center;color:#666">— Ninguno —</p>';
        $h = '';
        $n = count($lista);
        foreach (array_values($lista) as $i => $e) {
            $h .= ($i > 0 ? '<div style="height:8px"></div>' : '') . self::bloqueEquipo($e, $modo, $i + 1, $n);
        }
        return $h;
    }

    // ---------------------------------------------------------
    // Condiciones de uso (segun la familia) y declaraciones
    // ---------------------------------------------------------

    private static function condicionesUso(array $a): string
    {
        $ents = $a['equipos_entregados'] ?? [];
        $fams = self::familias($ents);

        $p = function (string $titulo, string $texto): string {
            return '<p><b>' . self::h($titulo) . ':</b> ' . self::h(self::t($texto)) . '</p>';
        };

        $h = $p('Recepción de los bienes', 'El receptor declara haber recibido los bienes descritos en buen estado, operativos y con todas sus funcionalidades en correcto funcionamiento, conforme a la verificación realizada junto con el Área de TI.');
        $h .= $p('Propiedad', 'Los bienes son de propiedad exclusiva de {emp} y se entregan únicamente para fines laborales, quedando prohibido su uso personal o ajeno a las funciones asignadas.');

        foreach ($fams as $f) {
            $perfil = ActaPerfiles::de($f);
            if (count($fams) > 1) $h .= '<p style="margin-bottom:0"><u>' . self::h($perfil['nombre']) . '</u></p>';
            foreach ($perfil['uso'] as $c) $h .= $p($c[0], $c[1]);
        }

        $m = $a['modalidad'] ?? null;
        if ($m && ($m['tipo'] ?? '') === 'prestamo') {
            $ret = !empty($m['retorno']) ? date('d/m/Y', strtotime((string)$m['retorno'])) : 'la fecha acordada';
            $h .= $p('Préstamo temporal', 'Los bienes se entregan en calidad de préstamo temporal y deben devolverse al Área de TI a más tardar el ' . $ret . ', o antes si el Área de TI o el responsable del proyecto lo solicitan.');
        }
        $d = $a['destino'] ?? null;
        if ($d && ($d['tipo'] ?? 'persona') !== 'persona') {
            $h .= $p('Responsable de la custodia', 'El responsable que firma la presente acta custodia los bienes en nombre de ' .
                mb_strtolower((string)($d['tipo_texto'] ?? 'su destino')) . ' «' . ($d['nombre'] ?? '') . '» y responde por su ubicación, su uso adecuado y su devolución.');
        }

        $h .= $p('Devolución', 'Al término de la relación laboral, del préstamo o cuando el Área de TI lo requiera, el receptor se compromete a devolver los bienes y sus accesorios en las mismas condiciones de la entrega inicial, salvo el desgaste natural derivado del uso normal.');
        $h .= $p('Responsabilidad por daños o pérdida', 'En caso de pérdida, daño, manipulación indebida o no devolución, {emp} queda facultada a aplicar un descuento equivalente al valor del bien en las remuneraciones o liquidación de beneficios sociales, e iniciar las acciones legales correspondientes para la recuperación del patrimonio.');
        $h .= $p('Reporte de incidentes', 'El receptor se obliga a informar de inmediato cualquier desperfecto, falla técnica o incidente relacionado con los bienes entregados.');
        return '<div class="decl">' . $h . '</div>';
    }

    private static function declaracionEntrega(array $a): string
    {
        $col = $a['colaborador'] ?? [];
        $d = $a['destino'] ?? null;
        $rep = ($d && ($d['tipo'] ?? 'persona') !== 'persona')
            ? ', en representación de ' . mb_strtolower((string)($d['tipo_texto'] ?? '')) . ' <b>' . self::h($d['nombre'] ?? '') . '</b>,'
            : '';
        return '<div class="decl">Yo, <b>' . self::h($col['nombre'] ?? '') . '</b>, identificado con DNI N° <b>' . self::h($col['dni'] ?? '') . '</b>' . $rep .
            ' declaro haber recibido a conformidad los bienes antes detallados, comprometiéndome a su adecuado uso y custodia, bajo las condiciones establecidas en el presente documento.</div>';
    }

    private static function declaracionDevolucion(array $a, bool $cese): string
    {
        $fams = self::familias($a['equipos_devueltos'] ?? []);
        $extra = '';
        foreach ($fams as $f) $extra .= ' ' . ActaPerfiles::de($f)['dev_texto'];

        $h = 'Mediante la suscripción de la presente acta, quien devuelve declara haber entregado al Área de Tecnología de la Información (TI) los bienes detallados en este documento, los cuales le fueron asignados por {emp} para el desempeño de sus funciones, encontrándose en las condiciones registradas, salvo las observaciones expresamente anotadas.<br><br>' .
            'El Área de TI deja constancia de la recepción física de los bienes y se reserva el derecho de efectuar las verificaciones técnicas, funcionales y de seguridad necesarias para confirmar su integridad física, su estado operativo y la devolución de todos los componentes y accesorios asignados.' . $extra . '<br><br>' .
            'En caso de identificarse bienes no entregados, accesorios faltantes, daños atribuibles al uso indebido, alteraciones no autorizadas o cualquier incumplimiento de las políticas corporativas sobre gestión de activos e información, {emp} podrá adoptar las medidas administrativas, laborales y legales que correspondan, de conformidad con la legislación vigente y las políticas institucionales aplicables.<br><br>' .
            'La firma de la presente acta constituye evidencia documental de la devolución de los activos tecnológicos, formando parte del ' .
            ($cese ? 'expediente de cese del colaborador y del ' : '') .
            'registro de control patrimonial de {emp}, y pudiendo ser utilizada en procesos de auditoría interna y externa.';
        return '<div class="decl">' . str_replace('{emp}', self::h(Actas::empresa()), $h) . '</div>';
    }

    private static function secObs(array $a, int $n): string
    {
        return self::h2($n, 'Observaciones') . '<div class="obs">' . self::h(($a['observaciones'] ?? '') !== '' ? $a['observaciones'] : 'Sin observaciones.') . '</div>';
    }

    /** $quienes = lista de [rol, nombre opcional]. */
    private static function firma(array $quienes, array $a): string
    {
        $ciudad = Actas::cfg('empresa_ciudad', 'Lima');
        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Setiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $f = strtotime($a['fecha'] ?? 'now');
        $fechaFirma = $ciudad . ', ' . date('j', $f) . ' de ' . $meses[(int)date('n', $f)] . ' del ' . date('Y', $f);

        $cols = '';
        foreach ($quienes as $q) {
            $cols .= '<div class="firma"><div class="firma-linea"></div><div class="quien">' . self::h($q[0]) .
                (!empty($q[1]) ? '<br><b>' . self::h($q[1]) . '</b>' : '') . '</div></div>';
        }
        return '<div class="fecha-firma">' . self::h($fechaFirma) . '</div><div class="firmas">' . $cols . '</div>';
    }

    private static function firmantes(array $a, array $extra = []): array
    {
        $col = $a['colaborador'] ?? [];
        $rol = self::esPersona($a) ? 'Colaborador' : 'Responsable';
        $f = [[$rol, $col['nombre'] ?? ''], ['Área de TI', $a['ti_nombre'] ?? '']];
        foreach ($extra as $x) $f[] = [$x];
        return $f;
    }

    // ---------------------------------------------------------
    // ENTREGA
    // ---------------------------------------------------------

    private static function entrega(array $a): string
    {
        $n = 1;
        $h = self::secDestino($a, $n++, false, false);
        $h .= self::h2($n++, 'Descripción de los equipos entregados') . self::bloques($a['equipos_entregados'] ?? [], 'ent');
        $h .= self::h2($n++, 'Condiciones de uso y custodia') . self::condicionesUso($a);
        $h .= self::h2($n++, 'Declaración de conformidad') . self::declaracionEntrega($a);
        $h .= self::secObs($a, $n++);
        $h .= self::h2($n++, 'Firmas') . self::firma(self::firmantes($a), $a);
        return $h;
    }

    // ---------------------------------------------------------
    // DEVOLUCION
    // ---------------------------------------------------------

    private static function devolucion(array $a): string
    {
        $cese = ($a['motivo'] ?? '') === 'cese';
        $n = 1;
        $h = self::secDestino($a, $n++, $cese, true);

        $h .= self::h2($n++, 'Motivo de la devolución') .
            self::checkboxes(ActaPerfiles::MOTIVOS_DEVOLUCION, [(string)($a['motivo'] ?? '')]) .
            (!empty($a['motivo_texto']) ? '<p style="margin:4px 0">Detalle: <b>' . self::h($a['motivo_texto']) . '</b></p>' : '');

        $h .= self::h2($n++, 'Equipos devueltos') . self::bloques($a['equipos_devueltos'] ?? [], 'dev');

        if ($cese || !empty($a['verificacion_accesos'])) {
            $h .= self::h2($n++, 'Verificación de accesos') . self::checkboxes([
                'credenciales' => 'Devolución de credenciales',
                'bloqueo'      => 'Bloqueo de cuentas corporativas',
                'token'        => 'Entrega de token MFA',
                'vpn'          => 'Revocación de VPN',
                'sistemas'     => 'Revocación de accesos a sistemas',
                'sesiones'     => 'Cierre de sesiones activas',
                'lineas'       => 'Cierre de líneas telefónicas',
            ], $a['verificacion_accesos'] ?? []);
        }

        $h .= self::h2($n++, 'Declaración de conformidad') . self::declaracionDevolucion($a, $cese);
        $h .= self::secObs($a, $n++);
        $extra = $cese ? ['Jefe Inmediato', 'RR.HH.'] : [];
        $h .= self::h2($n++, 'Firmas') . self::firma(self::firmantes($a, $extra), $a);
        return $h;
    }

    // ---------------------------------------------------------
    // CAMBIO / REEMPLAZO
    // ---------------------------------------------------------

    private static function cambio(array $a): string
    {
        $n = 1;
        $h = self::secDestino($a, $n++, false, false);

        $h .= self::h2($n++, 'Motivo del cambio') .
            self::checkboxes(ActaPerfiles::MOTIVOS_CAMBIO, [(string)($a['motivo'] ?? '')]) .
            (!empty($a['motivo_texto']) ? '<p style="margin:4px 0">Detalle: <b>' . self::h($a['motivo_texto']) . '</b></p>' : '');

        $h .= self::h2($n++, 'Devolución del equipo anterior') . self::bloques($a['equipos_devueltos'] ?? [], 'dev');
        $h .= self::h2($n++, 'Entrega del nuevo equipo') . self::bloques($a['equipos_entregados'] ?? [], 'ent');

        $h .= self::h2($n++, 'Registros de TI') . self::checkboxes([
            'inventario'  => 'Se actualizó el inventario de activos (el equipo anterior ingresó a revisión y el nuevo quedó asignado).',
            'patrimonial' => 'Se actualizó el sistema de control patrimonial.',
        ], $a['registros'] ?? []);

        $h .= self::h2($n++, 'Condiciones de uso y custodia del nuevo equipo') . self::condicionesUso($a);

        $h .= self::h2($n++, 'Declaración de conformidad') . '<div class="decl">' . str_replace('{emp}', self::h(Actas::empresa()),
            'El receptor declara haber devuelto el activo tecnológico anteriormente asignado, junto con todos sus accesorios y componentes, aceptando que el Área de Tecnología realizará las verificaciones técnicas correspondientes para confirmar su estado físico, funcional y la integridad de la información institucional.<br><br>' .
            'Asimismo, declara haber recibido el nuevo activo tecnológico descrito en la presente acta, verificando que se encuentra operativo y en condiciones adecuadas para el desempeño de sus funciones, comprometiéndose a utilizarlo exclusivamente para fines laborales, conforme a las políticas de seguridad de la información, uso aceptable de los recursos tecnológicos y gestión de activos establecidas por {emp}.<br><br>' .
            'El receptor asume la responsabilidad sobre la custodia, conservación y uso adecuado del equipo recibido desde la fecha de su entrega, obligándose a reportar inmediatamente cualquier incidente, pérdida, robo o daño que pudiera afectar el activo asignado.<br><br>' .
            'La presente acta constituye evidencia documental del proceso de devolución y entrega de activos tecnológicos, formando parte del registro oficial de control patrimonial y pudiendo ser utilizada como evidencia durante auditorías internas, externas y en el marco de los Sistemas de Gestión implementados por {emp}.') . '</div>';

        $h .= self::secObs($a, $n++);
        $h .= self::h2($n++, 'Firmas') . self::firma(self::firmantes($a, ['Jefe Inmediato']), $a);
        return $h;
    }
}

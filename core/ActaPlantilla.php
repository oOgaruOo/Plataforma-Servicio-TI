<?php
// ============================================================
// SIGTI - Motor de plantillas de actas (formatos LUMAT) - v2
// v2: logo en cabecera (incrustado base64: documento autocontenido),
//     RUC/web configurables, color de marca y pie corrido en
//     cada pagina al imprimir. Fix: enlaces WhatsApp/correo.
// ============================================================

class ActaPlantilla
{
    private static function cfg(string $k, string $def = ''): string
    {
        $v = Database::getValue('SELECT valor FROM configuracion WHERE clave = ?', [$k]);
        return $v ?? $def;
    }

    /** Logo de empresa embebido como data-URI (autocontenido) */
    private static function logo(): string
    {
        foreach (['png', 'jpg', 'jpeg'] as $ext) {
            $f = STORAGE_PATH . '/branding/logo.' . $ext;
            if (is_file($f)) {
                $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
                return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($f));
            }
        }
        return '';
    }

    public static function render(array $a): string
    {
        $tipo = $a['tipo'] ?? 'entrega';

        $titulo = [
            'entrega'    => 'ACTA DE ENTREGA Y RECEPCIÓN DE EQUIPOS DE TECNOLOGÍA DE LA INFORMACIÓN',
            'cambio'     => 'ACTA DE ENTREGA Y RECEPCIÓN DE ACTIVOS TECNOLÓGICOS POR REEMPLAZO DE EQUIPO',
            'devolucion' => 'ACTA DE DEVOLUCIÓN DE ACTIVOS TECNOLÓGICOS',
        ][$tipo];

        $formato = $a['formato_codigo'];

        $cuerpo = $tipo === 'devolucion' ? self::cuerpoDevolucion($a)
                : ($tipo === 'cambio'    ? self::cuerpoCambio($a)
                : self::cuerpoEntrega($a));

        $nombre = self::cfg('empresa_nombre', 'LUMAT CONSTRUCCIONES');
        $ruc    = trim(self::cfg('empresa_ruc'));
        $web    = trim(self::cfg('empresa_web'));
        $color  = self::cfg('acta_color', '#1e2732');
        $c      = htmlspecialchars($color);

        $logo = self::logo();

        $pie = self::cfg('empresa_telefonos') . '<br>' . self::cfg('empresa_correo')
             . '<br>Oficina: ' . self::cfg('empresa_direccion')
             . ($web ? '<br>' . htmlspecialchars($web) : '');

        // URL publica completa (para WhatsApp / correo)
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $urlPub = $scheme . '://' . $host . BASE_URL . 'acta.php?token=' . rawurlencode($a['token'] ?? '');

        $logoHtml   = $logo ? '<div class="enc-logo"><img src="' . $logo . '" alt=""></div>' : '';
        $logoSpacer = $logo ? '<div class="enc-logo"></div>' : '';   // centra el texto si hay logo
        $rucHtml    = $ruc ? '<div class="ruc">RUC: ' . htmlspecialchars($ruc) . '</div>' : '';

        return '<!doctype html><html lang="es"><head><meta charset="utf-8">
<title>' . htmlspecialchars($titulo) . '</title>
<style>
  @page{size:A4;margin:14mm 12mm 17mm}
  *{box-sizing:border-box}
  body{font-family:"Segoe UI",Arial,sans-serif;color:#111;font-size:10.5pt;margin:0}
  .doc{max-width:186mm;margin:0 auto}
  .enc{border-bottom:2.5px solid ' . $c . ';padding-bottom:8px;margin-bottom:14px}
  .enc-grid{display:flex;align-items:center;gap:10px}
  .enc-logo{flex:0 0 26mm;width:26mm}
  .enc-logo img{width:100%;height:auto;max-height:26mm;object-fit:contain}
  .enc-txt{flex:1;text-align:center}
  .enc h1{font-size:12pt;margin:2px 0;letter-spacing:.4px;text-transform:uppercase}
  .enc .meta{font-size:8.5pt;color:#444;margin-top:3px}
  .enc .empresa{font-size:14pt;font-weight:800;color:' . $c . ';letter-spacing:2px;text-transform:uppercase}
  .ruc{font-size:9pt;color:#444;margin:1px 0}
  h2{font-size:10.5pt;background:' . $c . ';color:#fff;padding:4px 8px;margin:14px 0 6px;text-transform:uppercase;letter-spacing:.5px}
  table{width:100%;border-collapse:collapse;margin:4px 0}
  td,th{border:1px solid #999;padding:4px 6px;font-size:9.5pt;vertical-align:top}
  th{background:#e8ecf1;text-align:left}
  .lbl{width:34%;background:#f4f6f9;font-weight:600}
  .firma-linea{border-bottom:1px solid #333;height:24px;margin:0 6px}
  .firmas{display:flex;gap:10px;margin-top:8px}
  .firma{flex:1;text-align:center}
  .firma .quien{font-size:8.5pt;color:#555;margin-top:3px}
  .decl{font-size:9.5pt;text-align:justify;line-height:1.45;margin:6px 0}
  .obs{border:1px solid #999;height:17mm;padding:4px 6px;font-size:9pt;white-space:pre-wrap}
  .chks{font-size:9.5pt;line-height:1.7}
  .pie{text-align:center;font-size:8pt;color:#666;border-top:1px solid #999;margin-top:16px;padding-top:6px}
  .fecha-firma{font-size:10pt;margin:12px 0 4px}
  .noprint{display:flex;gap:8px;justify-content:center;padding:10px;border-bottom:1px solid #ddd;margin-bottom:14px}
  .noprint button{padding:7px 14px;border:0;border-radius:6px;color:#fff;cursor:pointer;font-size:10pt;font-family:inherit}
  .running{display:none}
  @media print{
    .noprint{display:none!important}
    .running{display:flex;position:fixed;bottom:5mm;left:12mm;right:12mm;justify-content:space-between;
      font-size:7.5pt;color:#666;border-top:.5px solid #999;padding-top:1mm}
  }
</style></head><body>

<div class="noprint">
  <button style="background:' . $c . '" onclick="window.print()">&#128424; Imprimir / Guardar PDF</button>
  <button style="background:#25d366" onclick="compartirWa()">&#128172; WhatsApp</button>
  <button style="background:#2563eb" onclick="compartirMail()">&#9993; Correo</button>
</div>

<div class="running">
  <span>' . htmlspecialchars($nombre) . ' &middot; ' . htmlspecialchars($formato) . '</span>
  <span>Acta N&deg; ' . htmlspecialchars($a['codigo'] ?? '') . ' &middot; ' . htmlspecialchars($a['fecha_formal'] ?? '') . '</span>
</div>

<div class="doc">
  <div class="enc">
    <div class="enc-grid">' . $logoHtml . '
      <div class="enc-txt">
        <div class="empresa">' . htmlspecialchars($nombre) . '</div>' . $rucHtml . '
        <h1>' . htmlspecialchars($titulo) . '</h1>
        <div class="meta">
          Proceso: Gestión de Activos Tecnológicos &nbsp;&middot;&nbsp;
          <b>Código: ' . htmlspecialchars($formato) . '</b> &nbsp;&middot;&nbsp; Versión: 1.0<br>
          N&deg; de Acta: <b>' . htmlspecialchars($a['codigo'] ?? '') . '</b> &nbsp;&middot;&nbsp;
          Fecha: ' . htmlspecialchars($a['fecha_formal'] ?? '') . '
        </div>
      </div>' . $logoSpacer . '
    </div>
  </div>

  ' . $cuerpo . '

  <div class="pie">' . $pie . '</div>
</div>

<script>
var URL_ACTA=' . json_encode($urlPub) . ';
var COD_ACTA=' . json_encode($a['codigo'] ?? '') . ';
function compartirWa(){
  var m=encodeURIComponent("Adjunto el acta "+COD_ACTA+" de equipos TI. Puede verla, imprimirla o guardarla en PDF aquí:\\n"+URL_ACTA);
  window.open("https://wa.me/?text="+m,"_blank");
}
function compartirMail(){
  var s=encodeURIComponent("Acta "+COD_ACTA+" - Equipos TI");
  var b=encodeURIComponent("Buen día,\\n\\nAdjunto el enlace del acta de entrega/devolución de equipos:\\n"+URL_ACTA+"\\n\\nSaludos,\\nÁrea de Sistemas");
  window.location="mailto:?subject="+s+"&body="+b;
}
</script>
</body></html>';
    }

    // ================== SECCIONES ==================
    private static function secColaborador(array $a, bool $conCese = false): string
    {
        $col = $a['colaborador'];
        $filas = '
        <tr><td class="lbl">Nombre Completo</td><td>' . htmlspecialchars($col['nombre']) . '</td></tr>
        <tr><td class="lbl">DNI</td><td>' . htmlspecialchars($col['dni']) . '</td></tr>
        <tr><td class="lbl">Cargo</td><td>' . htmlspecialchars($col['cargo']) . '</td></tr>
        <tr><td class="lbl">Área</td><td>' . htmlspecialchars($col['area']) . '</td></tr>' .
        ($conCese
            ? '<tr><td class="lbl">Fecha de Cese</td><td>' . htmlspecialchars($col['fecha_cese']) . '</td></tr>'
            : '') . '
        <tr><td class="lbl">Jefe Inmediato</td><td>' . htmlspecialchars($col['jefe']) . '</td></tr>';
        return '<h2>1. Datos del colaborador</h2><table>' . $filas . '</table>';
    }

    private static function filaEquipo(array $e, string $esquema): string
    {
        $acc = implode(', ', $e['accesorios'] ?: ['—']);
        $base = '
        <tr><td class="lbl">Tipo de Equipo</td><td>' . htmlspecialchars($e['tipo']) . '</td></tr>
        <tr><td class="lbl">Marca / Modelo</td><td>' . htmlspecialchars($e['marca'] . ' ' . $e['modelo']) . '</td></tr>
        <tr><td class="lbl">Código Patrimonial</td><td>' . htmlspecialchars($e['codigo']) . '</td></tr>
        <tr><td class="lbl">Número de Serie</td><td>' . htmlspecialchars($e['serie'] ?: '—') . '</td></tr>' .
        ($e['imei'] ? '<tr><td class="lbl">IMEI</td><td>' . htmlspecialchars($e['imei']) . '</td></tr>' : '');

        if ($esquema === 'ent') {
            $specs = [];
            foreach (['Procesador' => 'cpu', 'Memoria RAM' => 'ram', 'Disco' => 'disco',
                      'Sistema Operativo' => 'so', 'Almacenamiento' => 'almacenamiento',
                      'Pantalla' => 'pantalla'] as $lbl => $k) {
                if (!empty($e['specs'][$k])) $specs[] = $lbl . ': ' . $e['specs'][$k];
            }
            $base .= ($specs ? '<tr><td class="lbl">Especificaciones</td><td>' . htmlspecialchars(implode(' · ', $specs)) . '</td></tr>' : '') . '
            <tr><td class="lbl">Accesorios entregados</td><td>' . htmlspecialchars($acc) . '</td></tr>
            <tr><td class="lbl">Estado físico</td><td>' . htmlspecialchars($e['condicion']) . '</td></tr>';
        } else {
            $base .= '
            <tr><td class="lbl">Accesorios devueltos</td><td>' . htmlspecialchars($acc) . '</td></tr>
            <tr><td class="lbl">Estado físico</td><td>&#9744; Bueno &nbsp; &#9744; Regular &nbsp; &#9744; Malo' .
            ($e['condicion_devolucion'] ? ' &nbsp;&rarr; <b>' . htmlspecialchars($e['condicion_devolucion']) . '</b>' : '') . '</td></tr>
            <tr><td class="lbl">Estado funcional</td><td>&#9744; Operativo &nbsp; &#9744; Con fallas</td></tr>' .
            ($e['obs_devolucion'] ? '<tr><td class="lbl">Observaciones</td><td>' . htmlspecialchars($e['obs_devolucion']) . '</td></tr>' : '');
        }
        return $base;
    }

    private static function checkboxes(array $items, array $marcados): string
    {
        $h = '<div class="chks">';
        foreach ($items as $val => $lbl) {
            $marca = in_array($val, $marcados, true);
            $h .= ($marca ? '<b>&#9745; ' : '&#9744; ') . htmlspecialchars($lbl) . ($marca ? ' ✔</b>' : '') . '<br>';
        }
        return $h . '</div>';
    }

    private static function firma(array $quienes, array $a): string
    {
        $ciudad = self::cfg('empresa_ciudad', 'Lima');
        $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio',
                  'Agosto','Setiembre','Octubre','Noviembre','Diciembre'];
        $f = strtotime($a['fecha'] ?? 'now');
        $fechaFirma = $ciudad . ', ' . date('j', $f) . ' de ' . $meses[(int)date('n', $f)]
                    . ' del ' . date('Y', $f);

        $cols = '';
        foreach ($quienes as $q) {
            $cols .= '<div class="firma"><div class="firma-linea"></div>
              <div class="quien">' . htmlspecialchars($q) . '</div></div>';
        }
        return '<div class="fecha-firma">' . $fechaFirma . '</div>
                <div class="firmas">' . $cols . '</div>';
    }

    private static function secObservaciones(array $a): string
    {
        return '<h2>Observaciones</h2>
        <div class="obs">' . htmlspecialchars($a['observaciones'] ?: 'Sin observaciones.') . '</div>';
    }

    // ================== PLANTILLA: ENTREGA ==================
    private static function cuerpoEntrega(array $a): string
    {
        $esMovil = ($a['familia'] ?? '') === 'movil';
        $e = $a['equipos_entregados'][0] ?? [];
        $col = $a['colaborador'];

        $condiciones = $esMovil ? '
      <p><b>Recepción de equipos:</b> El colaborador declara haber recibido el celular en buen estado,
      operativo y con todas sus funcionalidades en correcto funcionamiento.</p>
      <p><b>Propiedad:</b> El equipo es de propiedad exclusiva de LUMAT y se entrega únicamente para fines
      laborales, quedando prohibido su uso personal o ajeno a las funciones asignadas.</p>
      <p><b>Restricciones de manipulación:</b> Está terminantemente prohibida la manipulación, alteración o
      sustitución de: sistema operativo, configuraciones corporativas y aplicaciones instaladas; partes
      integradas, componentes internos o externos (pantalla, batería, cámara, memoria, etc.).</p>
      <p><b>Devolución:</b> Al término de la relación laboral, el colaborador se compromete a devolver el
      celular y sus accesorios en las mismas condiciones de entrega inicial, salvo el desgaste natural
      derivado del uso normal.</p>
      <p><b>Responsabilidad por daños o pérdida:</b> En caso de pérdida, daño, manipulación indebida o no
      devolución, LUMAT queda facultada a aplicar un descuento equivalente al valor del bien en las
      remuneraciones o liquidación de beneficios sociales, e iniciar las acciones legales correspondientes
      para la recuperación del patrimonio.</p>
      <p><b>Reporte de incidentes:</b> El colaborador se obliga a informar de inmediato cualquier
      desperfecto, falla técnica o incidente relacionado con el celular entregado.</p>'
        : '
      <p><b>Recepción de equipos:</b> El colaborador declara haber recibido los equipos en buen estado,
      operativos y con todas sus funcionalidades en correcto funcionamiento.</p>
      <p><b>Propiedad:</b> Los equipos son de propiedad exclusiva de LUMAT y se entregan únicamente para
      fines laborales, quedando prohibido su uso personal o ajeno a las funciones asignadas.</p>
      <p><b>Restricciones de manipulación:</b> Está terminantemente prohibida la manipulación, alteración o
      sustitución de: sistema operativo, configuraciones corporativas, software y licencias instaladas;
      partes integradas, componentes internos o externos (RAM, disco, batería, cámara, micrófono,
      conectores, etc.); configuraciones de red, perfiles de usuario y parámetros de seguridad definidos
      por LUMAT.</p>
      <p><b>Devolución:</b> Al término de la relación laboral, el colaborador se compromete a devolver los
      equipos y accesorios en las mismas condiciones de entrega inicial, salvo el desgaste natural derivado
      del uso normal.</p>
      <p><b>Responsabilidad por daños o pérdida:</b> En caso de pérdida, daño, manipulación indebida o no
      devolución, LUMAT queda facultada a aplicar un descuento equivalente al valor del bien en las
      remuneraciones o liquidación de beneficios sociales, e iniciar las acciones legales correspondientes
      para la recuperación del patrimonio.</p>
      <p><b>Reporte de incidentes:</b> El colaborador se obliga a informar de inmediato cualquier
      desperfecto, falla técnica o incidente relacionado con los equipos entregados.</p>';

        return self::secColaborador($a) . '

    <h2>2. Descripción del equipo entregado</h2>
    <table>' . self::filaEquipo($e, 'ent') . '</table>

    <h2>3. Condiciones de uso y devolución</h2>
    <div class="decl">' . $condiciones . '</div>

    <h2>4. Declaración de conformidad</h2>
    <div class="decl">Yo, <b>' . htmlspecialchars($col['nombre']) . '</b>, identificado con DNI N°
    <b>' . htmlspecialchars($col['dni']) . '</b>, declaro haber recibido a conformidad el equipo antes
    detallado, comprometiéndome a su adecuado uso y custodia, bajo las condiciones establecidas en el
    presente documento.</div>

    ' . self::secObservaciones($a) . '

    <h2>5. Firmas</h2>
    ' . self::firma(['Colaborador', 'Área de TI'], $a);
    }

    // ================== PLANTILLA: CAMBIO / REEMPLAZO ==================
    private static function cuerpoCambio(array $a): string
    {
        $ant = $a['equipos_devueltos'][0] ?? [];
        $nue = $a['equipos_entregados'][0] ?? [];

        $motivos = [
            'cese' => 'Cese de personal',
            'renovacion' => 'Reemplazo por renovación tecnológica',
            'falla' => 'Equipo con falla técnica',
            'sin_reparacion' => 'Equipo sin reparación',
            'reasignacion' => 'Cambio por reasignación interna',
            'ascenso' => 'Ascenso o cambio de puesto',
            'otro' => 'Otro',
        ];

        return self::secColaborador($a) . '

    <h2>2. Motivo del movimiento</h2>
    <div class="chks">' . self::checkboxes($motivos, [$a['motivo']] ?? []) .
        ($a['motivo_texto'] ? '<br>Específico: <b>' . htmlspecialchars($a['motivo_texto']) . '</b>' : '') . '
    </div>

    <h2>3. Devolución del equipo anterior</h2>
    <table>' . self::filaEquipo($ant, 'dev') . '</table>

    <h2>4. Entrega del nuevo equipo</h2>
    <table>' . self::filaEquipo($nue, 'ent') . '</table>

    <h2>5. Validación de TI</h2>
    <div class="chks">' . self::checkboxes([
            'verificado'      => 'Se verificó el estado del equipo devuelto.',
            'respaldo'        => 'Se realizó respaldo de la información institucional.',
            'borrado'         => 'Se eliminó la información del equipo anterior.',
            'configurado'     => 'Se configuró el nuevo equipo.',
            'aplicaciones'    => 'Se instalaron aplicaciones corporativas.',
            'antivirus'       => 'Se configuró el antivirus.',
            'm365'            => 'Se configuró Microsoft 365.',
            'vpn'             => 'Se configuró VPN.',
            'impresoras'      => 'Se configuró impresoras.',
            'inventario'      => 'Se actualizó el inventario de activos.',
            'patrimonial'     => 'Se actualizó el sistema de control patrimonial.',
        ], $a['validacion_ti'] ?? []) . '</div>

    <h2>6. Declaración de conformidad</h2>
    <div class="decl">
      El colaborador declara haber devuelto el activo tecnológico anteriormente asignado, junto con todos
      sus accesorios y componentes, aceptando que el Área de Tecnología realizará las verificaciones
      técnicas correspondientes para confirmar su estado físico, funcional y la integridad de la
      información institucional.<br><br>
      Asimismo, declara haber recibido el nuevo activo tecnológico descrito en la presente acta, verificando
      que se encuentra operativo y en condiciones adecuadas para el desempeño de sus funciones,
      comprometiéndose a utilizarlo exclusivamente para fines laborales, conforme a las políticas de
      seguridad de la información, uso aceptable de los recursos tecnológicos y gestión de activos
      establecidas por LUMAT.<br><br>
      El colaborador asume la responsabilidad sobre la custodia, conservación y uso adecuado del equipo
      recibido desde la fecha de su entrega, obligándose a reportar inmediatamente cualquier incidente,
      pérdida, robo o daño que pudiera afectar el activo asignado.<br><br>
      La presente acta constituye evidencia documental del proceso de devolución y entrega de activos
      tecnológicos, formando parte del registro oficial de control patrimonial y pudiendo ser utilizada como
      evidencia durante auditorías internas, externas y en el marco de los Sistemas de Gestión implementados
      por LUMAT.
    </div>

    ' . self::secObservaciones($a) . '

    <h2>7. Firmas</h2>
    ' . self::firma(['Colaborador', 'Área de TI', 'Jefe Inmediato'], $a);
    }

    // ================== PLANTILLA: DEVOLUCIÓN POR CESE ==================
    private static function cuerpoDevolucion(array $a): string
    {
        $tablaEq = function (array $lista, string $titulo): string {
            $h = '<h2>' . $titulo . '</h2><table>
              <tr><th style="width:14%">Equipo</th><th style="width:24%">Marca / Modelo</th>
                  <th style="width:14%">N° Activo</th><th style="width:16%">N° Serie / IMEI</th>
                  <th style="width:18%">Estado</th><th>Observaciones</th></tr>';
            if (!$lista) {
                $h .= '<tr><td colspan="6" style="text-align:center;color:#666">— Ninguno —</td></tr>';
            }
            foreach ($lista as $e) {
                $h .= '<tr><td>' . htmlspecialchars($e['tipo']) . '</td>
                  <td>' . htmlspecialchars($e['marca'] . ' ' . $e['modelo']) . '</td>
                  <td>' . htmlspecialchars($e['codigo']) . '</td>
                  <td>' . htmlspecialchars($e['serie'] ?: ($e['imei'] ?: '—')) . '</td>
                  <td>&#9744; Bueno &#9744; Regular &#9744; Malo' .
                  ($e['condicion_devolucion'] ? ' &rarr; <b>' . htmlspecialchars($e['condicion_devolucion']) . '</b>' : '') . '</td>
                  <td>' . htmlspecialchars($e['obs_devolucion'] ?: '') . '</td></tr>';
            }
            return $h . '</table>';
        };

        return self::secColaborador($a, true) .

        $tablaEq($a['equipos_devueltos'], '2. Devolución de equipos de TI') . '

    <h2>3. Verificación de accesos</h2>
    <div class="chks">' . self::checkboxes([
            'credenciales'  => 'Devolución de credenciales',
            'bloqueo'       => 'Bloqueo de cuentas corporativas',
            'token'         => 'Entrega de token MFA',
            'vpn'           => 'Revocación de VPN',
            'sistemas'      => 'Revocación de accesos a sistemas',
            'sesiones'      => 'Cierre de sesiones activas',
            'lineas'        => 'Cierre de líneas telefónicas',
        ], $a['verificacion_accesos'] ?? []) . '</div>

    <h2>4. Declaración de conformidad</h2>
    <div class="decl">
      Mediante la suscripción de la presente Acta, el colaborador declara haber efectuado la entrega total
      de los activos tecnológicos detallados en este documento, los cuales le fueron asignados por LUMAT
      para el desempeño de sus funciones laborales.<br><br>
      Asimismo, manifiesta que los bienes entregados corresponden a los equipos, dispositivos, accesorios
      y demás activos recibidos durante la vigencia de la relación laboral, encontrándose en condiciones
      acordes con su uso normal, salvo las observaciones expresamente registradas en la presente acta.<br><br>
      El Área de Tecnología de la Información (TI) deja constancia de la recepción física de los activos y
      se reserva el derecho de efectuar las verificaciones técnicas, funcionales y de seguridad necesarias
      para confirmar: la integridad física de los equipos y accesorios; el estado operativo de los activos
      entregados; la devolución de todos los componentes asignados; la eliminación o resguardo de la
      información institucional conforme a las políticas internas; y la revocación de accesos, credenciales,
      dispositivos de autenticación y demás recursos tecnológicos asignados al colaborador.<br><br>
      En caso de identificarse equipos no entregados, accesorios faltantes, daños atribuibles al uso indebido,
      alteraciones no autorizadas, manipulación de configuraciones de seguridad o cualquier incumplimiento de
      las obligaciones establecidas en las políticas corporativas sobre gestión de activos e información,
      LUMAT podrá adoptar las medidas administrativas, laborales y legales que correspondan, de conformidad
      con la legislación vigente, el Reglamento Interno de Trabajo, los documentos de asignación de activos
      y las políticas institucionales aplicables.<br><br>
      La firma de la presente Acta constituye evidencia documental de la entrega y recepción de los activos
      tecnológicos, formando parte del expediente de cese del colaborador y del sistema de control patrimonial
      y gestión de activos de información de LUMAT, pudiendo ser utilizada como evidencia en procesos de
      auditoría interna y externa, así como para el cumplimiento de los requisitos establecidos en los
      sistemas de gestión implementados por la organización.
    </div>

    ' . self::secObservaciones($a) . '

    <h2>5. Firmas</h2>
    ' . self::firma(['Colaborador', 'Área de TI', 'Jefe Inmediato', 'RR.HH.'], $a);
    }
}
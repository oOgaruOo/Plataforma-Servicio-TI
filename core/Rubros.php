<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Perfiles de rubro
//
// Cada empresa elige su rubro y el sistema se ADAPTA: carga catalogos propios del giro (categorias de tickets,
// tipos de equipo, areas), cambia la palabra «obra» por la que corresponde (sede, tienda, planta...) y propone
// que modulos mantener. Todo se agrega SIN borrar nada de lo existente y luego se puede ajustar a mano.
// ============================================================

final class Rubros
{
    /** @return array<string,array> */
    public static function todos(): array
    {
        return [
            'general' => [
                'nombre' => 'General / Oficinas', 'icono' => 'bi-building',
                'descripcion' => 'Empresa con oficinas y personal administrativo. Es el perfil base.',
                'termino' => ['sede', 'sedes'], 'modulos_off' => ['capacitaciones'],
                'categorias' => [],
                'tipos' => [], 'areas' => [],
            ],
            'construccion' => [
                'nombre' => 'Construccion e ingenieria', 'icono' => 'bi-cone-striped',
                'descripcion' => 'Obras y proyectos con equipos que viajan a campo, residentes y almacenes de obra.',
                'termino' => ['obra', 'obras'], 'modulos_off' => [],
                'categorias' => [
                    'Equipos en obra' => ['Soporte a equipos y redes en obras', ['Internet en obra', 'Equipo de residente', 'Traslado a obra', 'Retorno de obra']],
                    'Software de ingenieria' => ['CAD, presupuestos, cronogramas', ['AutoCAD / Revit', 'S10 / Delphin', 'MS Project', 'Licencia de ingenieria']],
                ],
                'tipos' => [['Estacion total / GPS', 'otro'], ['Plotter', 'impresion'], ['Radio / handy', 'telefonia'], ['Camara de obra', 'visual']],
                'areas' => [['Obras', 'OBR', 'Direccion y residencia de obras'], ['Presupuestos y costos', 'PRE', 'Costos y valorizaciones'], ['Seguridad y salud (SSOMA)', 'SSO', 'Seguridad, salud y medio ambiente']],
            ],
            'educacion' => [
                'nombre' => 'Educacion (colegios, institutos, universidades)', 'icono' => 'bi-mortarboard',
                'descripcion' => 'Aulas, laboratorios de computo, docentes y administrativos.',
                'termino' => ['sede', 'sedes'], 'modulos_off' => [],
                'categorias' => [
                    'Aulas y laboratorios' => ['Equipamiento de aulas y laboratorios', ['Proyector de aula', 'Laboratorio de computo', 'Pizarra digital', 'Prestamo a docente']],
                    'Plataforma educativa' => ['Aula virtual, notas y matricula', ['Aula virtual', 'Sistema de notas', 'Matricula en linea', 'Cuenta de estudiante']],
                ],
                'tipos' => [['Pizarra digital', 'visual'], ['Laptop docente', 'computo'], ['Tablet estudiantil', 'movil']],
                'areas' => [['Direccion academica', 'ACA', 'Gestion academica'], ['Secretaria academica', 'SEC', 'Matriculas y registros'], ['Docentes', 'DOC', 'Plana docente'], ['Bienestar estudiantil', 'BIE', 'Tutoria y bienestar']],
            ],
            'salud' => [
                'nombre' => 'Salud (clinicas, consultorios, laboratorios)', 'icono' => 'bi-heart-pulse',
                'descripcion' => 'Consultorios, admision, historia clinica y equipos de apoyo al diagnostico.',
                'termino' => ['sede', 'sedes'], 'modulos_off' => [],
                'categorias' => [
                    'Historia clinica / HIS' => ['Sistema de historia clinica e informacion en salud', ['Acceso al HIS', 'Lentitud del sistema', 'Receta electronica', 'Facturacion de atencion']],
                    'Equipos de apoyo' => ['Equipos conectados a diagnostico', ['Lector de codigo / pulseras', 'Equipo de laboratorio', 'Integracion con imagenes']],
                ],
                'tipos' => [['Lector de codigo de barras', 'periferico'], ['Impresora de etiquetas', 'impresion'], ['Terminal de admision', 'computo']],
                'areas' => [['Admision y caja', 'ADM', 'Admision de pacientes y caja'], ['Consultorios', 'CON', 'Atencion medica'], ['Laboratorio', 'LAB', 'Laboratorio clinico'], ['Farmacia', 'FAR', 'Farmacia y botica'], ['Enfermeria', 'ENF', 'Enfermeria y hospitalizacion']],
            ],
            'comercio' => [
                'nombre' => 'Comercio y retail (tiendas)', 'icono' => 'bi-shop',
                'descripcion' => 'Tiendas, cajas, punto de venta, inventario y comprobantes.',
                'termino' => ['tienda', 'tiendas'], 'modulos_off' => ['capacitaciones'],
                'categorias' => [
                    'Punto de venta' => ['Cajas, POS y comprobantes electronicos', ['Caja no abre', 'Impresora de tickets', 'Comprobante electronico', 'Cierre de caja']],
                    'Inventario y stock' => ['Sistema de inventario y almacen', ['Descuadre de stock', 'Lector de codigo', 'Ingreso de mercaderia']],
                ],
                'tipos' => [['POS / Caja', 'computo'], ['Lector de codigo de barras', 'periferico'], ['Impresora de tickets', 'impresion'], ['Cajon de dinero', 'periferico'], ['Balanza electronica', 'periferico']],
                'areas' => [['Tiendas', 'TIE', 'Atencion y venta en tienda'], ['Caja', 'CAJ', 'Cajas y cobranza'], ['Almacen', 'ALM', 'Almacen y reposicion'], ['Compras', 'COM', 'Compras y proveedores']],
            ],
            'industria' => [
                'nombre' => 'Industria y manufactura', 'icono' => 'bi-gear-wide-connected',
                'descripcion' => 'Plantas, lineas de produccion, almacenes y mantenimiento industrial.',
                'termino' => ['planta', 'plantas'], 'modulos_off' => [],
                'categorias' => [
                    'Planta y produccion' => ['TI de planta y lineas de produccion', ['Terminal de planta', 'Red de planta', 'Reporte de produccion', 'Balanza / etiquetadora']],
                    'ERP y calidad' => ['ERP, calidad y trazabilidad', ['Acceso al ERP', 'Orden de produccion', 'Trazabilidad por lote']],
                ],
                'tipos' => [['Terminal industrial', 'computo'], ['Etiquetadora industrial', 'impresion'], ['PLC / controlador', 'otro'], ['Tablet de planta', 'movil']],
                'areas' => [['Produccion', 'PRO', 'Lineas de produccion'], ['Calidad', 'CAL', 'Control de calidad'], ['Mantenimiento industrial', 'MAN', 'Mantenimiento de maquinaria'], ['Almacen', 'ALM', 'Almacen de materia prima y producto']],
            ],
            'logistica' => [
                'nombre' => 'Logistica y transporte', 'icono' => 'bi-truck',
                'descripcion' => 'Bases, flota, almacenes y rastreo de equipos y conductores.',
                'termino' => ['base', 'bases'], 'modulos_off' => [],
                'categorias' => [
                    'Flota y rastreo' => ['GPS, telemetria y equipos de flota', ['GPS de unidad', 'Tablet de conductor', 'Telemetria']],
                    'Almacen y despacho' => ['Sistema de almacen y despacho', ['Guia de remision', 'Pistola lectora', 'Despacho']],
                ],
                'tipos' => [['GPS vehicular', 'otro'], ['Pistola lectora (PDA)', 'movil'], ['Tablet de conductor', 'movil']],
                'areas' => [['Operaciones y flota', 'OPE', 'Despacho y control de flota'], ['Almacen', 'ALM', 'Almacen y distribucion'], ['Taller', 'TAL', 'Mantenimiento de unidades']],
            ],
            'servicios' => [
                'nombre' => 'Servicios profesionales y consultoria', 'icono' => 'bi-briefcase',
                'descripcion' => 'Estudios, consultoras y empresas de servicios con equipo en cliente.',
                'termino' => ['sucursal', 'sucursales'], 'modulos_off' => [],
                'categorias' => [
                    'Trabajo remoto' => ['Teletrabajo y acceso a distancia', ['VPN', 'Videoconferencia', 'Equipo en domicilio']],
                    'Equipos en cliente' => ['Equipos que trabajan en sede del cliente', ['Equipo en cliente', 'Entrega a cliente', 'Devolucion de cliente']],
                ],
                'tipos' => [['Laptop de consultor', 'computo'], ['Equipo de videoconferencia', 'visual']],
                'areas' => [['Consultoria', 'CNS', 'Equipo consultor'], ['Comercial', 'COM', 'Ventas y propuestas'], ['Operaciones', 'OPE', 'Entrega de servicios']],
            ],
        ];
    }

    public static function existe(string $r): bool { return isset(self::todos()[$r]); }

    public static function actual(): string
    {
        try {
            $v = Database::getValue("SELECT valor FROM configuracion WHERE clave = 'rubro'");
            return ($v !== null && $v !== false && self::existe((string)$v)) ? (string)$v : '';
        } catch (Throwable $e) {
            return '';
        }
    }

    /** Palabra que reemplaza a «obra» en la pantalla: [singular, plural]. null si no hay que cambiar nada. */
    public static function terminos(): ?array
    {
        $r = self::actual();
        if ($r === '' || $r === 'construccion') return null;
        $t = self::todos()[$r]['termino'] ?? null;
        return $t ? ['singular' => $t[0], 'plural' => $t[1]] : null;
    }

    /** Lista para el selector de la pantalla. */
    public static function lista(): array
    {
        $o = [];
        foreach (self::todos() as $k => $p) {
            $o[] = [
                'clave' => $k, 'nombre' => $p['nombre'], 'icono' => $p['icono'], 'descripcion' => $p['descripcion'],
                'termino' => $p['termino'][0],
                'modulos_off' => $p['modulos_off'],
                'categorias' => array_keys($p['categorias']), 'tipos' => array_column($p['tipos'], 0), 'areas' => array_column($p['areas'], 0),
            ];
        }
        return $o;
    }

    /**
     * Aplica un perfil: agrega sus catalogos (sin borrar ni repetir), fija el rubro y propone los modulos.
     * @return array{categorias:int,subcategorias:int,tipos:int,areas:int}
     */
    public static function aplicar(string $clave, bool $modulos = true): array
    {
        $p = self::todos()[$clave] ?? null;
        if (!$p) throw new InvalidArgumentException('Rubro desconocido.');
        $n = ['categorias' => 0, 'subcategorias' => 0, 'tipos' => 0, 'areas' => 0];

        foreach ($p['categorias'] as $nombre => [$desc, $subs]) {
            $id = Database::getValue('SELECT id FROM categorias WHERE nombre = ?', [$nombre]);
            if (!$id) {
                $id = Database::insert('categorias', ['nombre' => $nombre, 'descripcion' => $desc, 'activo' => 1]);
                $n['categorias']++;
            }
            foreach ($subs as $s) {
                if (!Database::getValue('SELECT id FROM subcategorias WHERE categoria_id = ? AND nombre = ?', [$id, $s])) {
                    Database::insert('subcategorias', ['categoria_id' => $id, 'nombre' => $s, 'activo' => 1]);
                    $n['subcategorias']++;
                }
            }
        }
        foreach ($p['tipos'] as [$nombre, $familia]) {
            if (!Database::getValue('SELECT id FROM tipo_equipos WHERE nombre = ?', [$nombre])) {
                Database::insert('tipo_equipos', ['nombre' => $nombre, 'familia' => $familia, 'activo' => 1]);
                $n['tipos']++;
            }
        }
        foreach ($p['areas'] as [$nombre, $codigo, $desc]) {
            if (!Database::getValue('SELECT id FROM areas WHERE nombre = ?', [$nombre])) {
                $cod = $codigo;
                if (Database::getValue('SELECT id FROM areas WHERE codigo = ?', [$cod])) $cod = null;   // el codigo ya lo usa otra area
                Database::insert('areas', ['nombre' => $nombre, 'codigo' => $cod, 'descripcion' => $desc, 'estado' => 'activo']);
                $n['areas']++;
            }
        }
        Licencia::setCfg('rubro', $clave, 'Rubro de la empresa (perfil que adapta el sistema)');
        if ($modulos) Licencia::guardarModulosOff($p['modulos_off']);
        return $n;
    }
}

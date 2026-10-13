<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Perfiles de acta por TIPO DE EQUIPO (familia)
//
// Cada familia de equipo (computo, movil, impresion, red, energia,
// visual, periferico, telefonia, otro) tiene su propio contenido en
// las actas de ENTREGA, DEVOLUCION y CAMBIO:
//   - titulo del documento
//   - datos tecnicos que se imprimen del equipo
//   - lista de verificacion de TI al entregar ("ent") y al recibir ("dev")
//   - condiciones de uso y custodia
//   - frase de la declaracion de devolucion
//
// Los textos usan {emp} = nombre de la empresa (Configuracion).
// Son una base para que RR.HH. / Legal los revise: se editan aqui.
// ============================================================

class ActaPerfiles
{
    public const MOVIMIENTOS = ['entrega' => 'Entrega', 'devolucion' => 'Devolución', 'cambio' => 'Cambio de equipo'];

    public const MOTIVOS_DEVOLUCION = [
        'fin_uso'      => 'Ya no requiere el equipo',
        'fin_prestamo' => 'Fin del préstamo',
        'fin_proyecto' => 'Fin del proyecto u obra',
        'cese'         => 'Cese de personal',
        'renovacion'   => 'Renovación tecnológica',
        'falla'        => 'Equipo con falla técnica',
        'reasignacion' => 'Reasignación interna',
        'otro'         => 'Otro',
    ];

    public const MOTIVOS_CAMBIO = [
        'renovacion'     => 'Reemplazo por renovación tecnológica',
        'falla'          => 'Equipo con falla técnica',
        'sin_reparacion' => 'Equipo sin reparación',
        'robo_perdida'   => 'Robo o pérdida del equipo anterior',
        'reasignacion'   => 'Cambio por reasignación interna',
        'ascenso'        => 'Ascenso o cambio de puesto',
        'cese'           => 'Cese de personal',
        'otro'           => 'Otro',
    ];

    public const CONDICIONES = [
        'nuevo' => 'Nuevo', 'bueno' => 'Bueno', 'regular' => 'Regular', 'danado' => 'Dañado',
        'faltante' => 'No entregado (faltante)', 'irreparable' => 'Irreparable',
    ];

    /** Etiqueta de cada especificacion tecnica (misma clave que guarda Inventario). */
    public const SPECS = [
        'cpu' => 'Procesador', 'ram' => 'Memoria RAM', 'disco' => 'Disco / almacenamiento',
        'so' => 'Sistema operativo', 'grafica' => 'Tarjeta gráfica',
        'almacenamiento' => 'Almacenamiento', 'pantalla' => 'Pantalla',
        'linea' => 'Línea telefónica', 'plan' => 'Plan de datos',
        'tecnologia' => 'Tecnología', 'color' => 'Impresión a color', 'consumible' => 'Consumible / repuesto',
        'rendimiento' => 'Rendimiento mensual', 'duplex' => 'Impresión dúplex',
        'puertos' => 'Puertos', 'velocidad' => 'Velocidad', 'banda' => 'Banda WiFi', 'rack' => 'Montable en rack',
        'capacidad_va' => 'Capacidad (VA)', 'potencia_w' => 'Potencia (W)', 'baterias' => 'Baterías', 'tomas' => 'Tomacorrientes',
        'pulgadas' => 'Pulgadas', 'resolucion' => 'Resolución', 'panel' => 'Panel / tecnología', 'video' => 'Conexión de video',
        'conexion_p' => 'Conexión', 'extension' => 'Extensión / línea', 'canales' => 'Canales / líneas',
        'descripcion_tecnica' => 'Descripción técnica',
        'ip_asignada' => 'Dirección IP', 'vlan' => 'VLAN',
    ];

    /** Verificaciones que valen para cualquier familia. */
    private const COMUN_ENT = [
        'accesorios' => 'Los accesorios entregados fueron verificados junto con el receptor.',
    ];
    private const COMUN_DEV = [
        'fisico'     => 'Se revisó el estado físico del equipo (carcasa, pantalla, puertos y partes visibles).',
        'accesorios' => 'Los accesorios fueron devueltos completos.',
    ];

    public static function perfiles(): array
    {
        static $p = null;
        if ($p !== null) return $p;

        $p = [

            'computo' => [
                'nombre' => 'Equipos de cómputo', 'titulo' => 'EQUIPO DE CÓMPUTO', 'titulo_pl' => 'EQUIPOS DE CÓMPUTO',
                'specs' => ['cpu', 'ram', 'disco', 'so', 'grafica'],
                'ent' => [
                    'encendido' => 'El equipo enciende y el sistema operativo inicia correctamente.',
                    'imagen'    => 'Equipo configurado con el estándar corporativo.',
                    'usuario'   => 'Cuenta de usuario creada y credenciales entregadas al receptor.',
                    'antivirus' => 'Antivirus y actualizaciones de seguridad activos.',
                    'cifrado'   => 'Cifrado de disco habilitado (BitLocker u otro).',
                    'software'  => 'Software y licencias corporativas instalados.',
                ] + self::COMUN_ENT,
                'dev' => [
                    'encendido' => 'El equipo enciende y el sistema operativo carga correctamente.',
                    'respaldo'  => 'Se realizó el respaldo de la información institucional.',
                    'borrado'   => 'Se eliminó la información y las cuentas del usuario (equipo restablecido).',
                    'cuentas'   => 'Se cerraron las sesiones y cuentas corporativas del equipo.',
                    'licencias' => 'Se liberaron las licencias de software asignadas.',
                ] + self::COMUN_DEV,
                'uso' => [
                    ['Software y configuración', 'Está prohibido instalar software no autorizado, desinstalar o desactivar el antivirus, el cifrado de disco y demás medidas de seguridad, así como alterar el sistema operativo, los perfiles de usuario, la configuración de red y los parámetros de seguridad definidos por {emp}.'],
                    ['Información', 'La información generada o almacenada en el equipo es de propiedad de {emp}. El receptor es responsable de resguardarla en los repositorios autorizados y de no compartir sus credenciales de acceso.'],
                    ['Manipulación del hardware', 'Está prohibido abrir el equipo o sustituir sus componentes internos o externos (memoria RAM, disco, batería, pantalla, teclado, conectores, etc.). El mantenimiento y las reparaciones los realiza únicamente el Área de TI.'],
                    ['Traslado y custodia', 'El equipo no debe dejarse sin supervisión en vehículos, espacios públicos ni áreas sin control de acceso. En caso de robo o pérdida, el receptor debe comunicarlo de inmediato al Área de TI y presentar la denuncia policial correspondiente.'],
                ],
                'dev_texto' => 'En equipos de cómputo se verificará además el respaldo y la eliminación de la información institucional y de las cuentas del usuario, y la liberación de las licencias de software asignadas.',
            ],

            'movil' => [
                'nombre' => 'Equipos móviles', 'titulo' => 'EQUIPO MÓVIL', 'titulo_pl' => 'EQUIPOS MÓVILES',
                'specs' => ['so', 'almacenamiento', 'pantalla', 'linea', 'plan'],
                'ent' => [
                    'funcional' => 'Se probaron encendido, pantalla táctil, llamadas, datos y cámara.',
                    'imei'      => 'El IMEI fue verificado con el equipo y su caja.',
                    'linea'     => 'Línea activa y chip (SIM) entregado, cuando corresponde.',
                    'cuenta'    => 'Cuenta corporativa y aplicaciones institucionales configuradas.',
                    'bloqueo'   => 'Bloqueo de pantalla (PIN o biometría) configurado.',
                ] + self::COMUN_ENT,
                'dev' => [
                    'funcional'   => 'El equipo enciende y funciona (pantalla, botones, llamadas y datos).',
                    'cuentas'     => 'Se cerraron las cuentas personales y corporativas y se desactivó el bloqueo de activación (Find My / FRP).',
                    'respaldo'    => 'Se realizó el respaldo de la información institucional.',
                    'restablecido'=> 'El equipo fue restablecido de fábrica.',
                    'linea'       => 'Se recibió el chip (SIM) o se registró la reasignación de la línea.',
                ] + self::COMUN_DEV,
                'uso' => [
                    ['Uso del equipo y la línea', 'El equipo y la línea telefónica asociada (cuando corresponda) se destinan exclusivamente a las funciones laborales. Está prohibido ceder la línea, cambiar el chip, solicitar portabilidad o modificar el plan contratado sin autorización del Área de TI.'],
                    ['Configuración y seguridad', 'Está prohibido alterar el sistema operativo (root, jailbreak o desbloqueo), desinstalar las aplicaciones y configuraciones corporativas, retirar el bloqueo de pantalla o instalar aplicaciones de procedencia no autorizada.'],
                    ['Manipulación de partes', 'Está prohibida la manipulación, alteración o sustitución de partes integradas o componentes internos o externos (pantalla, batería, cámara, memoria, etc.).'],
                    ['Robo, pérdida o extravío', 'Ante un robo, pérdida o extravío, el receptor debe avisar de inmediato al Área de TI para bloquear la línea y el equipo (IMEI) y presentar la denuncia policial correspondiente.'],
                ],
                'dev_texto' => 'En equipos móviles se verificará además la desvinculación de las cuentas personales y corporativas, el restablecimiento del equipo y la devolución o reasignación de la línea y el chip.',
            ],

            'impresion' => [
                'nombre' => 'Impresión e imagen', 'titulo' => 'EQUIPO DE IMPRESIÓN', 'titulo_pl' => 'EQUIPOS DE IMPRESIÓN',
                'specs' => ['tecnologia', 'color', 'duplex', 'consumible', 'rendimiento', 'ip_asignada'],
                'ent' => [
                    'prueba'     => 'Se realizó la impresión de prueba y el resultado es correcto.',
                    'consumible' => 'Consumible instalado y nivel verificado.',
                    'red'        => 'Equipo configurado en red y controladores instalados.',
                    'contador'   => 'Contador de páginas registrado al momento de la entrega.',
                ] + self::COMUN_ENT,
                'dev' => [
                    'prueba'     => 'Se realizó la impresión de prueba y el resultado es correcto.',
                    'consumible' => 'Se verificó el estado de los consumibles (tóner, cartuchos, tambor).',
                    'contador'   => 'Contador de páginas registrado al momento de la devolución.',
                    'red'        => 'Se eliminaron la configuración de red y los usuarios del equipo.',
                ] + self::COMUN_DEV,
                'uso' => [
                    ['Uso y custodia', 'El equipo de impresión se destina al uso institucional. El receptor (o el responsable del área) vela por su cuidado y por un uso racional, evitando impresiones personales o innecesarias.'],
                    ['Consumibles y mantenimiento', 'Los consumibles (tóner, cartuchos, tinta, papel) y repuestos deben ser los autorizados por el Área de TI. El mantenimiento, la extracción de atascos internos y las reparaciones los realiza únicamente el personal de TI.'],
                    ['Ubicación y red', 'El equipo no debe cambiarse de ubicación ni reconfigurarse (red, dirección IP, controladores) sin autorización del Área de TI.'],
                ],
                'dev_texto' => 'En equipos de impresión se registrará además el contador de páginas y se verificará el estado de los consumibles y la eliminación de las configuraciones de red y de usuarios.',
            ],

            'red' => [
                'nombre' => 'Equipos de red', 'titulo' => 'EQUIPO DE RED Y COMUNICACIONES', 'titulo_pl' => 'EQUIPOS DE RED Y COMUNICACIONES',
                'specs' => ['puertos', 'velocidad', 'banda', 'rack', 'ip_asignada', 'vlan'],
                'ent' => [
                    'encendido'    => 'El equipo enciende y sus puertos e interfaces están operativos.',
                    'config'       => 'Configuración aplicada y documentada (IP, VLAN, parámetros de red).',
                    'credenciales' => 'Credenciales de administración entregadas al responsable autorizado de TI.',
                    'montaje'      => 'Equipo instalado, rotulado y asegurado en su ubicación.',
                ] + self::COMUN_ENT,
                'dev' => [
                    'encendido'    => 'El equipo enciende y sus puertos e interfaces responden.',
                    'reset'        => 'Se restableció la configuración a valores de fábrica.',
                    'credenciales' => 'Se revocaron o rotaron las credenciales de administración.',
                ] + self::COMUN_DEV,
                'uso' => [
                    ['Custodia de infraestructura', 'El equipo forma parte de la infraestructura de red de {emp}. El responsable debe velar por su seguridad física, ventilación y suministro eléctrico, y evitar accesos no autorizados al lugar donde está instalado.'],
                    ['Configuración', 'Está prohibido modificar la configuración, las credenciales de administración, el cableado o el firmware del equipo. Estas acciones corresponden únicamente al personal autorizado del Área de TI.'],
                    ['Traslado', 'El equipo no debe desconectarse, reubicarse ni sustituirse sin coordinación previa con el Área de TI.'],
                ],
                'dev_texto' => 'En equipos de red se verificará además el restablecimiento de la configuración y la revocación o rotación de las credenciales de administración.',
            ],

            'energia' => [
                'nombre' => 'Energía eléctrica', 'titulo' => 'EQUIPO DE ENERGÍA (UPS / ESTABILIZADOR)', 'titulo_pl' => 'EQUIPOS DE ENERGÍA (UPS / ESTABILIZADORES)',
                'specs' => ['capacidad_va', 'potencia_w', 'baterias', 'tomas'],
                'ent' => [
                    'encendido' => 'El equipo enciende sin alarmas ni fallas.',
                    'bateria'   => 'Baterías con carga completa y prueba de autonomía realizada.',
                    'tomas'     => 'Tomacorrientes verificados y operativos.',
                    'carga'     => 'Se verificó que la capacidad es adecuada para los equipos que alimentará.',
                ] + self::COMUN_ENT,
                'dev' => [
                    'encendido' => 'El equipo enciende sin alarmas ni fallas.',
                    'bateria'   => 'Se verificó el estado de las baterías y su autonomía.',
                    'tomas'     => 'Tomacorrientes verificados.',
                ] + self::COMUN_DEV,
                'uso' => [
                    ['Uso', 'El equipo de energía solo debe alimentar los equipos de cómputo y comunicaciones autorizados. Está prohibido conectar equipos de alto consumo (hornos, estufas, impresoras láser, aire acondicionado, etc.) o exceder la capacidad indicada.'],
                    ['Cuidado', 'Debe mantenerse ventilado y alejado de la humedad y el polvo. El cambio de baterías y cualquier reparación los realiza únicamente el Área de TI.'],
                ],
                'dev_texto' => 'En equipos de energía se verificará además el estado de las baterías y la capacidad de respaldo.',
            ],

            'visual' => [
                'nombre' => 'Visualización', 'titulo' => 'EQUIPO DE VISUALIZACIÓN (MONITOR / PROYECTOR)', 'titulo_pl' => 'EQUIPOS DE VISUALIZACIÓN',
                'specs' => ['pulgadas', 'resolucion', 'panel', 'video'],
                'ent' => [
                    'imagen'   => 'La imagen es correcta, sin píxeles muertos, manchas ni líneas.',
                    'conexion' => 'Conexión de video probada con el equipo asignado.',
                ] + self::COMUN_ENT,
                'dev' => [
                    'imagen'   => 'Se revisó la imagen (píxeles muertos, manchas, líneas o parpadeo).',
                    'conexion' => 'Se probó el encendido y la conexión de video.',
                ] + self::COMUN_DEV,
                'uso' => [
                    ['Uso y cuidado', 'El equipo debe manipularse con cuidado: no apoyar objetos sobre la pantalla, no usar químicos agresivos para su limpieza y mantenerlo ventilado. En proyectores, no moverlos mientras están encendidos ni obstruir su ventilación.'],
                    ['Configuración y accesorios', 'No debe alterarse la configuración del equipo ni intercambiar sus cables y accesorios con otros equipos sin conocimiento del Área de TI.'],
                ],
                'dev_texto' => 'En equipos de visualización se verificará además que no existan daños en la pantalla o la imagen (píxeles muertos, manchas, líneas) y la devolución de los cables.',
            ],

            'periferico' => [
                'nombre' => 'Periféricos', 'titulo' => 'PERIFÉRICO', 'titulo_pl' => 'PERIFÉRICOS',
                'specs' => ['conexion_p'],
                'ent' => [
                    'funcional' => 'El periférico fue probado y funciona correctamente.',
                ] + self::COMUN_ENT,
                'dev' => [
                    'funcional' => 'Se probó el periférico y su funcionamiento.',
                ] + self::COMUN_DEV,
                'uso' => [
                    ['Uso', 'El periférico se entrega para uso laboral; debe cuidarse y conectarse solo al equipo asignado. Cualquier falla debe reportarse al Área de TI para su revisión o reposición.'],
                ],
                'dev_texto' => 'En periféricos se verificará además su funcionamiento y la devolución de los receptores o cables que correspondan.',
            ],

            'telefonia' => [
                'nombre' => 'Telefonía', 'titulo' => 'EQUIPO DE TELEFONÍA', 'titulo_pl' => 'EQUIPOS DE TELEFONÍA',
                'specs' => ['extension', 'canales', 'ip_asignada'],
                'ent' => [
                    'funcional' => 'Se verificó el tono, el registro de la extensión y las llamadas de prueba.',
                    'extension' => 'Extensión asignada y configurada.',
                ] + self::COMUN_ENT,
                'dev' => [
                    'funcional' => 'Se verificó el funcionamiento del equipo.',
                    'extension' => 'Se liberó la extensión y se borró la configuración del usuario.',
                ] + self::COMUN_DEV,
                'uso' => [
                    ['Uso', 'El equipo de telefonía y la extensión asociada son de uso laboral. Está prohibido reconfigurar el equipo, reasignar la extensión o desviar llamadas sin autorización del Área de TI.'],
                ],
                'dev_texto' => 'En equipos de telefonía se verificará además la liberación de la extensión y el borrado de la configuración del usuario.',
            ],

            'otro' => [
                'nombre' => 'Otros equipos TI', 'titulo' => 'EQUIPO DE TECNOLOGÍA DE LA INFORMACIÓN', 'titulo_pl' => 'EQUIPOS DE TECNOLOGÍA DE LA INFORMACIÓN',
                'specs' => ['descripcion_tecnica', 'ip_asignada'],
                'ent' => [
                    'funcional' => 'El equipo fue probado y funciona correctamente.',
                ] + self::COMUN_ENT,
                'dev' => [
                    'funcional' => 'Se probó el equipo y su funcionamiento.',
                ] + self::COMUN_DEV,
                'uso' => [
                    ['Uso', 'El receptor se compromete a darle un uso adecuado y exclusivamente laboral, a conservarlo en buen estado y a no manipular su configuración ni sus componentes sin autorización del Área de TI.'],
                ],
                'dev_texto' => 'Se verificará además el funcionamiento del equipo y la integridad de los componentes asignados.',
            ],
        ];
        return $p;
    }

    public static function existe(string $familia): bool
    {
        return isset(self::perfiles()[$familia]);
    }

    /** Perfil de una familia; las desconocidas usan «otro». */
    public static function de(?string $familia): array
    {
        $p = self::perfiles();
        return $p[(string)$familia] ?? $p['otro'];
    }

    public static function claveFamilia(?string $familia): string
    {
        return isset(self::perfiles()[(string)$familia]) ? (string)$familia : 'otro';
    }

    /** Verificaciones de TI de una familia: $mov = 'ent' (al entregar) o 'dev' (al recibir). */
    public static function checks(string $mov, ?string $familia): array
    {
        $mov = $mov === 'dev' ? 'dev' : 'ent';
        return self::de($familia)[$mov];
    }

    /** Solo las claves validas de esa familia (descarta lo que no exista). */
    public static function checksValidos(string $mov, ?string $familia, $marcados): array
    {
        if (!is_array($marcados)) return [];
        $ok = array_keys(self::checks($mov, $familia));
        return array_values(array_unique(array_filter(array_map('strval', $marcados), fn($k) => in_array($k, $ok, true))));
    }

    public static function etiquetaSpec(string $k): string
    {
        return self::SPECS[$k] ?? ucfirst(str_replace('_', ' ', $k));
    }

    /** Titulo del documento segun el movimiento y las familias que intervienen. */
    public static function titulo(string $mov, array $familias): string
    {
        $familias = array_values(array_unique(array_map([self::class, 'claveFamilia'], $familias)));
        $uno = count($familias) === 1;
        $p = $uno ? self::de($familias[0]) : self::de('otro');
        if ($mov === 'devolucion') return 'ACTA DE DEVOLUCIÓN DE ' . $p['titulo_pl'];
        if ($mov === 'cambio')     return 'ACTA DE CAMBIO (REEMPLAZO) DE ' . $p['titulo_pl'];
        return 'ACTA DE ENTREGA Y RECEPCIÓN DE ' . $p['titulo_pl'];
    }

    /** Estructura lista para el navegador (listas de verificacion, motivos, etc.). */
    public static function paraJs(): array
    {
        $fam = [];
        foreach (self::perfiles() as $k => $p) {
            $fam[$k] = ['nombre' => $p['nombre'], 'ent' => $p['ent'], 'dev' => $p['dev']];
        }
        return [
            'familias' => $fam,
            'movimientos' => self::MOVIMIENTOS,
            'motivos_devolucion' => self::MOTIVOS_DEVOLUCION,
            'motivos_cambio' => self::MOTIVOS_CAMBIO,
            'condiciones' => self::CONDICIONES,
        ];
    }
}

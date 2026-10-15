<?php
// @autor Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código de autor: SIGTI-DEV-AMA-2026 · © 2026
// ============================================================
// SIGTI - Guias del sistema (centro de ayuda).
// Cada guia: id, titulo, icono (Bootstrap Icons), color, categoria, permiso (lista de alternativas [modulo, accion],
// null = todos), ruta (modulo al que lleva), resumen, para (a quien le sirve), tags y secciones.
// Cada seccion: titulo, pasos[] y opcionalmente nota (consejo) y alerta (cuidado).
// Lo usan api/guias/catalogo.php (pantalla) y views/guias/ver.php (version imprimible).
// ============================================================

class Guias
{
    public const CATEGORIAS = ['Para empezar', 'Operación', 'Inventario', 'Personas', 'Análisis', 'Administración', 'Guías técnicas'];

    /** Todas las guias (sin filtrar por permiso). */
    public static function todas(): array
    {
        return [
            [
                'id' => 'primeros-pasos', 'titulo' => 'Primeros pasos en SIGTI', 'icono' => 'rocket-takeoff', 'color' => '#2563eb',
                'categoria' => 'Para empezar', 'permiso' => null, 'ruta' => 'dashboard/principal', 'para' => 'Todas las personas',
                'resumen' => 'Cómo moverse por el sistema: menú, notificaciones, chat, tu perfil y por qué ves unas opciones y otras no.',
                'tags' => ['inicio', 'menú', 'notificaciones', 'perfil', 'permisos', 'contraseña', 'foto'],
                'secciones' => [
                    ['titulo' => 'Entrar y salir', 'pasos' => [
                        'Abre la dirección del sistema en el navegador (Chrome o Edge recomendados).',
                        'Escribe tu usuario y contraseña y pulsa «Ingresar». El ojito de la contraseña sirve para ver lo que escribes.',
                        'Al terminar, abre el menú de tu foto (arriba a la derecha) y pulsa «Cerrar sesión». Si dejas el sistema sin usar un buen rato, la sesión se cierra sola por seguridad.',
                    ], 'alerta' => 'Si la cuenta se bloquea por intentos fallidos, pide a Sistemas que la desbloquee desde Usuarios. Si olvidaste la contraseña, también te la restablecen ahí.'],
                    ['titulo' => 'El menú de la izquierda', 'pasos' => [
                        'El menú se divide en General, Operación, Inventario, Personal, Análisis y Administración.',
                        'Solo ves las opciones que tu rol permite. Si te falta alguna que necesitas, pide a Sistemas que revise tus permisos.',
                        'El botón de tres rayas (arriba a la izquierda) oculta o muestra el menú; en el celular se oculta solo al elegir una opción.',
                        'Cada sección se pliega con un clic en su título (el menú recuerda cuáles cerraste). Si una sección plegada tiene pendientes, ves su total junto al título.',
                        'Las insignias de colores muestran lo pendiente de cada módulo: rojo = urgente (vencido o atrasado), ámbar = por atender, azul = informativo (por ejemplo, mensajes sin leer). Pasa el mouse por encima para ver el detalle.',
                        'El buscador del menú filtra mientras escribes (atajos: tecla «/» o Ctrl + K); Enter abre el primer resultado y Esc limpia la búsqueda.',
                        'Pasa el mouse sobre una opción y pulsa la estrella para fijarla en «Favoritos», arriba del menú. El botón de las flechas (abajo) contrae el menú a solo iconos.',
                        'Si algo no se actualiza después de una mejora del sistema, pulsa Ctrl + F5 para recargar la página completa.',
                    ]],
                    ['titulo' => 'Notificaciones, chat y perfil', 'pasos' => [
                        'La campana muestra avisos: tickets asignados, comentarios, vencimientos, actas y anuncios. «Marcar todas» las deja como leídas.',
                        'El globo azul flotante abre el chat emergente (sin salir de la pantalla en la que estás) y muestra los mensajes sin leer.',
                        'En el menú de tu foto puedes cambiar tu foto. Se verá en el chat, en los tickets, en tu carné y en la pantalla de ingreso: al escribir tu usuario aparece tu foto.',
                        '«Acerca de SIGTI» (también en el menú de tu foto) muestra la versión del sistema y los datos del desarrollador.',
                    ]],
                    ['titulo' => 'Tablas y filtros', 'pasos' => [
                        'Casi todas las listas tienen un buscador arriba a la derecha: escribe parte del código, nombre o serie.',
                        'Los filtros (estado, tipo, fechas…) se aplican con «Filtrar». Para volver a ver todo, deja los filtros vacíos y filtra de nuevo.',
                        'Haz clic en el título de una columna para ordenar por ella.',
                    ], 'nota' => 'Los botones con lupa u ojo abren el detalle; el lápiz edita; los íconos de color rojo eliminan o dan de baja y siempre piden confirmación.'],
                ],
            ],
            [
                'id' => 'dashboard', 'titulo' => 'Dashboard y anuncios', 'icono' => 'speedometer2', 'color' => '#0891b2',
                'categoria' => 'Para empezar', 'permiso' => null, 'ruta' => 'dashboard/principal', 'para' => 'Todas las personas',
                'resumen' => 'Qué muestra cada tarjeta, cómo leer «Requiere atención» y cómo publicar anuncios para todo el personal.',
                'tags' => ['dashboard', 'tarjetas', 'anuncios', 'alertas', 'sla', 'inicio', 'rrhh'],
                'secciones' => [
                    ['titulo' => 'Qué ves en el Dashboard', 'pasos' => [
                        'Arriba, los accesos rápidos llevan a los módulos que más se usan.',
                        'Las tarjetas resumen la mesa de ayuda, el inventario, la operación (mantenimientos, devoluciones, actas), el software y el personal. Haz clic en una tarjeta para ir al módulo.',
                        'Si el módulo de RR.HH. está activo y tienes permiso, también aparecen tarjetas de contratos por vencer/vencidos y vacaciones pendientes, además de alertas de requerimientos de personal pendientes de tomar, atrasados o puestos sin validar (en «Requiere atención»). Llevan directo a RR.HH. › Contratos o RR.HH. › Requerimientos.',
                        '«Requiere atención» reúne lo urgente: tickets con el SLA vencido, mantenimientos atrasados, devoluciones vencidas, garantías por vencer, licencias por vencer, requerimientos de RR.HH. atrasados y más. Lo rojo es urgente; lo amarillo conviene revisarlo pronto.',
                        '«Para ti» lista tus tickets asignados, los que están sin asignar y los mantenimientos activos.',
                        'Los gráficos muestran los últimos 6 meses. Todo se actualiza solo cada minuto; «Refrescar» lo hace al instante.',
                    ], 'nota' => 'Cada persona ve solo lo que su rol permite. Quien no tiene acceso a Software, por ejemplo, no verá esa tarjeta.'],
                    ['titulo' => 'Publicar un anuncio (quien gestiona la configuración)', 'pasos' => [
                        'En el Dashboard pulsa «Gestionar anuncios» y luego «Nuevo anuncio».',
                        'Elige una plantilla (mantenimiento, corte, aviso…) o escribe el título y el mensaje. La vista previa en vivo muestra cómo se verá.',
                        'Define la vigencia (fecha y hora de inicio y fin) y a quién va dirigido: todos, un rol o un área.',
                        'Marca «Fijo» si no debe poder cerrarse. Marca «Avisar por notificación» para que llegue también a la campana.',
                        'Desde la lista puedes editar, duplicar, ocultar o eliminar, y ver cuántas personas ya lo cerraron.',
                    ]],
                ],
            ],
            [
                'id' => 'tickets', 'titulo' => 'Mesa de ayuda: crear y atender tickets', 'icono' => 'life-preserver', 'color' => '#dc2626',
                'categoria' => 'Operación', 'permiso' => [['tickets', 'ver'], ['tickets', 'ver_propios'], ['tickets', 'ver_area']], 'ruta' => 'tickets/lista',
                'para' => 'Solicitantes y técnicos',
                'resumen' => 'Cómo reportar un problema, cómo se atiende, qué significa cada estado y cómo se mide el SLA.',
                'tags' => ['tickets', 'soporte', 'incidencia', 'sla', 'prioridad', 'asignar', 'solución', 'reabrir'],
                'secciones' => [
                    ['titulo' => 'Crear un ticket', 'pasos' => [
                        'Entra a Operación › Tickets y pulsa «Nuevo ticket».',
                        'Elige el solicitante y el área afectada, la categoría (y subcategoría si corresponde), la prioridad y el impacto.',
                        'Escribe un título corto y una descripción clara: qué pasó, desde cuándo y qué equipo está involucrado.',
                        'Si ayuda, adjunta imágenes como evidencia. Pulsa «Registrar».',
                    ], 'nota' => 'La prioridad define el tiempo máximo de atención (SLA): Crítica y Alta tienen plazos muy cortos. Úsalas solo cuando el trabajo está detenido.'],
                    ['titulo' => 'Atender un ticket (técnicos)', 'pasos' => [
                        'Abre el ticket y usa «Asignar» para ponerle técnico (o asígnatelo tú).',
                        'Registra avances con «Comentar». Marca «Nota interna» si el comentario no debe verlo el solicitante.',
                        'Usa «Cambiar estado» cuando esté en atención, esperando respuesta del usuario o esperando a un proveedor.',
                        'Al terminar, pulsa «Finalizar ticket»: indica la solución aplicada y el tiempo de trabajo. El ticket pasa a Resuelto.',
                        'El ticket se cierra con «Cerrar definitivamente». Si el problema vuelve, «Reabrir» lo reactiva y cuenta como reapertura.',
                    ]],
                    ['titulo' => 'Estados del ticket', 'pasos' => [
                        'Abierto → recién creado, sin técnico.',
                        'Asignado → ya tiene técnico responsable.',
                        'En atención → el técnico está trabajando en él.',
                        'Pendiente de usuario / de proveedor → se espera una respuesta o una pieza; el reloj del servicio queda explicado en los comentarios.',
                        'Resuelto → la solución está aplicada. Cerrado → confirmado y archivado. Cancelado → no procede (se indica el motivo).',
                    ]],
                    ['titulo' => 'SLA y fechas', 'pasos' => [
                        'El SLA es la fecha límite de atención según la prioridad. En las listas aparece en rojo cuando ya venció.',
                        'En Reportes › Tickets ves el cumplimiento del SLA, el tiempo promedio de resolución y la carga por técnico.',
                    ], 'alerta' => 'Un ticket con el SLA vencido sigue contando como vencido aunque se resuelva después: por eso conviene comentar el motivo del retraso.'],
                ],
            ],
            [
                'id' => 'capacitaciones', 'titulo' => 'Solicitar y gestionar capacitaciones', 'icono' => 'mortarboard', 'color' => '#0d9488',
                'categoria' => 'Operación', 'permiso' => [['capacitaciones', 'solicitar'], ['capacitaciones', 'ver_area'], ['capacitaciones', 'ver'], ['capacitaciones', 'gestionar']],
                'ruta' => 'capacitaciones/lista', 'para' => 'Todas las áreas y Sistemas',
                'resumen' => 'Cómo pedir una capacitación a Sistemas desde cualquier área, qué significa cada estado y cómo la programa y cierra Sistemas.',
                'tags' => ['capacitación', 'curso', 'taller', 'solicitud', 'agenda', 'excel', 'office', 'calificar', 'temas'],
                'secciones' => [
                    ['titulo' => 'Pedir una capacitación (cualquier persona)', 'pasos' => [
                        'Entra a Capacitaciones › «Mis solicitudes» y pulsa «Solicitar capacitación».',
                        'Elige un tema de la lista o «Otro» y escribe cuál. Cuenta qué necesitan aprender, cuántas personas son y si prefieren presencial o virtual.',
                        'Si hay una fecha límite, marca «Es urgente» o indica la fecha deseada.',
                        'Pulsa «Enviar solicitud». Sistemas recibe un aviso y tú ves la solicitud en «Mis solicitudes».',
                    ]],
                    ['titulo' => 'Qué significa cada estado', 'pasos' => [
                        'Enviada: la solicitud llegó a Sistemas y espera ser revisada.',
                        'En revisión: Sistemas la está evaluando.',
                        'Programada: ya tiene fecha, hora, lugar o enlace y capacitador. También aparece en la pestaña «Agenda».',
                        'Realizada: la capacitación se dio. Puedes calificarla de 1 a 5 estrellas.',
                        'Rechazada: Sistemas no pudo atenderla; verás el motivo. Cancelada: la canceló quien la pidió o Sistemas.',
                    ], 'nota' => 'Cada cambio de estado te llega a la campana de notificaciones. Mientras esté Enviada, En revisión o Programada, puedes cancelarla.'],
                    ['titulo' => 'Para Sistemas: revisar, programar y cerrar', 'pasos' => [
                        'En «Solicitudes» verás todas (o las de tu área, según tu permiso). Filtra por estado, área o texto; las tarjetas de arriba muestran lo pendiente.',
                        '«Empezar a revisar» cambia la solicitud a En revisión. «Programar» pide fecha, hora, duración, lugar o enlace y capacitador; también sirve para reprogramar.',
                        '«Rechazar» exige un motivo, que verá quien la pidió. Pasada la fecha, la capacitación queda marcada «Por cerrar» hasta que pulses «Marcar realizada» e indiques los asistentes.',
                        'La pestaña «Temas» administra el catálogo que se ofrece al solicitar (activar, desactivar, editar).',
                    ]],
                ],
            ],
            [
                'id' => 'mantenimiento', 'titulo' => 'Mantenimiento de equipos', 'icono' => 'tools', 'color' => '#ea580c',
                'categoria' => 'Operación', 'permiso' => [['mantenimiento', 'ver']], 'ruta' => 'mantenimiento/lista', 'para' => 'Técnicos y jefatura de Sistemas',
                'resumen' => 'Registrar mantenimientos correctivos y preventivos, el flujo de estados, repuestos, costos y la programación de preventivos.',
                'tags' => ['mantenimiento', 'preventivo', 'correctivo', 'proveedor', 'repuestos', 'costos', 'garantía', 'percance'],
                'secciones' => [
                    ['titulo' => 'Las tres pestañas', 'pasos' => [
                        'Panel: resumen de lo que está en curso, atrasado o por vencer.',
                        'Mantenimientos: la lista completa con filtros por estado, tipo, técnico y fechas.',
                        'Preventivos programados: los mantenimientos que se repiten cada cierto tiempo para cada equipo.',
                    ]],
                    ['titulo' => 'Registrar un mantenimiento', 'pasos' => [
                        'Pulsa «Registrar mantenimiento» y elige el equipo. El equipo pasa a «En mantenimiento» mientras dura.',
                        'Indica si es correctivo (hay una falla) o preventivo, el origen (por ejemplo un ticket) y el técnico.',
                        'Si lo repara un tercero, elige el proveedor y registra la fecha de envío y la fecha estimada de retorno.',
                        'Avanza el trabajo con «Cambiar estado» (evaluación, en proceso, enviado a proveedor, cotizado, aprobado, en reparación, listo…).',
                        'Anota el diagnóstico y la solución con «Editar diagnóstico/solución». Agrega repuestos con su costo.',
                    ]],
                    ['titulo' => 'Terminar y costos', 'pasos' => [
                        'Con «Finalizar» indica el resultado: el equipo vuelve a stock, se entrega a su usuario o queda como no reparable.',
                        'El costo total suma la mano de obra y los repuestos. Se ve en cada mantenimiento y en Reportes › Mantenimiento (por mes, por tipo de equipo y por proveedor).',
                        'Si ocurrió un daño o pérdida durante el servicio, regístralo con «Registrar percance» para dejar constancia.',
                        'Pasa la evidencia (fotos, cotización) con «Evidencia»; queda guardada en el mismo mantenimiento.',
                    ], 'alerta' => 'Un mantenimiento «Enviado a proveedor» con la fecha de retorno vencida aparece como atrasado en el Dashboard.'],
                    ['titulo' => 'Preventivos programados', 'pasos' => [
                        'En la pestaña «Preventivos programados» pulsa «Programar preventivo», elige equipo, tipo, frecuencia y la próxima fecha.',
                        'El sistema genera el mantenimiento cuando llega la fecha. «Forzar ahora» lo genera de inmediato.',
                        'Con el interruptor puedes pausar o reactivar una programación.',
                    ]],
                ],
            ],
            [
                'id' => 'equipos', 'titulo' => 'Inventario de equipos', 'icono' => 'pc-display', 'color' => '#16a34a',
                'categoria' => 'Inventario', 'permiso' => [['equipos', 'ver']], 'ruta' => 'equipos/lista', 'para' => 'Sistemas y almacén',
                'resumen' => 'Registrar equipos, leer su ficha, estados, importar desde Excel, imprimir etiquetas y dar de baja.',
                'tags' => ['equipos', 'inventario', 'laptop', 'pc', 'serie', 'etiquetas', 'importar', 'exportar', 'baja', 'estado'],
                'secciones' => [
                    ['titulo' => 'Registrar un equipo', 'pasos' => [
                        'En Inventario › Equipos pulsa «Registrar equipo».',
                        'Elige el tipo (laptop, PC, celular, impresora, red…). El formulario muestra los datos propios de ese tipo: especificaciones, IMEI, direcciones de red, etc.',
                        'Completa marca, modelo, número de serie, fecha y costo de compra, proveedor, garantía y ubicación.',
                        'En laptops, PC y servidores aparece la sección «Software instalado»: ya trae el software estándar y puedes agregar o quitar programas. Si no indicas ninguno, el sistema pregunta antes de guardar.',
                        'Agrega los accesorios que se entregan (cargador, mochila…). Al guardar, el equipo recibe su código (EQ-…) y queda «En stock».',
                    ]],
                    ['titulo' => 'La ficha del equipo', 'pasos' => [
                        'Al abrir un equipo ves sus datos, el software instalado, la persona o el destino al que está asignado y su historial de movimientos.',
                        'Desde la ficha: «Editar», «Asignar» (entrega), «Trasladar» (cambia de ubicación), «Rastreo» y «Software».',
                    ]],
                    ['titulo' => 'Estados', 'pasos' => [
                        'En stock → disponible. Asignado / En préstamo → lo tiene una persona, área, proyecto u obra.',
                        'En revisión, En mantenimiento, En reparación externa → fuera de servicio por un trabajo.',
                        'Obsoleto → ya no se recomienda usar. De baja → salió del inventario y no se puede editar.',
                    ], 'nota' => 'El estado cambia solo cuando haces entregas, devoluciones o mantenimientos. Evita cambiarlo a mano salvo en revisión u obsoleto.'],
                    ['titulo' => 'Importar, exportar y etiquetas', 'pasos' => [
                        '«Importar Excel» carga muchos equipos a la vez desde una plantilla; el sistema te muestra los errores antes de guardar.',
                        '«Exportar» descarga el inventario en CSV o en Excel con formato.',
                        'Marca equipos con las casillas y pulsa «Etiquetas (n)» para imprimir sus etiquetas con código. El diseño de la etiqueta se cambia en Diseñador de Etiquetas.',
                    ]],
                    ['titulo' => 'Dar de baja', 'pasos' => [
                        'Un equipo asignado debe devolverse antes. Luego usa la opción de baja de la ficha, indica el motivo y quién autoriza.',
                        'La baja es definitiva: libera los programas que tenía instalados y lo deja solo para consulta.',
                    ], 'alerta' => 'Antes de dar de baja un equipo con información de la empresa, asegúrate de haber respaldado y borrado sus datos.'],
                ],
            ],
            [
                'id' => 'software', 'titulo' => 'Software y licencias', 'icono' => 'app-indicator', 'color' => '#7c3aed',
                'categoria' => 'Inventario', 'permiso' => [['software', 'ver']], 'ruta' => 'software/lista', 'para' => 'Sistemas, auditoría y gerencia',
                'resumen' => 'Qué programas tiene cada equipo, cuántas licencias hay, qué está sin licencia o prohibido y cómo sacar el informe para auditoría.',
                'tags' => ['software', 'licencias', 'auditoría', 'programas', 'catálogo', 'instalaciones', 'cumplimiento', 'gerencia'],
                'secciones' => [
                    ['titulo' => 'Cómo está organizado', 'pasos' => [
                        'Resumen: cobertura de equipos con software registrado, observaciones para auditoría y cumplimiento por programa.',
                        'Instalaciones: cada programa instalado en cada equipo, con versión, fecha y licencia usada.',
                        'Por equipo: el estado de cada equipo y el botón «Confirmar inventario», que deja constancia de quién verificó y cuándo.',
                        'Catálogo: los programas conocidos (autorizado, en evaluación o prohibido; estándar o no).',
                        'Licencias: tipo, número de puestos, fecha de activación y duración (el vencimiento se calcula solo), costo y proveedor. Al registrar o editar una licencia puedes adjuntar la factura (PDF o imagen) y verla después con «Ver factura».',
                    ]],
                    ['titulo' => 'Registrar software', 'pasos' => [
                        'Para un equipo nuevo, usa la sección «Software instalado» al registrarlo.',
                        'Para uno existente, abre la ficha › «Software», o usa «Registrar instalación» en la pestaña Instalaciones.',
                        'Para muchos equipos: «Instalar en varios equipos» (elige el programa y marca los equipos) o «Carga masiva» (pega líneas CODIGO;Programa;Versión;Licencia).',
                        'Si eliges «Licencia automática», el sistema usa la primera licencia vigente con un puesto libre. Si no hay, instala igual pero queda observado como «sin licencia».',
                    ]],
                    ['titulo' => 'Observaciones para auditoría', 'pasos' => [
                        'Instalación de un programa prohibido; instalación sin licencia o con licencia vencida.',
                        'Programas con más instalaciones que puestos de licencia.',
                        'Licencias por vencer (60 días) o ya vencidas; equipos de cómputo sin software registrado.',
                        'Equipos cuyo inventario de software no se confirma hace más de 180 días.',
                    ], 'nota' => 'Haz clic en una observación del Resumen para ver directamente la lista filtrada.'],
                    ['titulo' => 'Informe y descargas', 'pasos' => [
                        '«Informe para gerencia» abre un informe listo para imprimir o guardar como PDF, sin claves de licencia.',
                        '«Descargar» entrega CSV o Excel de instalaciones, licencias, cumplimiento o equipos.',
                        'Las claves completas solo las ve quien tiene el permiso «software.gestionar».',
                    ]],
                ],
            ],
            [
                'id' => 'asignaciones', 'titulo' => 'Entregas, préstamos y devoluciones', 'icono' => 'box-seam', 'color' => '#0d9488',
                'categoria' => 'Inventario', 'permiso' => [['asignaciones', 'ver']], 'ruta' => 'asignaciones/lista', 'para' => 'Sistemas y almacén',
                'resumen' => 'Entregar un equipo a una persona, área, proyecto u obra; préstamos con fecha de devolución; recibir y cambiar equipos.',
                'tags' => ['asignaciones', 'entrega', 'préstamo', 'devolución', 'solicitud', 'cambio', 'traslado', 'actas'],
                'secciones' => [
                    ['titulo' => 'Entregar un equipo', 'pasos' => [
                        'En Inventario › Asignaciones pulsa «Registrar entrega».',
                        'Elige el destino: una persona, un área, un proyecto o una obra, y quién recibe y firma.',
                        'Elige el tipo: entrega permanente, préstamo (con fecha de devolución) o traslado. Para reemplazar un equipo, usa la operación «cambio».',
                        'Selecciona el equipo (solo aparecen los que están en stock) y la condición en que sale.',
                        'Al guardar se genera automáticamente el acta de entrega para firmar.',
                    ], 'nota' => 'Los equipos enviados a un proyecto u obra también llevan fecha de devolución estipulada.'],
                    ['titulo' => 'Solicitudes de equipo', 'pasos' => [
                        'Cualquiera con permiso puede usar «Solicitar equipo» e indicar qué necesita y por qué.',
                        'En la pestaña «Solicitudes» se aprueba o se rechaza. Una solicitud aprobada se atiende con «Entregar», que abre el formulario de entrega ya llenado.',
                    ]],
                    ['titulo' => 'Recibir un equipo', 'pasos' => [
                        'En la fila de la entrega pulsa «Recibir equipo».',
                        'Indica la condición en que vuelve (bueno, regular, dañado o con faltantes) y las observaciones.',
                        'El equipo vuelve a stock y se genera el acta de devolución.',
                    ], 'alerta' => 'Los préstamos con la fecha vencida aparecen en rojo y en el Dashboard como «Devoluciones vencidas».'],
                ],
            ],
            [
                'id' => 'actas', 'titulo' => 'Actas de entrega, devolución y cambio', 'icono' => 'file-earmark-text', 'color' => '#4f46e5',
                'categoria' => 'Inventario', 'permiso' => [['actas', 'generar']], 'ruta' => 'actas/lista', 'para' => 'Sistemas',
                'resumen' => 'Cómo se generan las actas, cómo imprimirlas y registrar la firma, y cómo configurar logo y códigos de formato.',
                'tags' => ['actas', 'firma', 'formato', 'imprimir', 'logo', 'entrega', 'devolución', 'cambio'],
                'secciones' => [
                    ['titulo' => 'Cómo se generan', 'pasos' => [
                        'Al registrar una entrega, un cambio o recibir un equipo, el acta se crea sola y se adapta al tipo de equipo (cómputo, celular, impresión, red…): trae sus datos técnicos, la lista de verificación y las cláusulas.',
                        'Si hubo movimientos sin acta, usa «Generar acta» para documentarlos (incluso varios equipos del mismo destino).',
                        'Las actas ya emitidas no cambian aunque después edites el formato.',
                    ]],
                    ['titulo' => 'Imprimir y firmar', 'pasos' => [
                        'En Actas › lista, abre el acta para verla o imprimirla (o guardarla como PDF).',
                        'Cuando regrese firmada, sube el archivo con el botón de la flecha ⬆ (PDF o foto, hasta 10 MB): queda adjunto y el acta pasa a «Firmada». Con el clip o el botón de ver lo abres en una ventana emergente, sin descargarlo.',
                        'Si te equivocaste de archivo, vuelve a pulsar ⬆ para reemplazarlo o quitarlo. Las pendientes se ven en el filtro «Pendiente» y en el Dashboard.',
                    ]],
                    ['titulo' => 'Configurar', 'pasos' => [
                        '«Códigos de formato» define el código del documento (por ejemplo TI-FOR-003) por tipo de equipo y movimiento.',
                        '«Logo / Encabezado» cambia el logo y los datos de la empresa que salen en las actas.',
                    ], 'nota' => 'Los textos de las cláusulas son una propuesta; conviene que RR. HH. o Legal los revisen.'],
                ],
            ],
            [
                'id' => 'proyectos', 'titulo' => 'Proyectos y obras', 'icono' => 'kanban', 'color' => '#ca8a04',
                'categoria' => 'Inventario', 'permiso' => [['asignaciones', 'ver']], 'ruta' => 'proyectos/lista', 'para' => 'Sistemas y jefes de obra',
                'resumen' => 'Registrar proyectos u obras, enviarles equipos con fecha de devolución y ver todo lo que tienen en campo.',
                'tags' => ['proyectos', 'obras', 'campo', 'equipos', 'devolución'],
                'secciones' => [
                    ['titulo' => 'Crear y administrar', 'pasos' => [
                        'En Inventario › Proyectos y Obras pulsa «Nuevo proyecto / obra».',
                        'Indica si es proyecto u obra, el nombre, el cliente, la ubicación, el responsable y las fechas de inicio y fin.',
                        'Cuando termina, cámbiale el estado a cerrado: antes verifica que todos sus equipos hayan vuelto.',
                    ]],
                    ['titulo' => 'Enviar equipos', 'pasos' => [
                        'Dentro del proyecto u obra pulsa «Enviar un equipo» y elige el equipo, quién lo recibe y la fecha de devolución.',
                        'Se genera el acta de entrega a nombre del responsable. Al volver, se recibe desde Asignaciones › «Recibir equipo».',
                        'Reportes › Inventario muestra cuántos equipos y qué valor tiene cada proyecto u obra.',
                    ]],
                ],
            ],
            [
                'id' => 'seguimiento', 'titulo' => 'Seguimiento y rastreo de equipos', 'icono' => 'geo-alt', 'color' => '#e11d48',
                'categoria' => 'Inventario', 'permiso' => [['equipos', 'ver']], 'ruta' => 'equipos/seguimiento', 'para' => 'Sistemas',
                'resumen' => 'Saber dónde está cada equipo de cómputo: verificación de ubicación, sedes y redes, y el agente de rastreo en vivo.',
                'tags' => ['rastreo', 'ubicación', 'mapa', 'robo', 'agente', 'sedes', 'verificación'],
                'secciones' => [
                    ['titulo' => 'Verificación de ubicación', 'pasos' => [
                        'En la pestaña «Verificación de ubicación» ves los equipos de cómputo con un semáforo: verde (verificado en los últimos 30 días), ámbar (30 a 90) y rojo (más de 90 o nunca).',
                        'Cuando compruebes dónde está un equipo, pulsa «Confirmar que está en esa ubicación».',
                    ]],
                    ['titulo' => 'Sedes y redes', 'pasos' => [
                        'En «Sedes y redes» registra tus oficinas y los rangos de red que usan. Así el sistema reconoce desde qué sede se conecta cada equipo.',
                    ]],
                    ['titulo' => 'Rastreo en vivo', 'pasos' => [
                        'Desde la ficha del equipo pulsa «Rastreo» y actívalo. Descarga el agente (.ps1) e instálalo una sola vez en el equipo.',
                        'Luego el equipo informa su ubicación y red. Si se pierde o lo roban, usa «Marcar perdido / robado»; cuando aparezca, «Equipo encontrado».',
                        'Con «Regenerar token» invalidas el agente anterior (por ejemplo si lo copiaron).',
                    ], 'nota' => 'La guía técnica paso a paso del agente está en el centro de ayuda, sección «Guías técnicas».'],
                ],
            ],
            [
                'id' => 'personal', 'titulo' => 'Personal: ingresos, ceses y listas', 'icono' => 'people', 'color' => '#db2777',
                'categoria' => 'Personas', 'permiso' => [['personal', 'ver']], 'ruta' => 'personal/lista', 'para' => 'RR. HH. y Sistemas',
                'resumen' => 'Registrar el ingreso de una persona, su lista de pendientes de TI, procesar un cese y usar la selección masiva.',
                'tags' => ['personal', 'ingreso', 'cese', 'checklist', 'cumpleaños', 'etiquetas', 'lockers', 'correos'],
                'secciones' => [
                    ['titulo' => 'Registrar un ingreso', 'pasos' => [
                        'En Personal › «Registrar ingreso (alta)» completa DNI, nombres, área, cargo, fechas y datos de contacto.',
                        'La persona queda «Por ingresar» y se le crea una lista de pendientes de TI (correo, usuario, equipo, accesos…). Marca cada ítem cuando lo cumplas.',
                        'Al ingresar, pasa a «Activo» y ya puede recibir equipos y crear tickets.',
                    ]],
                    ['titulo' => 'Ceses', 'pasos' => [
                        'Registra la fecha de cese en la ficha de la persona. Desde «Ceses / Pendientes» ves quiénes se van pronto y qué equipos deben devolver.',
                        'Al procesar el cese se generan las devoluciones y sus actas. Las personas cesadas ya no aparecen en las listas de selección.',
                    ], 'alerta' => 'Antes del último día, recupera los equipos y revoca accesos: el Dashboard avisa los ceses de los próximos 30 días.'],
                    ['titulo' => 'Selección masiva', 'pasos' => [
                        'Marca personas con las casillas (se conservan al cambiar de página) y abre el menú «Seleccionados».',
                        'Desde ahí puedes imprimir etiquetas del personal o de lockers, copiar sus correos o descargar la lista en CSV.',
                        '«Cumpleaños» muestra los próximos cumpleaños del mes.',
                    ]],
                ],
            ],
            [
                'id' => 'carnets', 'titulo' => 'Carnets de trabajadores', 'icono' => 'person-vcard', 'color' => '#059669',
                'categoria' => 'Personas', 'permiso' => [['personal', 'ver']], 'ruta' => 'carnets/lista', 'para' => 'RR. HH.',
                'resumen' => 'Generar, imprimir y entregar los carnés institucionales y llevar el control de cuáles ya se imprimieron.',
                'tags' => ['carnets', 'carné', 'imprimir', 'cr80', 'foto'],
                'secciones' => [
                    ['titulo' => 'Flujo', 'pasos' => [
                        'En Personal › Carnets marca a las personas y pulsa «Generar».',
                        'Con «Imprimir» se abre la hoja lista para tu impresora de tarjetas (tamaño CR80).',
                        'Luego marca «Marcar impresos» y, al repartirlos, «Marcar entregados». Si te equivocas, el carné se puede revertir al estado anterior.',
                    ], 'nota' => 'La foto de la persona sale de su ficha: pídele que se tome la foto o sube una desde Personal.'],
                ],
            ],
            [
                'id' => 'chat', 'titulo' => 'Chat interno', 'icono' => 'chat-dots', 'color' => '#2563eb',
                'categoria' => 'Para empezar', 'permiso' => null, 'ruta' => 'chat/principal', 'para' => 'Todas las personas',
                'resumen' => 'Conversar con una persona, con un área, en grupos o en reuniones, con archivos, stickers, notas de voz, menciones y reacciones.',
                'tags' => ['chat', 'mensajes', 'grupos', 'reuniones', 'archivos', 'emojis', 'menciones', 'stickers', 'audio', 'nota de voz'],
                'secciones' => [
                    ['titulo' => 'Empezar una conversación', 'pasos' => [
                        'Desde cualquier pantalla, pulsa el globo azul: se abre un chat emergente para ver tus conversaciones, escribir, adjuntar un archivo o empezar un chat nuevo (persona o área) sin salir de lo que estás haciendo. «Abrir el chat completo» te lleva a este módulo.',
                        'Pulsa «Nuevo» y elige: chat con una persona, hablar con un área, crear un grupo o programar una reunión (grupos y reuniones se crean en el chat completo).',
                        'Cada área tiene su canal. Los grupos tienen administradores que pueden agregar o quitar integrantes, cambiar el nombre y cerrar el grupo.',
                        'Una reunión es una conversación con fecha, hora y enlace de videollamada; el sistema avisa a los invitados.',
                    ]],
                    ['titulo' => 'Mensajes', 'pasos' => [
                        'Usa emojis, adjunta archivos o pega una captura directamente. Los archivos se guardan de forma privada.',
                        'Responde a un mensaje concreto, menciona a alguien con @ y reacciona con un emoji.',
                        'Puedes editar o borrar tus propios mensajes. La campana del chat silencia el sonido de aviso.',
                    ], 'nota' => 'En los chats entre personas, el área de Sistemas puede supervisar la conversación en modo lectura y la pantalla se lo avisa a los participantes.'],
                    ['titulo' => 'Stickers y notas de voz', 'pasos' => [
                        'El botón de stickers (junto al de emojis) abre una galería de imágenes para enviar con un clic, igual en el chat completo y en el chat emergente.',
                        'El botón del micrófono graba una nota de voz desde el navegador (hasta 2 minutos): se puede cancelar antes de enviarla. Se reproduce con un reproductor de audio dentro del mensaje.',
                        'El área de Sistemas puede agregar (subir una imagen) o quitar stickers del catálogo desde el mismo selector; un sticker quitado ya no se puede volver a enviar, pero los mensajes que ya lo usaron lo siguen mostrando.',
                    ], 'nota' => 'Grabar audio necesita permiso del navegador para el micrófono, y el navegador solo lo permite si el sitio usa una conexión segura (HTTPS) o se abre como localhost.'],
                ],
            ],
            [
                'id' => 'reportes', 'titulo' => 'Reportes e informes', 'icono' => 'graph-up-arrow', 'color' => '#0284c7',
                'categoria' => 'Análisis', 'permiso' => [['reportes', 'ver'], ['reportes', 'ver_area']], 'ruta' => 'reportes/principal', 'para' => 'Jefatura y gerencia',
                'resumen' => 'Elegir el período, leer cada pestaña, imprimir el informe para gerencia y descargar los datos en Excel.',
                'tags' => ['reportes', 'informe', 'excel', 'csv', 'gerencia', 'sla', 'costos', 'pdf', 'rrhh'],
                'secciones' => [
                    ['titulo' => 'Elegir el período', 'pasos' => [
                        'Arriba tienes períodos rápidos (este mes, 30 días, 3 meses, 6 meses, este año) y dos fechas para un rango propio. Pulsa «Aplicar».',
                        'Las tarjetas con flecha comparan contra el período anterior de la misma duración: ▲ es más, ▼ es menos.',
                    ]],
                    ['titulo' => 'Las pestañas', 'pasos' => [
                        'Resumen: una mirada de todo para gerencia, con las observaciones que requieren atención.',
                        'Tickets: volumen, cumplimiento del SLA, tiempos, técnicos, categorías, áreas y detalle de cada ticket.',
                        'Mantenimiento: costos por mes, tipo de equipo y proveedor; equipos con más fallas.',
                        'Inventario: estado, antigüedad, garantías, valor, ubicación verificada y equipos por proyecto u obra.',
                        'Software: cumplimiento por programa y licencias (muestra la situación actual).',
                        'Asignaciones y actas: entregas, devoluciones vencidas y actas pendientes de firma.',
                        'Personal: ingresos, ceses y distribución por área.',
                        'RR.HH.: vacantes pendientes, cuántas están atrasadas (pasó la fecha límite), el tiempo promedio que toma cubrir una vacante (desde que se crea el requerimiento hasta que se marca «Cubierto»), solicitadas y cubiertas por mes, y la distribución por estado y por área. No incluye datos de contratos ni de vacaciones.',
                    ]],
                    ['titulo' => 'Imprimir y descargar', 'pasos' => [
                        '«Informe» abre una versión con el logo de la empresa y espacios para firmas, lista para imprimir o guardar como PDF.',
                        '«Descargar» entrega todo el detalle del período en Excel (con formato) o CSV.',
                        'En las tablas de detalle puedes escribir en el buscador para filtrar sin recargar.',
                    ], 'nota' => 'Si tu permiso es «reportes de mi área», verás los tickets, el personal y el RR.HH. de tu área.'],
                ],
            ],
            [
                'id' => 'usuarios', 'titulo' => 'Usuarios, roles y permisos', 'icono' => 'person-gear', 'color' => '#475569',
                'categoria' => 'Administración', 'permiso' => [['usuarios', 'gestionar'], ['configuracion', 'gestionar']], 'ruta' => 'usuarios/lista', 'para' => 'Administradores',
                'resumen' => 'Crear usuarios, asignar roles, ajustar permisos puntuales y entender qué puede hacer cada rol.',
                'tags' => ['usuarios', 'roles', 'permisos', 'contraseña', 'bloqueo', 'matriz', 'administrador'],
                'secciones' => [
                    ['titulo' => 'Crear y administrar usuarios', 'pasos' => [
                        'En Administración › Usuarios pulsa «Nuevo usuario», vincúlalo a una persona del personal y elige su rol.',
                        'Desde la lista puedes restablecer la contraseña, desbloquear, desactivar o cambiar el rol de una cuenta.',
                        'Un usuario desactivado ya no puede entrar, pero su historial se conserva.',
                    ]],
                    ['titulo' => 'Roles y permisos', 'pasos' => [
                        'Cada rol agrupa permisos del tipo módulo.acción (por ejemplo tickets.crear). En Administración › Permisos hay tres pestañas:',
                        'Catálogo: todos los permisos del sistema, con búsqueda, filtros y los marcados como sensibles.',
                        'Matriz por rol: casillas rol × permiso. Marca o desmarca y se guarda al instante.',
                        'Ajustes por usuario: concede o quita un permiso a una persona sin cambiar su rol.',
                        'El rol super_admin tiene todo y no se puede editar.',
                    ], 'alerta' => 'El sistema no te deja quitarte a ti mismo el acceso a Permisos o Usuarios. Todos los cambios quedan en Auditoría.'],
                    ['titulo' => 'Dar acceso a un módulo nuevo', 'pasos' => [
                        'Cuando se agrega un módulo (por ejemplo Software), sus permisos aparecen en el catálogo. Entra a la Matriz y márcalos para los roles que lo necesiten.',
                        'Las personas con la sesión ya abierta deben volver a iniciar sesión para ver el menú nuevo.',
                    ]],
                ],
            ],
            [
                'id' => 'licencia', 'titulo' => 'Licencia y rubro de la empresa', 'icono' => 'patch-check', 'color' => '#0f766e',
                'categoria' => 'Administración', 'permiso' => [['configuracion', 'gestionar']], 'ruta' => 'licencia/principal', 'para' => 'Administradores',
                'resumen' => 'Activar o renovar la licencia, adaptar el sistema al rubro de la empresa y encender o apagar módulos.',
                'tags' => ['licencia', 'rubro', 'módulos', 'vencimiento', 'solo lectura', 'activar', 'renovar', 'empresa'],
                'secciones' => [
                    ['titulo' => 'Activar o renovar la licencia', 'pasos' => [
                        'Entra a Administración › Licencia y rubro. Arriba ves el estado, el nombre y RUC de la empresa y la fecha de vencimiento.',
                        'Copia el «Código de esta instalación» y envíalo, junto con el RUC de la empresa, a tu proveedor de SIGTI. Él te devuelve un texto que empieza con SIGTI1.',
                        'Pégalo en «Activar o renovar la licencia» y pulsa Activar. Se cargan solos el nombre, las siglas y el RUC de la empresa. Para renovar se hace lo mismo con la licencia nueva.',
                    ], 'nota' => 'La licencia solo sirve para esta instalación y para el RUC con que fue emitida.'],
                    ['titulo' => 'Qué pasa al vencer', 'pasos' => [
                        'Hasta 30 días antes de vencer aparece un aviso amarillo debajo de la barra superior.',
                        'Si vence, hay 15 días de gracia con aviso rojo y el sistema sigue funcionando normal.',
                        'Pasada la gracia (o sin licencia, o si no es válida) el sistema queda en SOLO LECTURA: se puede entrar, consultar, buscar y exportar, pero no registrar ni modificar. Los datos nunca se borran; al activar la licencia todo vuelve a la normalidad.',
                        'Un sistema nuevo funciona completo durante 15 días de prueba.',
                    ]],
                    ['titulo' => 'Rubro y módulos', 'pasos' => [
                        'Elige el rubro de tu empresa (construcción, educación, salud, comercio, industria, logística, servicios o general) y pulsa «Aplicar rubro»: se agregan categorías de tickets, tipos de equipo y áreas propias del giro, y «obra» pasa a llamarse sede, tienda, planta, etc.',
                        'Solo agrega: no borra ni cambia lo que ya tienes, y puedes repetirlo sin duplicar.',
                        'En «Módulos de la empresa» apaga lo que no usas (Proyectos, Capacitaciones, Mantenimiento, Software, Seguimiento, Carnets, Etiquetas, Chat). Desaparece del menú; sus datos se conservan y puedes volver a encenderlo.',
                    ]],
                ],
            ],
            [
                'id' => 'rrhh', 'titulo' => 'RR.HH. ampliado (Contratos, Vacaciones, Asistencia, Planillas y Requerimiento de personal)', 'icono' => 'person-badge', 'color' => '#7c3aed',
                'categoria' => 'Personas', 'permiso' => [['rrhh', 'ver'], ['rrhh', 'gestionar'], ['rrhh', 'solicitar'], ['rrhh', 'ver_area'], ['rrhh', 'eliminar']], 'ruta' => 'rrhh/contratos', 'para' => 'Cualquier área (Requerimiento de personal) y RR. HH. / Administradores (el resto)',
                'resumen' => 'Gestión interna del personal: contratos, vacaciones, asistencia, una planilla referencial y el requerimiento de personal de cada área (con catálogo de puestos, biblioteca de requisitos, equipos de TI, EPP y salario referencial). No reemplaza PLAME ni tiene validez tributaria/legal.',
                'tags' => ['rrhh', 'contratos', 'vacaciones', 'asistencia', 'planilla', 'boleta', 'personal', 'sueldo', 'requerimiento', 'epp', 'equipos', 'puestos', 'requisitos', 'biblioteca', 'salario', 'antiguedad', 'excel', 'csv', 'importar', 'marcador', 'huella', 'biometrico', 'horario', 'tolerancia', 'tardanza', 'sugerido', 'almuerzo'],
                'secciones' => [
                    ['titulo' => 'Contratos', 'pasos' => [
                        'En RR.HH. › Contratos hay dos pestañas: «Personal contratado» (un renglón por persona, solo las que tienen contrato vigente ahora) e «Historial completo» (todos los contratos de siempre, incluidos los ya renovados o finalizados).',
                        'Pulsa «Nuevo contrato», elige la persona, el tipo (indeterminado, plazo fijo, practicante, locación, part time o por horas) y las fechas.',
                        'Cada persona solo puede tener UN contrato vigente a la vez. Para cambiar las condiciones usa «Renovar» (cierra el anterior y crea uno nuevo); para terminarlo sin renovar usa «Finalizar».',
                        'Las tarjetas de arriba avisan los contratos por vencer en 30 días y los que ya vencieron sin renovar ni finalizar.',
                        'Ambas pestañas muestran, por cada persona, su antigüedad (tiempo trabajado desde su fecha de ingreso) y los días de vacaciones que tiene disponibles ahora mismo, calculados solos: no hace falta ir a otra pantalla a revisarlo.',
                    ]],
                    ['titulo' => 'Vacaciones', 'pasos' => [
                        'El saldo se calcula solo: 2.5 días por cada mes trabajado desde la fecha de ingreso (30 días al año).',
                        'Solo acumulan los contratos de plazo indeterminado, plazo fijo, part time y por horas. Practicante y locación de servicios NO acumulan vacaciones (se mira el tipo de su contrato VIGENTE en Contratos); si alguien aún no tiene ningún contrato registrado, el sistema asume que sí acumula.',
                        'El sistema recalcula solo y avisa arriba («Ya pueden salir») a quién ya le toca: apenas alguien llega a 30 días o más disponibles, aparece en un aviso con un botón «Registrar» que abre la solicitud con esa persona ya elegida. No hay que ir a revisar el saldo de cada persona una por una.',
                        'Al registrar una solicitud se muestra el saldo disponible (o el aviso de que no acumula); si no alcanza, el sistema avisa antes de guardar.',
                        'RR.HH. aprueba, rechaza (con motivo) o cancela cada solicitud pendiente desde la lista.',
                        'Una vez aprobada, esos días aparecen solos en Asistencia (ver abajo): no hay que marcarlos a mano ahí también.',
                    ]],
                    ['titulo' => 'Asistencia', 'pasos' => [
                        'En RR.HH. › Asistencia elige la fecha (y opcionalmente el área) y marca a cada persona: Normal, Tardanza, Falta, Falta justificada, Permiso o Vacaciones. Ahora se registran 4 horas por persona: entrada, salida a almorzar, regreso de almuerzo y salida.',
                        'Horario de referencia: lunes a viernes de 8:00 a.m. a 5:00 p.m. (almuerzo de 1:00 a 2:00 p.m.); sábados de 8:00 a.m. a 1:00 p.m. (jornada corrida, sin almuerzo). Hay una tolerancia de ingreso de 15 minutos: entrar a las 8:00 o hasta las 8:15 cuenta como Normal.',
                        'El sistema calcula solo si es Normal o Tardanza apenas se escribe (o se importa) la hora de entrada, usando esa tolerancia; no hay que elegirlo a mano salvo que se quiera marcar otra cosa (Falta, Permiso, etc.), que siempre se puede forzar manualmente.',
                        'Si la persona tiene una solicitud de vacaciones APROBADA que cubre ese día, el sistema ya la muestra marcada como «Vacaciones» con el aviso «(automático)»: nadie tiene que escribirla a mano, y así se cuenta igual en los resúmenes aunque no se pulse «Guardar» ese día.',
                        'Se guarda una marca por persona y por día; volver a guardar ese día la actualiza (sea que se escribió a mano o se importó).',
                        'Botón «Importar Excel/CSV»: sirve para subir las marcaciones de un marcador de huella (o cualquier otro equipo) exportadas a un archivo. El archivo debe tener en la primera fila los encabezados DNI, Fecha, y opcionalmente Hora Entrada, Hora Salida Almuerzo, Hora Entrada Almuerzo, Hora Salida, Tipo y Observación (hay un botón para descargar una plantilla de ejemplo); el DNI debe coincidir con el de la ficha de Personal. Si el archivo no trae el Tipo, el sistema lo calcula solo con la hora de entrada y la tolerancia. Al terminar, avisa cuántas filas se guardaron y cuáles tuvieron problemas (por ejemplo, un DNI que no existe).',
                        'El sistema todavía no se conecta directo al equipo marcador de huella (no hay forma de saber la marca ni el protocolo de cada fabricante): por ahora se trabaja exportando desde el equipo e importando ese archivo aquí. Tampoco valida todavía si la salida, el almuerzo o el regreso se hicieron a la hora que correspondía (solo se guardan); eso puede agregarse después si hace falta.',
                    ]],
                    ['titulo' => 'Planillas', 'pasos' => [
                        'En la pestaña «Conceptos» se definen los ingresos y descuentos (monto fijo en soles o porcentaje del sueldo base) que se aplican al generar.',
                        'Elige el periodo (mes) y pulsa «Generar / recalcular»: toma el sueldo del contrato vigente de cada persona activa y aplica los conceptos activos. Se puede recalcular mientras esté en borrador.',
                        'Cada fila tiene una boleta imprimible (referencial). «Cerrar periodo» deja todas sus planillas en borrador como cerradas; una planilla cerrada se puede «Reabrir» si hay que corregir algo.',
                    ], 'nota' => 'La planilla de SIGTI es referencial y de uso interno: no reemplaza PLAME, boletas electrónicas ni un sistema contable oficial.'],
                    ['titulo' => 'Requerimiento de personal', 'pasos' => [
                        'Cualquier área, desde «Mis requerimientos», pulsa «Solicitar personal»: escribe el puesto (residente, analista, practicante…), la cantidad, un tipo de contrato sugerido (opcional), un salario referencial (mínimo y máximo, opcional), la justificación y el plazo para tener a la persona contratada y firmando sus documentos.',
                        'Si el puesto escrito ya está activo en el catálogo, aparece debajo una «Ficha del puesto» con sus requisitos (educación, formación, experiencia, etc.) y sugiere el contrato y el salario; si es un puesto nuevo, se registra igual y queda propuesto en el catálogo como «Pendiente de validar».',
                        'Si la vacante necesita equipos de TI (laptop, módem/router, celular, impresora u otro, según el catálogo) o EPP (casco, guantes, lentes, chaleco…), marca las casillas correspondientes antes de enviar.',
                        'Una vez enviado, el área YA NO puede editarlo ni cancelarlo: solo puede pedir un cambio a Sistemas / RR.HH. por el chat interno o creando un ticket (botón «Pedir un cambio» en el detalle).',
                        'Sistemas / RR.HH. ve todos los requerimientos (o los de su área, según el permiso): los toma, corrige si hace falta (incluyendo agregar o quitar equipos de TI y EPP del pedido, sin perder lo que ya estaba marcado como entregado), marca cada equipo o EPP como entregado, y los marca «Cubierto» cuando la vacante se llena, o «Cancelado» con motivo.',
                        'Un requerimiento ya «Cubierto» o «Cancelado» se puede eliminar de verdad (con su historial, equipos y EPP) solo con el permiso «rrhh.eliminar» —es decir, el área de Sistemas—; mientras esté «Pendiente» o «En proceso» no se puede eliminar, solo cubrirlo o cancelarlo primero.',
                        'Pestaña «Puestos y requisitos»: quien gestiona RR.HH. valida los puestos propuestos (o los crea directo, ya «Activos»), define el contrato sugerido, el salario referencial y los requisitos —categoría y detalle, tantos como haga falta, ej. Educación / Formación / Experiencia / Otros—, eligiendo de la biblioteca de requisitos o escribiendo uno nuevo (se suma solo a la biblioteca para reutilizarlo en otros puestos), y puede inactivar un puesto; eliminarlo de verdad requiere además el permiso «rrhh.eliminar» (solo se elimina si ningún requerimiento lo usó todavía; si ya se usó, se inactiva).',
                        'Pestaña «Biblioteca de requisitos»: frases de requisito reutilizables entre varios puestos (agregar, editar, activar/desactivar con rrhh.gestionar; eliminar de verdad con rrhh.eliminar, solo si ningún puesto la tiene asignada).',
                        'Pestañas «Equipos de TI» y «Catálogo EPP»: las administra quien gestiona RR.HH. (agregar, editar, activar/desactivar); eliminar de verdad un equipo de TI requiere el permiso «rrhh.eliminar» y solo si nunca se pidió en un requerimiento. Los cambios no afectan los requerimientos ya enviados.',
                    ]],
                    ['titulo' => 'Encender o apagar el módulo', 'pasos' => [
                        'Como los demás módulos, RR.HH. ampliado se puede apagar por empresa en Administración › Licencia y rubro › «Módulos de la empresa».',
                    ]],
                ],
            ],
            [
                'id' => 'auditoria', 'titulo' => 'Auditoría del sistema', 'icono' => 'journal-check', 'color' => '#b45309',
                'categoria' => 'Administración', 'permiso' => [['auditoria', 'ver']], 'ruta' => 'auditoria/lista', 'para' => 'Administradores y control interno',
                'resumen' => 'Revisar quién hizo qué y cuándo: inicios de sesión, cambios, accesos denegados y exportaciones.',
                'tags' => ['auditoría', 'registro', 'seguridad', 'accesos', 'cambios', 'exportar', 'login'],
                'secciones' => [
                    ['titulo' => 'Leer la pantalla', 'pasos' => [
                        'Las tarjetas de arriba resumen el día: eventos, usuarios activos, inicios fallidos, accesos denegados y cambios de seguridad. Haz clic en una para filtrar.',
                        'Los atajos «Hoy», «7 días», «30 días» y «Todo» cambian el período; los filtros permiten afinar por usuario, módulo, acción y resultado.',
                        'Las filas en rojo son intentos fallidos o accesos denegados; conviene revisarlos.',
                    ]],
                    ['titulo' => 'Ver el detalle', 'pasos' => [
                        'Abre un evento para ver la comparación antes / después de un cambio, la dirección IP y el historial completo de ese registro.',
                        'Las claves y contraseñas nunca se guardan en el registro.',
                        '«Exportar CSV» descarga lo que tienes filtrado. Cada descarga queda también registrada.',
                    ]],
                ],
            ],
            [
                'id' => 'configuracion', 'titulo' => 'Configuración y diseñador de etiquetas', 'icono' => 'sliders', 'color' => '#64748b',
                'categoria' => 'Administración', 'permiso' => [['configuracion', 'gestionar']], 'ruta' => 'configuracion/principal', 'para' => 'Administradores',
                'resumen' => 'Catálogos del sistema (áreas, tipos de equipo, categorías, prioridades, proveedores…) y cómo diseñar etiquetas.',
                'tags' => ['configuración', 'catálogos', 'áreas', 'tipos de equipo', 'prioridades', 'proveedores', 'etiquetas', 'plantillas', 'correo', 'smtp', 'whatsapp', 'anuncios'],
                'secciones' => [
                    ['titulo' => 'Catálogos', 'pasos' => [
                        'En Administración › Configuración hay una pestaña por catálogo. Agrega, edita o desactiva elementos; un elemento en uso no se borra, se desactiva.',
                        'Las prioridades definen el tiempo del SLA de los tickets. Los tipos de equipo determinan los campos del formulario de equipos y el formato de sus actas.',
                        'También se ajustan datos de la empresa (nombre, RUC) que salen en informes y actas.',
                    ]],
                    ['titulo' => 'Correo y WhatsApp (avisos de Anuncios)', 'pasos' => [
                        'En Administración › Configuración › «Correo y WhatsApp» hay dos tarjetas: una para el correo (SMTP) y otra para WhatsApp. Cada una tiene su propio interruptor «Activo»: mientras esté apagado, ese canal no manda nada aunque esté configurado.',
                        'Correo: pide el servidor SMTP (ej. smtp.gmail.com), el puerto, el tipo de seguridad (STARTTLS, SSL o sin cifrar), el usuario/correo, la contraseña (para Gmail es una «contraseña de aplicación», no la contraseña normal de la cuenta) y el correo remitente. El botón «Probar» manda un correo de prueba con lo ya guardado.',
                        'WhatsApp: es un vínculo NO oficial, igual que WhatsApp Web (no es la API de Meta ni un servicio como Twilio). Hace falta tener encendido, en una PC o servidor, el programa aparte de la carpeta «whatsapp-bridge» (vea whatsapp-bridge/LEEME.txt). Ahí se pone la dirección de ese programa (ej. http://127.0.0.1:3300) y un token; al guardar aparece un código QR para escanear desde WhatsApp › Dispositivos vinculados.',
                        'No hay que marcar nada por cada anuncio: cuando un anuncio se publica con «Avisar por notificación» marcado, el sistema intenta avisar también por correo y por WhatsApp a quienes tengan ese dato de contacto, solo si el canal correspondiente está «Activo». Si un canal falla (SMTP caído, WhatsApp desvinculado), el anuncio igual se publica y avisa por los demás medios: nunca se pierde el anuncio por un canal caído.',
                        'El correo que recibe cada persona es el de su cuenta (si tiene), si no el correo corporativo de Personal, y si tampoco el correo personal. El WhatsApp usa el teléfono registrado en su ficha de Personal.',
                    ]],
                    ['titulo' => 'Diseñador de etiquetas', 'pasos' => [
                        'En Inventario › Diseñador de Etiquetas pulsa «Nueva plantilla», define el tamaño del papel y arrastra los campos (código, QR, marca, serie…).',
                        'Guarda la plantilla y elígela al imprimir etiquetas de equipos o de personal.',
                    ]],
                ],
            ],
            // ---- Guias tecnicas (paginas imprimibles que ya existian) ----
            [
                'id' => 'guia-rastreo', 'titulo' => 'Guía técnica: agente de rastreo', 'icono' => 'broadcast', 'color' => '#2563eb',
                'categoria' => 'Guías técnicas', 'permiso' => [['equipos', 'ver']], 'ruta' => 'equipos/seguimiento', 'para' => 'Sistemas',
                'resumen' => 'Instalación del agente de rastreo paso a paso, verificación y procedimiento en caso de robo.',
                'tags' => ['rastreo', 'agente', 'instalación', 'robo', 'powershell'], 'url' => 'views/rastreo/guia.php', 'secciones' => [],
            ],
            [
                'id' => 'guia-etiquetas', 'titulo' => 'Guía técnica: impresora de etiquetas XP-237B', 'icono' => 'printer', 'color' => '#f59e0b',
                'categoria' => 'Guías técnicas', 'permiso' => [['equipos', 'ver']], 'ruta' => 'equipos/lista', 'para' => 'Sistemas',
                'resumen' => 'Cómo imprimir etiquetas con la Xprinter XP-237B: configuración, tamaño de papel y prueba.',
                'tags' => ['etiquetas', 'impresora', 'xprinter', 'térmica', 'driver'], 'url' => 'views/guias/etiquetas.php', 'secciones' => [],
            ],
            [
                'id' => 'guia-carnets', 'titulo' => 'Guía técnica: impresión de carnets CR80', 'icono' => 'person-vcard', 'color' => '#16a34a',
                'categoria' => 'Guías técnicas', 'permiso' => [['personal', 'ver']], 'ruta' => 'carnets/lista', 'para' => 'RR. HH. y Sistemas',
                'resumen' => 'Generación, impresión y entrega de carnés institucionales en tamaño CR80.',
                'tags' => ['carnets', 'cr80', 'impresión', 'tarjetas'], 'url' => 'views/guias/carnets.php', 'secciones' => [],
            ],
        ];
    }

    /** Preguntas frecuentes (se muestran siempre). */
    public static function faq(): array
    {
        return [
            ['p' => 'No veo una opción del menú que necesito.', 'r' => 'El menú muestra solo lo que tu rol permite. Pide a Sistemas que revise tus permisos (Administración › Permisos). Después de un cambio de permisos, cierra sesión y vuelve a entrar.', 'tags' => ['menú', 'permisos', 'acceso']],
            ['p' => 'Cambiaron algo en el sistema pero yo sigo viendo lo anterior.', 'r' => 'Recarga la página completa con Ctrl + F5. Así el navegador descarga los archivos nuevos.', 'tags' => ['actualizar', 'recargar', 'caché']],
            ['p' => '¿Cómo reporto un problema con mi computadora?', 'r' => 'Crea un ticket en Tickets › «Nuevo ticket»: elige la categoría, describe qué pasó y desde cuándo, y adjunta una captura si puedes. Recibirás avisos en la campana cuando lo asignen o lo comenten.', 'tags' => ['ticket', 'soporte', 'ayuda']],
            ['p' => '¿Qué significa SLA?', 'r' => 'Es el tiempo máximo para atender un ticket según su prioridad. Si pasa ese tiempo sin resolverse, el ticket aparece en rojo como «SLA vencido».', 'tags' => ['sla', 'tickets', 'prioridad']],
            ['p' => '¿Por qué un equipo no aparece al hacer una entrega?', 'r' => 'Solo se pueden entregar equipos «En stock». Si está asignado, devuélvelo primero; si está en mantenimiento, termina el mantenimiento; si está de baja, ya no se puede usar.', 'tags' => ['entrega', 'stock', 'equipo']],
            ['p' => '¿Cómo sé qué equipos debo recoger de alguien que se va?', 'r' => 'En Personal › «Ceses / Pendientes» ves quién se va y qué equipos tiene. Al procesar el cese se generan las devoluciones y sus actas.', 'tags' => ['cese', 'devolución', 'personal']],
            ['p' => '¿Dónde veo las actas que faltan firmar?', 'r' => 'En Inventario › Actas, filtra por «Pendiente». También aparecen como observación en el Dashboard.', 'tags' => ['actas', 'firma']],
            ['p' => '¿Cómo sé si una laptop tiene licencia de Office o Windows?', 'r' => 'Abre su ficha › «Software». Cada programa muestra su licencia, y si falta aparece «Sin licencia». Para una vista global usa Inventario › Software.', 'tags' => ['software', 'licencia', 'office', 'windows']],
            ['p' => '¿Puedo sacar un informe para gerencia?', 'r' => 'Sí. En Análisis › Reportes elige el período y pulsa «Informe»: sale listo para imprimir o guardar como PDF. En Software también hay un informe propio.', 'tags' => ['informe', 'gerencia', 'pdf', 'reportes']],
            ['p' => '¿Se puede deshacer una baja de equipo?', 'r' => 'No. La baja es definitiva y deja el equipo solo para consulta. Por eso el sistema pide confirmación y el motivo.', 'tags' => ['baja', 'equipo']],
            ['p' => 'Publiqué un anuncio pero no llegó por correo ni por WhatsApp.', 'r' => 'Revise en Administración › Configuración › «Correo y WhatsApp» que el canal esté con el interruptor «Activo» encendido y, en WhatsApp, que diga «Vinculado» (si sale el código QR, hay que escanearlo de nuevo). También revise que la persona tenga correo o teléfono registrado en su ficha de Personal.', 'tags' => ['anuncios', 'correo', 'whatsapp', 'notificación']],
        ];
    }

    public static function glosario(): array
    {
        return [
            ['t' => 'SLA', 'd' => 'Plazo máximo de atención de un ticket según su prioridad.'],
            ['t' => 'Acta', 'd' => 'Documento firmado que deja constancia de la entrega, devolución o cambio de un equipo.'],
            ['t' => 'Asignación', 'd' => 'Entrega de un equipo a una persona, área, proyecto u obra.'],
            ['t' => 'Preventivo', 'd' => 'Mantenimiento programado para evitar fallas, aunque el equipo funcione.'],
            ['t' => 'Correctivo', 'd' => 'Mantenimiento para reparar una falla que ya ocurrió.'],
            ['t' => 'Puesto de licencia', 'd' => 'Cada instalación que una licencia permite. Una licencia de 5 puestos cubre 5 equipos.'],
            ['t' => 'Cobertura de software', 'd' => 'Porcentaje de equipos de cómputo que ya tienen su software registrado.'],
            ['t' => 'Equipo estándar', 'd' => 'Software que se instala en todo equipo de cómputo nuevo (sistema operativo, ofimática, antivirus…).'],
            ['t' => 'Stock', 'd' => 'Equipos disponibles para entregar.'],
            ['t' => 'Auditoría', 'd' => 'Registro automático de quién hizo qué en el sistema y cuándo.'],
        ];
    }

    /** ¿Puede esta persona ver la guia? permiso = null (todos) o lista de alternativas [modulo, accion]. */
    public static function visible(array $g): bool
    {
        if (empty($g['permiso'])) return true;
        foreach ($g['permiso'] as [$m, $a]) {
            if (Auth::can($m, $a)) return true;
        }
        return false;
    }

    /** Guias que esta persona puede ver, con minutos de lectura estimados. */
    public static function visibles(): array
    {
        $out = [];
        foreach (self::todas() as $g) {
            if (!self::visible($g)) continue;
            $palabras = 0;
            foreach ($g['secciones'] as $s) {
                foreach ($s['pasos'] as $p) $palabras += str_word_count(strip_tags($p), 0, 'áéíóúñÁÉÍÓÚÑ');
            }
            $g['minutos'] = $palabras ? max(1, (int) ceil($palabras / 180)) : null;
            unset($g['permiso']);
            $out[] = $g;
        }
        return $out;
    }

    public static function buscar(string $id): ?array
    {
        foreach (self::todas() as $g) if ($g['id'] === $id) return $g;
        return null;
    }
}

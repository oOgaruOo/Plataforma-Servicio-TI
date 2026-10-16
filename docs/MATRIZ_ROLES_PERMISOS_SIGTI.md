# Matriz de roles y permisos SIGTI

**Tipo:** análisis funcional y técnico; no implementa cambios.  
**Evidencia:** archivos del repositorio, en particular `db/sigti.sql`, `core/Auth.php`, `core/Auditoria.php`, `api/tickets/`, `api/auditoria/`, `api/reportes/` y los JavaScript de tickets/auditoría. No se consultó una base de datos activa. Las asignaciones de la semilla pueden diferir de las de un entorno desplegado.

## 1. Objetivo

Dejar documentada la matriz funcional/técnica de roles, permisos y alcance para SIGTI, como insumo previo a implementar H-01 de autorización de tickets. Se distingue lo confirmado por negocio, la situación del código, las brechas y las propuestas técnicas.

## 2. Alcance

Se revisaron roles y permisos del SQL versionado, identidad/sesión, endpoints de tickets, historial, auditoría, reportes y frontend asociado. No se modificó código, esquema ni datos; tampoco se ejecutaron migraciones, pruebas funcionales, commits o push.

## 3. Roles

### Roles existentes en `db/sigti.sql`

- `super_admin`
- `admin_ti`
- `supervisor` (descripción: Supervisor de Mesa de Ayuda)
- `tecnico` (Técnico de Soporte)
- `jefe_area`
- `usuario_final`
- `rrhh`

### Perfiles funcionales confirmados para el diseño

- **Usuario final / solicitante:** crea y consulta solicitudes; puede trabajar únicamente en sus borradores y no modificar un ticket enviado.
- **Jefe de área:** puede crear y consultar tickets de su ámbito, editar sus borradores y enviar; puede solicitar reapertura. No registra ni modifica directamente información técnica.
- **Gerencia:** visualiza reportes, indicadores, auditoría e información histórica; solicita tickets y consulta sus estados. Estos accesos no conceden automáticamente administración de tickets.
- **Área TI:** Soporte TI, Especialista TI, Analista TI, Administrador TI, Coordinador TI, Jefe TI y Gerente TI son perfiles solicitados para el modelo funcional. Salvo `admin_ti`, `tecnico` y `supervisor`, no aparecen como roles diferenciados en la semilla.

No hay rol denominado Gerencia, Gerente TI, Coordinador TI, Jefe TI, Analista TI, Especialista TI, Soporte TI o SSOMA en las filas de roles semilla. `jefe_area` no debe equipararse automáticamente a Jefe TI. Un cargo organizacional no equivale por sí solo a rol o permiso del sistema.

## 4. Funciones

En el código actual, el permiso está representado por pareja `modulo.accion`, asociada al rol por `rol_permisos`. `Auth::attempt()` incorpora esos grants en sesión y puede sumar/revocar overrides de `usuario_permisos` si la tabla existe. `Auth::can()` consulta permisos en sesión; `super_admin` recibe un bypass general en código. Las asignaciones runtime no se comprobaron.

El esquema separa algunos conceptos, pero aún no modela de forma explícita el cargo, función operativa y especialidades técnicas aquí descritos. `usuarios.rol_id` define rol, `usuarios.personal_id` enlaza persona, `personal.area_id` define área y `tickets.tecnico_usuario_id` asigna responsable actual. `nivel_atencion` usa niveles N1–N3. No se encontraron campos/tablas que vinculen personal con competencias Backend/Frontend/Full Stack/DBA.

## 5. Especialidades

**Regla de negocio confirmada:** personal especializado puede atender tickets cuando la incidencia corresponde a su competencia. Relación de trabajo futura:

```text
TIPO DE TICKET
    → ESPECIALIDAD REQUERIDA
    → PERSONAL COMPETENTE
    → ASIGNACIÓN
```

Referencias funcionales dadas:

- Backend: PHP, APIs, servicios, lógica de negocio e integraciones.
- Frontend: JavaScript, AJAX, interfaz, componentes y errores visuales.
- Full Stack: frontend, backend e integración.
- Base de Datos / DBA: SQL, consultas, rendimiento, integridad, estructura, procedimientos y respaldos.

**Hallazgo actual:** no se encontró asignación automática ni modelo de competencias. `api/tickets/asignar.php` recibe un usuario técnico y nivel de atención; `tickets.tecnico_usuario_id` y `nivel_atencion` guardan asignación/nivel, sin validar especialidad. No se propone automatizar asignaciones.

## 6. Reglas de negocio

### REGLA DE NEGOCIO CONFIRMADA

1. Ticket pasa por `BORRADOR → ENVIADO/ABIERTO → EN ATENCIÓN → RESUELTO → CERRADO`.
2. Guardar borrador y enviar ticket son acciones conceptualmente distintas.
3. Solicitante puede guardar/continuar/editar su propio borrador; luego de enviar no puede editarlo.
4. Usuario final no asigna, escala, cambia estado, registra solución técnica, resuelve, cierra ni elimina tickets.
5. Jefe de área puede solicitar reapertura y consultar los tickets de su ámbito; no modifica directamente información técnica.
6. Técnicos atienden según competencia y la información técnica se registra mediante acciones específicas, no por edición indiscriminada.
7. Coordinador, Jefe y Gerente TI normalmente gestionan/supervisan. Si no existe personal adecuado, pueden asumir excepcionalmente atención/solución; esa acción debe dejar actor, ticket, motivo, fecha/hora, carácter excepcional y justificación para auditoría.
8. Asignar, reasignar y escalar son conceptos distintos. Cambiar el responsable no constituye automáticamente escalamiento.
9. Reapertura: el Jefe de Área la solicita cuando la solución no resolvió la incidencia original; TI revisa y ejecuta si corresponde. Solo durante el mismo día calendario del cierre. Al día siguiente debe crearse un nuevo ticket.
10. No eliminar físicamente tickets; eliminación lógica debe conservar historial.
11. Gerencia consulta reportes/indicadores, auditoría e información histórica, puede solicitar tickets y consultar estado; esto no implica permisos administrativos de ticket.

### PROPUESTA TÉCNICA

Autorización backend por sesión, CSRF, permiso explícito, estado/transición y alcance. La identidad se resuelve con datos de servidor y relaciones de usuario/persona/área; nunca se confía en rol, personal, permisos o capacidad suministrados por JavaScript. Cada acción administrativa debe mantener registro de auditoría. Las propuestas de esquema/permisos se detallan más adelante y requieren aprobarse antes de ejecutar.

## 7. Matriz de permisos

### Catálogo y asignaciones actuales

El SQL semilla define permisos de tickets: `tickets.ver`, `tickets.ver_propios`, `tickets.ver_area`, `tickets.crear`, `tickets.editar`, `tickets.asignar`, `tickets.cambiar_estado`. No define `tickets.eliminar`, `tickets.escalar`, `tickets.resolver`, `tickets.reabrir`, `tickets.enviar`, ni permisos independientes para atención/diagnóstico/actividad/solución/cierre.

Asignaciones relevantes según consultas de semilla:

| Rol semilla | Permisos actuales de tickets según `rol_permisos` |
|---|---|
| `super_admin` | Todos los permisos del catálogo; además `Auth::can()` permite cualquier permiso por bypass. |
| `admin_ti` | Todos los permisos del catálogo semilla. |
| `supervisor` | Todas las acciones del módulo tickets presentes en el catálogo (la exclusión de `eliminar` no tiene efecto porque esa acción no está catalogada). |
| `tecnico` | `ver`, `crear`, `editar`, `cambiar_estado`. No recibe `asignar` en la consulta semilla. |
| `jefe_area` | `ver_area`, `crear`. |
| `usuario_final` | `ver_propios`, `crear`. |
| `rrhh` | Ningún permiso de tickets. Tiene personal y `reportes.ver`. |

`admin_ti` también recibe `reportes.ver` y `auditoria.ver` por recibir todo el catálogo. `super_admin` también obtiene esos permisos en la semilla y por su bypass. `supervisor` no recibe `auditoria.ver`, debido a la exclusión de ese módulo. `jefe_area` obtiene `reportes.ver_area`, no `reportes.ver`. No existe grant semilla para una identidad llamada Gerencia.

En el código, `api/tickets/guardar.php` usa `tickets.crear` para crear **y editar**, aunque la edición requiera semánticamente `tickets.editar`. `api/tickets/listar.php` usa también `tickets.crear` para publicar `puede_editar`. `api/tickets/eliminar.php` exige `tickets.eliminar`, pero la clave no está en el catálogo semilla. `api/tickets/estado.php` usa permiso genérico `tickets.cambiar_estado` para todas sus transiciones.

### Matriz funcional detallada

Los estados de “Implementación actual” se refieren al código/semilla; no son permisos efectivos verificados contra una instancia activa.

| ROL | ACCIÓN | PERMITIDO | CONDICIÓN | OBSERVACIONES | IMPLEMENTACIÓN ACTUAL | BRECHA |
|---|---|---|---|---|---|---|
| Usuario final / `usuario_final` | Crear | Sí | Sesión, CSRF y `tickets.crear`; identidad/solicitante deben corresponder al actor según política | Puede enviar `solicitante_id` en formulario actual | Crear inserta directo en `abierto` | No valida que solicitante enviado corresponda al actor; no hay borrador |
| Usuario final | Guardar borrador | Sí | Ticket propio no enviado | Mantener para continuar después | No existe estado ni endpoint diferenciado | Estado/endpoint/capacidad faltantes |
| Usuario final | Editar borrador | Sí | Propio y exclusivamente mientras BORRADOR | Nunca usar `tickets.crear` como permiso de edición | Rama editar permite `abierto` con `tickets.crear` | H-01; borrador ausente |
| Usuario final | Enviar | Sí | Confirmación de borrador | Envío separado de guardado | No endpoint/acción separada; create inicia `abierto` | Acción y evento envío faltantes |
| Usuario final | Ver propio | Sí | Solo asociado a `usuarios.personal_id` | Consultar también historial propio | Lista/detalle tienen chequeo específico del rol | Alcances no unificados en todos endpoints |
| Usuario final | Ver área | No, salvo autorización de ámbito aprobada | No conceder por defecto | El permiso de rol actual no lo incluye | Filtro de lista/detalle es especial al rol | Formalizar alcance en política central |
| Usuario final | Editar enviado | No | No editable tras envío | Incluye campos técnicos/de TI | Abierto es editable por endpoint directo | H-01 crítico para autorización horizontal |
| Usuario final | Registrar atención | No | — | No es edición libre ni comentario técnico | No hay acción de atención separada; puede comentar | Separar comentario y actividad técnica |
| Usuario final | Registrar diagnóstico | No | — | Información técnica de TI | Sin acción estructurada de diagnóstico | No existe modelo/permiso específico |
| Usuario final | Registrar actividad | No para actividad técnica | — | No confundir con comentario de seguimiento | Comentarios se guardan en `ticket_comentarios` | Definir si comentario post envío es permitido |
| Usuario final | Registrar solución | No | — | Solución técnica | Estado permite enviar campo solución | Usuario con `tickets.cambiar_estado` según grant podría usar endpoint sin restricción de rol/propiedad |
| Usuario final | Asignar | No | — | Acción TI | Endpoint `asignar.php` requiere `tickets.asignar` | Sin alcance de ticket validado |
| Usuario final | Reasignar | No | — | Distinta de escalamiento | Mismo endpoint `asignar.php`, evento siempre `asignado` con detalle reasignación | Diferenciar acción/evento en auditoría |
| Usuario final | Escalar | No | — | Acción independiente de reasignar | No permiso/endpoint separado encontrado | No existe capacidad explícita |
| Usuario final | Cambiar estado | No | — | — | Endpoint exige `tickets.cambiar_estado` | No combina perfil/alcance en servidor |
| Usuario final | Resolver | No | — | Permiso independiente deseado | `estado.php`, transición a `resuelto`, requiere solución; permiso genérico | Falta `tickets.resolver` y chequeo de actor/alcance |
| Usuario final | Cerrar | No | — | — | `estado.php` usa `tickets.cambiar_estado` | Falta permiso específico y alcance |
| Jefe de área / `jefe_area` | Crear | Sí | Ticket dentro de función institucional | No es Jefe TI | Concede `tickets.crear` | Solicitante/creador no separado |
| Jefe de área | Guardar/editar borrador | Sí | Solo borradores propios | — | No existe borrador; no tiene `tickets.editar` en semilla | Flujo/capacidad ausente |
| Jefe de área | Enviar | Sí | Borrador propio | — | Sin acción separada | Endpoint/evento faltantes |
| Jefe de área | Ver propio/área | Sí en área | Ámbito de su área | `tickets.ver_area` | Lista filtra por área para quien carece de global ver; detalle chequea específicamente `usuario_final` | Detalle no generaliza el control de área |
| Jefe de área | Editar enviado/solución técnica | No | No modifica información técnica | — | Puede no tener editar, pero la rama `guardar.php` solo pide crear | Endpoint permite actualizar ticket abierto ajeno: H-01 |
| Jefe de área | Solicitar reapertura | Sí | Mismo día calendario del cierre; solución no corrigió incidencia | No ejecuta la reapertura | No endpoint/permiso de solicitud | Falta solicitud y plazo temporal |
| Gerencia | Ver reportes/indicadores | Sí | Solo lectura, según alcance autorizado | No concede administración de tickets | `reportes.ver` existe; no hay rol Gerencia; reportes protegidos por permiso | Falta rol/grant y definir indicadores/ámbito |
| Gerencia | Ver auditoría/histórico | Sí | Solo lectura y con permiso separado | No concede administración de tickets | `auditoria.ver` y endpoint de listado existen; sin rol Gerencia | Falta grant/perfil Gerencia |
| Gerencia | Solicitar/consultar tickets | Sí | Como solicitante y alcance aprobado | No administrar tickets | Puede existir mediante usuario final, pero no existe rol Gerencia | Definir combinación de perfil/alcance |
| Gerencia | Editar/asignar/estado/solución/eliminar | No por acceso a reportes/auditoría | Solo si otro rol/permisos expresos se aprueban | Lectura nunca implica escritura | El SQL concede permisos por rol, no hay rol Gerencia | Prevenir grants implícitos |
| `tecnico` / Soporte TI | Ver ticket | Sí según `tickets.ver` de semilla | Alcance técnico pendiente de regla | Nombre funcional Soporte TI no es rol exacto en sistema | `tickets.ver` asignado | Definir alcance (global, asignado, equipo, área) |
| Técnico TI | Editar ticket enviado | Sí solo por permiso/alcance, en campos permitidos | Requiere `tickets.editar`, estado editable, ticket en alcance | No edición indiscriminada | `tecnico` tiene editar, pero endpoint no la exige | H-01 |
| Técnico TI | Registrar atención/diagnóstico/actividad | Sí según perfil/competencia | Acciones específicas y auditadas | Backend debe separar eventos y campos | Comentario existe; no hay entidad de atención/diagnóstico estructurada | Falta modelado/acciones explícitas |
| Técnico TI | Registrar solución/resolver | Sí según permisos específicos | Estado válido, solución requerida | — | `tickets.cambiar_estado` permite resolver | Falta `tickets.resolver` independiente |
| Técnico TI | Asignar/reasignar | Según permiso | Competencia del asignado y alcance | Técnico semilla no recibe `tickets.asignar` | Endpoint existe y verifica `tickets.asignar` | Semilla/función pueden no coincidir con Soporte TI esperado |
| Técnico TI | Escalar | Sí cuando corresponda | Escalamiento distinto de asignación | Ruta ejemplo N1 → supervisor → especialista → Jefe TI | Sin endpoint/permisos de escalar; niveles N1–N3 en asignación no representan por sí mismos escalamiento | Capacidad inexistente |
| `supervisor` / Coordinador TI | Gestión/supervisión; atención excepcional | Según permiso | Atención excepcional requiere justificación y evento especial | `supervisor` es Mesa de Ayuda; no existe Coordinador TI | Tiene grants amplios del módulo tickets semilla | No hay controles de excepción ni rol coordinador |
| Especialista TI / Analista TI | Atender tickets | Según competencia y permisos aprobados | Especialidad requerida debe corresponder | No existen roles semilla con esos nombres | Solo puede asignarse como usuario activo y nivel N1–N3 | Sin perfil/competencias modelados |
| Administrador TI / `admin_ti` | Administración de tickets | Según permisos individuales | Administrar no debe borrar trazabilidad | `admin_ti` sí existe | Todos los permisos actualmente catalogados | Faltan permisos granulares de acciones y eliminación lógica |
| Jefe TI / Gerente TI | Gestión/supervisión; atención excepcional | Según matriz aprobada | Si asume atención, motivo y carácter excepcional obligatorios | No equivalen a `jefe_area` o Gerencia organizacional | Roles no existen | Definir roles y grants separados |
| Técnico/administrador autorizado | Eliminación lógica | Según permiso dedicado | Guardar actor/fecha/motivo; conservar historial | Regla confirmada: no hard delete | `eliminar.php` borra físicamente ticket, comentarios, adjuntos e historial | Contradicción directa; requiere baja lógica |
| Perfil Gerencia autorizado | Ver auditoría | Sí solo lectura | Requiere `auditoria.ver` | Separado de permisos de tickets | Endpoint `/api/auditoria/listar.php` exige `auditoria.ver`; menú JS respeta clave | Crear rol/grant Gerencia sin permisos administrativos de ticket |
| Perfil Gerencia autorizado | Ver reportes | Sí solo lectura | Permiso global o de área conforme a alcance | — | API de reportes requiere `reportes.ver`; menú reconoce global/área | Implementar rol/alcance Gerencia y reportes/indicadores necesarios |

## 8. Flujo de tickets

### Implementación actual

No existe `borrador` en el `ENUM tickets.estado`; tampoco un campo de fecha de envío ni operación separada de envío. `api/tickets/guardar.php` valida título, descripción, solicitante, área, categoría, subcategoría si se da, prioridad e impacto; consulta referencias activas y estado de persona; después crea directamente en `abierto` (o `asignado` si llega un técnico activo por POST). La rama de edición permite actualizar solo si `estado` es `abierto`, pero usa permiso de creación.

Estados actuales: `abierto`, `asignado`, `en_atencion`, `pendiente_usuario`, `pendiente_proveedor`, `resuelto`, `cerrado`, `cancelado`. Transiciones actuales en `api/tickets/estado.php`: abierto→asignado/cancelado; asignado→en_atencion/pendiente_proveedor/cancelado; en_atencion→pendiente_usuario/pendiente_proveedor/resuelto; pendiente_usuario→en_atencion/resuelto/cancelado; pendiente_proveedor→en_atencion/resuelto/cancelado; resuelto→cerrado/en_atencion; cerrado→en_atencion; cancelado→ninguno. La lista de transiciones se duplica en `detalle.php`.

### Flujo funcional confirmado

```text
BORRADOR
  ├─ guardar / continuar / editar: solicitante dueño
  └─ enviar: solicitante dueño
       ↓
ENVIADO / ABIERTO
       ↓ personal TI asigna e inicia atención
EN ATENCIÓN
       ↓ TI registra solución y resuelve
RESUELTO
       ↓ actor de cierre autorizado
CERRADO
```

`BORRADOR` y la distinción guardar/enviar son reglas confirmadas, pero aún no existen en la implementación actual.

## 9. Asignación

`api/tickets/asignar.php` requiere `tickets.asignar`, sesión y CSRF; valida ticket no cerrado/cancelado, usuario técnico activo y nivel entre 1 y 3; actualiza `tecnico_usuario_id`, estado `asignado`, `nivel_atencion` y fecha. Inserta historial `asignado`, con texto de reasignación si ya existía responsable. No valida especialidad/competencia del asignado. Además, `guardar.php` acepta `tecnico_id` al crear y puede iniciar el ticket asignado sin exigir en esa rama `tickets.asignar`.

En términos futuros deben ser operaciones distintas:

- **Asignación:** primera designación de responsable.
- **Reasignación:** cambio de responsable, con anterior/nuevo y motivo.
- **Escalamiento:** decisión de elevar nivel/responsabilidad por complejidad o falta de resolución; puede llevar a nueva asignación, pero no se reduce a ella.

## 10. Escalamiento

No se encontró permiso `tickets.escalar` ni endpoint separado de escalamiento. `nivel_atencion` admite 1–3 y el endpoint de asignación lo recibe; esto representa nivel asignado, no un flujo de escalamiento completo con origen/destino/causa. El esquema de historial es genérico y puede almacenar evento/detalle, pero no hay campos/entidad específicos del escalamiento.

La ruta ilustrativa confirmada (`Técnico N1 → Supervisor → Técnico especializado → Jefe TI`) debe considerarse modelo funcional por definir, no comportamiento implementado. Debe guardarse quién inició, nivel/actor anterior, destino, motivo, momento y resultado.

## 11. Reapertura

Regla confirmada: el Jefe de Área solicita reapertura cuando la solución no resolvió la incidencia original. TI revisa y ejecuta si corresponde. Solo se solicita durante el mismo día calendario que el cierre; a partir del día siguiente se crea un ticket nuevo.

Actual: no existe `tickets.reabrir` ni endpoint/solicitud de reapertura. `estado.php` permite `resuelto`→`en_atencion` y `cerrado`→`en_atencion` a quien tenga `tickets.cambiar_estado`; requiere motivo y aumenta `reaperturas`. No comprueba fecha/plazo ni actor solicitante. El incremento se ejecuta antes de validar motivo requerido. El historial registra `cambio_estado` genérico con transición y motivo.

El esquema tiene `fecha_resolucion`, `fecha_cierre`, `reaperturas` y `ticket_historial.created_at`; no hay fecha específica de solicitud/ejecución de reapertura. Para la regla del mismo día, la referencia funcional expresada es fecha de cierre (`fecha_cierre`), pero la zona horaria/calendario aplicable aún debe confirmarse.

## 12. Eliminación lógica

Regla confirmada: no borrar físicamente; conservar historial. Actual `api/tickets/eliminar.php` elimina filas de `ticket_comentarios`, `ticket_adjuntos`, `ticket_historial` y `tickets`, y borra archivos físicos asociados. Luego registra una entrada genérica en `auditoria_logs`; así se destruye el historial propio del ticket.

Representación futura a decidir: indicador/estado de eliminado, `deleted_at`, `deleted_by`, motivo y evento de historial; definir si se ocultan registros por defecto, quién puede restaurarlos y cómo se conservan archivos/relaciones. No se agrega automáticamente ninguna columna.

## 13. Auditoría

### Capacidad actual

- Existe `core/Auditoria.php`, tabla `auditoria_logs`, pantalla JS `assets/js/app/auditoria.js`, endpoint `api/auditoria/listar.php`, y permiso `auditoria.ver`.
- El registro genérico incluye usuario, acción, tabla, registro, datos anteriores/nuevos, IP, user-agent y fecha. El usuario puede unirse a `usuarios` para obtener nombre/rol actual; la tabla no conserva explícitamente snapshot del rol que tenía al momento del evento.
- `ticket_historial` conserva por ticket actor, evento, detalle y fecha/hora; incluye eventos actuales como `creado`, `actualizado`, `asignado`, `cambio_estado`, `comentario`, `adjunto`, `evidencia` y `prioridad_cambiada`.
- Creación, estado, asignación y cambios de prioridad llaman además a `Auditoria::registrar()`; comentario también genera auditoría. Edición llama a auditoría. La eliminación borra antes el historial y deja registro global de auditoría.

### Eventos mínimos requeridos por negocio

Creación; guardado/modificación de borrador; envío; asignación; reasignación; escalamiento; cambio de estado; atención; diagnóstico; solución; resolución; cierre; solicitud/ejecución de reapertura; eliminación lógica; cambios de permisos; cambios relevantes de configuración. Cada evento debe considerar usuario, rol, acción, módulo, registro afectado, fecha/hora, valor anterior/nuevo, motivo, IP si aplica y resultado.

### Brecha técnica observada

`core/Auditoria.php` intenta insertar `ip_real`, `ubicacion`, `latitud` y `longitud`, y `api/auditoria/listar.php` consulta esas columnas; el `CREATE TABLE auditoria_logs` de `db/sigti.sql` mostrado solo declara `ip` y `user_agent` (además de datos de acción). Esto es una discrepancia entre código y semilla que debe verificarse contra la base activa antes de planificar cambios de auditoría. Se registra como impacto/pendiente, sin modificar el esquema.

## 14. Estado actual

- **Ya existe:** roles/permisos basados en catálogo y sesión; asociaciones usuario-personal-área; tickets con solicitante, área afectada, técnico asignado, estado, nivel, fechas, solución, contador de reaperturas; historial por ticket; comentarios/adjuntos; endpoints de asignación, estado, prioridad y eliminación; módulo global de auditoría y permiso de reportes.
- **Parcial:** visibilidad propia/área/global; validación de transiciones; registro de varios eventos; cambio a `en_atencion` como reapertura; auditoría genérica antes/después; interfaz que oculta acciones con flags.
- **Incorrecto frente a política confirmada:** permiso `tickets.crear` usado para editar; edición de ticket abierto ajeno sin propiedad/alcance; creación puede asignar técnico por POST sin permiso específico; eliminación es física; reapertura ejecutable por permiso genérico y sin plazo; detalle no generaliza alcance de jefe de área.
- **No encontrado:** borrador y envío separados; endpoint/permiso de solicitar reapertura; escalamiento independiente; acciones estructuradas de diagnóstico/atención/solución; roles Gerencia/Coordinador/Jefe TI y especialistas; catálogo de competencias; eliminación lógica; auditoría específica de excepciones de atención.

## 15. Brechas

1. **H-01:** falta autorización de edición por permiso `tickets.editar`, propietario/alcance y estado; interfaz deriva edición de `tickets.crear`.
2. Falta estado/ciclo de borrador y confirmación de envío, requisito central de la política.
3. El solicitante/creador no se identifica de forma inequívoca: existe `solicitante_personal_id`, pero el creador queda solo en historial y se acepta solicitante POST.
4. Faltan permisos independientes para eliminar, escalar, resolver y reabrir; `tickets.cambiar_estado` concentra varias acciones.
5. El permiso de eliminación se exige en API/UI pero no está en la semilla; la operación contradice la regla al borrar historial físicamente.
6. No existe rol Gerencia ni grants de lectura específicos de Gerencia; acceso a reportes/auditoría no debe activar acciones de ticket.
7. Roles TI de coordinación/jefatura/gerencia y roles de especialidad no están representados en semilla.
8. No hay modelo de competencias, escalamiento ni atención excepcional con justificación.
9. Reapertura no verifica quién solicita, quién ejecuta ni fecha de cierre/plazo; historial usa evento genérico.
10. La bitácora de auditoría no contempla en catálogo de acción todas las acciones de negocio específicas; posible desajuste de columnas entre código de auditoría y SQL semilla.

## 16. Impacto técnico

El modelo actual mezcla parcialmente rol, permiso, visibilidad y capacidad en frontend/endpoints. La futura implementación debe mantener separados perfil/cargo, especialidad, permiso, ámbito de acceso, acción y estado. Los impactos concretos por capa se detallan en los apartados siguientes.

## 17. Impacto en BD

**Propuesta, no ejecutada.**

- Decidir cómo representar `borrador` y envío (`estado` o campos de ciclo de vida como fecha de envío). Evaluar todos los consumidores del enum.
- Agregar al catálogo únicamente permisos aprobados, por ejemplo para escalar, resolver, reabrir, envío o eliminación lógica. No inventar ni asignar automáticamente grants.
- Definir perfiles/roles organizacionales que faltan y sus grants sin convertir reportes/auditoría en permisos de escritura.
- Para borrado lógico, evaluar columnas/estado y metadatos de actor, fecha y motivo, preservando filas e historial.
- Para atención especializada, evaluar relación de especialidades/competencias con personal y tipo/categoría de ticket; sin automatizar asignación en esta etapa.
- Para reapertura, evaluar datos de solicitud/aprobación/ejecución o eventos y fecha, más referencia a `fecha_cierre`.
- Para auditoría, definir eventos/resultado/rol snapshot y resolver la discrepancia de columnas observada.
- El campo de creador explícito solo se consideraría si negocio confirma que solicitante puede diferir de quien registra; actualmente existe evento de creación en historial.

## 18. Impacto en endpoints

- `api/tickets/guardar.php`: separar crear, guardar/editar borrador, enviar y edición TI; validar identidad, propiedad, permiso, estado y alcance; retirar asignación implícita o comprobar permiso correspondiente.
- `api/tickets/listar.php`: calcular capacidades por acción, estado y alcance; no usar `tickets.crear` como edición.
- `api/tickets/detalle.php`: autorización de lectura por alcance y capacidades coherentes.
- `api/tickets/estado.php`: permisos independientes, actor/alcance y transiciones; reapertura con regla temporal y auditoría.
- `api/tickets/asignar.php`: diferenciar asignación/reasignación y registrar cambios; validar competencia si se modela.
- `api/tickets/eliminar.php`: reemplazar borrado físico por baja lógica y conservar historial.
- `api/tickets/prioridad.php`: confirmar permiso/acción de negocio para cambios técnicos.
- Endpoints nuevos o acciones separadas para envío, escalamiento y solicitud/ejecución de reapertura si se aprueba ese diseño.
- `api/tickets/comentario.php` y `adjunto.php`: confirmar que el acceso posterior al envío no permite modificar contenido prohibido; preservar la distinción entre seguimiento y edición.
- `api/auditoria/listar.php`/`core/Auditoria.php`: solo tras verificar el esquema activo y definir campos/eventos de auditoría requeridos.

## 19. Impacto frontend

`assets/js/app/tickets.js` actualmente presenta creación/edición en el mismo formulario/endpoint, botones basados en flags del servidor y una transición de estado común. Posteriormente deberá separar guardar borrador de enviar, bloquear edición visible tras envío, presentar acciones específicas según capacidades y mostrar solicitud de reapertura. La interfaz solo representa autorización; cada endpoint vuelve a validarla.

`assets/js/app/auditoria.js` muestra logs; menú `assets/js/core/menu.js` distingue `reportes.ver`/`reportes.ver_area` y `auditoria.ver`. Un futuro rol Gerencia debe recibir explícitamente solo capacidades de lectura aprobadas.

## 20. Riesgos

- Acceso horizontal a tickets ajenos por ID manipulable y rama de edición sin alcance (alto, H-01).
- Escalamiento de privilegios horizontal: permiso de creación permite modificación; endpoint directo evita controles visuales.
- Exposición de datos por diferencia entre reglas de lista/detalle y demás endpoints, descargas, comentarios o adjuntos.
- Eliminación física que destruye historia necesaria para investigación y auditoría.
- Cambios de estado/solución/reapertura protegidos con permiso amplio compartido.
- La falta de estado borrador hace imposible cumplir el corte de edición al enviar.
- Grupos de especialidad/gerencia no están modelados; asignaciones manuales no verifican competencia.
- La auditoría puede fallar si el esquema activo corresponde al SQL semilla sin las columnas esperadas; `Auditoria::registrar()` captura la excepción y escribe en log, lo que puede hacer que la operación principal continúe sin fila de auditoría.

## 21. Recomendaciones

1. Aprobar matriz funcional por perfil y ámbito antes de cambiar grants.
2. Cerrar decisión de borrador/envío y distinguir solicitante de actor creador.
3. Resolver H-01 en backend primero: permiso correcto, propietario/alcance y estado, sin confiar en cliente.
4. Separar acciones de atención técnica y de administración del ciclo de vida; no reutilizar permiso de crear ni uno genérico donde se exige independencia.
5. Diferenciar asignar/reasignar/escalar y registrar anterior/nuevo, motivo y actores.
6. Modelar reapertura con el día calendario de cierre y aprobación TI conforme a la regla confirmada; definir zona horaria de negocio.
7. Cambiar eliminación a lógica conservando historial, una vez aprobado el diseño de retención/restauración.
8. Preparar auditoría para los eventos requeridos y verificar primero la compatibilidad SQL/código actual.
9. Mantener perfiles de Gerencia de solo lectura de reportes/auditoría salvo grants adicionales explícitos e independientes.
10. Mantener especialidad como dato distinto de rol/cargo y no implementar asignación automática en este trabajo.

## 22. Decisiones confirmadas

- Solicitante puede guardar y editar su propio borrador; tras envío no puede editar.
- Jefe de área puede crear/enviar/consultar por su ámbito y solicitar reapertura, sin editar datos técnicos.
- Gerencia consulta reportes, indicadores, auditorías e histórico, solicita tickets y consulta su estado; no recibe permisos administrativos de ticket automáticamente.
- Acciones TI son diferenciadas; técnicos atienden por competencia.
- Asignación, reasignación y escalamiento no son sinónimos.
- Excepción para Coordinador/Jefe/Gerente TI que asume atención debe quedar justificada y auditable.
- Reapertura la solicita Jefe de Área por la incidencia original, requiere revisión TI y solo puede solicitarse el día del cierre; al día siguiente procede ticket nuevo.
- Tickets no se eliminan físicamente; debe conservarse historial.
- Este documento no implementa ninguna de estas reglas.

## 23. Decisiones pendientes

- Si borrador será valor de `estado` o ciclo de vida separado; significado exacto de enviar y validaciones finales.
- Si enviar tendrá permiso propio o será parte de crear, manteniendo edición independiente.
- Permiso/grant para edición de borrador del usuario final, ya que la semilla actual no le concede `tickets.editar`.
- Ámbitos exactos para soporte, especialista, analista, coordinador, jefe y gerente TI; y diferencia entre roles de supervisor existentes.
- Si Gerencia organizacional y Gerente TI comparten perfil o son identidades distintas; permisos de reportes/indicadores/auditoría/histórico y límites de datos.
- Definición y atribución de SSOMA en la instalación real; no hay rol encontrado en semilla.
- Catálogo exacto de permisos independientes a crear y asignaciones por perfil.
- Competencias/especialidades: catálogo, vínculo con personal y cómo se identifica tipo de ticket.
- Datos/eventos de atención, diagnóstico, actividad y solución; comentarios/adjuntos permitidos después del envío.
- Quién revisa y ejecuta reapertura, cómo registrar solicitud y regla de zona horaria para “mismo día”.
- Política de restauración/retención del borrado lógico.
- Resolver discrepancia entre columnas de `auditoria_logs` usadas por código y declaradas en la semilla.
- Si se requiere campo de creador distinto del solicitante y snapshot histórico de rol/permisos.

## 24. Criterios de aceptación para futura implementación

1. `tickets.crear` no autoriza editar, reasignar, escalar ni cambiar campos técnicos.
2. El solicitante edita solo su borrador; una llamada HTTP directa no puede modificar ticket enviado.
3. Enviar cambia el ticket fuera de borrador y registra actor/fecha/historial.
4. TI ejecuta acciones solo con permiso específico, estado válido y alcance aplicable.
5. Usuario final no puede ejecutar acciones técnicas ni eliminar ticket.
6. Jefe de área puede solicitar reapertura solo el día calendario del cierre; TI revisa/ejecuta y queda trazabilidad.
7. Asignación, reasignación y escalamiento tienen semántica y registro distintos.
8. Atención excepcional de gerencia TI registra actor, ticket, motivo, fecha/hora, indicador de excepción y justificación.
9. Eliminación es lógica y conserva ticket/historial según retención aprobada.
10. Cada evento de auditoría relevante identifica actor, acción, módulo, registro, fecha/hora, cambios, motivo y resultado; IP si corresponde.
11. Gerencia recibe solo las capacidades explícitamente aprobadas; reportes/auditoría no conceden administración de tickets.
12. Roles, cargos, especialidades, permisos, ámbito, acción y estado permanecen conceptos distintos en validación backend.

## Conclusión

El proyecto tiene una base de roles y permisos, pero solo modela parcialmente los perfiles organizacionales solicitados. H-01 está confirmado: el permiso de creación sustituye indebidamente la autorización de edición y la rama de escritura no valida propiedad/alcance. El sistema tampoco tiene borradores ni envío separado, y la eliminación física y reapertura actual contradicen las reglas confirmadas. Hay funciones reutilizables de historial y auditoría, pero no cubren todo el ciclo propuesto y existe una posible incompatibilidad entre columnas de auditoría usadas por el código y la definición SQL semilla. Antes de implementar, deben aprobarse perfiles/grants, ciclo de borrador/envío, permisos independientes y detalles de reapertura/eliminación lógica.

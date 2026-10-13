# SIGTI — Licenciamiento por empresa

SIGTI se entrega **una instalación por empresa** (su propio servidor y base de datos). Cada instalación funciona con una
**licencia** emitida por el autor, que se valida **sin internet**.

Autor: Ing. Alejandro Aldo Meneses Aliaga · ORCID 0000-0003-1788-1978 · Código SIGTI-DEV-AMA-2026

## Qué controla la licencia
- **Empresa**: razón social, siglas y **RUC**. Al activarla se cargan en el sistema (actas, informes, carnets).
- **Instalación**: cada copia genera un código `INST-XXXX-XXXX-XXXX`; la licencia se emite para ese código y no sirve en otra copia.
- **Vigencia**: fecha de vencimiento (o permanente).

## Estados
| Estado | Cuándo | Qué pasa |
|---|---|---|
| Prueba | Sin licencia, primeros 15 días de uso | Funciona completo |
| Activa | Vigente | Funciona completo |
| Por vencer | Faltan 30 días o menos | Funciona + aviso |
| Gracia | Vencida hace 15 días o menos | Funciona + aviso rojo |
| Vencida / Sin licencia / No válida | Pasada la gracia, sin licencia, firma/RUC/instalación distintos o reloj alterado | **Solo lectura**: se consulta y se exporta; no se registra ni modifica. Los datos nunca se borran |

## Rubros y módulos
Al elegir el rubro (general, construcción, educación, salud, comercio, industria, logística, servicios) el sistema agrega
categorías de tickets, tipos de equipo y áreas propias del giro y cambia la palabra «obra» por la que corresponde.
Los módulos que la empresa no use (Proyectos, Capacitaciones, Mantenimiento, Software, Seguimiento, Carnets, Etiquetas, Chat)
se pueden apagar.

## Técnica
Firma RSA-2048 / SHA-256 (`openssl_verify`). El sistema solo contiene la **clave pública** (`core/Licencia.php`); la clave privada
y el emisor viven en el *Kit del proveedor*, que no se entrega a clientes.

> Nota honesta: al ser PHP en texto plano, quien tenga acceso al código podría modificar la validación. La licencia disuade y
> respalda el contrato; la protección real es el acuerdo legal (ver LICENSE) y, si se requiere, ofuscar el código.

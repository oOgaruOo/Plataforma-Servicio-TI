# Autoría de SIGTI

**SIGTI — Plataforma de Servicio TI** fue desarrollado por:

| | |
|---|---|
| **Autor** | Ing. Alejandro Aldo Meneses Aliaga |
| **ORCID** | [0000-0003-1788-1978](https://orcid.org/0000-0003-1788-1978) |
| **Código de autor** | `SIGTI-DEV-AMA-2026` |
| **Sello de autoría** | `1401-794B-B9E5` |
| **Año** | 2026 |

## Dónde consta la autoría

- Primera línea de cada archivo PHP, JS y CSS: comentario `@autor … ORCID … Código de autor`.
- `core/Autor.php`: datos del autor en un solo lugar (nombre, código, ORCID y sello).
- Pantalla de ingreso y **Acerca de SIGTI** (menú de la foto): nombre, código y enlace al ORCID.
- Código fuente de la página: `<meta name="author">`, `citation_author_orcid` y datos estructurados (schema.org).
- `CITATION.cff` y `codemeta.json`: permiten citar el software y que GitHub, Zenodo u otros lo asocien con el ORCID del autor.
- `LICENSE`: todos los derechos reservados al autor.

## Cómo reforzar la prueba de autoría

1. **Historial de GitHub**: suba el proyecto con *commits* firmados a nombre del autor; las fechas del historial son evidencia de creación.
2. **Zenodo + DOI** (gratis): enlace el repositorio con Zenodo y publique una *release*; se obtiene un DOI que cita el ORCID del autor.
3. **Perfil ORCID**: en orcid.org agregue el software en *Works* (con el DOI de Zenodo o el enlace del repositorio).
4. **Registro de derechos de autor**: el software puede inscribirse en el INDECOPI (Perú) para tener un certificado oficial.

> El ORCID identifica al autor de forma inequívoca, pero **no es por sí mismo un título de propiedad**. El derecho de autor nace con la
> creación de la obra; si el sistema se desarrolló en el marco de una relación laboral o contrato, conviene revisar qué dice ese documento
> sobre la titularidad. Esto no es asesoría legal.

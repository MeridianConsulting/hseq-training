# Aislamiento de datos por rol / área / proyecto

## Estado actual (2026-09)

El acceso se controla por **permiso de módulo** (`asignaciones.ver`, `personal.ver`, etc.).
En esta etapa el sistema opera con un único perfil operativo (**Administrador HSEQ**): quien inicia sesión ve **todos** los registros del módulo.

No hay filtrado automático por área, proceso, proyecto ni cargo del usuario autenticado.

## Contexto de trabajador (no es aislamiento de usuario)

En `persona_contexto_hseq` (base capacitaciones) se guarda **1 proceso** y, si aplica, **1 proyecto** por trabajador, enlazado por `persona_id_ext` / `numero_documento` de `meridian_personal`.

Eso describe el contexto laboral del trabajador (y se **suma** a los procesos inferidos por matriz/cargo). **No** restringe qué pantallas ve el admin.

La vista de Matriz (con proceso —y proyecto si aplica— filtrado) lista solo los **cargos** de trabajadores Activos con ese mismo contexto en `persona_contexto_hseq`; no usa la lista fija del Excel HSEQ-PRG-10.

## Decisión pendiente (si aparecen más roles)

Antes de filtrar por alcance habría que definir:

1. ¿Qué roles deben ver solo su proceso o proyecto?
2. ¿Dónde se guarda esa relación para el **usuario** del sistema (no el trabajador corporativo)?
3. ¿Administrador HSEQ siempre ve todo?
4. ¿Aplica a reportes y exportaciones Excel?

## Extensión técnica preparada

Clase: `backend/app/Services/AlcanceDatosService.php`

- Hoy retorna `modo=global` y `activo=false` (sin filtrar).
- Cuando exista la matriz de negocio de **usuarios**, activar el alcance y aplicarlo en
  repositorios de listado vía `aplicarAFiltros()`.

## No hacer sin definición de negocio

- No filtrar silenciosamente por área/proyecto inventada.
- No romper reportes globales de gerencia.
- No confundir `persona_contexto_hseq` (dato del trabajador) con alcance del usuario de sesión.

# Aislamiento de datos por rol / área / proyecto

## Estado actual (2026-09)

El acceso se controla por **permiso de módulo** (`asignaciones.ver`, `personal.ver`, etc.).
Cualquier usuario con el permiso ve **todos** los registros del módulo.

No existe hoy filtrado automático por:

- Área
- Proceso
- Proyecto
- Cargo del usuario autenticado

## Decisión pendiente de negocio

Antes de implementar hay que definir:

1. ¿Qué roles deben ver solo su proceso o proyecto?
2. ¿Dónde se guarda esa relación (tabla intermedia, atributo en `usuarios`, catálogo)?
3. ¿Administrador HSEQ siempre ve todo?
4. ¿Aplica a reportes y exportaciones Excel?

## Extensión técnica preparada

Clase: `backend/app/Services/AlcanceDatosService.php`

- Hoy retorna `modo=global` y `activo=false` (sin filtrar).
- Cuando exista la matriz de negocio, activar el alcance y aplicarlo en
  repositorios de listado (`AsignacionRepository`, `ReporteRepository`, `AlertaRepository`, etc.)
  vía `aplicarAFiltros()`.

## No hacer sin definición de negocio

- No filtrar silenciosamente por área/proyecto inventada.
- No romper reportes globales de gerencia.

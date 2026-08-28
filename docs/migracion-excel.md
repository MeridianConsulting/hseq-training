# Estrategia de migración Excel (no ejecutar en esta etapa)

Fuente operativa actual: cronograma y matriz por cargo en `docs/02_HSEQ_PRG_10_Capacitacion_entrenamiento_Frontera.xlsx` y el formato de levantamiento.

## Principio

El maestro de personas es `meridian_personal`. HSEQ **no crea ni inactiva trabajadores**. Cada fila del Excel se resuelve a un `persona_id` existente.

## Pasos previstos (etapa posterior)

1. Normalizar documento de identidad del Excel (quitar puntos, espacios y ceros a la izquierda de forma controlada).
2. Buscar en `meridian_personal.personas.numero_documento`.
3. Si hay coincidencia única: usar ese `persona_id` (y el contrato vigente si aplica) al crear asignaciones o cumplimientos.
4. Si no hay coincidencia, hay varias, o el estado laboral no cuadra: **no crear persona**. Registrar la fila en un reporte de inconsistencias.
5. Cargos del Excel se cruzan con `meridian_personal.cargos` por nombre; si no existen, van al mismo reporte.
6. Capacitaciones se cruzan por código/nombre con `meridian_capacitaciones.capacitaciones`; las faltantes se crean en el catálogo HSEQ, no en personal.

## Inconsistencias típicas a reportar

- Documento ausente en personal
- Nombre que no coincide con el documento
- Cargo del Excel distinto al cargo actual (el snapshot de asignación se tomará al asignar, no se sobrescribe después)
- Fechas ilegibles o límite de cumplimiento confundido con vigencia

## Fuera de alcance ahora

No hay importador, ni job, ni carga masiva. Este documento solo deja la regla de negocio acordada.

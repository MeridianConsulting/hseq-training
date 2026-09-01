# Personal corporativo y Excel

## Fuente única

El maestro de trabajadores es `meridian_personal.personas` (con `cargos` y `contratos`). HSEQ **escribe** altas individuales y cargas masivas en ese maestro. No se duplican personas en `meridian_capacitaciones`.

Las asignaciones siguen usando `persona_id_ext`. Un trabajador creado en `/personal` queda disponible de inmediato para asignar capacitaciones.

## Alta individual y carga masiva (módulo `/personal`)

- Formulario: documento, nombre, correo (opcional), cargo (catálogo), proyecto (opcional), fecha de ingreso.
- Carga masiva: Excel `.xlsx` / `.xls` y CSV. Plantilla en `GET /api/personal/plantilla`.
- Cada fila se valida e inserta por separado. Un error no hace rollback de las filas válidas.
- Duplicados: documento ya en BD, o repetido dentro del mismo archivo.
- El cargo debe existir en `meridian_personal.cargos`. **No se crean cargos automáticamente.**
- Fecha de ingreso se guarda en `contratos.fecha_inicio`.
- Área/proceso **no** se persisten en el trabajador (son catálogos de matriz/asignaciones). Pendiente de decisión HSEQ.

## Carga inicial de la matriz Excel HSEQ (`/migracion`)

La matriz histórica (formato HSEQ-PRG-10) se carga en **Carga inicial Excel**. El flujo es validar → revisar inconsistencias → confirmar. **No se escriben trabajadores, capacitaciones, matriz ni cumplimientos hasta confirmar.**

Cruce de documento (igual que en personal):

1. Normalizar documento (quitar espacios y puntos de miles; **no** quitar ceros a la izquierda).
2. Buscar en `meridian_personal.personas.numero_documento`.
3. Si hay coincidencia única: usar ese `persona_id`.
4. Si no hay coincidencia: registrar primero en `/personal` (individual o carga masiva) o corregir el Excel y volver a validar.
5. Cargos del Excel se cruzan con `meridian_personal.cargos` por nombre.

## Inconsistencias típicas

- Documento ya registrado
- Documento duplicado dentro del archivo
- Cargo que no existe en el catálogo
- Fechas ilegibles (`DD/MM/YYYY` en la plantilla)

## Fixture de prueba

`docs/fixtures/carga_personal_50.csv`: 45 filas válidas y 5 inválidas (duplicado en archivo, documento vacío, nombre vacío, cargo vacío, fecha vacía). Documentos de prueba con prefijo `9000`.

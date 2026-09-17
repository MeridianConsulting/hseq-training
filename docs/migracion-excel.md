# Carga inicial de historial y Personal corporativo

## Fuente única de personal

El maestro de trabajadores es `meridian_personal.personas` (con `cargos` y `contratos`). HSEQ **no** crea personas desde la carga de historial. Las altas individuales y masivas viven en `/personal`.

Las asignaciones y cumplimientos usan `persona_id_ext`.

## Alta individual y carga masiva (módulo `/personal`)

- Formulario: documento, nombre, correo (opcional), cargo (catálogo), proyecto (opcional), fecha de ingreso.
- Carga masiva: Excel `.xlsx` / `.xls` y CSV. Plantilla en `GET /api/personal/plantilla`.
- Duplicados: documento ya en BD, o repetido dentro del mismo archivo.
- El cargo debe existir en `meridian_personal.cargos`. **No se crean cargos automáticamente** en la carga de personal.

## Carga inicial de historial (`/migracion`)

Formato de archivo: plantilla HSEQ-PRG-10 (hojas CRONOGRAMA, MATRIZ POR CARGO, SEGUIMIENTO_PERSONAL).

Flujo: validar → revisar inconsistencias → confirmar.

**Qué importa**

- Solo ejecuciones históricas con estado `E` (ejecutado).
- Trabajador existente en Personal Corporativo (por documento). Inactivos sí pueden tener historial.
- Capacitación existente en el catálogo (por código `HSEQ-NN` del cronograma).
- Fecha **real completa** de ejecución (día/mes/año). No se inventa el día 01 a partir del solo nombre del mes.
- Nota si la capacitación exige evaluación; vigencia calculada desde el catálogo.

**Qué no hace**

- No crea personas, cargos, capacitaciones ni reglas de matriz.
- No importa pendientes `P` ni programa Fecha Desde/Hasta futuras.
- No guarda el Excel en disco (queda el nombre del archivo + auditoría).
- La hoja MATRIZ POR CARGO y las marcas `X` no se persisten.
- Certificado SI/SÍ en Excel es solo advertencia (no hay PDF en el archivo).

**Persistencia**

Cada ejecución válida crea un cumplimiento y un contenedor de asignación (FK obligatoria del esquema) con `fecha_asignacion` = `fecha_limite_cumplimiento` = fecha de realización. Eso **no** es programación operativa ni “fuera de tiempo”. Observaciones marcan el origen (carga inicial o registro manual).

**Duplicados**

Misma persona + misma capacitación + misma fecha de realización → se omite.

**Historial manual**

En el perfil del trabajador (`/personal/{id}`), con permiso `cumplimientos.crear`, se puede registrar el mismo tipo de historial vía `POST /api/cumplimientos/historial` (misma lógica de vigencia y contenedor).

## Inconsistencias típicas

- Trabajador no encontrado
- Capacitación no encontrada
- Fecha incompleta o inválida
- Nota requerida ausente / nota bajo la mínima
- Código/letra de estado sin equivalencia (`E` / `P` / `N/A`)
- Registro duplicado

## Fixture de prueba (personal, no migración)

`docs/fixtures/carga_personal_50.csv`: 45 filas válidas y 5 inválidas. Documentos de prueba con prefijo `9000`.

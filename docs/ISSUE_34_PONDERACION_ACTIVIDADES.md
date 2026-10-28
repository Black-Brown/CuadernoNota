# Issue #34 — Ponderación de actividades

## Uso

En el workspace docente → Gestión de actividades, la columna «Peso (%)» permite configurar el reparto de las actividades activas. «Guardar ponderaciones» guarda todos los porcentajes juntos, exige un total de 100% y recalcula los borradores. «Repartir equitativamente» propone porcentajes con dos decimales y distribuye el residuo para sumar exactamente 100%.

Al crear una actividad también se puede indicar un porcentaje. Se permite guardar una configuración parcial para reorganizar el curso, pero no produce promedios completos ni permite enviar notas a revisión hasta que el reparto activo sea válido. Las actividades inactivas no participan. Activar/desactivar o crear actividades puede exigir reajustar el reparto. Los porcentajes válidos son mayores que cero y hasta 100, con dos decimales como máximo.

## Cálculo

Con Proyecto 60% y Examen 40%, cada competencia se calcula como `notaProyecto × 0.60 + notaExamen × 0.40`. Las cuatro competencias siguen teniendo igual peso en el período: `(C1 + C2 + C3 + C4) / 4`.

En modo porcentajes se requieren las cuatro notas de **todas** las actividades activas; los vacíos quedan pendientes y no se convierten en cero ni redistribuyen el peso. Un cero registrado sí participa. Se mantiene el guardado parcial de progreso.

## Compatibilidad y permisos

La migración agrega `course_activities.weight_configured = false`. No interpreta como vigentes los valores antiguos de `weight`, que antes eran informativos, ni modifica notas. Los cursos/períodos existentes mantienen el promedio equitativo hasta que el docente configure explícitamente porcentajes. Una vez configurado, desactivar una actividad no cambia silenciosamente al modo equitativo.

Solo docentes asignados al curso pueden configurar porcentajes, durante un período abierto. Se bloquean cambios cuando existen notas en revisión u oficiales. Las operaciones y recálculos son transaccionales, con bloqueo del curso para evitar carreras entre configuración, guardado y envío a revisión.

Si un ajuste dejaría incompleta una nota que ya tiene recuperación pedagógica, se rechaza la operación y se revierte íntegramente para no perder esa recuperación. En ese caso se debe guardar un reparto completo y válido.

## API

- `POST /api/docente/activities`: `weight` opcional, activa configuración explícita para esa actividad.
- `PATCH /api/docente/activities/{id}` con `{ "weight": 60 }`: guarda un porcentaje; puede dejar el reparto pendiente.
- `PUT /api/docente/activity-weights`: recibe `section_id`, `subject_id`, `period_id` y `weights: [{id, weight}]`. Debe incluir exactamente las actividades activas del curso/período y sumar 100%; se rechazan duplicados, IDs ajenos y totales inválidos sin guardar parcialmente.
- El listado de actividades devuelve `weighting` con `mode`, `valid`, `total`, `active_count` y `weights`; cada actividad indica `weight_configured`.
- La comprobación de envío también devuelve `weighting` y bloquea repartos inválidos.

## Despliegue

Requiere `2026_10_27_000001_enable_activity_weight_configuration.php`. Aplicar la migración antes de desplegar API/frontend compatibles. No requiere seeders ni recalcular notas históricas. No se ha aplicado a Supabase durante esta implementación. El rollback se bloquea si existen porcentajes configurados.

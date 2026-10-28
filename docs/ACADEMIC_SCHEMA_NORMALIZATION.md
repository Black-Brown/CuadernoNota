# Niveles académicos y promociones

## Catálogo de niveles

`academic_levels.name` es una clave primaria natural. `grades.level` y
`users.coordinator_level` tienen claves foráneas hacia ella. Se conservan los
campos y respuestas existentes de la API: no requiere cambios en el frontend.

La migración incorpora Inicial, Primaria y Secundaria y conserva los valores
históricos, incluso variantes de escritura. No reclasifica grados ni amplía
permisos: la supervisión sigue usando las reglas de CoordinatorScope.
La API permite los mismos niveles que antes y valida su existencia en el catálogo.

## Una fuente de promociones

`promotion_decisions` contiene tanto las decisiones administrativas como los
registros históricos y automáticos. Siempre referencia estudiante y año escolar.
La matrícula, responsable y destino son opcionales únicamente cuando no hay
información histórica suficiente; el flujo administrativo continúa exigiéndolos
según sus reglas originales.

La migración:

1. Rechaza contradicciones entre una promoción histórica y una decisión negativa.
2. Completa estudiante y año de las decisiones existentes a partir de su matrícula.
3. Integra el historial antiguo, conservando la fila original en `legacy_record`.
4. Si ya existe una decisión compatible para estudiante/año, incorpora el historial
   en ella. En otro caso crea un registro `source=legacy`, sin inventar matrícula,
   responsable ni asignación pendiente.
5. Elimina `student_promotions` después de copiar todos sus registros.

El repositorio antiguo ahora consulta y escribe en la tabla unificada. Es idempotente
y rechaza sobrescribir automáticamente decisiones negativas. Una decisión administrativa
puede completar un registro histórico sin matrícula del mismo estudiante/año.

Se actualizan respaldo, protección contra eliminación de estudiantes, seeder de ejemplo
y alcance del restablecimiento. El catálogo de niveles se conserva en un restablecimiento.

## Despliegue

Ejecutar `php artisan migrate --force` con la aplicación sin escrituras concurrentes.
Hacer un respaldo previo. La migración de promociones no tiene rollback automático:
dividir nuevamente el historial requiere una migración explícita para evitar pérdida de datos.
Los cambios no ejecutan seeders ni recalculan calificaciones.

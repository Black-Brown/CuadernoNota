# Registro académico por curso

Administración → Reportes → Académico muestra tarjetas por grado, sección, tanda y año. Toda la tarjeta abre el registro; se conserva el resumen anual debajo.

Dentro del curso se pueden filtrar las calificaciones por materia, período y nombre o matrícula del estudiante, y exportar la selección a CSV. Cada fila corresponde a un estudiante, materia y período e incluye C1, C2, C3, nota del período, recuperación (RP) y nota efectiva (RP si existe; de lo contrario, nota del período).

Solo aparecen calificaciones oficiales, como en el reporte anterior. Las notas inexistentes se muestran como «—», no como cero. Los registros se asocian a la sección de la calificación, no a la sección actual del estudiante, para conservar el historial de traslados y bajas.

Los indicadores del curso respetan los filtros: estudiantes evaluados únicos, promedio de notas efectivas no nulas y estudiantes únicos con alguna nota efectiva menor que 70. No representan una decisión de promoción.

## Endpoints de solo lectura para administradores

- `GET /api/admin/reports/academic/courses?academic_year_id=ID`: secciones del año, incluidas las que no tienen notas oficiales.
- `GET /api/admin/reports/academic/courses/ID?academic_year_id=ID`: `rows` con notas oficiales y `periods` del año. Verifica que la sección pertenezca al año indicado y excluye notas de períodos de otros años.

No requiere migraciones, seeders ni cambios en Supabase.

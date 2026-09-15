# Reporte de asistencia por curso (issue #36)

En Administración → Reportes → Asistencia, cada tarjeta representa una sección exacta del año seleccionado, identificada por grado, sección y tanda. Toda la tarjeta permite abrir el registro.

El registro abre el último día guardado. Permite elegir otra fecha, consultar los días con datos, filtrar por materia y buscar por nombre o matrícula. Muestra presente (P), tardanza (T), ausente (A), justificada (E) y quién registró la asistencia. El CSV exporta la selección visible del día.

No se crean ni modifican asistencias desde este reporte. Un día sin registro no se interpreta como ausencia. Los registros de estudiantes trasladados o inactivos siguen disponibles. Los registros antiguos sin materia se identifican explícitamente como históricos. Los contadores son registros por materia, no estudiantes únicos. La tasa anual conserva P + T como asistencia.

## API (solo administrador autenticado y activo)

- `GET /api/admin/reports/attendance/courses?academic_year_id=ID`: secciones del año, incluidas las que no tienen registros.
- `GET /api/admin/reports/attendance/courses/ID?academic_year_id=ID&date=AAAA-MM-DD`: registros de esa sección y día; fecha opcional, usa el último día guardado. Devuelve `date`, `dates` y `rows`.
- `GET /api/admin/reports/attendance?academic_year_id=ID`: resumen existente; ahora diferencia IDs de sección y materia y expone tanda para evitar mezclar cursos homónimos.

No requiere migraciones ni seeders. Las pruebas usan la base de datos aislada de testing.

# Issue #41: workspaces administrativos por nivel

## Diagnóstico

La consulta de solo lectura del catálogo de producción encontró seis grados de Primaria con once secciones, sin materias vinculadas ni ofertas de curso activas. El selector docente requiere una oferta sección/materia: la ausencia de Primaria no era una restricción del coordinador. El usuario confirmó que recordaba la configuración pendiente y pidió continuar solo con los workspaces. No se asignaron materias ni se modificó Supabase.

## Organización visual

El componente compartido `LevelWorkspace` muestra tarjetas de Primaria y Secundaria, permite abrir cada nivel y regresar. Toda la tarjeta es un botón accesible por teclado; mantiene el indicador «Abrir» y el branding actual. Los niveles adicionales (Inicial o sin clasificación) se conservan, no se ocultan ni se reclasifican por el nombre del grado.

Se utiliza en:

- Asignaciones docentes: listado por nivel y selección de materias/secciones por nivel en el drawer.
- Configuración institucional: grados, secciones y selección de grados al editar materias.
- Workspaces de estudiantes, conservando el listado general como vista inicial y los pendientes sin sección fuera de los niveles.
- Promoción escolar.
- Registros académico y de asistencia por curso.
- Revisión de notas administrativa. La vista del coordinador se conserva sin el selector adicional.

Los filtros de año/estado existentes siguen funcionando. Cambiar de nivel no borra las selecciones del formulario. Las materias compartidas entre niveles abren grupos separados; los bloques del modal usan ID de año y grado cuando están disponibles para no mezclar nombres iguales.

## Contrato API

- `GET /api/admin/teacher-assignments/options` agrega `sections` para mostrar niveles aunque aún no tengan materias, y `grade_id`, `grade_level`, `academic_year_id` en los cursos. No crea ofertas ni asignaciones al consultar.
- Los listados de cursos de reportes y revisión de notas agregan `grade_level`. No se crean endpoints nuevos ni se cambian permisos.
- Las secciones sin materias activas muestran un aviso y enlace a Configuración institucional → Materias. El administrador debe decidir las materias de cada grado.

No requiere migraciones ni seeders. Es necesario desplegar API y frontend para disponer de los campos nuevos. Los datos académicos y las asignaciones existentes permanecen intactos.

# Implementación del portal de coordinación

## Base y alcance

Se parte de Requerimientos v4.0 (apartado 4) y CasosDeUso v3.0 (CU-10 a CU-16 y CU-29), contrastados con la aplicación existente.

El coordinador consulta calificaciones y seguimiento, aprueba o rechaza con comentario. No edita notas ni elimina estudiantes. La gestión y reapertura del calendario siguen siendo administrativas. El flujo de excusas con documento adjunto queda fuera del alcance por decisión del usuario.

Decisión confirmada: solo secciones asignadas por el administrador. Cada sección identifica grado, tanda y año. Se aplica en la API al listar, consultar y aprobar/rechazar. Sin asignaciones no hay acceso a cursos.

## Primera entrega

- Ruta de inicio: `/coordinador/dashboard`; usa indicadores reales del año activo.
- Revisión: `/coordinador/reviews`, con detalle y aprobación/rechazo mediante ConfirmDialog.
- Usuarios: creación de coordinadores y botón «Asignar secciones de supervisión», con SideDrawer y selección múltiple.
- Migración nueva: `2026_09_11_000001_create_coordinator_sections_table.php`. Ejecutar `php artisan migrate` en la API antes de usar el portal. No contiene datos de ejemplo ni asignaciones automáticas.
- API: `GET /api/coordinador/dashboard`, `GET /api/coordinador/grade-reviews`, `GET /api/coordinador/grade-reviews/{sectionId}/{subjectId}/{periodId}`, `POST /api/coordinador/grade-reviews/decision`.
- Asignaciones administrativas: `GET|PUT /api/admin/users/{user}/coordinator-sections`; PUT recibe `section_ids` y reemplaza la selección. Una lista vacía revoca todas las asignaciones.
- Pendientes de siguientes entregas: seguimiento, observaciones, asistencia, reportes y corrección posterior al rechazo fuera de fechas.

## Reutilización visual

## Seguimiento por sección

Desde Inicio se abre `/coordinador/sections/{sectionId}` al pulsar una sección. Incluye estudiantes activos, búsqueda por nombre/matrícula, selección de períodos del año de la sección y filtro de riesgo por materia (nota efectiva menor a 70, con RP cuando exista). El promedio mostrado usa solo materias con notas; no representa un boletín completo.

La ficha reutiliza SideDrawer y DataTable con pestañas de calificaciones, asistencia y observaciones. Solo consulta la sección autorizada y el período seleccionado. Las observaciones históricas sin sección/período no se atribuyen automáticamente. La asistencia cuenta registros por materia, con P y T como asistencia; no se presenta como días únicos.

Endpoints adicionales: `GET /api/coordinador/sections/{sectionId}/students?period_id=...` y `GET /api/coordinador/sections/{sectionId}/students/{studentId}?period_id=...`. Un período de otro año se rechaza; estudiantes de otra sección o inactivos no se muestran.

El seguimiento es de consulta. Los comentarios privados de coordinación quedan pendientes; los reportes se incorporan en la ampliación siguiente.

## Ampliación: base visual administrativa (2026-09-23)

Se recuperó el trabajo guardado de coordinación en `feat/modulo-coordinador`. El stash se conserva como respaldo. No se incorporaron automáticamente permisos de administrador.

El sidebar incluye Estudiantes, Catálogo académico, Configuración institucional, Asignaciones docentes, Revisión de notas, Asignación estudiantes, Promoción escolar y Reportes. Reutiliza AppLayout, PageHeader, DataTable, FilterBar, SearchInput y SideDrawer sin cambiar el branding.

- Estudiantes: listado de las secciones asignadas, incluidos inactivos; edición de nombre, apellido y número de matrícula con validación de unicidad. No permite alterar sección/estado enviando campos extra ni eliminar expedientes.
- Catálogo y configuración: consulta de secciones, materias y períodos de los años asociados al alcance del coordinador. Sin cambios globales.
- Asignaciones docentes: consulta de asignaciones de sus secciones. La gestión de asignaciones permanece administrativa en esta etapa.
- Promoción: consulta de elegibilidad por sección, reutilizando el cálculo administrativo. Todavía no habilita decisiones.
- Asignación estudiantes: consulta de pendientes cuya última matrícula procede de una sección asignada; excluye nuevos estudiantes sin procedencia. Todavía no habilita colocación.
- Reportes: cursos asignados, calificaciones oficiales por período, asistencia por fecha, búsqueda y CSV. No atribuye registros ajenos al curso ni convierte ausencia de nota en cero.

Endpoints: `GET /api/coordinador/catalog`, `/students`, `/assignments`, `/student-placements`, `/promotions/{sectionId}`, `/reports/{sectionId}` y `PATCH /api/coordinador/students/{studentId}`. Las escrituras están auditadas. Las rutas administrativas de usuarios, respaldos, reinicio y eliminación permanecen restringidas a admin.

Esta ampliación no constituye paridad funcional completa con admin: creación/importación de estudiantes, cambios de matrícula/estado, gestión docente, decisiones de promoción y colocación quedan pendientes de implementación con controles de ámbito y pruebas. No se ejecutó ninguna migración sobre producción.

- AppLayout: sidebar, cabecera, navegación móvil, identidad y cierre de sesión.
- PageHeader, KpiCard, FilterBar, DataTable y StatusBadge: encabezados, indicadores, listados y estados.
- ConfirmDialog, FormField y Toast: decisiones de revisión y comentarios.
- GradeReviews y GradeReviewDetail admiten funciones de API, claves de caché, rutas y nombre del portal por propiedades; mantienen sus valores administrativos predeterminados. La propiedad canReopen permite excluir esa acción del portal de coordinación. La API deberá exigir el permiso correspondiente independientemente del frontend.

## Orden de implementación

1. Implementado: alcance de supervisión protegido en backend, con pruebas de acceso ajeno, revocación, roles e inactividad.
2. Implementado: acceso, dashboard y revisión reutilizando los componentes existentes.
3. Añadir seguimiento de estudiantes, asistencia y observaciones dentro del alcance autorizado.
4. Reportes y boletines; el formato institucional de Excel requiere especificación de columnas.

## Consideración del flujo de devolución

El envío docente se habilita después de terminar el período, pero la edición se bloquea fuera de sus fechas. Antes de completar el flujo de rechazo hay que definir e implementar cómo se autoriza la corrección y el reenvío, sin abrir todas las notas ni todo el calendario. Un comentario de rechazo por sí solo no habilita la edición actual.

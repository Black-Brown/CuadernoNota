# Issue #42: interfaz del coordinador

- Reportes es la primera opción del menú. Inicio conserva el dashboard, que también es el destino al iniciar sesión o entrar a `/coordinador`.
- Estudiantes, Catálogo y Reportes utilizan tarjetas por curso, con filtro de año escolar y búsqueda por grado, sección y tanda.
- Las tarjetas completas son clicables y conservan una acción visible. Los iconos usan contenedores centrados y el estilo existente.
- Cada workspace mantiene el identificador de su sección; no mezcla cursos de distintos años.
- Estudiantes conserva edición de datos personales y acceso al seguimiento, sin permitir eliminación.
- El catálogo muestra una materia por fila con su estado, en lugar de una lista concatenada.
- La presentación se pagina en el cliente: 12 cursos o 20 registros por página. No se modifica la paginación del servidor.
- Exportar reportes incluye todas las filas filtradas, no solamente la página visible.
- El catálogo de la API añade `grade_level` y `year_active`; conserva las restricciones de supervisión.

No requiere migraciones ni datos de prueba en producción. No cambia permisos, notas ni matrículas.

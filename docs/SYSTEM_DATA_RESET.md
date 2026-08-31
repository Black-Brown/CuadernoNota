# Restablecer datos del sistema

Función administrativa en **Sistema → Zona peligrosa** (`/admin/system`). Afecta
los datos académicos de **todos los años**, independientemente del período
seleccionado en la barra superior. No se ejecuta automáticamente ni mediante una
migración. No requiere una migración nueva: no modifica el esquema.

## Análisis previo del esquema

Inspección de solo lectura realizada el 26 de agosto de 2026 en el MySQL local:
34 tablas InnoDB, 2 vistas, migraciones aplicadas, sin triggers ni restricciones
CHECK adicionales. Se revisaron columnas, nulabilidad, claves primarias, índices
únicos/no únicos y las claves foráneas reales, además de modelos, servicios,
seeders y controladores. Los enums permanecen intactos.

La clasificación explícita está en `ResetDataSchema`. Una tabla nueva **no** se
agrega automáticamente al borrado: bloquea el proceso hasta revisar su finalidad.

### CONSERVAR

| Tabla | Motivo / relaciones |
| --- | --- |
| `users` | Credenciales, identidad Google, rol, estado. Profesores y administradores son usuarios. |
| `academic_years` | Configuración de los años escolares. |
| `periods` | FK `academic_year_id → academic_years.id`; fechas, nombres y estados conservados. |
| `personal_access_tokens` | Autenticación Sanctum; relación polimórfica `tokenable_type/tokenable_id`, sin FK física. |
| `sessions` | Sesiones; `user_id` tiene índice, no FK física. |
| `password_reset_tokens` | Recuperación de contraseña por correo. |
| `competencies` | Configuración técnica fija de evaluación C1/C2/C3. |
| `activity_templates` | Catálogo de actividades base; se conservan registros e identificadores, incluidas las seis fijas. |
| `audit_logs` | Trazabilidad histórica; FK `user_id → users.id`. Se añade un evento nuevo. |
| `migrations` | Historial de estructura. |
| `cache` | Estado técnico, incluido OAuth Google. No se ejecuta un vaciado global. |
| `cache_locks` | Bloqueos técnicos. |
| `jobs` | Cola técnica; si contiene registros se bloquea el reset. |
| `job_batches` | Historial técnico; si tiene trabajos pendientes se bloquea el reset. |
| `failed_jobs` | Historial técnico de fallos; no se reintenta ni elimina automáticamente. |

No existen tablas separadas de roles/permisos ni una tabla independiente de
profesores en este esquema. `users.role` contiene la autorización.

### ELIMINAR DATOS

Todas las referencias siguientes apuntan a `id` en la tabla destino. Se incluyen
también relaciones opcionales `SET NULL`: los hijos se borran antes que los padres,
sin depender de cascadas.

| Tabla | Claves foráneas (columna → tabla) |
| --- | --- |
| `activity_scores` | `activity_id → course_activities`, `student_id → students`, `competency_id → competencies`, `period_id → periods`, `subject_id → subjects` |
| `alerts` | `student_id → students`, `resolved_by → users` |
| `attendances` | `student_id → students`, `section_id → sections`, `user_id → users` |
| `course_activities` | `course_offering_id → course_offerings`, `period_id → periods`, `activity_template_id → activity_templates`, `created_by → users` |
| `course_offerings` | `section_id → sections`, `subject_id → subjects` |
| `final_grades` | `student_id → students`, `subject_id → subjects`, `academic_year_id → academic_years` |
| `grade_review_actions` | `period_id → periods`, `subject_id → subjects`, `section_id → sections`, `performed_by → users` |
| `grade_subjects` | `grade_id → grades`, `subject_id → subjects` |
| `grades` | Sin FK. |
| `legacy_activities_unresolved` | Archivo académico de migración; payload sin FK física. |
| `observations` | `student_id → students`, `user_id → users`, `section_id → sections`, `subject_id → subjects`, `period_id → periods` |
| `period_grades` | `student_id → students`, `subject_id → subjects`, `period_id → periods`, `approved_by → users` |
| `promotion_decisions` | `student_enrollment_id → student_enrollments`, `destination_section_id → sections`, `decided_by → users` |
| `sections` | `grade_id → grades`, `academic_year_id → academic_years` |
| `student_enrollments` | `student_id → students`, `section_id → sections`, `created_by → users` |
| `student_promotions` | `student_id → students`, `academic_year_id → academic_years` |
| `students` | `section_id → sections`, `academic_year_id → academic_years` |
| `subjects` | Sin FK. |
| `teacher_assignments` | `teacher_id → users`, `course_offering_id → course_offerings`, `assigned_by → users` |

No se encontraron tablas de horarios ni aulas; no se inventaron acciones para ellas.

### REVISAR: decisiones tomadas

- **Competencias:** el frontend y los cálculos usan los identificadores fijos
  `1=C1`, `2=C2`, `3=C3`. Se conservan como configuración técnica. Un catálogo
  poblado diferente bloquea el reset para evitar conservar datos no revisados.
- **Plantillas:** se conserva íntegro el catálogo `activity_templates`, con sus
  mismos identificadores y valores. Las seis fijas no pueden renombrarse,
  desactivarse ni eliminarse mediante el modelo/API. La UI las identifica como
  protegidas. Solo se limpian las instancias `course_activities` y sus notas;
  los cursos nuevos reutilizan las plantillas existentes. Los eventos del modelo
  no protegen frente a SQL manual externo a la aplicación.
- **Auditoría:** se conserva todo el historial, que puede contener datos históricos
  en JSON. `affected_table/record_id` son referencias históricas, no FKs vivas:
  pueden describir un registro eliminado. Esto no es una herramienta de borrado
  de datos personales ni borra respaldos/archivos externos.
- **Colas:** conservarlas no autoriza ejecutar tareas antiguas. Detener workers y
  revisar trabajos fallidos antes de reintentarlos después del reset. No hay
  trabajos pendientes en el esquema local inspeccionado.
- **Vistas:** `activities` combina actividades/cursos/secciones/plantillas;
  `teacher_sections` combina asignaciones/cursos/secciones. Se conservan sus
  definiciones y quedan vacías al limpiar las tablas base.

### PK, índices y cardinalidades

Todas las tablas poseen PK `id`, excepto `cache`/`cache_locks` (`key`) y
`password_reset_tokens` (`email`). `sessions.id` y `job_batches.id` son cadenas;
las demás PK `id` son numéricas. No se reinician contadores/autoincrementos.

Se revisaron los índices de FK y los índices técnicos de sesión/cola/expiración.
Los siguientes índices únicos describen las relaciones funcionales principales:

- `grade_subjects(grade_id, subject_id)`: grados ↔ materias, muchos a muchos.
- `course_offerings(section_id, subject_id)`: sección ↔ materia, oferta única.
- `teacher_assignments(teacher_id, course_offering_id)`: docente ↔ curso.
- `student_enrollments(student_id, section_id)`: estudiante ↔ sección/matrícula.
- `promotion_decisions(student_enrollment_id)`: matrícula → decisión, cero o una.
- `course_activities(course_offering_id, period_id, activity_template_id)`:
  plantilla por curso/período; las actividades personalizadas pueden no tener plantilla.
- `activity_scores(activity_id, student_id, competency_id, period_id, subject_id)`.
- `period_grades(student_id, subject_id, period_id)`.
- `final_grades(student_id, subject_id, academic_year_id)`.
- `student_promotions(student_id, academic_year_id)`.
- `attendances(student_id, date)`.
- Otros únicos: `users.email`, `users.google_id`, `subjects.name`, `subjects.code`,
  `students.enrollment_no`, `activity_templates.name`,
  `legacy_activities_unresolved.legacy_activity_id`, `personal_access_tokens.token`
  y `failed_jobs.uuid`.

Las demás FK representan relaciones uno-a-muchos (con participación opcional
cuando son anulables). No se modifica ningún índice ni constraint.

## Orden y garantías

Se calcula un orden topológico a partir de las FK consultadas en cada ejecución.
Para el esquema inspeccionado, las capas resultantes son:

1. Calificaciones por actividad, alertas, asistencias, notas finales, revisiones,
   relaciones grado-materia, archivo de actividades, observaciones, notas por
   período, decisiones/promociones y asignaciones docentes.
2. Actividades de cursos y matrículas.
3. Cursos y estudiantes.
4. Secciones y materias.
5. Grados.

Este orden no está codificado como una lista de DELETE: se deriva del grafo.
Los ciclos, tablas/vistas desconocidas, dependencias de tablas protegidas hacia
tablas a limpiar, FK externas, triggers sin revisar y motores no transaccionales
bloquean el proceso. Las FK deben estar habilitadas y nunca se deshabilitan.

La ejecución utiliza una única transacción. En MySQL: bloqueo nombrado para
evitar dos resets simultáneos, aislamiento SERIALIZABLE y lecturas con bloqueo de
todas las PK/rangos antes de borrar. En SQLite se utilizan sus transacciones y FK.
No hay reintentos automáticos de borrado. PostgreSQL/Supabase y prefijos de tablas
se rechazan de forma explícita hasta implementar y probar su inspección/bloqueo.

Antes de borrar se verifican autorización vigente, esquema, vista previa y
referencias huérfanas. Después se comprueba que todas las tablas objetivo estén
vacías y que el hash de **cada fila** protegida siga igual; se vuelve a validar
cada FK (incluidas compuestas/anulables). Finalmente se escribe
`SYSTEM_DATA_RESET` en `audit_logs`, con actor, fecha, IP, resultado y conteos.
Si cualquier paso falla, también si falla la auditoría, se revierte la transacción.
El middleware administrativo omite su auditoría genérica en esta ruta para no
registrar el token de confirmación ni crear un evento fuera de la transacción.

La operación debe programarse sin actividad de usuarios, migraciones, tareas
externas ni workers. Los bloqueos protegen la transacción, no impiden nuevas
operaciones válidas después de su commit. Evitar despliegues/DDL concurrentes.
La inspección requiere acceso a los metadatos completos del esquema.

## API y uso

- `GET /api/admin/system/reset-data/preview`: solo administrador activo; devuelve
  `delete`, `preserve`, conteos reales, orden, vencimiento y `preview_token`.
- `POST /api/admin/system/reset-data`: mismo acceso Sanctum/`role:admin` existente.
  Cuerpo: `confirmation` exactamente `RESTABLECER DATOS` y `preview_token`.
  No acepta listas de tablas del cliente.

El token está autenticado/cifrado, ligado al administrador, esquema, contenido y
conteos académicos y último reset. Caduca a los cinco minutos. Un cambio de datos,
token manipulado, otro actor o repetición del reset exige otra previsualización.
No expone filas, hashes de contraseñas ni credenciales. Respuestas `no-store`.

UI: advertencia de alcance y respaldo → resumen real → reconocimiento de riesgo
→ primera confirmación → escritura exacta → ejecución → resultado por entidad.
El diálogo usa foco modal nativo, Escape/cancelación antes del envío y botones
bloqueados mientras se procesa. Los datos almacenados en caché del cliente se
invalidan tras el éxito. Un fallo de red no se reintenta: revisar auditoría antes
de repetir, porque una respuesta perdida no prueba que no hubo commit.

**Backup:** la exportación JSON existente no es un respaldo SQL completo ni tiene
un procedimiento de restauración implementado. Preparar y verificar un respaldo
con las herramientas del servidor antes del uso real. No se crea un backup falso.

## Verificación

Pruebas habituales (SQLite en memoria configurado por `phpunit.xml`):

```powershell
php artisan test --compact
npm run build
```

Prueba MySQL opcional, fuera de la suite habitual:

```powershell
$env:RUN_MYSQL_RESET_TESTS = '1'
try {
    php artisan test --compact tests/Integration/MySqlSystemResetTest.php
} finally {
    Remove-Item Env:RUN_MYSQL_RESET_TESTS
}
```

Requiere permiso para crear una base temporal. El test crea un nombre aleatorio
`cuaderno_reset_test_<16 hex>`, migra **solo esa base**, prueba exclusión mutua,
rollback y commit, y elimina exclusivamente la base que creó. Nunca ejecuta
`migrate:fresh` ni reset sobre la base configurada de la aplicación.

Las pruebas cubren datos relacionados en todas las tablas académicas, varios
años, preservación completa de autenticación/configuración, login y APIs vacías,
generación posterior de seis actividades, permisos, token/confirmación exactos,
caducidad/cambios de contenido, tablas/triggers desconocidos, ciclos, trabajos
pendientes, fallo intermedio, fallo de auditoría y alteración de información
protegida. La ejecución real queda a decisión explícita del administrador.

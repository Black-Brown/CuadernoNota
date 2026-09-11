# Documentación funcional de la API

Esta guía describe la API REST de **Cuaderno Nota** según el código disponible en el repositorio. Incluye arquitectura, autenticación, permisos, entidades, reglas de negocio, endpoints, ejemplos de consumo, configuración y pruebas.

> Estado de la documentación: 7 de septiembre de 2026. La fuente de verdad para las rutas sigue siendo `api/routes/api.php`.

## 1. Resumen

La API está construida con Laravel y sirve a un frontend React desplegable en un dominio independiente. La comunicación se realiza mediante JSON y tokens Bearer de Laravel Sanctum.

Funcionalidades principales:

- Autenticación local y con Google Workspace.
- Administración de usuarios, años escolares, períodos, grados, secciones, materias y actividades base.
- Registro, importación, edición, matrícula, desactivación y colocación de estudiantes.
- Asignación masiva de docentes a ofertas académicas.
- Gestión docente de actividades, calificaciones, recuperaciones, asistencia y observaciones.
- Identificación de estudiantes en riesgo.
- Revisión administrativa de calificaciones.
- Promoción escolar individual y masiva.
- Reportes, auditoría, respaldos y restablecimiento controlado de datos.

## 2. Tecnologías y arquitectura

### Tecnologías

- PHP 8.4.
- Laravel 13.
- Laravel Sanctum para tokens personales.
- Eloquent ORM.
- PostgreSQL como base de datos objetivo.
- Supabase PostgreSQL en producción.
- SQLite en memoria para pruebas automatizadas.
- Docker con Nginx, PHP-FPM, Supervisor y OPcache en producción.

### Organización inspirada en DDD

```text
api/app/
├── Domain/          Entidades, servicios de dominio y contratos
├── Application/     Casos de uso de la aplicación
└── Infrastructure/  HTTP, modelos Eloquent y repositorios
```

Flujo habitual:

```text
Frontend React
     │ HTTPS + JSON + Bearer token
     ▼
Controlador Laravel
     ▼
Caso de uso de Application
     ▼
Contrato/repositorio + servicio de dominio
     ▼
Eloquent / PostgreSQL
```

Esta separación permite mantener las reglas académicas fuera de los componentes visuales y reducir el acoplamiento con Laravel.

## 3. URL base y protocolo

### Desarrollo local

```text
http://localhost:8000/api
```

### Producción

```text
https://<servicio-api>.onrender.com/api
```

El frontend debe apuntar a esa URL mediante `VITE_API_URL`.

### Encabezados

Para solicitudes JSON autenticadas:

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer <TOKEN>
```

Las cargas CSV usan `multipart/form-data`, por lo que el navegador o cliente HTTP debe generar automáticamente el `Content-Type` con su boundary.

### Convenciones

- Los identificadores son enteros.
- Las fechas enviadas a la API usan `AAAA-MM-DD`.
- Los campos booleanos se representan con `true` y `false`.
- Los listados paginados usan la estructura de paginación de Laravel y contienen `data`, `current_page`, `last_page`, `per_page` y `total`.
- La API no impone un sobre único a todas las respuestas: algunos endpoints devuelven el recurso directamente y otros devuelven un objeto con `message`, resumen y datos.

### Estados HTTP frecuentes

| Estado | Significado en esta API |
|---|---|
| `200` | Consulta o actualización correcta. |
| `201` | Recurso o importación creada. |
| `401` | Token ausente, inválido o credenciales incorrectas. |
| `403` | El usuario no tiene el rol, la asignación o la propiedad requerida. |
| `404` | Recurso inexistente. |
| `409` | Conflicto de estado o integridad. |
| `422` | Error de validación o regla académica incumplida. |
| `423` | Workspace bloqueado por período o revisión de calificaciones. |
| `503` | Servicio externo, como Google OAuth, sin configurar. |

Ejemplo típico de validación:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["El correo ya está registrado."]
  }
}
```

## 4. Autenticación, roles y permisos

### Roles disponibles

| Rol | Acceso actual |
|---|---|
| `admin` | Todo el prefijo `/admin`. |
| `teacher` | Todo el prefijo `/docente`, limitado a sus asignaciones activas. |
| `coordinator` | Puede existir como dato heredado, pero el portal del coordinador no está implementado para la beta. No se crean nuevos coordinadores desde el flujo normal. |

Todos los endpoints protegidos pasan por `auth:sanctum`. Los grupos administrativos y docentes también pasan por su middleware de rol. Las cuentas inactivas no pueden iniciar sesión y, al desactivar una cuenta, se eliminan sus tokens vigentes.

### Autenticación local

#### Iniciar sesión

`POST /auth/login`

```json
{
  "email": "docente@colegio.edu",
  "password": "contraseña"
}
```

Respuesta correcta:

```json
{
  "token": "1|token-sanctum",
  "user": {
    "id": 2,
    "name": "Profesor de ejemplo",
    "email": "docente@colegio.edu",
    "role": "teacher",
    "avatar": null
  }
}
```

- Credenciales incorrectas: `401`.
- Cuenta inactiva: `403`.

#### Usuario autenticado

`GET /auth/me`

Devuelve la identidad asociada al token actual.

#### Cerrar sesión

`POST /auth/logout`

Elimina el token Sanctum usado por la solicitud.

#### Solicitar recuperación de contraseña

`POST /auth/forgot-password`

```json
{
  "email": "usuario@colegio.edu"
}
```

El endpoint devuelve un mensaje genérico para no revelar si el correo existe. Actualmente no envía el correo ni completa el restablecimiento; es un punto pendiente de integración.

### Google Workspace

| Método | Ruta | Función |
|---|---|---|
| `GET` | `/auth/google/redirect` | Inicia OAuth y crea un `state` temporal. |
| `GET` | `/auth/google/callback` | Valida Google, dominio, correo y usuario local; redirige al frontend con un código de un solo uso. |
| `POST` | `/auth/google/exchange` | Intercambia el código temporal por un token Sanctum. |

El cuerpo del intercambio es:

```json
{
  "code": "codigo-de-un-solo-uso"
}
```

Reglas de seguridad:

- El `state` de OAuth dura 10 minutos.
- El código de intercambio dura 2 minutos y solo se usa una vez.
- El correo de Google debe estar verificado.
- El dominio debe coincidir exactamente con `GOOGLE_WORKSPACE_DOMAIN`.
- La cuenta debe existir previamente en Cuaderno Nota y estar activa.
- El callback registrado en Google debe apuntar a la API, no al frontend.

## 5. Modelo académico

### Entidades principales

| Entidad | Responsabilidad |
|---|---|
| `users` | Administradores y docentes. |
| `academic_years` | Ciclos escolares con fecha inicial, final y estado activo. |
| `periods` | Cuatro períodos por año, con calendario y estado académico. |
| `grades` | Grados ordenados por nivel y secuencia. |
| `subjects` | Materias asociadas a uno o varios grados. |
| `sections` | División de un grado dentro de un año y tanda. |
| `course_offerings` | Combinación exacta de sección y materia. |
| `teacher_assignments` | Relación entre docente y oferta académica. |
| `students` | Expediente actual del estudiante. |
| `student_enrollments` | Historial de matrículas por sección. |
| `activity_templates` | Tipos de actividad base definidos por administración. |
| `activities` | Actividades habilitadas o creadas en un workspace docente. |
| `competencies` | Competencias C1, C2 y C3. |
| `activity_scores` | Nota de estudiante por actividad y competencia. |
| `period_grades` | Resumen del período y su estado de revisión. |
| `final_grades` | Calificación final y recuperaciones finales/especiales. |
| `attendances` | Asistencia por estudiante, materia, sección, docente y fecha. |
| `observations` | Observaciones generales o ligadas a un workspace. |
| `alerts` | Alertas académicas y de asistencia. |
| `grade_review_actions` | Historial de aprobación, rechazo y reapertura. |
| `promotion_decisions` | Decisión administrativa de promoción por matrícula. |
| `audit_logs` | Registro de acciones administrativas. |

### Conceptos importantes

- Una **sección** clasifica estudiantes: por ejemplo, `1RO SECUNDARIA`, sección `A`, tanda `Matutina`, año `2026-2027`.
- Una **oferta académica** agrega una materia a esa sección.
- Una **asignación docente** vincula un profesor con una oferta académica exacta.
- Un **workspace docente** siempre se identifica por sección, materia y período.
- El estudiante conserva su historial en `student_enrollments`; `students.section_id` representa únicamente su ubicación actual.
- Después de una promoción, el estudiante queda activo pero sin sección hasta ser colocado en una sección del nuevo año.

## 6. Reglas transversales del negocio

### Calendario y períodos

- Solo puede existir un año escolar marcado como activo.
- Cada año acepta períodos numerados del 1 al 4.
- Las fechas de un período deben estar dentro del año escolar y no pueden solaparse con otro período.
- El estado efectivo del período combina el estado guardado con la fecha actual. Un período futuro no se considera abierto aunque tenga `status = open`.
- Las escrituras docentes se bloquean fuera de las fechas del período, cuando está cerrado o cuando las notas del workspace están en revisión u oficiales.

### Calificaciones

Las actividades se califican bajo tres competencias:

- `C1`: comunicativa.
- `C2`: pensamiento lógico, creativo y crítico.
- `C3`: científica y tecnológica.

```text
Competencia = promedio de sus actividades calificadas
Nota del período = promedio de C1, C2 y C3
Nota final = promedio de las notas efectivas de los cuatro períodos
```

Solo se incluyen actividades que tengan una nota registrada. La recuperación pedagógica (`rp`) sustituye la nota ordinaria del período en el cálculo final. Los estados de las notas de período son:

```text
draft → in_review → official
          │              │
          └─ rechazo ────┴─ reapertura → draft
```

Las notas de una actividad desactivada se conservan como historial, pero no se muestran en el registro docente ni participan en C1, C2, C3, la nota del período o los indicadores de riesgo. Al reactivar la actividad, la API vuelve a incluirlas y recalcula los resúmenes afectados.

### Promoción

- La promoción solo se habilita cuando los cuatro períodos del año están cerrados.
- La elegibilidad comprueba las materias exactas asignadas al grado, no solo la cantidad de notas.
- Todas las materias requeridas deben tener nota final efectiva igual o superior a 70 para promoción automática.
- Una decisión contraria al criterio automático requiere justificación.
- `promoted` exige el grado activo inmediatamente siguiente del mismo nivel.
- `not_promoted` mantiene como destino el grado actual.
- La decisión cierra la matrícula actual y deja al estudiante pendiente de colocación; no lo introduce automáticamente en una sección.

### Asistencia y riesgo

- La asistencia queda aislada por estudiante, sección, materia, docente y fecha.
- Solo el docente asignado a la combinación sección/materia puede consultarla o modificarla.
- `present` y `late` cuentan como asistencia; `absent` cuenta como ausencia y `excused` como justificada.
- Los cálculos de riesgo se limitan al curso y período solicitados.
- Un estudiante puede aparecer en riesgo por promedio inferior a 70, asistencia inferior a 80% o tres ausencias consecutivas.
- Las alertas históricas sin materia no se mezclan con el workspace actual.

### Conservación del historial

- Desactivar un usuario, estudiante, materia, actividad base o asignación conserva el historial.
- Un estudiante solo puede eliminarse físicamente si no tiene sección actual ni relaciones académicas históricas.
- Las materias y actividades base se desactivan mediante sus rutas `DELETE`; no se borran los datos académicos asociados.

## 7. Endpoints públicos y de sesión

| Método | Endpoint | Descripción |
|---|---|---|
| `POST` | `/auth/login` | Autenticación por correo y contraseña. |
| `POST` | `/auth/forgot-password` | Respuesta genérica de recuperación; envío pendiente. |
| `GET` | `/auth/google/redirect` | Inicia autenticación con Google. |
| `GET` | `/auth/google/callback` | Callback OAuth de la API. |
| `POST` | `/auth/google/exchange` | Convierte el código temporal en token. |
| `POST` | `/auth/logout` | Revoca el token actual. Requiere autenticación. |
| `GET` | `/auth/me` | Obtiene el usuario actual. Requiere autenticación. |

## 8. API administrativa

Todos los endpoints de esta sección requieren:

```http
Authorization: Bearer <TOKEN_ADMIN>
```

También son auditados por el middleware administrativo.

### 8.1 Dashboard y mantenimiento

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/admin/dashboard` | Indicadores del año activo: usuarios, docentes, estudiantes y secciones. |
| `GET` | `/admin/system/reset-data/preview` | Calcula el alcance y genera el token de confirmación para un restablecimiento. |
| `POST` | `/admin/system/reset-data` | Ejecuta el restablecimiento validado y transaccional. |

Para el restablecimiento se envían la frase y el token recibidos en la vista previa:

```json
{
  "confirmation": "frase-exacta-indicada-por-la-vista-previa",
  "preview_token": "token-de-vista-previa"
}
```

La operación conserva configuración institucional y elimina datos académicos operativos según una lista explícita. Consulte [Restablecimiento de datos](./SYSTEM_DATA_RESET.md) antes de usarla.

### 8.2 Usuarios

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/admin/users` | Lista usuarios; acepta `role`, `active`, `search` y `per_page`. |
| `POST` | `/admin/users` | Crea un administrador o docente. |
| `PATCH` | `/admin/users/{user}` | Edita identidad, rol, contraseña o estado. |
| `DELETE` | `/admin/users/{user}` | Desactiva el usuario y revoca sus tokens. |

Cuerpo de creación:

```json
{
  "name": "María Martínez",
  "email": "maria@colegio.edu",
  "password": "mínimo-8-caracteres",
  "role": "teacher",
  "active": true
}
```

`password` y `active` son opcionales. En edición todos los campos son opcionales. El administrador autenticado no puede desactivarse a sí mismo ni retirarse su propio rol administrativo.

### 8.3 Estudiantes

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/admin/students` | Listado general paginado y filtrable. |
| `GET` | `/admin/students/workspaces` | Resumen y tarjetas de estudiantes agrupados por sección. |
| `POST` | `/admin/students` | Crea un expediente, con matrícula inicial opcional. |
| `GET` | `/admin/students/{student}` | Perfil, resumen e historial académico del estudiante. |
| `PATCH` | `/admin/students/{student}` | Edita nombre, apellido o número de matrícula. |
| `DELETE` | `/admin/students/{student}` | Elimina únicamente un expediente sin historial. |
| `POST` | `/admin/students/{student}/enrollments` | Inscribe o cambia al estudiante de sección. |
| `POST` | `/admin/students/{student}/deactivate` | Da de baja conservando historial. |
| `POST` | `/admin/students/{student}/reactivate` | Reactiva y deja pendiente de sección. |

Filtros de `GET /admin/students`:

- `active`: estado del expediente.
- `pending`: estudiantes sin matrícula/sección activa.
- `section_id`, `academic_year_id` y `grade_id`.
- `search`: nombre, apellido o matrícula.
- `per_page`: entre 1 y 1000; por defecto 25.

Creación pendiente, sin sección:

```json
{
  "name": "Ana",
  "last_name": "Pérez",
  "enrollment_no": "2026-0001"
}
```

Creación con matrícula inicial:

```json
{
  "name": "Ana",
  "last_name": "Pérez",
  "enrollment_no": "2026-0001",
  "section_id": 12,
  "enrolled_at": "2026-08-31"
}
```

`section_id` y `enrolled_at` deben aparecer juntos. La fecha debe pertenecer al año escolar de la sección.

Cambio o asignación de sección:

```json
{
  "section_id": 18,
  "enrolled_at": "2027-08-30"
}
```

La API cierra la matrícula activa anterior, crea o reactiva la nueva y actualiza la ubicación actual del estudiante.

Eliminación definitiva:

```json
{
  "confirmation": "2026-0001"
}
```

`confirmation` debe coincidir exactamente con la matrícula. Si existe cualquier matrícula, nota, asistencia, observación, alerta, promoción u otro historial, la API rechaza la eliminación y debe usarse la baja.

### 8.4 Importación y reemplazo masivo

| Método | Endpoint | Descripción |
|---|---|---|
| `POST` | `/admin/students/import/preview` | Analiza un CSV sin escribir en base de datos. |
| `POST` | `/admin/students/import` | Importa las filas válidas. |
| `POST` | `/admin/students/bulk-replace/preview` | Previsualiza cambios de texto en matrículas. |
| `POST` | `/admin/students/bulk-replace` | Aplica el reemplazo de forma atómica. |

El archivo CSV se envía en el campo multipart `file`, con extensión `.csv` y máximo 5 MB. Encabezados esperados:

```csv
MATRICULA,NOMBRES,APELLIDOS,ANO_ESCOLAR,GRADO,SECCION,TANDA,FECHA_INSCRIPCION,NOMBRE_TUTOR
2026-0001,Ana,Pérez,2026-2027,1RO SECUNDARIA,A,Matutina,2026-08-31,
```

La vista previa informa errores por fila, duplicados y destino académico. La sección debe coincidir exactamente con año, grado, sección y tanda. La guía completa está en [Importación de estudiantes](./importacion-estudiantes.md).

Ejemplo de reemplazo masivo:

```json
{
  "student_ids": [10, 11, 12],
  "search": "2025-",
  "replace": "2026-"
}
```

Se aceptan hasta 1000 estudiantes. Antes de escribir se validan matrículas vacías, duplicadas, demasiado largas y conflictos con estudiantes no seleccionados.

### 8.5 Colocación de estudiantes pendientes

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/admin/student-placements/pending` | Lista estudiantes activos sin sección y propone el grado de destino. |
| `POST` | `/admin/student-placements` | Coloca varios estudiantes en una sección. |

Cuerpo:

```json
{
  "student_ids": [10, 11, 12],
  "section_id": 18,
  "enrolled_at": "2027-08-30"
}
```

Máximo 200 estudiantes por operación. Todos deben estar activos y sin matrícula activa. Para estudiantes promovidos, la sección debe pertenecer al grado decidido y al año escolar inmediatamente siguiente.

### 8.6 Catálogo académico

#### Años escolares

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/admin/academic-years` | Lista años con sus períodos. |
| `POST` | `/admin/academic-years` | Crea un año. |
| `PATCH` | `/admin/academic-years/{academicYear}` | Edita fechas, nombre o estado. |
| `DELETE` | `/admin/academic-years/{academicYear}` | Elimina si no tiene períodos ni secciones. |

```json
{
  "name": "2026-2027",
  "start_date": "2026-08-31",
  "end_date": "2027-06-30",
  "active": true
}
```

Activar uno desactiva los demás.

#### Períodos

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/admin/academic-years/{academicYear}/periods` | Lista períodos del año. |
| `POST` | `/admin/academic-years/{academicYear}/periods` | Crea un período. |
| `PATCH` | `/admin/periods/{period}` | Edita calendario o estado. |
| `DELETE` | `/admin/periods/{period}` | Elimina si no tiene calificaciones. |
| `GET` | `/admin/periods/{period}/activity-summary` | Resume actividades y estados antes de cerrar o modificar. |

```json
{
  "number": 1,
  "name": "Primer Período",
  "months": "SEP-NOV",
  "start_date": "2026-08-31",
  "end_date": "2026-11-30",
  "status": "open"
}
```

Estados configurables: `open`, `in_review` y `closed`. La apertura real también depende del calendario.

#### Grados

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/admin/grades` | Lista; acepta filtro `status`. |
| `POST` | `/admin/grades` | Crea un grado. |
| `PATCH` | `/admin/grades/{grade}` | Edita un grado. |
| `GET` | `/admin/grades/{grade}/deletion-check` | Indica dependencias y si puede eliminarse. |
| `DELETE` | `/admin/grades/{grade}` | Elimina si no tiene secciones ni materias. |
| `PATCH` | `/admin/grades/{grade}/deactivate` | Desactiva conservando relaciones. |
| `PATCH` | `/admin/grades/{grade}/reactivate` | Reactiva. |

```json
{
  "name": "1RO SECUNDARIA",
  "level": "SECUNDARIA",
  "sort_order": 7
}
```

`sort_order` determina la secuencia usada para promoción.

#### Secciones

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/admin/sections` | Lista secciones y relaciones. |
| `POST` | `/admin/sections` | Crea una sección. |
| `PATCH` | `/admin/sections/{section}` | Edita una sección. |
| `DELETE` | `/admin/sections/{section}` | Elimina cuando no tiene dependencias académicas. |

```json
{
  "grade_id": 7,
  "academic_year_id": 2,
  "name": "A",
  "shift": "Matutina"
}
```

`period_id` puede enviarse como contexto de bloqueo al editar. No pueden repetirse año, grado, nombre y tanda. Al crear una sección se generan ofertas para las materias asociadas al grado.

#### Materias

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/admin/subjects` | Lista materias con grados asociados. |
| `POST` | `/admin/subjects` | Crea y vincula una materia a grados. |
| `PATCH` | `/admin/subjects/{subject}` | Edita materia y sus grados. |
| `DELETE` | `/admin/subjects/{subject}` | Desactiva materia y ofertas; conserva historial. |

```json
{
  "name": "Ciencias Sociales",
  "code": "SOC",
  "active": true,
  "grade_ids": [7, 8, 9]
}
```

Al cambiar `grade_ids`, se sincronizan ofertas académicas sin duplicar combinaciones.

#### Plantillas de actividades

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/admin/activity-templates` | Lista actividades base. |
| `POST` | `/admin/activity-templates` | Crea y propaga una actividad base. |
| `PATCH` | `/admin/activity-templates/{activityTemplate}` | Edita nombre, icono o estado. |
| `DELETE` | `/admin/activity-templates/{activityTemplate}` | Desactiva sin borrar historial. |

```json
{
  "name": "Proyecto",
  "icon": "project",
  "active": true
}
```

El administrador define las actividades base. Cada profesor puede activarlas o desactivarlas según la materia, pero no eliminar su definición institucional.

### 8.7 Asignaciones docentes

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/admin/teacher-assignments/options` | Devuelve docentes y ofertas disponibles para el selector. |
| `GET` | `/admin/teacher-assignments` | Lista asignaciones con docente, materia, sección y año. |
| `POST` | `/admin/teachers` | Crea directamente un usuario docente. |
| `POST` | `/admin/teacher-assignments` | Asigna una o varias ofertas a un docente. |
| `PATCH` | `/admin/teacher-assignments/{teacherAssignment}` | Cambia oferta o estado. |
| `DELETE` | `/admin/teacher-assignments/{teacherAssignment}` | Elimina la relación de asignación. |

Asignación masiva:

```json
{
  "teacher_id": 4,
  "course_offering_ids": [21, 22, 28]
}
```

También se admite `course_offering_id` para compatibilidad con la asignación individual. El docente y todas las ofertas deben estar activos; se aceptan hasta 100 ofertas y no se crean duplicados.

### 8.8 Revisión de calificaciones

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/admin/grade-reviews` | Lista workspaces agrupados; `status` es opcional y por defecto `in_review`. |
| `GET` | `/admin/grade-reviews/{sectionId}/{subjectId}/{periodId}` | Detalle de calificaciones del workspace. |
| `POST` | `/admin/grade-reviews/decision` | Aprueba, rechaza o reabre el conjunto. |

```json
{
  "section_id": 12,
  "subject_id": 5,
  "period_id": 9,
  "action": "approved",
  "comment": null
}
```

Acciones:

- `approved`: `in_review` → `official`.
- `rejected`: `in_review` → `draft`.
- `reopened`: `official` → `draft`.

`comment` es obligatorio al rechazar o reabrir. Cada acción queda registrada en `grade_review_actions`.

### 8.9 Promoción escolar

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/admin/promotions/candidates` | Candidatos y criterio automático por curso. |
| `POST` | `/admin/promotions/{studentEnrollment}/decision` | Registra una decisión individual. |
| `POST` | `/admin/promotions/bulk-decision` | Aplica la misma decisión a estudiantes de una sección. |

Consulta:

```text
/admin/promotions/candidates?academic_year_id=2&section_id=12
```

Decisión individual:

```json
{
  "status": "promoted",
  "target_grade_id": 8,
  "justification": null
}
```

Decisión masiva:

```json
{
  "enrollment_ids": [101, 102, 103],
  "section_id": 12,
  "status": "promoted",
  "target_grade_id": 8,
  "justification": null
}
```

Máximo 200 matrículas, todas de la sección indicada. La operación es transaccional.

### 8.10 Reportes, auditoría y respaldos

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/admin/reports/academic` | Reporte de notas oficiales del año solicitado o activo. |
| `GET` | `/admin/reports/attendance` | Reporte de asistencia por grado, sección y materia. |
| `GET` | `/admin/audit-logs` | Historial administrativo filtrable. |
| `POST` | `/admin/backups` | Descarga un respaldo JSON seguro. |

Los reportes aceptan `academic_year_id`. Auditoría acepta `user_id`, `action` y `per_page`.

El respaldo excluye contraseñas, tokens, identificadores de Google y otros secretos aunque consulte datos directamente desde la base. Su archivo sigue siendo información sensible y debe almacenarse con controles de acceso.

## 9. API docente

Todos los endpoints requieren un token de usuario `teacher`. Además del rol, la API comprueba que el profesor tenga una asignación activa para la combinación exacta de sección y materia.

### 9.1 Cursos, dashboard y períodos

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/docente/courses` | Ofertas activas asignadas al profesor y cantidad real de estudiantes por sección. |
| `GET` | `/docente/dashboard` | Indicadores agregados de sus cursos; acepta `period_id`. |
| `GET` | `/docente/dashboard/{sectionId}/{subjectId}` | Indicadores de un curso exacto; acepta `period_id`. |
| `GET` | `/docente/current-period` | Período efectivo según año activo, fechas y estado. |
| `GET` | `/docente/periods` | Lista de períodos con estado efectivo. |

Los indicadores no mezclan secciones, otros profesores ni años históricos. Si no existen registros, los promedios de notas o asistencia se devuelven como `null`; el frontend debe mostrar “—” o “Sin registros”.

### 9.2 Actividades y libro de calificaciones

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/docente/grades/summary/{sectionId}/{subjectId}` | Resumen de los cuatro períodos y nota final del curso. |
| `GET` | `/docente/activities/{subjectId}` | Actividades del workspace; exige `section_id` y acepta `period_id`. |
| `POST` | `/docente/activities` | Crea una actividad propia. |
| `PATCH` | `/docente/activities/{id}` | Renombra o cambia el estado de una actividad autorizada. |
| `GET` | `/docente/grades/activity/{activityId}/{periodId}` | Estudiantes y notas de una actividad. |
| `POST` | `/docente/grades/activity-score` | Crea, actualiza o limpia una nota de actividad. |
| `GET` | `/docente/grades/period/{subjectId}/{periodId}` | Libro del período; exige `section_id`. |
| `POST` | `/docente/grades/submit` | Envía todas las notas del workspace a revisión. |
| `POST` | `/docente/grades/recovery` | Registra RP, recuperación final o especial. |

Crear actividad:

```json
{
  "name": "Exposición sobre la comunidad",
  "subject_id": 5,
  "section_id": 12,
  "period_id": 9,
  "description": "Trabajo en equipos",
  "type": "presentation",
  "status": "active",
  "due_date": "2026-10-15",
  "weight": 20,
  "icon": "presentation"
}
```

Campos opcionales: `description`, `type`, `status`, `due_date`, `weight` e `icon`. Estados admitidos: `active`, `draft` e `inactive`.

Registrar una nota:

```json
{
  "activity_id": 44,
  "student_id": 167,
  "competency_id": 1,
  "period_id": 9,
  "subject_id": 5,
  "score": 92
}
```

`score` acepta valores de 0 a 100 o `null` para dejar la actividad sin calificar. La API comprueba que actividad, estudiante, sección, materia y período formen el mismo workspace.

No se pueden consultar ni registrar calificaciones de una actividad inactiva. Sus filas de notas se conservan y el resumen del período se recalcula automáticamente cuando cambia el estado de la actividad.

Enviar a revisión:

```json
{
  "section_id": 12,
  "subject_id": 5,
  "period_id": 9
}
```

Recuperación:

```json
{
  "type": "rp",
  "student_id": 167,
  "subject_id": 5,
  "academic_year_id": 2,
  "period_id": 9,
  "score": 75
}
```

Tipos:

- `rp`: recuperación pedagógica de un período; `period_id` es obligatorio.
- `final`: recuperación de la nota final.
- `special`: recuperación especial.

Las recuperaciones validan elegibilidad y estado académico. Las actualizaciones relacionadas se ejecutan en transacción para no dejar una nota parcialmente modificada si falla el cálculo final.

### 9.3 Asistencia

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/docente/attendance/{sectionId}/{subjectId}/{date}` | Lista estudiantes y asistencia de una materia en una fecha. |
| `POST` | `/docente/attendance` | Registra o actualiza un estado. |
| `PATCH` | `/docente/attendance/{id}/excuse` | Convierte una ausencia propia en justificada. |

Ejemplo de consulta:

```text
/docente/attendance/12/5/2026-09-07
```

Registro individual:

```json
{
  "student_id": 167,
  "subject_id": 5,
  "date": "2026-09-07",
  "status": "late"
}
```

Estados externos:

| Valor API | Código interno | Significado |
|---|---|---|
| `present` | `P` | Presente. |
| `late` | `T` | Tardanza; cuenta como asistencia. |
| `absent` | `A` | Ausente. |
| `excused` | `E` | Ausencia justificada. |

La fecha debe caer dentro del período activo. Un docente no puede consultar una sección/materia ajena, sobrescribir la asistencia creada por otro docente ni justificar sus registros.

### 9.4 Observaciones

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/docente/observations/course/{sectionId}/{subjectId}` | Observaciones del curso; acepta `period_id`. |
| `GET` | `/docente/observations/course/{sectionId}/{subjectId}/students/{studentId}` | Historial del estudiante dentro del workspace. |
| `GET` | `/docente/observations/{studentId}` | Consulta general heredada para un estudiante autorizado. |
| `POST` | `/docente/observations` | Registra una observación general o de workspace. |
| `PATCH` | `/docente/observations/{id}` | Edita una observación propia. |
| `DELETE` | `/docente/observations/{id}` | Elimina una observación propia. |

Observación ligada a workspace:

```json
{
  "student_id": 167,
  "section_id": 12,
  "subject_id": 5,
  "period_id": 9,
  "date": "2026-09-07",
  "type": "academic",
  "description": "Mostró una mejora sostenida en las actividades."
}
```

Tipos: `academic`, `disciplinary` e `incident`. `section_id`, `subject_id` y `period_id` se envían juntos o se omiten juntos. Solo el autor puede editar o eliminar. Un período cerrado o unas notas en revisión/oficiales bloquean la escritura del workspace.

### 9.5 Estudiantes en riesgo

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/docente/risk` | Resumen de riesgo en cursos asignados; acepta `period_id`. |
| `GET` | `/docente/risk/{sectionId}/{subjectId}` | Estudiantes en riesgo del workspace; acepta `period_id`. |
| `GET` | `/docente/risk/{sectionId}/{subjectId}/students/{studentId}` | Detalle académico, asistencia y observaciones. |

El detalle combina únicamente información autorizada de la materia y período solicitados. Los niveles altos consideran, entre otros criterios, promedio inferior a 60, asistencia inferior a 70% o ausencias consecutivas.

## 10. Flujos principales

### Configurar un año académico

1. Crear o activar el año escolar.
2. Crear sus cuatro períodos con fechas no solapadas.
3. Crear u ordenar los grados.
4. Crear materias y asociarlas a sus grados.
5. Crear secciones por grado, año y tanda.
6. La API crea las ofertas sección/materia.
7. Crear docentes y asignarles una o varias ofertas.

### Registrar estudiantes

1. Crear expedientes pendientes manualmente o importar el CSV.
2. Consultar `/admin/student-placements/pending`.
3. Seleccionar estudiantes y una sección válida.
4. Crear las matrículas con `/admin/student-placements`.
5. Consultar el workspace por sección o el perfil individual para verificar el historial.

El alta también puede incluir `section_id` y `enrolled_at` cuando el destino ya está decidido.

### Calificar y oficializar un período

1. El docente obtiene sus cursos y el período efectivo.
2. Crea o habilita actividades del workspace.
3. Registra notas por actividad, estudiante y competencia.
4. Revisa el libro de período.
5. Envía sección/materia/período a revisión.
6. El administrador aprueba o rechaza el conjunto.
7. Si se aprueba, las notas quedan oficiales; una reapertura administrativa las devuelve a borrador.

### Promover y colocar estudiantes

1. Cerrar los cuatro períodos del año.
2. Consultar candidatos por año y sección.
3. Registrar decisiones individuales o masivas.
4. Las matrículas actuales se cierran y los estudiantes quedan pendientes.
5. Crear/configurar las secciones del año siguiente.
6. Colocar a cada grupo pendiente en su sección de destino.

## 11. Seguridad e integridad

- Sanctum protege todas las operaciones privadas.
- El rol se valida en middleware antes de entrar al controlador.
- Los docentes están aislados por asignación activa a la oferta exacta.
- Las observaciones solo pueden ser editadas o eliminadas por su autor.
- Las fechas se validan contra el año y período correspondientes.
- Las operaciones académicas críticas usan transacciones y bloqueos de filas.
- Existen restricciones para evitar secciones, ofertas, asignaciones, matrículas y asistencias duplicadas.
- Los parámetros se validan y las consultas usan Eloquent o Query Builder parametrizado.
- Las acciones administrativas quedan en auditoría.
- Los respaldos no exportan credenciales ni identificadores de autenticación.
- CORS debe limitarse a los orígenes reales del frontend.

No deben almacenarse secretos reales en Git. Si una credencial fue compartida o expuesta, debe rotarse en el proveedor correspondiente.

## 12. Variables de entorno

Parta de `api/.env.example`. Variables principales:

```dotenv
APP_NAME="Cuaderno Nota"
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://api.ejemplo.com
FRONTEND_URL=https://app.ejemplo.com

DB_CONNECTION=pgsql
DB_HOST=...
DB_PORT=6543
DB_DATABASE=postgres
DB_USERNAME=...
DB_PASSWORD=...
DB_SSLMODE=require

CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database

CORS_ALLOWED_ORIGINS=https://app.ejemplo.com

GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=https://api.ejemplo.com/api/auth/google/callback
GOOGLE_WORKSPACE_DOMAIN=colegio.edu
```

Notas:

- En Supabase use `DB_CONNECTION=pgsql`, no `mysql`.
- `APP_URL` es la URL pública de Render sin `/api`.
- `FRONTEND_URL` es la URL pública de Vercel.
- `GOOGLE_REDIRECT_URI` apunta al callback de Render y debe coincidir carácter por carácter con Google Cloud.
- `CORS_ALLOWED_ORIGINS` puede contener los orígenes permitidos según el formato definido por la configuración del proyecto.
- En Vercel, `VITE_API_URL` debe ser la URL de Render terminada en `/api`.

## 13. Ejemplos con cURL

### Obtener token

```bash
curl -X POST "http://localhost:8000/api/auth/login" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@colegio.edu","password":"contraseña"}'
```

### Consultar estudiantes

```bash
curl "http://localhost:8000/api/admin/students?active=1&per_page=25" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer TOKEN_ADMIN"
```

### Previsualizar importación

```bash
curl -X POST "http://localhost:8000/api/admin/students/import/preview" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer TOKEN_ADMIN" \
  -F "file=@estudiantes.csv"
```

### Consultar asistencia docente

```bash
curl "http://localhost:8000/api/docente/attendance/12/5/2026-09-07" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer TOKEN_DOCENTE"
```

## 14. Desarrollo y pruebas

### Levantar todo el stack

Desde la raíz:

```bash
docker compose up --build
```

Servicios locales habituales:

- Frontend: `http://localhost:5173`.
- API: `http://localhost:8000/api`.
- PostgreSQL: puerto `5432` dentro de la red de Docker.

### Ejecutar migraciones y seeders

```bash
cd api
php artisan migrate
php artisan db:seed
```

Los seeders son para entornos locales o controlados. Las pruebas automatizadas usan su propia base SQLite y no insertan datos en Supabase.

### Ver todas las rutas reales

```bash
cd api
php artisan route:list --path=api
```

### Pruebas backend

```bash
cd api
php artisan test
```

### Pruebas y compilación frontend

```bash
cd frontend
npm test
npm run build
```

Las áreas de mayor riesgo que deben mantener cobertura son:

- Separación entre cursos de docentes distintos.
- Aislamiento de asistencia por materia y período.
- Bloqueo de períodos cerrados o futuros.
- Propiedad de observaciones.
- Transacciones de recuperaciones.
- Materias exactas requeridas para promoción.
- Colocación de estudiantes promovidos.
- Importación CSV y reemplazo masivo de matrículas.
- Exclusión de secretos en respaldos.

## 15. Despliegue resumido

### API en Render

- Directorio raíz: `api`.
- Entorno: Docker.
- El contenedor inicia Nginx y PHP-FPM mediante Supervisor.
- Nginx escucha el puerto proporcionado por `$PORT`.
- El entrypoint prepara cachés y ejecuta migraciones con `--force`.
- PostgreSQL/Supabase requiere SSL.

### Frontend en Vercel

- Directorio raíz: `frontend`.
- Variable `VITE_API_URL=https://<servicio>.onrender.com/api`.
- La URL de Vercel debe estar incluida en `FRONTEND_URL` y `CORS_ALLOWED_ORIGINS` de Render.
- Si se usa Google, el origen de Vercel se registra como origen JavaScript, pero el URI de redirección es el callback de Render.

## 16. Límites actuales conocidos

- El módulo del coordinador aún no está disponible.
- La solicitud de recuperación de contraseña responde de forma segura, pero no envía correo ni ejecuta el cambio de contraseña.
- No existe todavía un contrato OpenAPI/Swagger generado; este archivo es la referencia funcional legible y `api/routes/api.php` es la referencia ejecutable.
- No se implementa adjunto documental para excusas de asistencia; la justificación cambia el estado del registro.

## 17. Archivos de referencia

- Rutas: `api/routes/api.php`.
- Controladores HTTP: `api/app/Infrastructure/Http/Controllers`.
- Casos de uso: `api/app/Application`.
- Dominio: `api/app/Domain`.
- Modelos Eloquent: `api/app/Infrastructure/Models`.
- Migraciones: `api/database/migrations`.
- Pruebas: `api/tests`.
- Variables de ejemplo: `api/.env.example`.
- Importación CSV: [`docs/importacion-estudiantes.md`](./importacion-estudiantes.md).
- Restablecimiento de datos: [`docs/SYSTEM_DATA_RESET.md`](./SYSTEM_DATA_RESET.md).

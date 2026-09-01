# Cuaderno Nota

Cuaderno Nota es una aplicación web de gestión académica institucional. Permite administrar años escolares, grados, secciones, materias, actividades, calificaciones, asistencia, observaciones y alertas de riesgo estudiantil, con paneles diferenciados para docentes y administradores.

El proyecto está organizado como un **monorepo con frontend y API separados**: React/Vite por un lado, Laravel por otro, comunicados exclusivamente vía API REST con tokens Bearer (sin cookies ni sesiones compartidas), pensado para desplegarse en dominios distintos.

## Estructura del repositorio

```text
CuadernoNota/
├── api/           Backend Laravel (API REST)
├── frontend/       Frontend React + Vite
├── compose.yaml    Orquestación de todo el stack para desarrollo local
└── docs/
```

## Módulos

### Docente

- Panel con indicadores académicos del profesor.
- Cursos, secciones y asignaturas asignadas.
- Actividades por curso y período; calificación por estudiante, actividad y competencia.
- Libro de calificaciones (los cuatro períodos + calificación final).
- Envío de calificaciones a revisión y bloqueo de espacios cerrados.
- Recuperación pedagógica, final y especial.
- Registro de asistencia y justificación de ausencias.
- Observaciones académicas, disciplinarias e incidentes.
- Identificación de estudiantes en riesgo académico o de asistencia.

### Administración

- Dashboard administrativo.
- Gestión de usuarios y roles.
- Gestión de estudiantes: alta, matrícula, importación por CSV, colocación de estudiantes pendientes, desactivación.
- Revisión y aprobación/rechazo de calificaciones por sección, materia y período.
- Promoción de estudiantes (individual y masiva) con historial de decisiones.
- Catálogo académico: años escolares, períodos, grados, secciones, materias, plantillas de actividades.
- Asignaciones docentes.
- Reportes académicos y de asistencia; auditoría de acciones (`audit_logs`); respaldos.
- Restablecimiento controlado de datos del sistema, con vista previa, verificación de integridad y confirmación explícita — conserva usuarios, años escolares y períodos.

## Reglas de calificación

Las actividades se registran bajo una de tres competencias:

- C1: Competencia comunicativa.
- C2: Pensamiento lógico, creativo y crítico.
- C3: Competencia científica y tecnológica.

Solo se incluyen en el promedio las actividades que tengan una nota registrada.

```text
Competencia = promedio de sus actividades calificadas
Nota del período = (C1 + C2 + C3) / 3
Calificación final = promedio de las notas efectivas de los 4 períodos
```

Cuando existe una recuperación pedagógica, esta sustituye la nota ordinaria del período para calcular la calificación final.

## Arquitectura

### Backend (`api/`)

Separa las reglas de negocio de los detalles de Laravel siguiendo una estructura inspirada en Domain-Driven Design y arquitectura limpia:

```text
api/app/
├── Domain/          Entidades, reglas de negocio y contratos (interfaces de repositorio)
├── Application/      Casos de uso del sistema
└── Infrastructure/   Controladores HTTP, modelos Eloquent y repositorios
```

Recorrido habitual de una solicitud:

```text
React → API Laravel → Controller → Caso de uso → Repositorio → Base de datos
                                        ↓
                                Servicio de dominio
```

Autenticación por token con Laravel Sanctum (Bearer, sin cookies) — necesario porque frontend y API viven en dominios distintos en producción.

### Frontend (`frontend/`)

```text
frontend/src/
├── api/             Cliente Axios y funciones de acceso a la API
├── components/       Componentes reutilizables (incluye admin/ y ui/)
├── pages/            Pantallas: auth/, docente/, admin/
├── store/            Estado global con Zustand
└── utils/            Utilidades
```

## Tecnologías

### Backend

- PHP 8.4
- Laravel 13
- Laravel Sanctum
- Eloquent ORM
- PHPUnit 12

### Frontend

- React 19
- React Router 7
- TanStack React Query 5
- Zustand 5
- Tailwind CSS 4
- Vite 8
- Axios

### Base de datos

PostgreSQL es la base de datos objetivo (Docker local y Supabase en producción). SQLite se usa únicamente para la suite de pruebas automatizadas por velocidad.

## Desarrollo local con Docker (recomendado)

Requiere Docker Desktop.

```bash
git clone https://github.com/Black-Brown/CuadernoNota.git
cd CuadernoNota
docker compose up -d --build
```

Esto levanta tres servicios:

| Servicio   | URL                         | Descripción                          |
|------------|------------------------------|---------------------------------------|
| `db`        | `localhost:5433`             | PostgreSQL 16                         |
| `api`       | http://localhost:8000        | API Laravel                           |
| `frontend`  | http://localhost:5173        | React + Vite (hot reload)             |

Al iniciar, el contenedor de la API genera `.env` desde `.env.example` si no existe, corre las migraciones automáticamente y limpia cachés.

Carga datos de prueba:

```bash
docker compose exec api php artisan db:seed --force
```

Abre **http://localhost:5173** (usa `localhost`, no `127.0.0.1`: el origen debe coincidir exactamente con `CORS_ALLOWED_ORIGINS` en `compose.yaml`) e inicia sesión con:

```text
Correo: docente@demo.com
Contraseña: password
```

Comandos útiles:

```bash
docker compose ps                # estado de los servicios
docker compose logs -f api        # logs en vivo
docker compose down               # apagar (conserva los datos en el volumen)
docker compose up -d --build api  # reconstruir tras cambiar composer.json o api/Dockerfile
docker compose up -d --build frontend  # reconstruir tras cambiar package.json o frontend/Dockerfile
```

Editar código dentro de `api/` o `frontend/` no requiere reconstruir: ambos directorios están montados como volumen.

## Desarrollo local sin Docker

### Backend

```bash
cd api
composer install
cp .env.example .env
php artisan key:generate
```

Por defecto `.env.example` apunta a PostgreSQL con host `db` (el nombre del servicio en Docker). Para correr fuera de Docker, cambia `DB_HOST`/`DB_PORT` a tu instancia local de Postgres, o usa SQLite:

```env
DB_CONNECTION=sqlite
```

```bash
php artisan migrate --seed
php artisan serve
```

### Frontend

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

## Pruebas

La suite corre contra SQLite en memoria (configurado en `api/phpunit.xml`) por velocidad:

```bash
cd api
php artisan test
```

Si necesitas verificar compatibilidad con PostgreSQL, apunta las pruebas a una **base de datos separada** (nunca a la de desarrollo — `RefreshDatabase` ejecuta `migrate:fresh` y borra todo lo que tengas sembrado):

```bash
DB_CONNECTION=pgsql DB_DATABASE=cuaderno_nota_test DB_HOST=127.0.0.1 DB_PORT=5433 php artisan test
```

## Autenticación

### Correo y contraseña

Login clásico contra `/api/auth/login`, devuelve un token Sanctum que el frontend guarda y envía como `Authorization: Bearer`.

### Google Workspace

Restringido a cuentas institucionales del dominio configurado en `GOOGLE_WORKSPACE_DOMAIN`. El usuario debe existir previamente en Cuaderno Nota y estar activo — Google solo autentica la identidad, no asigna roles ni cursos.

Como frontend y API están en dominios distintos, el flujo no usa cookies: la API redirige a Google, valida el callback, genera un código de un solo uso y el frontend lo intercambia por un token Sanctum vía `POST /api/auth/google/exchange`.

Para habilitarlo:

1. Crea un cliente OAuth 2.0 de tipo **Aplicación web** en Google Cloud Console.
2. Registra la URI de callback de la API como URI de redirección autorizada (`GOOGLE_REDIRECT_URI`, p. ej. `http://localhost:8000/api/auth/google/callback` en desarrollo).
3. Completa `GOOGLE_CLIENT_ID` y `GOOGLE_CLIENT_SECRET` en `api/.env`.
4. Asegúrate de que `FRONTEND_URL` apunte al origen real del frontend (a donde se redirige tras el login).

## Despliegue en producción

Objetivo: **frontend en Vercel, API en Render/Railway (o un servidor PHP propio), base de datos en Supabase**.

### Base de datos (Supabase)

1. Crea el proyecto y toma la cadena de conexión de Postgres.
2. En las variables de entorno de la API: `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` de Supabase, y `DB_SSLMODE=require` (Supabase exige SSL).

### API (Render, Railway o servidor PHP)

1. Configura **todas** las variables de `api/.env.example` como variables de entorno reales de la plataforma (no subas un `.env` con secretos al repositorio).
2. `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` generado con `php artisan key:generate --show`.
3. `APP_URL` con el dominio real de la API; `FRONTEND_URL` y `CORS_ALLOWED_ORIGINS` con el dominio real de Vercel (deben coincidir exactamente, incluyendo `https://` y sin barra final).
4. `GOOGLE_REDIRECT_URI` con la URI de callback de producción, registrada también en Google Cloud Console.
5. Si despliegas con el `Dockerfile` de `api/`: su `CMD` invoca el servidor embebido de PHP directamente (no `php artisan serve`, que descarta variables de entorno reales en favor de `.env` — ver comentario en el `Dockerfile`). Aun así, el servidor embebido de PHP no es apto para tráfico de producción alto según la propia documentación de PHP; si la plataforma ofrece un runtime nativo de PHP (PHP-FPM + Nginx), suele ser preferible a este `Dockerfile`.
6. Ejecuta migraciones (`php artisan migrate --force`) como parte del despliegue.

### Frontend (Vercel)

1. Root directory del proyecto: `frontend/`.
2. Build command: `npm run build`. Output: `dist/`.
3. Variable de entorno `VITE_API_URL` apuntando a la URL pública de la API (p. ej. `https://api.tudominio.com/api`).

## Estado del proyecto

Implementados los módulos de docente y administración. El modelo de datos permite incorporar posteriormente coordinación académica, dirección y reportes institucionales adicionales.

## Licencia

Este proyecto utiliza la licencia MIT definida en `api/composer.json`.

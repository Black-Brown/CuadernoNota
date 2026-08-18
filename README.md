# Cuaderno Nota

Cuaderno Nota es una aplicación web de gestión académica diseñada para facilitar el trabajo diario de los docentes. Permite administrar cursos, actividades, calificaciones, asistencia, observaciones y alertas de riesgo estudiantil desde un mismo espacio.

El sistema organiza la información por año escolar, grado, sección, asignatura y período académico. Cada docente solo puede consultar o modificar los cursos que tiene asignados.

## Funcionalidades principales

- Autenticación mediante tokens con Laravel Sanctum.
- Panel general del docente con indicadores académicos.
- Consulta de cursos, secciones y asignaturas asignadas.
- Gestión de actividades por curso y período.
- Registro de calificaciones por estudiante, actividad y competencia.
- Cálculo automático de competencias y notas por período.
- Libro de calificaciones con los cuatro períodos y calificación final.
- Envío de calificaciones a revisión y bloqueo de espacios cerrados.
- Registro de recuperación pedagógica, final y especial.
- Registro de asistencia y justificación de ausencias.
- Observaciones académicas, disciplinarias e incidentes.
- Identificación de estudiantes con riesgo académico o de asistencia.
- Exportación de información académica en formato CSV.
- Control de acceso según el docente, la sección y el año académico.

## Reglas de calificación

Las actividades se registran bajo una de las tres competencias del sistema:

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

## Tecnologías

### Backend

- PHP 8.3
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

Laravel permite utilizar SQLite, MySQL, MariaDB o PostgreSQL. La configuración predeterminada del proyecto utiliza SQLite.

## Arquitectura

El backend separa las reglas de negocio de los detalles de Laravel siguiendo una estructura inspirada en Domain-Driven Design y arquitectura limpia:

```text
app/
├── Domain/          Entidades, reglas de negocio y contratos
├── Application/     Casos de uso del sistema
└── Infrastructure/  Controladores, modelos Eloquent y repositorios

resources/js/
├── api/             Cliente y funciones de acceso a la API
├── components/      Componentes reutilizables
├── pages/           Pantallas de autenticación y del docente
├── store/           Estado global con Zustand
└── utils/           Utilidades del frontend
```

El recorrido habitual de una solicitud es:

```text
React → API Laravel → Controller → Caso de uso → Repositorio → Base de datos
                                      ↓
                              Servicio de dominio
```

## Requisitos

- PHP 8.3 o superior con las extensiones habituales de Laravel.
- Composer 2.
- Node.js 20 o superior y npm.
- Git.
- SQLite, MySQL, MariaDB o PostgreSQL.

En Windows se puede utilizar Laragon para instalar PHP, Composer y MySQL de manera sencilla.

## Instalación

### 1. Clonar el repositorio

```bash
git clone https://github.com/Black-Brown/CuadernoNota.git
cd CuadernoNota
```

### 2. Instalar las dependencias

```bash
composer install
npm install
```

### 3. Configurar el entorno

Crea un archivo `.env` en la raíz. Para utilizar SQLite puedes comenzar con esta configuración mínima:

```env
APP_NAME="Cuaderno Nota"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
```

Crea el archivo de base de datos y genera la clave de la aplicación:

```bash
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan key:generate
```

En Windows también puedes crear manualmente el archivo vacío `database/database.sqlite`.

### 4. Ejecutar migraciones y datos de prueba

```bash
php artisan migrate
php artisan db:seed
```

El seeder principal carga competencias, cursos, estudiantes y otros datos demostrativos. Si solo quieres el conjunto mínimo de demostración, ejecuta:

```bash
php artisan db:seed --class=DemoSeeder
```

### 5. Iniciar el entorno de desarrollo

```bash
composer run dev
```

También puedes iniciar cada servidor por separado:

```bash
php artisan serve
npm run dev
```

La aplicación estará disponible normalmente en [http://localhost:8000](http://localhost:8000).

## Configuración con MySQL

Crea una base de datos llamada `cuaderno_nota` y utiliza una configuración similar:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cuaderno_nota
DB_USERNAME=root
DB_PASSWORD=
```

Después ejecuta:

```bash
php artisan migrate --seed
```

## Usuario de demostración

Después de ejecutar `DemoSeeder` puedes iniciar sesión con:

```text
Correo: docente@demo.com
Contraseña: password
```

Estas credenciales son exclusivamente para desarrollo. Deben cambiarse o eliminarse antes de publicar el sistema.

## Comandos útiles

```bash
# Iniciar backend, frontend, cola y visor de logs
composer run dev

# Ejecutar pruebas
composer test

# Aplicar el formato de Laravel
./vendor/bin/pint

# Compilar el frontend para producción
npm run build

# Limpiar las cachés de Laravel
php artisan optimize:clear

# Reconstruir la base de datos con datos de prueba
php artisan migrate:fresh --seed
```

El último comando elimina todos los datos existentes. Úsalo únicamente en un entorno de desarrollo.

## API y seguridad

Las rutas de la API se encuentran bajo `/api`. Las operaciones del módulo docente requieren:

- Un token válido de Laravel Sanctum.
- Una cuenta activa con el rol `teacher`.
- Una asignación válida al curso, sección y año académico solicitado.

El backend valida estas condiciones aunque la interfaz o una solicitud externa intente enviar identificadores de otro curso.

## Pruebas

Las pruebas cubren reglas importantes del dominio:

- Cálculo de competencias.
- Cálculo de notas por período.
- Cálculo de la calificación final.
- Alertas por ausencias consecutivas.
- Porcentaje mínimo de asistencia anual.

Para ejecutarlas:

```bash
composer test
```

## Preparación para producción

Antes de publicar el sistema:

1. Configura `APP_ENV=production` y `APP_DEBUG=false`.
2. Utiliza credenciales de base de datos seguras.
3. Elimina o cambia los usuarios y contraseñas de demostración.
4. Configura correo, colas, logs y copias de seguridad.
5. Ejecuta las migraciones y compila el frontend.

```bash
php artisan migrate --force
npm ci
npm run build
php artisan optimize
```

## Estado del proyecto

Actualmente está implementado el módulo académico del docente. La arquitectura y el modelo de datos permiten incorporar posteriormente módulos administrativos, coordinación académica, dirección y reportes institucionales.

## Licencia

Este proyecto utiliza la licencia MIT definida en `composer.json`.

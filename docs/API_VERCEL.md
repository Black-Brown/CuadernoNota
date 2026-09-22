# API Laravel en Vercel con Docker

Configuración preparada para un **proyecto API separado** del frontend. Render mantiene `api/Dockerfile`; Vercel detecta `api/Dockerfile.vercel` con Root Directory `api`. Validada localmente con Docker; no se ha desplegado ni probado todavía en Vercel.

La documentación de Vercel indica que Container Images está en beta en todos los planes. Aplican los límites y tarifas de Functions: esto no garantiza alojamiento gratuito ni capacidad suficiente. Revisar las condiciones del plan para uso institucional antes de sustituir Render.

Fuente: https://vercel.com/docs/functions/container-images

## Crear el proyecto

1. Importar el mismo repositorio como **nuevo proyecto**, por ejemplo `cuaderno-nota-api`. No cambiar el proyecto del frontend.
2. Seleccionar la rama `feat/api-vercel` para probar y Root Directory `api`.
3. Usar la detección de `Dockerfile.vercel`, sin comandos de Vite ni directorio `dist`. Si la cuenta no permite Container Images, detenerse y revisar disponibilidad; no seleccionar otro runtime a ciegas.
4. Configurar las variables siguientes en el proyecto API. Probar con una base de datos de pruebas independiente; no conectar despliegues Preview automáticamente a producción.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:CLAVE_EXISTENTE_DEL_BACKEND
APP_URL=https://TU-API.vercel.app
FRONTEND_URL=https://cuaderno-nota.vercel.app
CORS_ALLOWED_ORIGINS=https://cuaderno-nota.vercel.app
PORT=80
DB_CONNECTION=pgsql
DB_HOST=HOST_SESSION_POOLER_DE_SUPABASE
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.PROJECT_REF
DB_PASSWORD=SECRETO
DB_SSLMODE=require
CACHE_STORE=database
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=sync
LOG_CHANNEL=stderr
LOG_LEVEL=warning
GOOGLE_CLIENT_ID=CLIENT_ID
GOOGLE_CLIENT_SECRET=SECRETO
GOOGLE_REDIRECT_URI=https://TU-API.vercel.app/api/auth/google/callback
GOOGLE_WORKSPACE_DOMAIN=happylearningschool.net
```

Copiar los valores del pooler del proyecto correcto, sin usar la URL REST de Supabase como host SQL. Si se usan las variables DB_* anteriores, no dejar un DB_URL o DATABASE_URL antiguo que las sobrescriba. Los secretos son exclusivamente de la API, nunca variables VITE_*. Mantener APP_KEY estable; si una clave o contraseña fue expuesta, rotarla mediante un procedimiento planificado.

## Arranque y persistencia

- Nginx + PHP-FPM + Supervisor + OPcache; no se utiliza `php -S`.
- Solo `/tmp` contiene archivos temporales. No usar ese directorio como almacenamiento duradero.
- Caché y sesiones en PostgreSQL: el estado y los códigos de OAuth deben compartirse entre instancias.
- El contenedor **no ejecuta migraciones, seeders ni limpiezas de caché compartida al arrancar**.
- Antes del despliegue, comprobar las migraciones pendientes y aplicarlas una sola vez desde un entorno de confianza, con respaldo y autorización. No usar `migrate:fresh` ni seeders sobre producción. Las tablas de caché y sesiones deben existir.
- `QUEUE_CONNECTION=sync` evita trabajos pendientes sin worker. Si se incorporan tareas largas o periódicas, deben diseñarse y desplegarse por separado; no dejar un worker/scheduler permanente dentro de una función que puede apagarse.
- Las subidas CSV están sujetas también al límite de petición de Vercel, aunque PHP/Nginx acepten más. Probar archivos de tamaño real antes de producción.

## Prueba local del contenedor

Desde la raíz del repositorio, con Docker activo:

```powershell
docker build -f api/Dockerfile.vercel -t cuaderno-api-vercel api
docker run --rm --read-only --tmpfs /tmp:rw,nosuid,size=128m -p 8080:80 --env-file RUTA_ENV_DE_PRUEBAS cuaderno-api-vercel
```

El archivo externo debe contener una clave válida y una conexión de pruebas accesible desde Docker; nunca guardarlo en Git. Abrir `http://localhost:8080/up`. Ese endpoint confirma arranque, no conectividad SQL.

## Verificación y cambio del frontend

### Validación local realizada (2026-09-22)

La imagen se construyó y arrancó con `--read-only`, `/tmp` temporal y `--network none`, sin credenciales de producción. Resultado: `/up` 200, `/api/auth/me` 401 sin token, `/.env` 403, `/composer.json` y `/test.php` 404. Se comprobó que `.env` no esté en la imagen y que `pdo_pgsql` esté cargado. Los contenedores temporales se eliminaron al terminar.

Para repetir: construir con tag `cuaderno-api-vercel:validation` y ejecutar `./api/docker/vercel/smoke-test.ps1` desde PowerShell en la raíz. No prueba conexión SQL, OAuth completo, cargas de archivos ni límites específicos de Vercel.

Antes de cambiar producción: comprobar `/up`, login, CORS, redirección Google, login después de reiniciar la instancia, reportes y carga CSV contra datos de prueba. Confirmar que no son accesibles `/.env`, `/composer.json` ni otros archivos privados. No confundir un redirect de protección de Preview con una respuesta de la API.

Cuando la API haya pasado las pruebas:

1. Agregar en Google Cloud la URI exacta `https://TU-API.vercel.app/api/auth/google/callback` del cliente OAuth existente.
2. En el frontend configurar `VITE_API_URL=https://TU-API.vercel.app/api` y volver a desplegarlo.
3. Mantener Render y su callback mientras se valida la transición. Para volver, restaurar VITE_API_URL a Render y desplegar de nuevo el frontend (Render debe estar operativo).

No eliminar ni recrear Supabase. Cambiar de host de la API no requiere mover sus datos.

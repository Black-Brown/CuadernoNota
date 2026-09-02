# Registro masivo de estudiantes

Desde **Estudiantes → Importar CSV**, descarga la plantilla, completa los datos,
selecciona el archivo y pulsa **Validar archivo**. Revisa la vista previa antes de
pulsar **Registrar estudiantes**. La validación no crea expedientes.

```csv
MATRICULA,NOMBRES,APELLIDOS
000001,Ana María,Pérez Soto
000002,Juan,De la Cruz
```

- Solo se necesitan estas tres columnas. Pueden aparecer en cualquier orden.
- Se aceptan `NOMBRE`, `APELLIDO`, `NUMERO_MATRICULA` y
  `NUMERO_DE_MATRICULA` como alias; los encabezados no distinguen mayúsculas ni acentos.
- El archivo debe ser CSV UTF-8, con o sin BOM, separado por comas o punto y coma.
- La matrícula admite hasta 20 caracteres; nombres y apellidos, hasta 60 cada uno.
  Usa formato **Texto** en Excel para conservar los ceros iniciales de las matrículas.
- El límite es de **5 MB y 1,000 estudiantes por archivo**.
- Los números de matrícula existentes, incluso de estudiantes inactivos, se rechazan.
  No se actualizan ni se reactivan expedientes mediante esta carga.
- Todas las apariciones de una matrícula repetida en el archivo se marcan como error.
- Si hay alguna fila inválida, no se registra ninguna. Corrige el CSV y vuelve a
  cargarlo. La API repite la validación al confirmar y guarda el lote en una transacción.

## La sección se asigna después

Los estudiantes se registran activos, **pendientes de asignación**, sin sección,
año escolar ni matrícula académica. Para inscribirlos, utiliza
**Gestión académica → Asignar estudiantes**.

Los CSV antiguos con columnas como `ANO_ESCOLAR`, `GRADO`, `SECCION`, `TANDA`,
`FECHA_INSCRIPCION` o `NOMBRE_TUTOR` siguen pudiendo validarse, pero esas columnas
se muestran como **ignoradas**. Esta carga ya no realiza asignaciones académicas.

## API administrativa

Ambos endpoints requieren un administrador activo y reciben `multipart/form-data`
con el archivo en el campo `file` (no JSON ni Base64):

- `POST /api/admin/students/import/preview`: devuelve `summary` con `total`,
  `valid` e `invalid`, `ignored_columns` y `rows` con los datos y errores por fila.
  Un error estructural del archivo devuelve HTTP 422.
- `POST /api/admin/students/import`: devuelve HTTP 201 con `message`, `imported`
  y `pending_placement`. Devuelve HTTP 422 ante archivos o filas inválidas.

## Verificación local

En `api/`: `php artisan test --filter=AdminStudent`.
En `frontend/`: `node --test tests/js/studentImport.test.js` y `npm run build`.

Con el servidor Vite, `/tests/fixtures/student-crud.html` permite probar la vista
con datos sintéticos en memoria y archivos CSV de ejemplo. No llama a la API real
ni modifica la base de datos. Los controles de ejemplos pertenecen únicamente a
esa página de pruebas y no se incluyen en la aplicación de producción.

La imagen Docker configura `upload_max_filesize=5M` y `post_max_size=8M`.
Es necesario reconstruirla o redesplegar la API para aplicar esos límites.

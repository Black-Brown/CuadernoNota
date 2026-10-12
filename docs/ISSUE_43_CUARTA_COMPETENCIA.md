# Issue #43 — Cuarta competencia

## Regla confirmada

C4 es «Ética y ciudadanía + Desarrollo personal y espiritual», una sola nota de 0 a 100 por actividad. Cada competencia se calcula como el promedio de sus notas en actividades activas. El período es `(C1 + C2 + C3 + C4) / 4`, redondeado a dos decimales.

Se puede guardar progreso por celda. Una actividad iniciada necesita las cuatro notas para producir un resumen completo; un campo vacío es pendiente, no cero. Un cero explícito sí participa en el cálculo. Las actividades completamente vacías no aportan notas. Desactivar actividades conserva sus datos y las excluye del cálculo.

## Alcance

- Registro de actividades, resumen del workspace, libro anual y exportaciones CSV.
- Perfil del estudiante, revisión administrativa/coordinación, seguimiento académico y riesgo.
- Persistencia de `c4_score`, respuestas `c4`/`c4_score`, recuperación pedagógica y validación de envío a revisión.
- Catálogo C4 con ID 4; seeders de prueba actualizados, sin ejecutarlos en producción.
- Pruebas de fórmula, cero, vacíos, límites, permisos, actividades incompletas y conservación del historial.

## Despliegue

1. Respaldar la base de datos y coordinar una ventana sin escrituras docentes.
2. Aplicar la migración `2026_10_12_000001_add_fourth_competency` mediante `php artisan migrate --force` en el entorno de la API.
3. Desplegar API y frontend compatibles. No mantener el frontend anterior, pues solo registra tres notas.
4. Comprobar C4 en una actividad y verificar el promedio de cuatro notas.

La migración no inventa C4 ni recalcula registros existentes: añade una columna nullable y la entrada del catálogo. Los registros oficiales/en revisión se conservan. Los borradores anteriores requieren completar C4 antes de enviarse. Si se modifica un borrador incompleto, su resumen anterior se elimina y vuelve a generarse cuando estén las cuatro competencias; las notas de actividades se conservan.

El 12 de octubre de 2026, por solicitud del usuario, se aplicó únicamente esta migración en Supabase y en la base local mediante Docker. Se verificó C4/id 4, la columna nueva y que los 61 registros de notas de período existentes en Supabase conservaron íntegros sus campos anteriores (comparación de huella antes/después). No quedaron migraciones pendientes en Supabase. No se ejecutaron seeders ni `migrate:fresh`. El rollback se bloquea si ya existen notas de C4 para evitar perderlas.

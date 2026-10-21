# Códigos de tareas

Cada tarea conserva su UUID y recibe además un `code` inmutable, único dentro del usuario. El antiguo `task_code` numérico sigue intacto. Las categorías y subcategorías tienen un `code_prefix` propio; el prefijo sin categoría se configura en Ajustes de tareas y comienza en `MSO`.

Los prefijos aceptan de 2 a 5 letras ASCII, normalizadas a mayúsculas. No se comparten entre categorías actuales ni con el prefijo global del mismo usuario. Liberar un prefijo permite reasignarlo, continuando su contador histórico. Cambiar categoría, nombre o prefijo no cambia códigos anteriores. Los números eliminados nunca se reutilizan. Los números tienen un mínimo de cinco dígitos y crecen después de 99999.

La persistencia de `Task` bloquea al propietario e incluye contador e inserción en la misma transacción. Una creación cancelada o revertida no consume números. Las recurrencias conservan el código de la tarea activa y asignan otro a cada nueva fila del historial. Los procesos sin autenticación deben crear a través de la relación del propietario o establecer `user_id` explícitamente.

## Aplicación sobre datos existentes

Esta entrega no ejecuta migraciones sobre la base habitual. Para activarla, con autorización y copia de seguridad:

1. Suspender todas las escrituras de tareas y categorías (web, MCP, CalDAV y workers).
2. Ejecutar `php artisan migrate --force` para añadir columnas, índices y contadores.
3. Ejecutar `php artisan tasks:backfill-codes --dry-run`. Si hay propietarios inválidos o categorías huérfanas, corregir el catálogo según una decisión explícita antes de continuar.
4. Ejecutar `php artisan tasks:backfill-codes`. Inicializa prefijos y numera por fecha de creación, con UUID como desempate. Incluye completadas e historial y conserva las demás columnas y fechas.
5. Verificar que todas las tareas tengan código antes de reanudar escrituras.

El comando es reanudable: conserva códigos presentes, recupera los contadores y solo rellena ausentes. Cada usuario se procesa en una transacción. No ejecutar `migrate:rollback` como reversión funcional: eliminaría los identificadores ya comunicados; ante un fallo, mantener escrituras suspendidas y reanudar el backfill.

## MCP

`get-task-tool`, `update-task-tool`, `complete-task-tool` y `link-task-tool` conservan `task_id`, que acepta UUID o código exacto sin distinguir mayúsculas y con espacios exteriores opcionales. La resolución siempre limita por propietario. `list-tasks-tool.search` busca también por código y conserva sus filtros de estado existentes; usar `status=all` para incluir completadas. Get/list devuelven `code` y create informa código y UUID. `code` no es un campo de escritura.

## Validación reproducible

- `php artisan test --filter=TaskCodesTest`: numeración, prefijos, rollback, búsqueda, MCP, aislamiento y backfill.
- `npx playwright test --config=tests/Browser/tasks-settings.config.js --grep "task codes copy"`: copia por teclado, búsqueda y persistencia de configuración a 390 y 1280 px.
- `php tests/Integration/task-code-mysql.php`: configurar variables DB para una instancia MySQL desechable ya migrada, cuya base debe llamarse exactamente `miso_task_code_test`. Lanza procesos independientes para comprobar primera asignación concurrente, rollback y cambio simultáneo de prefijo. Nunca apuntar a datos habituales.
- `npm run build`, `php artisan ui:conformance`, `git diff --check`.

Se ensayó la numeración con una copia aislada en MySQL 8.4 de 43 tareas, 26 categorías y 2 propietarios de la base local. No cambiaron otras columnas de tareas y la segunda ejecución fue estable. La prueba concurrente verificó 14 inserciones sin duplicados ni huecos.

Fallos preexistentes contrastados con los modelos y el modal originales: `TaskViewsTest::test_existing_tasks_open_description_preview_on_every_open` y `HealthModuleTest::test_pending_health_tasks_are_managed_from_their_menu`. El toolbar Markdown también presenta contraste insuficiente en el botón Preview; la comprobación de accesibilidad nueva se limita al control de código.

# Arquitectura

## Recursos estáticos desde JavaScript

- En JavaScript, los recursos de `public/` se resuelven con una URL generada por `asset()` y pasada desde Blade, nunca con rutas absolutas «/…», porque la app puede vivir en una subruta (p. ej. `APP_URL=…/miso/public`).
  - Evidencia: 404 de las pistas de música en CHG-060.

## Convenciones de vistas Blade

- En una vista Blade que ya usa `@php(...)` inline, no agregues después un bloque `@php … @endphp`: Blade lo empareja mal y produce errores como «Undefined variable» o «unexpected endforeach». En esas vistas usa solo `@php(...)` inline.
  - Evidencia: CHG-043.

## Migraciones de base de datos

- En migraciones con índices compuestos (unique o index) sobre tablas de nombre largo, define un nombre corto explícito para el índice: MySQL limita los identificadores a 64 caracteres y las pruebas en SQLite no lo detectan.
  - Evidencia: `game_mission_contributions_game_mission_id_contribution_key_unique` falló solo al migrar en local (CHG-021).

## Tareas programadas y comandos de servidor
Regla para todo cambio que agregue algo que corra solo en el servidor (cron, scheduler, jobs periódicos):
- **Documentado:** en `docs/DEPLOY.md` debe quedar la línea de cron, el script bash si existe, las variables de entorno que necesita y cómo comprobarlo (`php artisan schedule:list`, un `--dry-run` o el log). Si no está documentado, no se puede desplegar.
- **Un solo punto de entrada:** el cron solo llama a `php artisan schedule:run` a la frecuencia que permita el hosting (idealmente cada minuto); la frecuencia de cada tarea se define en `routes/console.php`, no en el crontab.
- **Hosting compartido:** sin colas ni procesos permanentes; cada corrida tiene un límite de tiempo y de lote y la siguiente continúa.
- **Idempotente:** correrlo dos veces (cron duplicado, reintento, dos servidores) no repite la acción en su ventana (día, franja, minuto). Se garantiza en base de datos con una restricción única sobre (usuario, ventana) o una clave de idempotencia, no solo con banderas en memoria; además `->withoutOverlapping()` en el scheduler (`->onOneServer()` solo si algún día hay más de un servidor).
- **Probado:** cada tarea tiene una prueba que la ejecuta dos veces seguidas y comprueba que el efecto ocurre una sola vez.

## Piezas comunes (CHG-148 a CHG-153)
Antes de copiar código entre módulos, usa la pieza común que ya existe:

| Necesidad | Pieza | Dónde |
|---|---|---|
| Favoritos personales y «más usados» de un catálogo | `CatalogPicker` (`favoriteIds`, `ordered`, `quick`, `mostUsed`, `suggestions`), tabla `catalog_favorites`, trait `WithCatalogFavorites`, componente `x-ui.favorite-toggle` | `app/Services/Catalog`, `config/catalog_picker.php`; guía en `docs/catalog-favorites.md` |
| Gestionar categorías de un catálogo (`user_categories`) | trait `ManagesUserCategories` y parcial `livewire/category/partials/section` | `app/Livewire/Concerns`; la página `categories.settings` también los usa |
| Ajustes de un módulo con secciones | `SECTIONS` como mapa en el componente, rutas por sección con `->defaults('section', …)`, `#[Locked] $section` con 404 en `mount`, y `<x-settings-sections>` con las clases `module-settings-*` | Hidratación, Ejercicio, Ánimo, Formación, Comidas, Tareas, Planes y otros |
| Listas de gestión con búsqueda, orden, filtros y paginación | `WithManagementCard` y `x-ui.management-card` | `app/Livewire/Concerns` |

Para sumar un catálogo con favoritos basta registrarlo en `config/catalog_picker.php` (modelo y, si hay «más usados», tabla de registros y clave foránea).

### Lo que no se migró a propósito
- **`MoodState.is_pinned` y `sort_order`:** es un fijado con orden manual para el selector rápido y alimenta una API pública (`prioritized()`); `MoodLogger::prioritizedStates` ya mezcla fijados, recientes y frecuentes. El selector muestra `MoodLogger::PICKER_SIZE` (8) estados y el chat del compañero `MoodLogger::CHAT_QUICK_SIZE` (5).
- **`LearningShortcut` (accesos directos de Formación):** apuntan a dos tipos (formación o unidad) con posición propia y se borran en cascada con su destino; no son un favorito de un catálogo de un solo modelo.
- **`FavoriteCompanion`:** un único compañero con efectos de juego, guardado en la configuración del juego.
- **Categorías con campos propios (`TaskCategory`, `MealCategory`, `PlanCategory`):** tienen color, prefijo, ícono o reasignación; solo comparten `x-ui.category-parent`.
- **Ajustes con otro patrón:** `GameSettingsPage` y `SettingsPage` (grupos y un solo formulario), y las páginas de una sola sección (`HabitSettings`, `PomodoroSettings`, `NotificationSettings`).


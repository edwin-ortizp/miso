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

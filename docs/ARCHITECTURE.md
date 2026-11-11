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

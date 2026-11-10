# CHG-050 · Avisos de experiencia con la foto de cada Pokemon
**Estado:** En verificación
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-10 · **Aprobado:** 2026-11-10 · **Cerrado:** —
**Relacionado:** CHG-034, CHG-038, CHG-041, CHG-042

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: cuando el equipo gana experiencia, el aviso muestra la foto pequeña de cada Pokémon junto a su nombre y su +XP, en vez de solo el texto.
- Cómo comprobarlo tú: completa una tarea que dé experiencia al equipo y mira el aviso de arriba a la derecha: debe salir la foto de cada Pokémon que ganó puntos.

## 1. Resumen
El aviso de experiencia (`GameFeed`) ya conoce qué Pokémon ganó cuánta XP, pero lo aplana a un texto (`Gotin +3 · Flamix +2`). Se pasa a datos estructurados por integrante (nombre, XP, afinidad, imagen) y la vista pinta la foto de cada uno, resuelta con `SpeciesImageProvider` sobre la especie ya cargada. Sin consultas nuevas, coherente con CHG-041.

Alcance: solo el aviso de experiencia del equipo (tipo `xp`). Nivel y evolución conservan su ícono actual.

## 3. UX/UI
Hoy:

```
┌──────────────────────────────────────┐
│  ⭐  TU EQUIPO GANÓ EXPERIENCIA      │
│      Gotin +3 · Flamix +2            │
└──────────────────────────────────────┘
```

Después:

```
┌──────────────────────────────────────┐
│  ⭐  TU EQUIPO GANÓ EXPERIENCIA      │
│      [foto] Gotin +3                 │
│      [foto] Flamix +2 (afinidad)     │
└──────────────────────────────────────┘
```

- Foto de ~28 px, `object-fit: contain`, alineada con el texto de cada integrante.
- Máximo 3 integrantes visibles; si hay más, una línea «y N más».
- Sin `image_base_url` o sin `image_key`: marcador neutro (círculo con la inicial), igual que el resto del juego.
- La foto es decorativa (`alt=""`): el nombre sigue en texto. La pila de avisos mantiene su ancho de `min(22rem, …)`.

## 7. Tareas
- [x] T1 · El aviso de experiencia muestra la foto de cada Pokémon que ganó XP — Resp: developer — Modelo: estandar — Archivos: `app/Services/Game/GameFeed.php`, `resources/views/livewire/game/game-notifier.blade.php`, `resources/css/m3/modules/_game.css`, `tests/Feature/Game/GameFeedTest.php` — Hecho cuando: cada integrante del aviso `xp` llega con su imagen y la vista la pinta a ~28 px con su nombre y +XP; sin imagen se ve el marcador neutro; `php artisan test --filter=GameFeedTest` y `php artisan ui:conformance` en verde.

## 8. Bitácora de implementación (developer)
- 2026-11-10 — T1: `GameFeed` pasó de `companionNames()` (id → nombre) a `companionProfiles()` (id → nombre e imagen vía `SpeciesImageProvider`, reusando la especie ya cargada: sin consultas nuevas). El aviso `xp` lleva `members` estructurado (`name`, `xp`, `affinity`, `image`) y conserva `detail` en texto plano, así que CHG-042 sigue nombrando la fuente y las pruebas no cambian de contrato. La vista pinta hasta 3 fotos de 28 px con nombre y +XP, «y N más» y marcador neutro con la inicial cuando no hay `image_base_url`. `docs/game.md` actualizado. Verificación: GameFeedTest (8, con dos casos nuevos de foto y fallback) y ConsistencyXpTest (9) en verde; `npm run build` y `php artisan ui:conformance` («sin deuda visual nueva»).
- 2026-11-10 — Al iniciar se usó `start CHG-050 --force` porque había otros cuatro CHG «En desarrollo» (CHG-003, 015, 016 y 017), ajenos a este alcance; no se tocó ninguno.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-10 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-10 — Decidido: solo el aviso de experiencia; nivel y evolución quedan como están.
- 2026-11-10 — Aprobado por el usuario (Compuerta 2)
- 2026-11-10 — Movido a in-progress/ (En desarrollo)
- 2026-11-10 — Implementación terminada → En verificación

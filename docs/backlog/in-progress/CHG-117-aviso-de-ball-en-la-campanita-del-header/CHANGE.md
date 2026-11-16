# CHG-117 · Aviso de ball en la campanita del header
**Estado:** En verificación
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-16 · **Aprobado:** 2026-11-16 · **Cerrado:** —
**Relacionado:** CHG-019 (balls), CHG-034/038 (avisos del juego), CHG-085 (bandeja compacta), CHG-068 (patrón de aviso con fuente sintética)

## 0. En palabras simples
- Qué vas a ver distinto: cuando ganas una ball, además del aviso que aparece un momento en pantalla, queda un aviso en la campanita del header ("¡Ganaste una ball!" con el motivo). Si no estabas mirando, lo ves después y te lleva a abrirla. Al abrir la ball, el aviso se marca como leído.
- Cómo comprobarlo tú: completa una tarea que dé ball → la campanita suma 1 sin leer; ábrela, toca el aviso y llegas a Balls; abre la ball → el aviso queda leído. Con el juego apagado no aparece nada.

## 1. Resumen
Cada ball nueva (tareas, hábitos, foco, metas, salud, misiones, extras) crea una notificación `game_ball` en la bandeja existente. Es persistente y refuerza el bucle de gamificación. No envía push, Android ni Telegram: solo vive en la campanita.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: panel lateral con lista compacta (bandeja de notificaciones existente, CHG-085); solo se agrega una fila y un tipo de filtro.

```
┌─ Notificaciones ─────────────┐
│ 🎁 ¡Ganaste una ball!        │
│    Tarea: Pagar el recibo    │
│    Ball · hace 1 min     ✓   │
└──────────────────────────────┘
```

- Fila en la bandeja: icono de ball/regalo, título "¡Ganaste una ball!", detalle con el motivo (`source_label`); ball de meta: "¡Meta cumplida!". Contexto "Ball". Destino: la vista de Balls.
- Nuevo tipo en el filtro "Tipo": "Balls".
- Sin tono de urgencia (neutral); contador de no leídas como los demás avisos.
- Copy según docs/UX-WRITING.md; sin culpa ni castigo (no hay avisos por ausencia).

## 4. Diseño técnico
- Alta en `RewardGranter::rollEncounter` tras crear el `GameEncounter`, mediante un helper `App\Services\Game\BallNotice` (patrón `GuardianNudgePlanner`: `UserNotification::withoutGlobalScopes()->firstOrCreate` con `source_type='game'`, `source_id` UUID5 determinista, `type='game_ball'`, `occurrence_key='ball:{encounter_id}'`). Idempotente por el índice único existente. Fallos: try/catch + `Log::warning`, nunca rompe el premio.
- Canales externos: `NotificationPreferences::pushAllowed` ya devuelve false para tipos sin preferencia; verificar `AndroidDispatcher` y el canal Telegram para que tampoco lo tomen (si lo hacen, excluir `game_ball` o marcar `push_sent_at`/`telegram_sent_at` al crear).
- Presentación: `NotificationBell::TYPES` y los dos `in_array`/`in:` duplicados; `NotificationPresentation::present()` (special, icono, kind, destino); `UserNotification::destination()`.
- Lectura: `EncounterOpener::open()` marca `read_at` del aviso de esa ball.
- Frescura: la campanita no tiene polling; `GameNotifier::check()` ya corre al ganar y cada 60 s, así que al detectar eventos nuevos de ball despacha el evento Livewire que ya refresca la campanita (`android-notification-received` u otro nuevo `notifications-refresh`) para que el contador suba sin recargar.
- Sin migraciones: el esquema actual admite el tipo (`type` es string de 40).

## 5. Pruebas
Tests en `tests/Feature/Game` y `tests/Feature/Notifications*` (ver tareas). Validar con `php artisan test --filter='Notification|Game'`, `php artisan ui:conformance` y `git diff --check`.

## 7. Tareas
- [x] T1 · Al ganar una ball se crea el aviso en la campanita, sin duplicados y sin romper el premio — Resp: developer — Modelo: estandar — Archivos: app/Services/Game/BallNotice.php (nuevo), app/Services/Game/RewardGranter.php, tests/Feature/Game/BallNoticeTest.php — Hecho cuando: una ball crea 1 aviso `game_ball`; repetir la actividad no lo duplica; sin ball (tope diario/juego apagado) no hay aviso; un fallo al crearlo no impide la ball.
- [x] T2 · La campanita muestra el aviso, lo filtra por "Balls" y enlaza a Balls — Resp: developer — Modelo: estandar — Archivos: app/Livewire/Notifications/NotificationBell.php, app/Services/Notifications/NotificationPresentation.php, app/Models/UserNotification.php, tests/Feature/NotificationPresentationTest.php — Hecho cuando: fila con icono, título, motivo y destino correctos; filtro "Balls" funciona; el aviso no se envía por push/Android/Telegram (test con `FakePushGateway`).
- [x] T3 · Al abrir la ball el aviso queda leído y el contador se actualiza sin recargar — Resp: developer — Modelo: estandar — Archivos: app/Services/Game/EncounterOpener.php, app/Livewire/Game/GameNotifier.php, tests/Feature/Game/EncounterOpenerTest.php, tests/Feature/Game/GameNotifierTest.php — Hecho cuando: abrir la ball marca `read_at`; `GameNotifier::check()` con ball nueva refresca la campanita.

## 8. Bitácora de implementación (developer)
- 2026-11-16 — Implementado T1–T3: `BallNotice` (nuevo), enganche en `RewardGranter::rollEncounter`, lectura en `EncounterOpener`, tipo/filtro/icono/destino en campanita, `GameNotifier` despacha `game-notice-refresh` (la campanita lo escucha). Tests nuevos en `tests/Feature/Game/BallNoticeTest.php`.
- **Pendiente de ejecutar:** el entorno cloud tiene PHP 8.3 y `composer.lock` exige 8.4, por lo que no se pudo instalar `vendor/` ni correr la suite. Solo se verificó la sintaxis (`php -l`). Correr: `php artisan test --filter='BallNoticeTest|EncounterOpenerTest|GameNotifierTest|NotificationPresentationTest|NotificationsTest'`, `php artisan ui:conformance`.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-16 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-16 — Decisión: una notificación por ball (sin agrupar) en v1; sin push/Android/Telegram.
- 2026-11-16 — Aprobado por el usuario (Compuerta 2)
- 2026-11-16 — Movido a in-progress/ (En desarrollo)
- 2026-11-16 — Implementación terminada → En verificación

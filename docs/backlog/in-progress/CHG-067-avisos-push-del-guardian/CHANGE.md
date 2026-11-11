# CHG-067 · Avisos push del guardián
**Estado:** En verificación
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-11 · **Aprobado:** 2026-11-11 · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: tu guardián te escribe al celular (2–3 veces al día máximo, en las franjas que elijas) con un recordatorio amable, p. ej. "Llevas 1 de 4 hábitos, quedan 3 h". Al tocarlo abres directo lo que falta.
- Cómo comprobarlo tú: instala la PWA, activa avisos en Ajustes, elige una franja y espera el aviso; tócalo y llegas al módulo.

## 1. Resumen
Estrategia de recurrencia (2026-11-11): MiSo hoy es solo pull — notificaciones solo dentro de la app, sin push ni scheduler. Agregamos Web Push a la PWA y un job programado que, por franja elegida, decide si vale la pena avisar (hay algo pendiente) y envía un mensaje en voz del guardián del módulo (`ModuleGuardian`, `GuardianAdvisor`). Tope diario, sin culpa, sin un aviso por módulo. Depende de CHG-066 para medir el efecto (`?via=push`).

## 2. Producto (product-manager)
- Problema / usuario: a la gente se le olvida registrar; nada la trae de vuelta.
- Incluye: suscripción push opt-in, franjas (mañana / tarde / noche), tope diario (por defecto 2), un aviso agrupado con lo más relevante pendiente, deep link al módulo, desactivar desde Ajustes.
- No incluye: acciones dentro de la notificación (CHG-068), cierre del día (CHG-069), email/WhatsApp.
- Criterios de aceptación:
  - Dado avisos activos y un hábito pendiente, cuando llega la franja de la tarde, entonces recibo un aviso del guardián de Hábitos que abre `/habits?via=push`.
  - Dado que ya completé todo, cuando llega la franja, entonces no recibo aviso.
  - Dado que ya recibí el tope del día, entonces no llegan más.
  - Ningún texto usa palabras de culpa (`MissionProposalValidator::penaltyWord`).

## 3. UX/UI
<!-- Sin pantallas: escribe "No aplica — motivo" en cada parte. Con UI, approve exige un boceto ASCII en un bloque de código. Si hay flujo, agrega un diagrama en un bloque mermaid. -->
### 3.1 Experiencia (ux-designer)
- Flujo y cantidad de pasos: Ajustes → "Avisos de tu guardián" → activar (permiso del navegador) → elegir franjas. Aviso → 1 toque → módulo.
- Qué contiene cada pantalla y qué es lo más importante: en Ajustes, una tarjeta "Avisos de tu guardián" con interruptor general y tres casillas de franja; lo más importante es el interruptor. El aviso: foto del guardián, nombre y una frase con lo pendiente.
- Estados: navegador sin soporte → mensaje y casillas deshabilitadas; permiso denegado → cómo reactivarlo en el navegador; iOS sin PWA instalada → "Instala MiSo en tu pantalla de inicio para recibir avisos"; activado → "Listo, tu guardián te escribirá".
- Información necesaria y momento de presentación: el permiso del navegador se pide solo al activar el interruptor, nunca al cargar la página.
- Accesibilidad de uso: interruptor y casillas con etiqueta, operables por teclado; los errores se anuncian con aria-live.

### 3.2 Visual (ui-designer)
- Distribución:
```
┌ Avisos de tu guardián ─────────── [● ] ┐
│ Te escribe solo si hay algo pendiente.  │
│ [x] Mañana  8–10                        │
│ [x] Tarde  14–16                        │
│ [ ] Noche  19–21                        │
│ Máximo 2 avisos al día.                 │
└─────────────────────────────────────────┘
Aviso:  [foto] Chispin · MiSo
        Llevas 1 de 4 hábitos, quedan 3 h.
```
- Componentes existentes a reutilizar (ruta): tarjetas e interruptores de la pantalla actual de Ajustes (`app/Livewire/Settings`), preferencias de `NotificationPreferences`.
- Jerarquía, color, tipografía y espaciado (tokens): los de Ajustes (M3), sin estilos nuevos.
- Responsive y contraste: una columna en móvil; contraste AA.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: Ajustes (tarjeta) y la notificación del sistema.
- Textos finales (variables y variantes): título "Avisos de tu guardián"; ayuda "Te escribe solo si hay algo pendiente."; "Máximo 2 avisos al día."; aviso: título "{guardián} · MiSo", cuerpo = frase de `GuardianAdvisor` (≤ 60 caracteres).
- Reglas de docs/UX-WRITING.md aplicadas: tuteo, frases cortas, sin culpa ni urgencia falsa.
- Criterios de aceptación de los textos: pasan `MissionProposalValidator::penaltyWord` y miden ≤ 60 caracteres.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar: (actualizado 2026-11-11: se reutiliza la tabla `notifications`, ver §9) `resources/js/pwa.js` + service worker (push y notificationclick), tabla `push_subscriptions`, paquete `laravel-notification-channels/webpush` (VAPID en .env), job `SendGuardianNudges` registrado en el scheduler (`routes/console.php`), preferencias en `NotificationPreferences`, reutilizar `app/Services/Notifications/*` como fuente de "qué está pendiente".
- Enfoque y patrones a seguir: el job elige el módulo con más pendiente del día, pide la frase a `GuardianAdvisor`/`ModuleGuardian::line`, guarda el envío (idempotencia por usuario+franja+día) y respeta zona horaria del usuario.
- Riesgos y mitigación: hoy no hay cron en producción (T5, requisito de despliegue); iOS solo permite push con la PWA instalada (explicarlo en Ajustes); fatiga de avisos (tope y "no hay pendiente → no aviso"); el servidor necesita cron activo (`schedule:run`).
- Qué NO tocar: las notificaciones dentro de la app (siguen igual), la lógica del juego y los guardianes.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Franja con pendiente envía 1 aviso | Feature | Alto | `tests/Feature/Notifications/GuardianNudgesTest` |
| Sin pendiente no envía | Feature | Alto | ídem |
| Tope diario e idempotencia por franja | Feature | Alto | ídem |
| Franja desactivada no envía | Feature | Medio | ídem |
| Textos sin culpa y ≤ 60 caracteres | Unit | Medio | ídem |
- Comando para verificar: `php artisan test --filter=GuardianNudges`; prueba manual en el celular con la PWA instalada.
- Regresión a vigilar: notificaciones dentro de la app y la instalación de la PWA.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/game.md` (sección Guardianes: avisos push) y la documentación de despliegue (cron y VAPID).

## 7. Tareas
- [x] T1 · Puedo activar los avisos y elegir franjas — Resp: developer — Modelo: estandar — Archivos: Ajustes, `pwa.js`, service worker, `push_subscriptions` — Hecho cuando: la suscripción se guarda y se puede desactivar.
- [x] T2 · El guardián decide si avisar y qué decir — Resp: developer — Modelo: estandar — Archivos: `app/Jobs/SendGuardianNudges.php`, scheduler — Hecho cuando: respeta franja, pendiente real y tope; es idempotente según `docs/ARCHITECTURE.md` § Tareas programadas usando el índice único de `notifications` (ocurrencia `{fecha}:{franja}`) y el reclamo condicional de `push_sent_at`, con pruebas que corren los comandos dos veces.
- [x] T3 · Tocar el aviso abre el módulo — Resp: developer — Modelo: estandar — Archivos: service worker — Hecho cuando: abre la URL con `?via=push`.
- [x] T5 · Dejar documentado el cron del servidor — Resp: developer — Modelo: estandar — Archivos: `docs/DEPLOY.md` (nuevo) — Hecho cuando: documenta la línea de crontab (`* * * * * cd /ruta/miso && php artisan schedule:run >> /dev/null 2>&1`), el script bash si se usa, las claves VAPID en `.env`, cómo comprobar (`php artisan schedule:list`) y qué pasa si el cron corre dos veces (nada: idempotente).
- [x] T4 · Pruebas y docs — Resp: developer — Modelo: estandar — Archivos: `tests/Feature/Notifications/`, `docs/game.md` — Hecho cuando: hay pruebas de franja, tope, sin pendiente y texto sin culpa.

## 8. Bitácora de implementación (developer)
- 2026-11-11 — Migración (`push_sent_at`, `push_subscriptions`), `GuardianNudgePlanner`, `NotificationDispatcher`, `PushGateway`/`WebPushGateway` (minishlink/web-push ^11, compatible con Guzzle 8), comandos `miso:guardian-nudges`, `notifications:dispatch`, `miso:push-check`, `miso:vapid`, scheduler en `routes/console.php`, `ModuleProgress` extraído de `GuardianCard`, tarjeta en Ajustes → Notificaciones, `pwa.js` (`ltPushToggle`) y `sw.js` v7 (push y notificationclick), `docs/DEPLOY.md`, `docs/notifications.md`. Assets recompilados (incluye el CSS pendiente de CHG-065). Pruebas escritas y no ejecutadas en la nube (PHP 8.3 vs 8.4).

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-11 — Decisión (usuario): reutilizar el modelo multicanal existente (`notifications` + columna `*_sent_at` por canal) en vez de tablas propias. El aviso del guardián es el tipo `guardian_nudge`; se agrega `push_sent_at` y `push_subscriptions`. Hosting compartido: un solo cron a `schedule:run` (cada 1–15 min), sin colas, lotes con límite de tiempo.
- 2026-11-11 — Decisión (usuario): todo lo que corra en el servidor queda documentado (cron/script) y es idempotente por ventana; regla agregada en `docs/ARCHITECTURE.md`.
- Pregunta: ¿franjas fijas (8–10, 14–16, 19–21) o horas que elige cada usuario? → 2026-11-11 Decisión: hoy no existen franjas; se crean tres fijas (mañana 8–10, tarde 14–16, noche 19–21) que el usuario activa o desactiva en Ajustes. Horas personalizadas quedan para después.
- Pregunta: ¿el servidor de producción ya corre `schedule:run` por cron? → 2026-11-11 Decisión (usuario): no hay cron hoy, pero puede configurarse. El CHG documenta cómo y el despliegue requiere activarlo (ver T5).
- Depende de: CHG-066.
- 2026-11-11 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-11 — Aprobado por el usuario (Compuerta 2)
- 2026-11-11 — Movido a in-progress/ (En desarrollo)
- 2026-11-11 — Implementación terminada → En verificación

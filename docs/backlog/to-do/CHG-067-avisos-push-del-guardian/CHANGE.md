# CHG-067 · Avisos push del guardián
**Estado:** Borrador
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-11 · **Aprobado:** — · **Cerrado:** —
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
- Qué contiene cada pantalla y qué es lo más importante:
- Estados (vacío / carga / error / éxito / validaciones):
- Información necesaria y momento de presentación:
- Accesibilidad de uso (teclado, foco, errores):

### 3.2 Visual (ui-designer)
- Distribución:
- Componentes existentes a reutilizar (ruta):
- Jerarquía, color, tipografía y espaciado (tokens):
- Responsive y contraste:

### 3.3 Escritura (ux-writer)
- Ubicación y estado:
- Textos finales (variables y variantes):
- Reglas de docs/UX-WRITING.md aplicadas:
- Criterios de aceptación de los textos:

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar: `resources/js/pwa.js` + service worker (push y notificationclick), tabla `push_subscriptions`, paquete `laravel-notification-channels/webpush` (VAPID en .env), job `SendGuardianNudges` registrado en el scheduler (`routes/console.php`), preferencias en `NotificationPreferences`, reutilizar `app/Services/Notifications/*` como fuente de "qué está pendiente".
- Enfoque y patrones a seguir: el job elige el módulo con más pendiente del día, pide la frase a `GuardianAdvisor`/`ModuleGuardian::line`, guarda el envío (idempotencia por usuario+franja+día) y respeta zona horaria del usuario.
- Riesgos y mitigación: iOS solo permite push con la PWA instalada (explicarlo en Ajustes); fatiga de avisos (tope y "no hay pendiente → no aviso"); el servidor necesita cron activo (`schedule:run`).
- Qué NO tocar:

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
- Comando para verificar:
- Regresión a vigilar:

## 6. Documentación (tech-writer)
- Docs a actualizar:

## 7. Tareas
- [ ] T1 · Puedo activar los avisos y elegir franjas — Resp: developer — Modelo: estandar — Archivos: Ajustes, `pwa.js`, service worker, `push_subscriptions` — Hecho cuando: la suscripción se guarda y se puede desactivar.
- [ ] T2 · El guardián decide si avisar y qué decir — Resp: developer — Modelo: estandar — Archivos: `app/Jobs/SendGuardianNudges.php`, scheduler — Hecho cuando: respeta franja, pendiente real, tope e idempotencia.
- [ ] T3 · Tocar el aviso abre el módulo — Resp: developer — Modelo: estandar — Archivos: service worker — Hecho cuando: abre la URL con `?via=push`.
- [ ] T4 · Pruebas y docs — Resp: developer — Modelo: estandar — Archivos: `tests/Feature/Notifications/`, `docs/game.md` — Hecho cuando: hay pruebas de franja, tope, sin pendiente y texto sin culpa.

## 8. Bitácora de implementación (developer)

## 9. Preguntas abiertas, decisiones e historial
- Pregunta: ¿franjas fijas (8–10, 14–16, 19–21) o horas que elige cada usuario?
- Pregunta: ¿el servidor de producción ya corre `schedule:run` por cron?
- Depende de: CHG-066.
- 2026-11-11 — Creado en to-do/ (Borrador, nivel M)

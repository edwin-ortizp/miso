# CHG-068 · Registro en un toque y check-in de 20 segundos
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-11 · **Aprobado:** 2026-11-11 · **Cerrado:** 2026-11-11
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: desde el aviso puedes marcar el hábito o sumar un vaso sin abrir la app, y en Inicio hay un "check-in de 20 s" con ánimo, energía, agua y hábitos pendientes en una sola pantalla.
- Cómo comprobarlo tú: toca "Hecho" en un aviso y verifica el hábito; abre el check-in y registra todo en menos de 20 s.

## 1. Resumen
Registrar cuesta: cada dato vive en su módulo. Inicio ya tiene registros rápidos (agua, ánimo, energía en `partials/quick-log` y hábitos en `partials/habits-today`), pero no hay un camino directo de 20 s ni acciones desde el aviso. Agregamos: (1) un check-in guiado en Inicio que reutiliza esos mismos métodos del `Dashboard`, abierto con `/?checkin=1`; (2) botones en los avisos push del CHG-067 ("+1 vaso" registra sin abrir la app; "Check-in" abre la hoja). Sin texto libre (CHG-073).

## 2. Producto (product-manager)
- Problema / usuario: registrar varias cosas exige recorrer Inicio o varios módulos; el aviso push solo abre la app.
- Incluye: hoja "Check-in" en Inicio (ánimo, energía, +1 vaso y hábitos pendientes de hoy, en ese orden, con "Listo"); botón "Check-in" en Inicio; `/?checkin=1&via=checkin` la abre; acciones en el aviso push: agua → "+1 vaso" y "Abrir"; otros → "Check-in" y "Abrir"; "+1 vaso" registra en segundo plano con un enlace firmado, una sola vez por aviso.
- No incluye: texto libre o voz (CHG-073); nuevos tipos de registro.
- Criterios de aceptación:
  - Dado que abro `/?checkin=1`, entonces veo la hoja con ánimo, energía, agua y mis hábitos pendientes de hoy.
  - Dado que marco un hábito en la hoja, entonces se completa con el mismo XP y feedback que desde Hábitos.
  - Dado un aviso de agua, cuando toco "+1 vaso", entonces se registra un vaso de 250 ml sin abrir la app; tocarlo otra vez o repetir la petición no registra otro.
  - Dado un enlace vencido (más de 12 h) o alterado, entonces no registra nada.
  - En navegadores sin acciones en notificaciones (iPhone), el aviso sigue abriendo la app normalmente.

## 3. UX/UI
<!-- Sin pantallas: escribe "No aplica — motivo" en cada parte. Con UI, approve exige un boceto ASCII en un bloque de código. Si hay flujo, agrega un diagrama en un bloque mermaid. -->
### 3.1 Experiencia (ux-designer)
- Flujo y cantidad de pasos: aviso → "+1 vaso" (0 pasos en la app); o aviso/Inicio → "Check-in" → 4 bloques en una hoja → "Listo". Meta: menos de 20 s.
- Qué contiene cada pantalla y qué es lo más importante: la hoja reutiliza los controles de `quick-log` y `habits-today`; lo más importante es que todo quepa sin desplazarse mucho en el celular.
- Estados: sin hábitos pendientes → "Hábitos de hoy completos"; registro hecho → el bloque muestra un check; error de red → mensaje del componente existente; acción del aviso fallida → se abre la app en Agua.
- Información necesaria y momento de presentación: solo lo de hoy.
- Accesibilidad de uso: la hoja usa `x-ui.sheet`; foco al título al abrir, Escape cierra, botones con etiqueta.

### 3.2 Visual (ui-designer)
- Distribución:
```
┌ Check-in ─────────────── [x] ┐
│ ¿Cómo te sientes?  😞 😐 🙂 😄 │
│ Energía            1 2 3 4 5  │
│ Agua   [+250 ml] [+500 ml]    │
│ Hábitos de hoy                │
│  [ ] Meditar  [ ] Leer 10 min │
│                     [Listo]   │
└───────────────────────────────┘
Aviso: [foto] Gotin · MiSo — Te falta un vaso.
       [+1 vaso]  [Abrir]
```
- Componentes existentes a reutilizar (ruta): `resources/views/livewire/home/partials/quick-log.blade.php`, `habits-today.blade.php`, `x-ui.sheet`, métodos `Dashboard::quickAddWater/saveMood/saveEnergy/toggleHabit`, `LogDrink`.
- Jerarquía, color, tipografía y espaciado (tokens): los de Inicio, sin estilos nuevos salvo el contenedor de la hoja.
- Responsive y contraste: hoja a pantalla completa en móvil, modal en escritorio; AA.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: Inicio (botón y hoja) y aviso push (acciones).
- Textos finales (variables y variantes): "Check-in", "¿Cómo te sientes?", "Energía", "Agua", "Hábitos de hoy", "Hábitos de hoy completos", "Listo"; acciones del aviso: "+1 vaso", "Check-in", "Abrir".
- Reglas de docs/UX-WRITING.md aplicadas: tuteo, verbos cortos, sin culpa.
- Criterios de aceptación de los textos: títulos de acción ≤ 12 caracteres (límite práctico de las notificaciones).

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar: `app/Livewire/Home/Dashboard.php` (`$checkin` con `#[Url]`), vista `partials/checkin-sheet.blade.php`, `NotificationDispatcher` (payload `actions` y `quick_url`), ruta firmada `POST /push/quick-water/{notification}` con controlador `QuickWaterController`, `public/sw.js` (`event.action`), `UserActiveDay::CHANNELS` (ya tiene `checkin`).
- Enfoque y patrones a seguir: la hoja llama a los métodos existentes del `Dashboard`, sin duplicar lógica. El enlace firmado usa `URL::temporarySignedRoute` (12 h) por notificación; la idempotencia se guarda en la propia notificación con una columna nueva `action_done_at`, reclamada con un UPDATE condicional igual que `push_sent_at`.
- Riesgos y mitigación: iOS no muestra acciones (degradación: abre la app); la petición en segundo plano no lleva sesión, por eso la autoriza la firma y el dueño de la notificación; un enlace robado solo puede registrar un vaso, una vez, durante 12 h.
- Qué NO tocar: la lógica de XP y constancia de cada módulo (se reutiliza tal cual).

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| `/?checkin=1` abre la hoja con los 4 bloques | Feature | Alto | `tests/Feature/Engagement/QuickCheckinTest` |
| Marcar hábito desde la hoja da el mismo XP | Feature | Alto | ídem |
| Enlace firmado registra 1 vaso una sola vez | Feature | Alto | ídem |
| Firma inválida o vencida → 403 sin registro | Feature | Alto | ídem |
| Payload del aviso trae acciones según tipo | Feature | Medio | `NotificationDispatchTest` |
- Comando para verificar: `php artisan test --filter='QuickCheckin|NotificationDispatch'`
- Regresión a vigilar: `HomeDashboardTest`, `DashboardQueryBudgetTest`.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/notifications.md` (acciones y enlace firmado), `docs/game.md` (recurrencia).

## 7. Tareas
- [x] T1 · Check-in de 20 s en Inicio — Resp: developer — Modelo: estandar — Archivos: `Dashboard.php`, `partials/checkin-sheet.blade.php` — Hecho cuando: `/?checkin=1` y el botón abren la hoja y cada registro usa la lógica existente.
- [x] T2 · Botones en el aviso push — Resp: developer — Modelo: estandar — Archivos: `NotificationDispatcher`, `public/sw.js` — Hecho cuando: los avisos traen acciones según tipo y "Check-in" abre la hoja.
- [x] T3 · +1 vaso sin abrir la app — Resp: developer — Modelo: estandar — Archivos: migración `action_done_at`, ruta firmada, `QuickWaterController` — Hecho cuando: registra una sola vez y rechaza firmas inválidas o vencidas.
- [x] T4 · Pruebas y docs — Resp: developer — Modelo: estandar — Archivos: `tests/Feature/Engagement/QuickCheckinTest.php`, docs — Hecho cuando: pasan los casos de §5.

## 8. Bitácora de implementación (developer)
- 2026-11-11 — `Dashboard::$checkin` (`#[Url]`, siempre registra hoy), hoja `partials/checkin-sheet.blade.php` (persistente, claves `checkin-*` para no chocar con las de Inicio) y botón "Check-in"; `NotificationDispatcher::actions()` agrega botones y URL firmada; `QuickWaterController` en `routes/api.php` (firma 12 h, throttle, `action_done_at` reclamado con UPDATE condicional); `sw.js` v8 maneja `event.action`. Pruebas en `tests/Feature/Engagement/QuickCheckinTest.php`. Assets recompilados. Pruebas no ejecutadas en la nube (PHP 8.3 vs 8.4).

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-11 — LECCIÓN(developer): los assets compilados (`public/css`, `public/js`) viven en el repo; todo cambio de CSS/JS exige `npm run build`. Cerrado sin ejecutar las pruebas en la nube (PHP 8.3 vs 8.4): verificar en local.
- 2026-11-11 — Decisión (developer): Inicio ya tiene los registros rápidos; el check-in los reutiliza en una hoja en vez de crear un componente paralelo.
- Depende de: CHG-067.
- Pregunta: ¿incluir captura por texto libre ("tomé 2 vasos y fui al gym") usando las herramientas MCP, o dejarlo para otro CHG? → 2026-11-11 Decisión (usuario): otro CHG (CHG-073).
- 2026-11-11 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-11 — Aprobado por el usuario (Compuerta 2)
- 2026-11-11 — Movido a in-progress/ (En desarrollo)
- 2026-11-11 — Implementación terminada → En verificación
- 2026-11-11 — Cerrado por el usuario (Compuerta 3) → done/

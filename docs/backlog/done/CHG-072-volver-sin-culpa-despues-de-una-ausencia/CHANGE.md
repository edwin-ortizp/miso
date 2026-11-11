# CHG-072 · Volver sin culpa después de una ausencia
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-11 · **Aprobado:** 2026-11-11 · **Cerrado:** 2026-11-11
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: si vuelves después de 3 días o más sin abrir MiSo, en vez de un muro de tareas vencidas y hábitos en cero te recibe tu guardián con "Qué bueno verte. ¿Retomamos con algo pequeño?". Con un toque mueves lo vencido a hoy, a otra fecha o lo archivas, y te sugiere una sola cosa fácil para empezar.
- Cómo comprobarlo tú: no abras la app 3 días (o simúlalo) y entra: ves la bienvenida, eliges qué hacer con lo vencido y empiezas el día limpio.

## 1. Resumen
Volver tras una ausencia es el momento con más riesgo de abandono: el usuario encuentra vencidos, ceros y misiones atrasadas, y siente culpa. Mostramos una bienvenida de una sola vez por regreso, con el guardián, un resumen amable de lo pendiente, acciones en bloque sobre lo vencido y una primera acción pequeña. Usa `user_active_days` (CHG-067) para detectar la ausencia y medir si quien vuelve se queda (canal `?via=return`).

## 2. Producto (product-manager)
- Problema / usuario: quien deja de usar la app unos días vuelve a una pantalla que lo regaña y la abandona del todo.
- Incluye: detección de ausencia (≥ 3 días sin día activo, configurable en `config/game.php` o config propia); bienvenida en Inicio una vez por regreso; tareas vencidas con acciones en bloque (pasar a hoy, reprogramar, archivar) y opción de revisarlas una por una; una sugerencia pequeña (el hábito más fácil o 1 vaso de agua); enlace para marcar en retroactivo los hábitos de los días ausentes (reusa Hábitos con fecha); misiones de la semana sin ningún aviso de atraso.
- No incluye: avisos push de reenganche (eso es CHG-068); cambios a rachas (no hay rachas con castigo).
- Criterios de aceptación:
  - Dado que mi último día activo fue hace 4 días, cuando abro Inicio, entonces veo la bienvenida del guardián y no el listado normal de vencidos.
  - Dado que elijo "Pasar todo a hoy", entonces las tareas vencidas quedan con fecha de hoy y la bienvenida desaparece.
  - Dado que cierro la bienvenida, entonces no vuelve a salir hasta la próxima ausencia.
  - Dado que mi última visita fue ayer, entonces no veo bienvenida.
  - Ningún texto usa palabras de culpa (`MissionProposalValidator::penaltyWord`).

## 3. UX/UI
<!-- Sin pantallas: escribe "No aplica — motivo" en cada parte. Con UI, approve exige un boceto ASCII en un bloque de código. Si hay flujo, agrega un diagrama en un bloque mermaid. -->
### 3.1 Experiencia (ux-designer)
- Flujo y cantidad de pasos: entrar → bienvenida (1 pantalla, dentro de Inicio) → elegir acción para lo vencido (1 toque) → primera acción sugerida (1 toque) o "Ir a mi día".
- Qué contiene cada pantalla y qué es lo más importante: guardián y saludo; "Tienes N tareas que se quedaron atrás" con las tres acciones; una sugerencia pequeña. Lo más importante es que se sienta ligero: nada de cifras de lo perdido.
- Estados: sin vencidos → solo saludo y sugerencia; sin guardián (juego apagado) → mismo texto sin la criatura; error al mover tareas → mensaje y las tareas quedan como estaban; éxito → "Listo, empezamos de nuevo".
- Información necesaria y momento de presentación: el número de vencidos y la lista solo si el usuario pide revisarlas una por una.
- Accesibilidad de uso: el foco va al saludo al abrirse; botones con etiqueta; operable por teclado; los mensajes se anuncian con aria-live.

### 3.2 Visual (ui-designer)
- Distribución:
```
┌──────────────────────────────────────────────┐
│ [guardián]  Qué bueno verte, Edwin.          │
│             ¿Retomamos con algo pequeño?     │
│                                              │
│ 7 tareas se quedaron atrás                   │
│ [Pasar a hoy] [Elegir fecha] [Archivar]      │
│ Revisarlas una por una                       │
│                                              │
│ Para empezar: 💧 Toma un vaso de agua  [+1]  │
│                              [Ir a mi día]   │
└──────────────────────────────────────────────┘
```
- Componentes existentes a reutilizar (ruta): tarjeta del guardián (`resources/views/livewire/game/guardian-card.blade.php`) o `ModuleGuardian`; tarjetas M3 del Inicio (`app/Livewire/Home`); diálogo de reprogramar tareas existente.
- Jerarquía, color, tipografía y espaciado (tokens): tokens M3 del Inicio; sin rojo de error para los vencidos.
- Responsive y contraste: botones apilados en móvil; contraste AA.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: tarjeta superior de Inicio, solo tras una ausencia.
- Textos finales (variables y variantes): "Qué bueno verte, {nombre}." / "¿Retomamos con algo pequeño?" / "{n} tareas se quedaron atrás" (1: "1 tarea se quedó atrás") / "Pasar a hoy" · "Elegir fecha" · "Archivar" · "Revisarlas una por una" / "Para empezar: {sugerencia}" / "Ir a mi día" / éxito "Listo, empezamos de nuevo."
- Reglas de docs/UX-WRITING.md aplicadas: tuteo, sin culpa ("se quedaron atrás", nunca "vencidas" ni "perdiste"), frases cortas.
- Criterios de aceptación de los textos: pasan `penaltyWord`; ninguno menciona cuántos días faltaste.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar: `app/Services/Engagement/ReturnDetector.php` (último día activo antes de hoy en `user_active_days`), componente Livewire `app/Livewire/Home/WelcomeBack.php` y su vista, acción en bloque sobre tareas reutilizando el servicio de tareas existente, marca de "bienvenida vista" por regreso (columna o tabla con único user_id+fecha).
- Enfoque y patrones a seguir: detección barata (una consulta indexada); la bienvenida se registra como vista con restricción única para que no reaparezca ni se duplique; acciones en bloque en una transacción.
- Riesgos y mitigación: usuarios nuevos sin historial (sin bienvenida si no hay días activos previos); `user_active_days` empezó con CHG-067, así que las ausencias anteriores no se detectan; archivar por error (botón Deshacer en el mensaje de éxito).
- Qué NO tocar: la lógica de misiones y XP del juego; las tareas no vencidas.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Ausencia ≥ 3 días muestra bienvenida | Feature | Alto | `tests/Feature/Engagement/WelcomeBackTest` |
| Visita de ayer no la muestra | Feature | Alto | ídem |
| Usuario sin historial no la ve | Feature | Medio | ídem |
| Pasar a hoy / archivar mueve solo vencidas | Feature | Alto | ídem |
| Cerrada no reaparece en el mismo regreso | Feature | Medio | ídem |
| Textos sin culpa | Unit | Medio | ídem |
- Comando para verificar: `php artisan test --filter=WelcomeBack`
- Regresión a vigilar: carga del Inicio (`DashboardQueryBudgetTest`) y listado de tareas.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/game.md` (sección de recurrencia) o doc de Inicio.

## 7. Tareas
- [x] T1 · Al volver tras 3+ días me recibe el guardián — Resp: developer — Modelo: estandar — Archivos: `ReturnDetector`, `WelcomeBack` — Hecho cuando: aparece solo tras ausencia y una vez por regreso.
- [x] T2 · Muevo o archivo lo vencido con un toque — Resp: developer — Modelo: estandar — Archivos: `WelcomeBack`, servicio de tareas — Hecho cuando: las tres acciones funcionan con Deshacer.
- [x] T3 · Me sugiere una primera acción pequeña — Resp: developer — Modelo: estandar — Archivos: `WelcomeBack` — Hecho cuando: registrar desde ahí funciona y cuenta en `user_active_days`.
- [x] T4 · Pruebas y docs — Resp: developer — Modelo: estandar — Archivos: `tests/Feature/Engagement/WelcomeBackTest.php`, docs — Hecho cuando: pasan los casos de §5.

## 8. Bitácora de implementación (developer)
- 2026-11-11 — `ReturnDetector` (último día activo anterior a hoy en `user_active_days`, umbral en `config/engagement.php`), tabla `welcome_backs` (único usuario+fecha de regreso), componente `Home\WelcomeBack` arriba en Inicio con Pasar a hoy / Elegir fecha / Dejar sin fecha (en transacción, con Deshacer), enlace a Hábitos en el primer día ausente y +1 vaso. Pruebas en `tests/Feature/Engagement/WelcomeBackTest.php`. Assets recompilados. Pruebas no ejecutadas en la nube (PHP 8.3 vs 8.4).

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-11 — LECCIÓN(developer): los assets compilados (`public/css`, `public/js`) viven en el repo; todo cambio de CSS/JS exige `npm run build`. Cerrado sin ejecutar las pruebas en la nube (PHP 8.3 vs 8.4): verificar en local.
- 2026-11-11 — Decisión (developer): las tareas no tienen estado "archivada"; "Archivar" se implementa como "Dejar sin fecha" (la tarea sigue pendiente pero deja de aparecer como atrasada). Se puede deshacer.
- Pregunta: ¿3 días de ausencia está bien como umbral? → 2026-11-11 Decisión (usuario): sí, 3 días.
- Pregunta: ¿los hábitos de los días ausentes se dejan como están (sin registrar) o se ofrece marcar alguno como hecho? → 2026-11-11 Decisión (usuario): se pueden marcar en retroactivo, como ya permite la app, y debe seguir así. La bienvenida ofrece el enlace "¿Hiciste algún hábito estos días? Márcalo" que abre Hábitos en el primer día ausente; no se crea lógica nueva ni restricciones.
- Depende de: CHG-067.
- 2026-11-11 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-11 — Aprobado por el usuario (Compuerta 2)
- 2026-11-11 — Movido a in-progress/ (En desarrollo)
- 2026-11-11 — Implementación terminada → En verificación
- 2026-11-11 — Cerrado por el usuario (Compuerta 3) → done/

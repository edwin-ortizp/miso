# CHG-075 · Cabecera de Inicio con compañero integrado y check-in
**Estado:** Cerrado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-11 · **Aprobado:** 2026-11-11 · **Cerrado:** 2026-11-11
**Relacionado:** CHG-043 (integrante del día), CHG-049 (escenario del compañero), CHG-069 (check-in)

## 0. En palabras simples
- Qué vas a ver distinto: arriba del Inicio ya no aparecen tres franjas sueltas (fecha, botón Check-in solo y la tarjeta del Pokémon). Ahora hay un solo bloque de bienvenida: el compañero del día, con su escenario, te saluda con su frase y te pide hacer el check-in («¿Cómo vas hoy? Hagamos el check-in»). La fecha queda como un texto pequeño dentro del bloque y no ocupa una línea propia. Las tarjetas de abajo usan el mismo estilo que el resto de la app.
- Cómo comprobarlo tú: abre el Inicio. Arriba debe verse un único bloque con el Pokémon, la fecha y el botón de check-in como si te lo pidiera él, sin espacios vacíos. Al tocar el botón se abre el check-in de siempre.

## 1. Resumen
La cabecera del Inicio se ve desarmada: la fecha ocupa una fila completa, el botón Check-in flota solo con mucho aire alrededor y el compañero aparece como una franja plana (`game-buddy`) que no se integra con la página. Se unifica todo en un bloque «hero» que reutiliza el escenario del compañero del panel (`game-buddy-stage`, CHG-049). En ese bloque, el check-in pasa a ser el llamado a la acción del compañero. Además se revisan las tarjetas del Inicio para que sigan el sistema de diseño (`docs/design-system.md`).

## 3. UX/UI
```
┌───────────────────────────────────────────────────────────────┐
│ [escenario por tipo]                                          │
│  (Pokémon)  Miércoles 11 nov · Fennekin Nv 1                  │
│   96 px     “Un pequeño avance sigue siendo avance.”          │
│             ╭─ burbuja ───────────────────────╮               │
│             │ ¿Cómo vas hoy? [⚡ Hacer check-in] │               │
│             ╰─────────────────────────────────╯               │
└───────────────────────────────────────────────────────────────┘
┌ Registro rápido ───────────────┐ ┌ Hábitos 0/23 ── Ver todos ┐
```
- La fecha va como etiqueta (`md-label-medium`, on-surface-variant) dentro del hero. Se quita el `subtitle` de `x-page-header` en el Inicio.
- Si el check-in de hoy ya está hecho, la burbuja cambia a un texto de reconocimiento («Ya hiciste tu check-in de hoy») con la acción «Ver» o «Editar», sin repetir la invitación.
- Sin compañero (`empty`): el hero muestra la ball y «Abre tu primera ball…», y el check-in sigue disponible como botón tonal dentro del mismo bloque.
- Móvil: el Pokémon queda arriba y a la izquierda, a 64 px; la burbuja ocupa todo el ancho y el botón mide al menos 48 px.
- Tarjetas: Registro rápido, Hábitos, Tareas, Gráfica, Comidas y Bitácora usan una sola variante de card del sistema. Cabeceras: `md-title-small` con ícono y una acción de texto a la derecha, el mismo radio y el mismo espaciado. Se eliminan los estilos en línea (`style="color: …"`) a favor de clases.

## 7. Tareas
- [x] T1 · Bloque de bienvenida con el compañero, la fecha y el check-in como pedido del Pokémon — Resp: developer — Modelo: estandar — Archivos: `resources/views/livewire/home/dashboard.blade.php`, `resources/views/components/game/daily-buddy.blade.php` (nueva variante `hero`), CSS de `game-buddy` — Hecho cuando: el Inicio muestra un único bloque sin la fila de fecha ni el botón suelto, y el check-in abre `checkin-sheet`.
- [x] T2 · Estado «check-in hecho» y estado sin compañero — Resp: developer — Modelo: estandar — Archivos: `app/Livewire/Home/Dashboard.php`, vista del hero — Hecho cuando: ambos estados se ven según §3.
- [x] T3 · Tarjetas del Inicio alineadas con el sistema de diseño — Resp: developer — Modelo: estandar — Archivos: `resources/views/livewire/home/partials/*.blade.php` — Hecho cuando: todas usan la misma variante de card y la misma cabecera, sin colores en línea.
- [x] T4 · Prueba de render del Inicio (hero con y sin compañero, check-in hecho o pendiente) — Resp: developer — Modelo: estandar — Archivos: `tests/Feature/` — Hecho cuando: pasa `php artisan test` del archivo nuevo.

## 8. Bitácora de implementación (developer)
- 2026-11-11 — T1/T2: variante `hero` en `x-game.daily-buddy` (escenario por tipo, fecha y nombre en una línea, burbuja con «Hacer check-in»; «Ya hiciste tu check-in de hoy» + «Editar» cuando hay ánimo y energía del día; ball si no hay compañero). Se quitan el subtítulo de `x-page-header`, el botón suelto y la franja `game-buddy` del grid. CSS en `_game.css`.
- 2026-11-11 — T3: componente `x-home.card-head` (ícono en contenedor tonal, título, contador, acción) en las seis tarjetas; colores en línea reemplazados por `home-muted` / `home-tone-text--*` (`_home.css`). Solo quedan en línea los anchos dinámicos de las barras.
- 2026-11-11 — T4: dos pruebas nuevas en `tests/Feature/HomeDashboardTest.php`. **No se pudieron ejecutar**: la suite falla al cargar porque `App\Models\UserActiveDay::touch()` es estático y choca con `Model::touch()`, un problema ajeno a este CHG. Verificado con `view:cache`, `npm run build` y el render real de `Dashboard` con un usuario local.
- 2026-11-11 — Desviación aprobada: `UserActiveDay::touch()` estático chocaba con `Model::touch()` (error fatal al cargar la clase: tumbaba toda la suite y cualquier request con el middleware de días activos). Renombrado a `markActive()` en `app/Models/UserActiveDay.php`, `app/Http/Middleware/TrackActiveDay.php` y 2 pruebas de `tests/Feature/Engagement`. Engagement en verde.
- 2026-11-11 — T4: las 2 pruebas nuevas del hero pasan. Suite completa: 1397 pruebas, 37 fallan; ninguna la introduce este CHG (las de UI/Inicio fallan igual sin sus cambios). Fallas previas ocultas por el error fatal: contratos de UI y CSS, `HomeDashboardTest` (aserciones de CHG-065: `md-module-shell` y `panel-quick-dialog`), Plans, Relationships, GuidedReflection, ModuleGuardian, UrlState y otras.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-11 — Desviación (usuario): incluir en este CHG el arreglo de `UserActiveDay::touch()` → `markActive()`.
- LECCIÓN(developer): no nombrar métodos estáticos de un modelo Eloquent igual que métodos de instancia de `Model` (`touch`, `save`, `update`, `delete`, `fill`…) — PHP lo rechaza con error fatal al cargar la clase y tumba la app y la suite (CHG-067 → detectado en CHG-075) · destino sugerido: docs/ARCHITECTURE.md
- Sugerencia fuera de alcance: hay 37 pruebas en rojo previas; conviene un CHG de saneamiento de la suite.
- 2026-11-11 — Creado en to-do/ (Borrador, nivel S) a partir de la revisión visual del Inicio (captura del usuario).
- 2026-11-11 — Decisión: «Otra frase» queda solo en el panel lateral; el hero del Inicio no lo lleva. Aprobado por el usuario.
- 2026-11-11 — Aprobado por el usuario (Compuerta 2)
- 2026-11-11 — Movido a in-progress/ (En desarrollo)
- 2026-11-11 — Implementación terminada → En verificación
- 2026-11-11 — Cerrado por el usuario (Compuerta 3) → done/

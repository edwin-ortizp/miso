# CHG-136 · Mi día renovado, Kanban por tandas, lista de tareas y guardianes
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-16 · **Aprobado:** 2026-11-16 · **Cerrado:** 2026-11-16
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: Mi día abre con tu compañero y, justo debajo, un resumen del día de un vistazo (hábitos, tareas, agua y ánimo) que te lleva a cada bloque; primero aparece lo que tienes que hacer (hábitos y tareas) y al final tu semana. El gráfico ya no hace zoom ni se queda vacío al tocarlo. Mi día carga más rápido porque los formularios del botón + piden sus listas solo al abrirse. El Kanban muestra 50 tareas por columna con un «Ver más». La lista de Tareas se lee mejor en el celular. El botón «Otro consejo» del guardián queda arriba a la derecha en todos los módulos, como en Hábitos.
- Cómo comprobarlo tú: abre Mi día en el celular y toca el gráfico; abre el + y crea una compra, un hábito y un plan; abre Tareas › Kanban con una columna de más de 50 tareas; mira el guardián en Hidratación, Tareas y Objetivos.

## 1. Resumen
<!-- Qué se cambia y por qué (3-5 líneas). Entendimiento aprobado por el usuario. -->
Pedido del usuario el 2026-11-16 tras la auditoría CHG-135: mejorar con libertad creativa la experiencia de Mi día (primer contacto con la app), corregir el gráfico que hace zoom por accidente en el celular, aceptar la carga diferida de los formularios de Mi día, limitar el Kanban a 50 tareas por columna, repasar la UI de la lista de Tareas y arreglar el botón «Otro consejo» de los guardianes fuera de Hábitos. Sin cambios de esquema ni de reglas de negocio.

## 2. Producto (product-manager)
- Problema / usuario: en el celular Mi día abre con una tarjeta de hallazgos casi vacía, no hay un resumen del día y lo accionable queda lejos; el gráfico pierde los datos con un arrastre; Mi día consulta ~20 veces por formularios cerrados; el Kanban dibuja todas las tareas (≈720 KB con 180); la lista de tareas gasta mucha altura por fila en móvil; el botón del guardián queda suelto bajo el consejo.
- Incluye: nuevo orden y resumen de Mi día; tarjetas de hábitos, tareas, comidas y alerta de vehículos pulidas; gráficos sin zoom ni selección; opciones de los formularios del panel solo al abrirlos; Kanban con 50 por columna y «Ver más»; filas de la lista de tareas más compactas en móvil y descripción sin marcas de Markdown; guardián con una sola composición.
- No incluye: datos o módulos nuevos, cambios en el hero del compañero, cambios de esquema, cambios en el editor de tareas, rediseño de otras vistas de Tareas.
- Criterios de aceptación (Dado/Cuando/Entonces):
  - Dado Mi día en 390 px, cuando carga, entonces lo primero es el hero y debajo el resumen del día; no hay desborde horizontal.
  - Dado el gráfico semanal, cuando se arrastra o se toca la leyenda, entonces no cambia de escala ni oculta series.
  - Dado Mi día con los formularios cerrados, cuando se renderiza, entonces no consulta tiendas, catálogo de productos, hábitos paginados ni opciones de planes; al abrir cada formulario sus opciones están completas.
  - Dada una columna de Kanban con más de 50 tareas, cuando se abre, entonces muestra 50 y un control que carga 50 más; el contador de la columna sigue mostrando el total.
  - Dado un guardián en cualquier módulo, cuando hay consejo de hoy, entonces «Otro consejo» está en la cabecera, a la derecha del nombre.

## 3. UX/UI
<!-- Sin pantallas: escribe "No aplica — motivo" en cada parte. Con UI, approve exige un boceto ASCII en un bloque de código. Si hay flujo, agrega un diagrama en un bloque mermaid. -->
### 3.1 Experiencia (ux-designer)
- Plantilla: Mi día conserva su página de una columna con rejilla de tarjetas (no hay plantilla específica en `## Anatomía` de docs/UX-DESIGN.md); Kanban y Lista conservan el shell de módulo con pestañas.
- Flujo y cantidad de pasos: los mismos; el resumen agrega un atajo de un toque a cada bloque. Abrir un formulario del + sigue costando un toque.
- Qué contiene cada pantalla y qué es lo más importante: (1) compañero, fecha y check-in; (2) resumen del día; (3) hábitos y tareas pendientes; (4) registro rápido y comidas; (5) bitácora; (6) tu semana: tendencia y hallazgos.
- Estados (vacío / carga / error / éxito / validaciones): los existentes de cada tarjeta; el resumen muestra «—» cuando aún no hay ánimo y 0 cuando no hay nada planificado. «Ver más» del Kanban se deshabilita mientras carga.
- Información necesaria y momento de presentación: los hallazgos semanales bajan junto a la tendencia; siguen visibles sin interacción.
- Accesibilidad de uso (teclado, foco, errores): el resumen es una lista de enlaces con nombre accesible completo («Hábitos: 8 de 12»); «Ver más» es un botón con el número restante; el botón del guardián conserva su nombre «Otro consejo» y área táctil de 44 px.

### 3.2 Visual (ui-designer)
- Distribución:

```
Mi día (390 px)                         Mi día (≥ 992 px)
┌ Hero: fecha · compañero · check-in ┐  ┌ Hero ───────────────────────────────────┐
│ Racha            │ Reto de hoy     │  └─────────────────────────────────────────┘
└────────────────────────────────────┘  [Hábitos 8/12][Tareas 5][Agua 0,5/2,5 L][Ánimo 🙂]
[Hábitos][Tareas][ Agua ][Ánimo]        ┌ Hábitos ─────────┐ ┌ Tareas de hoy ──────┐
┌ Hábitos  8/12        Ver todos ┐      └──────────────────┘ └─────────────────────┘
┌ Tareas de hoy  5     Ver todas ┐      ┌ Registro rápido ────────┐ ┌ Comidas ─────┐
┌ Registro rápido                ┐      └─────────────────────────┘ └──────────────┘
┌ Comidas de hoy                 ┐      ┌ Bitácora ──┐ ┌ Tendencia semanal ────────┐
┌ Bitácora del día               ┐      └────────────┘ └───────────────────────────┘
┌ Tendencia semanal              ┐      ┌ Lo que dicen tus datos ─────────────────┐
┌ Lo que dicen tus datos         ┐

Guardián (todos los módulos)            Kanban, columna con 120
┌ Pikachu · Nv 12            (↻) ┐      ┌ Pendientes (120) ┐
│ Equipo de la semana · Salud    │      │ tarjeta × 50     │
│ “consejo…”            [sprite] │      │ [Ver 50 más · quedan 70] │
└────────────────────────────────┘      └──────────────────┘
```

- Componentes existentes a reutilizar (ruta): `resources/views/components/home/card-head.blade.php`, `components/ui/action.blade.php`, `components/ui/icon-action.blade.php`, `components/ui/progress.blade.php`, `components/game/daily-buddy.blade.php` (sin cambios), tonos `home-tone--*`.
- Jerarquía, color, tipografía y espaciado (tokens): tokens de `resources/css/m3`; el resumen usa superficies `surface-container-low`, valor en `title-medium` y etiqueta en `label-small`; cada casilla toma el tono de su módulo.
- Responsive y contraste: resumen a cuatro columnas en todos los anchos (dos filas si no caben); tarjetas en una columna por debajo de 992 px; sin desborde a 390 px. Contraste de textos sobre superficies existentes sin cambios.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: resumen de Mi día, pie de la tarjeta de tareas, control del Kanban.
- Textos finales (variables y variantes): resumen «Hábitos {hechos}/{total}», «Tareas {n} para hoy», «Agua {x} / {meta} L», «Ánimo {emoji o —}»; tarjeta de tareas «{n} para hoy», «{n} completada(s) este día», «{n} pendientes en total»; hábitos «y {n} más en otro momento del día»; Kanban «Ver {n} más», «quedan {n}».
- Reglas de docs/UX-WRITING.md aplicadas: mayúscula solo inicial, sin punto final en etiquetas, números antes que adjetivos.
- Criterios de aceptación de los textos: ningún texto existente cambia de significado; plurales correctos con 1 y con varios.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar: `resources/views/livewire/home/dashboard.blade.php` y parciales (`quick-log`, `habits-today`, `tasks-today`, `meals-today`, nuevo `day-summary`), `app/Livewire/Home/Dashboard.php`, `app/Livewire/Home/Panel/{ShoppingEditor,HabitEditor,PlanEditor}.php`, `resources/js/app.js`, `resources/css/m3/modules/{_home,_game,_tasks,_pomodoro}.css`, `resources/views/livewire/game/guardian-card.blade.php`, `app/Livewire/Task/TaskKanban.php` y su vista, `resources/views/livewire/task/task-list.blade.php`, CSS/JS publicados, pruebas.
- Enfoque y patrones a seguir: sin datos nuevos en Mi día salvo un conteo de tareas del día; los editores del panel ya hacen una petición al abrirse (`#[On('panel-…')]`), así que sus opciones se calculan solo con el formulario abierto; el Kanban limita en PHP por columna con una propiedad de Livewire y conserva los totales; el guardián usa una rejilla única.
- Riesgos y mitigación: formulario abierto sin opciones → prueba que abre cada editor y comprueba sus opciones; arrastrar entre columnas del Kanban con lista parcial → la posición se calcula sobre las tarjetas visibles como hoy, se prueba mover y ver más; regresión visual → capturas a 390 y 1440 px.
- Qué NO tocar: esquema, reglas de gamificación, hero del compañero, editor de tareas, herramientas MCP.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Mi día no consulta opciones de formularios cerrados y baja su presupuesto | Feature | Página | `tests/Feature/DashboardQueryBudgetTest.php` |
| Cada editor del panel tiene sus opciones al abrirse | Feature | Livewire | `tests/Feature/Home/…` |
| Mi día muestra el resumen del día | Feature | Página | `tests/Feature/HomeDashboardTest.php` |
| Kanban limita a 50 y «Ver más» amplía | Feature | Livewire | `tests/Feature/TaskKanbanGroupingTest.php` |
| Guardián: «Otro consejo» en todos los módulos y oculto en otra fecha | Feature | Livewire | `tests/Feature/Game/ModuleGuardianTest.php` |
- Comando para verificar: `php artisan test --filter='Dashboard|HomeDashboard|WallPanel|TaskKanban|ModuleGuardian|TaskList'` y `php artisan test`.
- Regresión a vigilar: crear compra, hábito y plan desde Mi día; panel de pared (usa los mismos editores); mover tarjetas en Kanban; lista de tareas con filtros.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/performance.md` (antes/después de Mi día y Kanban), `docs/backlog/UX-DESIGN.md` (UX-008 resuelto).

## 7. Tareas
<!-- Primero lo que cambia para el usuario; el detalle técnico después. Formato:
- [x] T1 · qué cambia (en simple) — Resp: developer — Modelo: perfil — Archivos: … — Hecho cuando: … -->
- [x] T1 · Mi día con resumen del día y nuevo orden — Resp: developer — Modelo: estandar — Archivos: resources/views/livewire/home/, app/Livewire/Home/Dashboard.php, resources/css/m3/modules/_home.css — Hecho cuando: capturas a 390 y 1440 px sin desborde y pasan las pruebas de Inicio.
- [x] T2 · El gráfico no hace zoom ni oculta series al tocarlo — Resp: developer — Modelo: estandar — Archivos: resources/js/app.js — Hecho cuando: arrastrar y tocar la leyenda no cambian el gráfico.
- [x] T3 · Mi día carga más rápido: los formularios piden sus listas al abrirse — Resp: developer — Modelo: estandar — Archivos: app/Livewire/Home/Panel/ — Hecho cuando: baja el presupuesto de consultas y cada editor abre con sus opciones.
- [x] T4 · Kanban muestra 50 tareas por columna con «Ver más» — Resp: developer — Modelo: estandar — Archivos: app/Livewire/Task/TaskKanban.php y su vista — Hecho cuando: una columna de 60 muestra 50 y el control carga el resto.
- [x] T5 · Lista de tareas más compacta y legible en el celular — Resp: developer — Modelo: estandar — Archivos: resources/views/livewire/task/task-list.blade.php, resources/css/m3/modules/_tasks.css — Hecho cuando: capturas a 390 px con más tareas por pantalla y sin marcas de Markdown.
- [x] T6 · «Otro consejo» del guardián bien ubicado en todos los módulos — Resp: developer — Modelo: estandar — Archivos: resources/views/livewire/game/guardian-card.blade.php, resources/css/m3/modules/_game.css — Hecho cuando: capturas de 7 módulos coinciden con Hábitos.
- [x] T7 · Mediciones documentadas — Resp: developer — Modelo: estandar — Archivos: docs/performance.md, docs/backlog/UX-DESIGN.md — Hecho cuando: está el antes/después.

## 8. Bitácora de implementación (developer)
- 2026-11-16 — T6: el guardián usa una sola rejilla (antes solo Hábitos); «Otro consejo» va en la cabecera en todos los módulos. Capturas de 7 módulos a 390 y 1440 px. La deuda de overrides de `_game.css` queda en 17, igual que antes.
- 2026-11-16 — T2: `staticChart` desactiva zoom y selección en los tres gráficos; la leyenda de la tendencia ya no oculta series al tocarla.
- 2026-11-16 — T3: `ShoppingEditor`, `PlanEditor` y `HabitEditor` calculan sus opciones solo con el diálogo abierto (ya abrían con una petición, así que no hay espera nueva). El editor de tareas no se tocó: abre sin petición.
- 2026-11-16 — T1: orden nuevo (hero → resumen → hábitos y tareas → registro y comidas → bitácora y tendencia → hallazgos), resumen del día con enlaces a cada bloque, tarjetas dimensionadas por contenido, alerta de vehículos y comidas sin estilos en línea. Un dato nuevo: conteo de tareas del día.
- 2026-11-16 — T4: 50 tarjetas por columna, «Ver N más» por columna; cambiar de agrupación reinicia las tandas.
- 2026-11-16 — T5: fecha y «Vencida» en una sola etiqueta, sin «00:00 – 00:00» en tareas de día completo, descripción sin marcas de Markdown, estado bajo el contenido en móvil (fila de ~168 px a ~125 px).
- 2026-11-16 — Validación: suite sin Browser 1.804 pruebas, 38 fallos, los mismos de antes; `npm run test:js` 59/59; `ui:conformance` baja de 20 a 18 hallazgos; sin desborde horizontal a 390 px en Mi día, Lista y Kanban.
- 2026-11-16 — Limitación: los sprites de los compañeros no están en este entorno; las capturas muestran el marcador de posición. La prueba del resumen no comprueba el valor de agua porque en SQLite `where('date', …)` no coincide con fechas guardadas con hora (ya documentado en CHG-095).
- 2026-11-16 — Proceso: igual que CHG-135, el CHG queda **Aprobado** con tareas marcadas; `start` exige `--force` por los CHG antiguos en desarrollo y no se forzó.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-16 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-16 — El usuario dio libertad creativa para Mi día, aceptó diferir los formularios y fijó 50 tareas por columna en Kanban. Sin preguntas abiertas.
- 2026-11-16 — Aprobado por el usuario (Compuerta 2)
- 2026-11-16 — Movido a in-progress/ (En desarrollo)
- 2026-11-16 — Implementación terminada → En verificación
- 2026-11-16 — Cerrado por el usuario (Compuerta 3) → done/

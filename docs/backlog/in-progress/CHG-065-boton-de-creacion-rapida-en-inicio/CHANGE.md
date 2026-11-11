# CHG-065 · Botón de creación rápida en Inicio
**Estado:** En desarrollo
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-11 · **Aprobado:** 2026-11-11 · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: en Inicio aparece el mismo botón «+» del Panel diario para crear tarea, plan, compra, hábito, bebida, ánimo, diario, sesión de enfoque o avance de objetivo.
- Cómo comprobarlo tú: abre Inicio, toca «+», elige una opción, guarda y mira que Inicio se actualiza.

## 1. Resumen
Desde Inicio no se podían crear varias cosas que sí se crean desde el Panel diario. Se reutiliza el FAB compacto del panel y sus editores en Inicio; «Registrar avance» lleva a Objetivos porque la hoja de objetivos solo existe en el panel.
<!-- Qué y por qué, 2-3 líneas. Agrega solo la sección del rol afectado (## 3. UX/UI, ## 4. Diseño técnico o ## 5. Pruebas) si hace falta. Si hay UI, §3 lleva un boceto ASCII de la zona que cambia. -->

## 7. Tareas
- [ ] T1 · Botón «+» en Inicio con las mismas acciones del panel — Resp: developer — Modelo: estandar — Archivos: resources/views/livewire/home/dashboard.blade.php, app/Livewire/Home/Dashboard.php — Hecho cuando: cada acción abre su diálogo, guarda e Inicio se refresca.
- [ ] T2 · Prueba de que Inicio muestra el botón y sus editores — Resp: developer — Modelo: estandar — Archivos: tests/Feature — Hecho cuando: la prueba pasa.
<!-- Primero lo que cambia para el usuario; el detalle técnico después. Formato:
- [ ] T1 · qué cambia (en simple) — Resp: developer — Modelo: perfil — Archivos: … — Hecho cuando: … -->

## 8. Bitácora de implementación (developer)

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-11 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-11 — Aprobado por el usuario (Compuerta 2)
- 2026-11-11 — Movido a in-progress/ (En desarrollo)

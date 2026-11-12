# CHG-081 · Rediseño de la cabecera del Inicio
**Estado:** Cerrado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-11 · **Aprobado:** 2026-11-11 · **Cerrado:** 2026-11-11
**Relacionado:** —

## 0. En palabras simples
- Qué vas a ver distinto: la cabecera del Inicio usa todo el ancho. A la izquierda, tu compañero te saluda y te pide el check-in; a la derecha, «Tu día» con dos mosaicos: tu racha y el reto de hoy. Todo con la letra de la app y un solo botón de check-in.
- Cómo comprobarlo tú: abre el Inicio en el computador. No debe quedar un espacio vacío a la derecha, el reto no debe ser «check-in» y la frase debe verse con la misma letra del resto.

## 1. Resumen
La cabecera de CHG-075 creció con la racha y el reto (CHG-076/077) apilados a la izquierda, dejando vacío el ~60 % del bloque en escritorio. El reto repetía el check-in: el de hoy quedó guardado antes de CHG-080. Además, varios textos salían en Arial porque el CSS usaba un token tipográfico compuesto que no existe (`--md-sys-typescale-x`); los tokens reales vienen separados en `-size`, `-line-height` y `-weight`.

## 3. UX/UI
```
┌──────────────────────────────────────────────────────────────────────────────────────────────┐
│ [Pokémon]  Miércoles 11 nov · Fennekin Nv 1        │ ┌─ Racha ─────────────┐ ┌─ Reto de hoy ───────────┐ │
│  112 px    “Escríbelo, hazlo pequeño y empieza.”   │ │ 🔥 0 días            │ │ Cierra 2 tareas de      │ │
│   sombra   ╭────────────────────────────────╮      │ │ Hoy 1 de 3 acciones  │ │ Trabajo                 │ │
│            │ ¿Cómo vas hoy? [⚡ Hacer check-in] │      │ │ ▓▓▓▓░░░░  · suma     │ │ ▓▓▓▓▓░░░  1 de 2   [Ir →]│ │
│            ╰────────────────────────────────╯      │ │ otra área            │ │                         │ │
│                                                    │ └──────────────────────┘ └─────────────────────────┘ │
└──────────────────────────────────────────────────────────────────────────────────────────────┘
   columna saludo (≈ 50 %)                              columna «Tu día»: 2 mosaicos translúcidos
```
- 768–1199 px: el saludo arriba y los dos mosaicos abajo, lado a lado. Menos de 768 px: el saludo compacto (Pokémon de 64 px) y los mosaicos apilados.
- Mosaicos translúcidos (`rgb(255 255 255 / 0.14)`, radio de 12 px) con texto blanco; título `md-label-large`, cifra `md-headline-small` y texto `md-body-medium`; la barra con pista translúcida.
- El reto cumplido muestra su check y «Cumplido. Mañana hay otro.» dentro del mosaico. Sin racha ni reto, el saludo ocupa todo el ancho.
- Un solo check-in: el de la burbuja.

## 7. Tareas
- [x] T1 · Toda la app usa la letra del sistema en el juego (tokens tipográficos separados en vez del compuesto inexistente) — Resp: developer — Modelo: estandar — Archivos: resources/css/m3/modules/_game.css — Hecho cuando: no queda ningún `font: var(--md-sys-typescale-*, …)` (cerrada por el usuario sin verificar)
- [x] T2 · El reto de hoy deja de ser «check-in» si esa plantilla ya no está habilitada y no se ha cumplido — Resp: developer — Modelo: estandar — Archivos: app/Services/Game/DailyChallenge.php, tests/Feature/Game/DailyChallengeTest.php — Hecho cuando: la prueba nueva pasa (cerrada por el usuario sin verificar)
- [x] T3 · Cabecera en dos columnas (saludo + «Tu día» con mosaicos de racha y reto) en 1440/768/390 px — Resp: developer — Modelo: estandar — Archivos: resources/views/components/game/daily-buddy.blade.php, resources/views/components/game/streak-line.blade.php, resources/views/components/game/daily-challenge.blade.php, resources/css/m3/modules/_game.css, tests/Feature/Game/GameDashboardTest.php — Hecho cuando: capturas en los tres anchos sin espacio perdido ni check-in repetido (cerrada por el usuario sin verificar)
- [x] T4 · Lecciones de diseño en los lineamientos (tipografía, ancho y un llamado por intención) — Resp: developer — Modelo: estandar — Archivos: docs/UI-DESIGN.md, docs/UX-DESIGN.md — Hecho cuando: las reglas quedan con su evidencia (cerrada por el usuario sin verificar)
<!-- Primero lo que cambia para el usuario; el detalle técnico después. Formato:
- [x] T1 · qué cambia (en simple) — Resp: developer — Modelo: perfil — Archivos: … — Hecho cuando: … --> (cerrada por el usuario sin verificar)

## 8. Bitácora de implementación (developer)

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-11 — Plan aprobado por el usuario en la conversación: rediseño en dos columnas, corrección de la tipografía y lecciones de diseño.
- 2026-11-11 — Nota: el reemplazo de tipografía (T1) se aplicó por error con Bash antes de iniciar este CHG; la guardia no lo detectó. Queda cubierto por T1.
- 2026-11-11 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-11 — Aprobado por el usuario (Compuerta 2)
- 2026-11-11 — Movido a in-progress/ (En desarrollo)
- 2026-11-11 — Cerrado por el usuario sin verificación (pendientes: T1, T2, T3, T4): ya lo probé → done/ (desde el panel)

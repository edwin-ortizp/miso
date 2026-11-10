# CHG-039 · Ampliar bios Pokémon con movimientos verificados
**Estado:** En desarrollo
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-09 · **Aprobado:** 2026-11-09 · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: las bios del catálogo Pokémon mencionarán movimientos reales disponibles para cada especie y el juego de referencia.
- Cómo comprobarlo tú: abrir `pokemon.json` y revisar que cada movimiento corresponda al juego nombrado.

## 1. Resumen
<!-- Qué y por qué, 2-3 líneas. Agrega solo la sección del rol afectado (## 3. UX/UI, ## 4. Diseño técnico o ## 5. Pruebas) si hace falta. Si hay UI, §3 lleva un boceto ASCII de la zona que cambia. -->
Ampliar el texto existente de las descripciones, sin campos nuevos ni cambios a la aplicación. Usar los movimientos por grupo de versiones y nombres en español de PokéAPI: Escarlata/Púrpura como referencia principal; para especies sin datos allí, el grupo de versiones más reciente con learnset disponible para esa especie. Nombrar el juego de referencia en el texto. Incluir hasta cinco movimientos dañinos reales ordenados por potencia base descendente (desempate por nombre español); si hay menos de tres nombres españoles verificables, conservar la descripción actual. No completar datos por inferencia.

## 7. Tareas
<!-- Primero lo que cambia para el usuario; el detalle técnico después. Formato:
- [ ] T1 · qué cambia (en simple) — Resp: developer — Modelo: perfil — Archivos: … — Hecho cuando: … -->
- [ ] T1 · Las bios mencionan movimientos reales y su juego de referencia — Resp: developer — Modelo: estandar — Archivos: pokemon.json — Hecho cuando: JSON válido con las 1025 especies originales; se conservan campos y descripciones sin datos verificables; cada bio ampliada incluye 3–5 movimientos dañinos en español confirmados para el juego que nombra, según PokéAPI.
- [ ] T2 · El catálogo queda contrastado — Resp: developer — Modelo: estandar — Archivos: pokemon.json — Hecho cuando: un validador independiente confirma la especie, el grupo de versiones, la disponibilidad de cada movimiento y su nombre español; `git diff --check` pasa.

## 8. Bitácora de implementación (developer)

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-09 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-09 — Alcance aprobado por el usuario: ampliar texto, Escarlata/Púrpura primero, elegir un juego alternativo con datos por especie y usar de 3 a 5 ejemplos.
- 2026-11-09 — Aprobado por el usuario (Compuerta 2)
- 2026-11-09 — Movido a in-progress/ (En desarrollo)

# CHG-172 · Corregir carga duplicada de Livewire y Alpine
**Estado:** Cerrado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-18 · **Aprobado:** 2026-11-18 · **Cerrado:** 2026-11-18
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Si MiSo se actualiza con una pestaña abierta, la siguiente navegación carga la versión nueva en una página completa.
- Abre el formulario de Planes y comprueba que sus selectores funcionan después de navegar.

## 1. Resumen
<!-- Qué y por qué, 2-3 líneas. Agrega solo la sección del rol afectado (## 3. UX/UI, ## 4. Diseño técnico o ## 5. Pruebas) si hace falta. Si hay UI, §3 lleva un boceto ASCII de la zona que cambia. -->

Al cambiar la URL del bundle durante una navegación parcial se pueden ejecutar dos instancias de Livewire y Alpine.

## 4. Diseño técnico
Publicar un marcador de versión en el head compartido. Si Livewire encuentra un marcador nuevo mientras navega, recarga el documento de destino. Las respuestas parciales identificadas por `X-Livewire-Navigate` llevan el marcador y las respuestas completas cargan el módulo con su URL versionada. Vite conserva su entrada de desarrollo.
La prueba reprodujo la ejecución duplicada incluso con `data-navigate-track`: el paquete programa la recarga y también inserta el script nuevo en el documento anterior.

## 5. Pruebas
Simular un cambio de versión al navegar hacia Planes en `tests/Browser/plans.interaction.spec.js`. Comprobar recarga, parámetros, consola y selectores. Validar navegación con la misma versión, build y conformidad web.

## 7. Tareas

- [x] T1 · Cargar una sola versión al navegar después de una actualización — Resp: developer — Modelo: estandar — Archivos: parcial de scripts, prueba y fixture de navegador de Planes — Hecho cuando: la regresión pasa y la navegación normal y los selectores funcionan.
<!-- Primero lo que cambia para el usuario; el detalle técnico después. Formato:
- [x] T1 · qué cambia (en simple) — Resp: developer — Modelo: perfil — Archivos: … — Hecho cuando: … -->

## 8. Bitácora de implementación (developer)

- 2026-11-18 — Se reprodujeron las advertencias de ambos runtimes. El control de versión en el head fuerza una recarga y reserva el bundle para respuestas completas.
- 2026-11-18 — Las dos regresiones de Chromium pasaron. Se comprobó navegación parcial con versión estable, recarga al cambiarla y persistencia de los cuatro selectores tras actualizaciones Livewire. El build aislado y `git diff --check` pasaron.
- La suite amplia reportó fallos en selectores de Ajustes y en la prueba de ancho o búsqueda. `PlanModuleTest` obtuvo 23 casos correctos y cuatro con fallos o errores de lógica del módulo. `ui:conformance` reportó tres incidencias en vistas de Juego e Inicio ajenas al cambio. Queda pendiente verificar con sesión autenticada tras el despliegue.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-18 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-18 — Aprobado por el usuario (Compuerta 2)
- 2026-11-18 — Movido a in-progress/ (En desarrollo)
- 2026-11-18 — Implementación terminada → En verificación

- LECCIÓN: ninguna adicional en el cierre.
- 2026-11-18 — Cerrado por el usuario (Compuerta 3) → done/

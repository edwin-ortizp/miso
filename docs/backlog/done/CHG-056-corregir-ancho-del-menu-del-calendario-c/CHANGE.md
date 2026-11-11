# CHG-056 · Corregir ancho del menú del calendario CHG-052
**Estado:** Cerrado
**Nivel:** XS
**Modelo sugerido:** rapido
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-10 · **Aprobado:** 2026-11-10 · **Cerrado:** 2026-11-10
**Relacionado:** CHG-052

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: Corregir ancho del menú del calendario CHG-052
- Cómo comprobarlo tú: revisarlo en la pantalla afectada

## 7. Tareas
<!-- Primero lo que cambia para el usuario; el detalle técnico después. Formato:
- [ ] T1 · qué cambia (en simple) — Resp: developer — Modelo: perfil — Archivos: … — Hecho cuando: … -->
- [x] T1 · Corregir ancho del menú del calendario CHG-052 — Resp: developer — Hecho cuando: menú compacto y estable en las cuatro vistas a 390/768/1440 px
## 8. Bitácora de implementación (developer)
- Usuario aprobó la corrección. CHG-052 ya estaba Cerrado y CLI rechazó reabrir; se registra seguimiento XS con la misma autorización.
- Menú del calendario: right:auto y width:max-content; conserva mínimo 210px y máximo del viewport. No se modifica el menú compartido.
- Superficie Alpine con wire:ignore para que actualizaciones Livewire no retiren la posición calculada (detectado al alternar vistas en tablet).
- Prueba de regresión: ancho compacto, anclaje, límites, estabilidad entre vistas/tarjetas y capturas con menú abierto.
- npm run build y 18 pruebas JS aprobadas. git diff --check aprobado. ui:conformance conserva desviaciones previas en task-list.blade.php; ninguna del calendario.


## 9. Preguntas abiertas, decisiones e historial
- 2026-11-10 — Cambio rápido (XS) aprobado por solicitud directa del usuario → in-progress/

- Browser: 3/3 pruebas aprobadas (390/768/1440), cuatro vistas, dos tarjetas y teclado. Capturas del menú abierto inspeccionadas en escritorio y móvil: ancho compacto y sin recorte.
- 2026-11-10 — Implementación terminada → En verificación
- 2026-11-10 — Cerrado por el usuario (Compuerta 3) → done/

- LECCIÓN(qa-tester): Verificar ancho y anclaje del menú abierto, además de límites del viewport; repetir tras actualizaciones Livewire para detectar pérdida de estilos de posición. → docs/TESTING.md

# CHG-110 · Alinear controles del header móvil
**Estado:** Cerrado
**Nivel:** XS
**Modelo sugerido:** rapido
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-14 · **Aprobado:** 2026-11-14 · **Cerrado:** 2026-11-16
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: Alinear controles del header móvil
- Cómo comprobarlo tú: en celular, búsqueda, ajustes, notificaciones y perfil quedan a la derecha con títulos cortos y largos; la búsqueda expandida y los menús siguen funcionando.

## 7. Tareas
<!-- Primero lo que cambia para el usuario; el detalle técnico después. Formato:
- [ ] T1 · qué cambia (en simple) — Resp: developer — Modelo: perfil — Archivos: … — Hecho cuando: … -->
- [x] T1 · Alinear controles del header móvil — Resp: developer — Archivos: resources/css/m3/archetypes/_app-frame.css — Hecho cuando: controles alineados a la derecha sin solapamiento en 320/360/390 px, búsqueda y menús operativos, sin regresión en 768 px y escritorio; build y conformance comprobados.
## 8. Bitácora de implementación (developer)
- 2026-11-14 — Implementado el plan aprobado: título con flex: 1 1 0 exclusivamente bajo 768 px, manteniendo min-width y elipsis. CSS público regenerado por npm run build. Sin cambios de lógica, orden ni etiquetas.
- 2026-11-14 — Playwright contra aplicación Laravel con fixture SQLite dedicado: 320/360/390 px, títulos cortos y largos, controles al margen derecho de 12 px sin solapamiento ni desbordamiento. Búsqueda con foco automático, cierre y restauración de herramientas; perfil y notificaciones abren/cierran y permanecen dentro del viewport en los cinco anchos. Zona segura derecha simulada de 20 px produce margen de 32 px. Escritorio 1440 correcto. Cero errores JavaScript. Captura móvil inspeccionada en storage/framework/testing/header-mobile.png; resultados y script temporal en la misma carpeta.
- 2026-11-14 — Hallazgo fuera del alcance: en 768 px un título muy largo desborda el header (72.3125 px); confirmado idéntico al comportamiento anterior, ya que la regla modificada solo aplica bajo 768 px. Título corto correcto en 768 px. No se amplía el ajuste aprobado.
- 2026-11-14 — npm run build pasa (aviso de chunk grande); git diff --check pasa. ui:conformance falla por infracciones preexistentes en vistas de juego, check-in y tareas sin modificar. CssLayerContractTest: 3 pasan, 2 fallan por dependencias/overrides preexistentes en _text-fields.css y módulos ajenos. No se ejecuta suite completa para este cambio de una declaración CSS.
- 2026-11-14 — CLI instalado 0.11.0 no admite context ni activity; lineamientos disponibles leídos directamente y evidencia registrada aquí. docs/DOCUMENTATION.md no existe. Sin cambios a skills ni actualización del CLI.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-14 — Cambio rápido (XS) aprobado por solicitud directa del usuario → in-progress/
- Plan aprobado: mantener búsqueda, ajustes, notificaciones y perfil a la derecha con un único layout responsive; modificar solamente la distribución del título móvil. Sin APIs ni cambios funcionales.
- LECCIÓN: ninguna.
- 2026-11-14 — Implementación terminada → En verificación
- 2026-11-16 — Cerrado por el usuario (Compuerta 3) → done/

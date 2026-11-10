# CHG-029 · Temporada como aventura ilustrada
**Estado:** En verificación
**Nivel:** M
**Modelo sugerido:** estandar
**Creado:** 2026-11-09 · **Aprobado:** 2026-11-09 · **Cerrado:** —
## 0. En palabras simples
Una portada de aventura y misiones visibles explican qué hacer y qué ganar.
## 1. Resumen
Implementar el plan aprobado en conversación: Temporada como aventura ilustrada.
## 2. Producto (product-manager)
Incluye portada, recorrido temporal, misiones reales, bonos, acceso a balls y administración secundaria.
No incluye reglas nuevas, premios finales, migraciones ni cambios MCP.
Aceptación: al entrar se ven objetivos reales, progreso y recompensas sin navegar a Semana.
## 3. UX/UI
### 3.1 Experiencia (ux-designer)
Una pantalla; enlaces a tareas, hábitos y balls. Estados sin temporada, sin equipo, sin misiones y completadas. Teclado y foco visibles.
### 3.2 Visual (ui-designer)
SVG local, compañeros existentes, tokens Material, tarjetas y una columna móvil.
```text
[ Portada: nombre, fechas, compañeros ]
[ Semana 1 -- Semana 2 -- Semana 3 -- Semana 4 ]
[ Tu próxima aventura: misiones y recompensas ]
[ Bonos de experiencia ] [ Cómo jugar / balls ]
[ Detalles e historial desplegables ]
```
### 3.3 Escritura (ux-writer)
Textos cálidos, objetivos concretos y recompensas adicionales explícitas. Sin culpa ni promesas ficticias.
## 4. Diseño técnico (software-architect)
GameSeasonView y vista, componente compartido de misiones, CSS de game y SVG local.
Consultas restringidas por usuario; conservar misiones y confirmación de reversión.
## 5. Pruebas (qa-tester)
Pruebas afectadas de Temporada y Semana; build, conformance, responsive y diff.
## 6. Documentación (tech-writer)
Este CHANGE. Las guías PRODUCT, ARCHITECTURE, UX-DESIGN, UI-DESIGN, UX-WRITING, TESTING y DOCUMENTATION no existen en este checkout; se usa docs/design-system.md.
## 7. Tareas
- [x] T1 · Portada y recorrido de temporada — Resp: developer — Modelo: estandar — Hecho cuando: datos reales y diseño responsive.
- [x] T2 · Misiones compartidas y bonos claros — Resp: developer — Modelo: estandar — Hecho cuando: objetivos y recompensas conservados.
- [x] T3 · Validar comportamiento y presentación — Resp: developer — Modelo: estandar — Hecho cuando: checks ejecutados y límites registrados.
- [x] T4 · Misiones como cartas de aventura — Resp: developer — Modelo: estandar — Hecho cuando: tres columnas en escritorio, dos en tablet y una móvil; emblemas, progreso y recompensas visuales sin cambiar Semana.
- [x] T5 · Validar tres cartas simultáneas y estados — Resp: developer — Modelo: estandar — Hecho cuando: títulos largos, tareas concretas, recompensas reales, teclado, capturas y contraste comprobados.
## 8. Bitácora de implementación (developer)
- Implementadas portada SVG local, compañeros del equipo o colección, recorrido temporal y misiones existentes sin creación ni reemplazo.
- Componente compartido de misiones: Semana mantiene su presentación; Temporada añade objetivos y recompensas reales.
- Bonos porcentuales, acceso a balls y administración secundaria con confirmación conservada.
- 24 pruebas pasan: GameSeasonViewTest, GameWeekViewTest y WeekMissionsProposalTest. APP_URL=http://localhost se fijó solo en el proceso de pruebas: la URL local con subruta provocaba 404 en el arnés.
- Build y ui:conformance pasan, sin deuda visual nueva; git diff --check limpio.
- Playwright sobre HTML renderizado por Laravel con datos de prueba, assets y JavaScript reales: 360/768/1440 sin overflow; detalles operables con Enter, enlace al ancla de misiones, axe sin infracciones en aventura en claro/oscuro y animación desactivada con movimiento reducido. Capturas en storage/framework/testing/season-*.png.
- Reversión y aislamiento verificados en pruebas Livewire/PHP; no se ejecutaron escrituras en datos reales ni despliegue.
- CssLayerContractTest: 3 pasan y 2 fallan por dependencias/overrides preexistentes en otros módulos; no reporta estilos nuevos de game. No se amplió este cambio a esas correcciones.
- Se preservó el trabajo concurrente CHG-030 en scripts/publish-vite-css.mjs; el CSS publicado incluye la salida vigente del compilador.
## 9. Preguntas abiertas, decisiones e historial
Sin preguntas abiertas. Plan aprobado explícitamente por el usuario en conversación.
- 2026-11-09 — Aprobado por el usuario (Compuerta 2)
- 2026-11-09 — Movido a in-progress/ (En desarrollo)
- 2026-11-09 — Implementación terminada → En verificación
- 2026-11-09 — Reabierto: Ajuste visual aprobado: misiones como cartas de aventura

### Ajuste visual aprobado: cartas de aventura
- Portada sin cambios. Cartas con emblemas ilustrados por tipo, un solo título, progreso segmentado hasta 6 unidades y barra nativa para metas mayores.
- Dos hitos visuales con balls y cantidades configuradas; estados por comenzar, en marcha, recompensa parcial y misión cumplida. Acciones al pie y tareas concretas legibles sin recorte.
- Semana mantiene la lista previa. No se modificaron reglas ni datos reales.
- 26 pruebas pasan (Temporada, Semana y propuestas semanales). Build y conformance pasan sin deuda visual nueva; diff limpio.
- Capturas revisadas con tres misiones simultáneas a 360, 768, 1280 y 1600 px, tanto ordinarias como con tareas largas: una/dos/tres columnas, sin overflow. Artefactos locales: storage/framework/testing/quests-standard-*.png y quests-*.png.
- Playwright sobre HTML renderizado por Laravel con datos de prueba y assets/JavaScript reales: axe sin infracciones en el bloque de misiones a los cuatro anchos y en oscuro, foco por teclado y destinos de enlaces comprobados. No se realizaron acciones sobre datos reales.
- 2026-11-09 — Implementación terminada → En verificación

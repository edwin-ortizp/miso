# CHG-146 · Ciclo menstrual con calendario e historial en cards
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-17 · **Aprobado:** 2026-11-17 · **Cerrado:** 2026-11-17
**Relacionado:** CHG-130, CHG-140

## 0. En palabras simples
- Qué vas a ver distinto: la pantalla de Ciclo pierde los textos explicativos sueltos. El calendario queda dentro de una tarjeta, con días más claros, y el historial de ciclos es una tabla de gestión como la de Mediciones, con paginación y acciones por fila.
- Cómo comprobarlo tú: abre Ciclo; verifica que el calendario y el historial están en tarjetas, que se entiende qué día es periodo, hoy y estimado sin leer párrafos, y que en el teléfono el historial se apila bien.

## 1. Resumen
La vista de Ciclo usa `x-ui.section` sueltas: el calendario es una tabla cruda, el historial no sigue el patrón de tabla de gestión del sistema de diseño y hay avisos y ayudas repartidos por toda la pantalla. Se lleva el historial a `x-ui.management-card`, el calendario a una tarjeta propia con rejilla rediseñada, y se consolidan los textos conservando los de CHG-130. No cambian cálculos, modelos ni validaciones.

## 2. Producto (product-manager)
- Problema / usuario: la pantalla se siente improvisada frente a las demás de Salud; los textos repetidos estorban y el calendario es difícil de leer.
- Incluye:
  - Historial de ciclos en card de gestión (contador, orden, paginación, ⋮ «Actualizar tabla», estado vacío, acciones por fila).
  - Calendario en card con encabezado (mes, anterior/siguiente, «Hoy»), rejilla de 7 columnas y leyenda compacta.
  - «Ciclo actual» y «Próximo periodo» en una sola card de resumen.
  - Consolidación de textos explicativos.
- No incluye: cambios de cálculo de ciclo, estimaciones, modelos, avisos médicos (se conservan literales), modales (CHG-140).
- Criterios de aceptación (Dado/Cuando/Entonces):
  - Dado el historial, cuando hay más ciclos que la página, entonces se pagina y se ordena por inicio con tabla apilada en móvil.
  - Dado el calendario, cuando un día tiene periodo, registro, es hoy o es estimado, entonces se distingue por forma e ícono además del color y tiene `aria-label`.
  - Dado tocar un día, cuando se pulsa, entonces abre «Registrar el día» como hoy.
  - Dado la pantalla, cuando se abre, entonces no hay párrafos explicativos sueltos fuera de su card, y el aviso médico sigue visible.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: dashboard/detalle del módulo con cards; historial como card de gestión (`docs/design-system.md` «Card de gestión»); el calendario no usa card de gestión.
- Flujo y cantidad de pasos: igual que hoy.
- Qué contiene cada pantalla y qué es lo más importante: aviso breve, resumen (día del ciclo y próximo periodo), calendario, registros del mes e historial. Lo más importante es el estado actual y el calendario.
- Estados (vacío / carga / error / éxito / validaciones): historial vacío con `x-ui.state`; resumen sin datos con la nota de 3 inicios.
- Información necesaria y momento de presentación: la estimación y su nota viven en la card de resumen; la leyenda al pie del calendario.
- Accesibilidad de uso (teclado, foco, errores): botón por día con `aria-label` completo, foco visible, objetivo ≥44 px, estado no solo por color.

```
┌ 💧 Resumen ─────────────────────────────┐
│ Día 12 del ciclo   Próximo: 3–7 dic     │
│ Es una estimación: tu ciclo puede variar│
└─────────────────────────────────────────┘
┌ 📅 Noviembre 2026   ‹  Hoy  ›           ┐
│ L  M  X  J  V  S  D                     │
│ ·  ·  💧 💧 💧 ·  ·                     │
│ (hoy)  ◌ estimado                       │
│ 💧 Periodo  ● Registro  ◌ Estimado      │
└─────────────────────────────────────────┘
┌ 💧 Historial de ciclos (6)         ⋮    ┐
│ Inicio  Fin  Periodo  Ciclo   acciones  │
└─────────────────────────────────────────┘
```

### 3.2 Visual (ui-designer)
- Distribución: cards `md-mcard`; calendario con rejilla CSS de 7 columnas y celdas cuadradas (`md-cycle-grid`), sin `md-table`.
- Componentes existentes a reutilizar (ruta): `x-ui.management-card`, `x-ui.badge`, `x-ui.icon-action`, `x-ui.destructive-action`, `x-ui.state`; referencia `resources/views/livewire/health/health-measurements.blade.php`.
- Jerarquía, color, tipografía y espaciado (tokens): tokens M3 existentes; sin estilos en línea ni colores directos.
- Responsive y contraste: 360/390/1440 px, contraste AA, sin desborde horizontal.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: aviso bajo el módulo, resumen, leyenda y formulario.
- Textos finales (variables y variantes): se conservan literales los de CHG-130 (aviso médico, «Tiende a empezar entre…», «Es una estimación: tu ciclo puede variar.», «Con 3 inicios registrados…», confirmación de eliminar). Se eliminan ayudas redundantes de las secciones del formulario.
- Reglas de docs/UX-WRITING.md aplicadas: ayuda solo cuando sirve para decidir; sin términos como «irregular» o «anormal».
- Criterios de aceptación de los textos: ningún aviso médico ni de privacidad cambia de sentido.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar: `resources/views/livewire/health/cycle.blade.php`, `app/Livewire/Health/Cycle.php` (trait `WithManagementCard`, orden y paginación de ciclos), `resources/css/m3/modules/_health.css`, tests de Ciclo, `docs/UX-WRITING.md` si cambia la ubicación de textos.
- Enfoque y patrones a seguir: copiar la estructura de `health-measurements.blade.php:44-114`; paginador con `perPage` por defecto; calendario con la misma data `weeks` ya calculada.
- Riesgos y mitigación: guardian rail y diálogo comparten la vista (CHG-137/140) → tocar solo secciones listadas; `ui:conformance` → sin `style=`.
- Qué NO tocar: `app/Actions/LogCycle*`, modelos, cálculos de `summary`, el MCP.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Historial en card con paginación y orden | Feature | 1 | `CycleModuleTest` |
| Calendario marca periodo, hoy y estimado | Feature | 1 | `CycleModuleTest` |
| Sin infracciones nuevas | Feature | 2 | `UiConformanceTest` |
| Tarjetas y tabla apilada en 360/390/1440 | Browser | 3 | `tests/Browser/health-dialogs.interaction.spec.js` |
- Comando para verificar: `php artisan test --filter=Cycle`, `php artisan ui:conformance`, `npm run build`, capturas a 390 y 1440.
- Regresión a vigilar: `health-guardian.interaction.spec.js`, `ScreenArchetypeTest`.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/UX-WRITING.md` (CHG-130, ubicación de textos del ciclo).

## 7. Tareas
- [x] T1 · Historial de ciclos como tabla de gestión — Resp: developer — Modelo: estandar — Archivos: `cycle.blade.php`, `Cycle.php` — Hecho cuando: card con contador, orden, paginación, estado vacío y acciones por fila.
- [x] T2 · Calendario en card con rejilla nueva — Resp: developer — Modelo: estandar — Archivos: `cycle.blade.php`, `_health.css` — Hecho cuando: cumple el boceto, estados por forma e ícono y 44 px.
- [x] T3 · Resumen unificado y textos consolidados — Resp: developer — Modelo: estandar — Archivos: `cycle.blade.php` — Hecho cuando: sin párrafos sueltos y textos de CHG-130 intactos.
- [x] T4 · Pruebas y documentación — Resp: developer — Modelo: estandar — Archivos: tests, docs — Hecho cuando: pasan los tests y capturas a 390/1440.

## 8. Bitácora de implementación (developer)
- 2026-11-17 — Implementado: historial de ciclos como `x-ui.management-card` (contador, paginación con `WithManagementCard`, badge «En curso», acciones por fila); calendario en card con rejilla CSS de 7 columnas (`md-cycle-grid`), celdas de 44 px, periodo/hoy/registrado/estimado por forma, ícono y `aria-label`, y leyenda al pie; «Ciclo actual» y «Próximo periodo» unidos en una card «Resumen»; aviso médico único bajo el módulo y ayudas redundantes del formulario retiradas, con los textos de CHG-130 intactos.
- Verificación local: `CycleModuleTest` (14, con dos pruebas nuevas de historial y calendario) y `CycleProgressTest` en verde; `npm run build` correcto; capturas a 390 y 1440 px revisadas. La spec de navegador `health-dialogs.interaction.spec.js` no se ejecutó en este entorno.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-17 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-17 — Especificado a partir del plan de pulido aceptado en la conversación; pendiente la Compuerta 2 (aprobación del usuario).
- 2026-11-17 — Aprobado por el usuario (Compuerta 2)
- 2026-11-17 — Movido a in-progress/ (En desarrollo)
- 2026-11-17 — Implementación terminada → En verificación
- 2026-11-17 — Cerrado por el usuario (Compuerta 3) → done/
- LECCIÓN: ninguna

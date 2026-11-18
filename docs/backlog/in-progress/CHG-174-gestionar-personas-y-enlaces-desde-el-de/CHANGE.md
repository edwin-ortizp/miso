# CHG-174 · Gestionar personas y enlaces desde el detalle del plan
**Estado:** En verificación
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-18 · **Aprobado:** 2026-11-18 · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
- Qué vas a ver distinto: Gestionar personas y enlaces en el editor existente desde cada tarjeta, con sección inicial y validación visible.
- Cómo comprobarlo tú: Abre Planes o Calendario y prueba el recorrido descrito.

## 1. Resumen
Gestionar personas y enlaces en el editor existente desde cada tarjeta, con sección inicial y validación visible.

## 2. Producto
Entrega independiente del plan aprobado de mejoras de Planes y Calendario. Criterio de aceptación: Gestionar personas y enlaces en el editor existente desde cada tarjeta, con sección inicial y validación visible.

## 3. UX/UI
### 3.1 Experiencia
Plantilla: detalle y editor

Los botones de estado vacío y los menús de las tarjetas abren el editor compartido y su sección indicada. La tarjeta Datos y la creación abren Información básica.

### 3.2 Visual
```text
Personas asociadas  ⋮  [Asociar personas]
Enlaces             ⋮  [Compartir enlace]
```

### 3.3 Escritura
Usar las etiquetas del boceto y los textos del plan aprobado.

## 4. Diseño técnico
El diálogo acepta una sección inicial y observa el último intento inválido para enfocar su sección. Una asociación directa se puede quitar sin borrar la participación en visitas.

Archivos: resources/views/livewire/plan, resources/views/components/ui/form-dialog.blade.php, app/Livewire/Plan/Concerns/ManagesPlanDialogs.php, tests/Browser/plans.interaction.spec.js

## 5. Pruebas
Playwright comprueba apertura, guardado, cancelación y conservación del borrador si otra sección presenta un error.

## 6. Documentación
No aplica — recorrido cubierto por la especificación y las pruebas.

## 7. Tareas
- [x] T1 · Gestionar personas y enlaces en el editor existente desde cada tarjeta, con sección inicial y validación visible — Resp: developer — Archivos: resources/views/livewire/plan, resources/views/components/ui/form-dialog.blade.php, app/Livewire/Plan/Concerns/ManagesPlanDialogs.php, tests/Browser/plans.interaction.spec.js — Hecho cuando: los criterios y las pruebas dirigidas estén verificados

## 8. Bitácora de implementación (developer)

- T1. El editor compartido abre Personas y círculos o Enlaces desde cada tarjeta y enfoca los errores de guardado. Los recorridos de escritorio y móvil pasaron en Playwright.
- Validación común: 29 pruebas PHP dirigidas, 4 escenarios Playwright y npm run build. ui:conformance conserva tres hallazgos previos en Game y Mi día. El CLI instalado carece de context y activity, por lo que el registro de evidencia es manual.

## 9. Preguntas abiertas, decisiones e historial
- LECCIÓN: ninguna.
- 2026-11-18 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-18 — Aprobado por el usuario (Compuerta 2)
- 2026-11-18 — Movido a in-progress/ (En desarrollo)
- 2026-11-18 — Implementación terminada → En verificación

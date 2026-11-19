# CHG-175 · Normalizar ubicaciones de planes y abrir mapas
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-18 · **Aprobado:** 2026-11-18 · **Cerrado:** 2026-11-18
**Relacionado:** —

## 0. En palabras simples
- Qué vas a ver distinto: Importar países y ciudades GeoNames, seleccionar país y ciudad opcionales, normalizar explícitamente ciudades anteriores al editar, conservar filtros y herramientas y abrir direcciones en Google Maps o Waze.
- Cómo comprobarlo tú: Abre Planes o Calendario y prueba el recorrido descrito.

## 1. Resumen
Importar países y ciudades GeoNames, seleccionar país y ciudad opcionales, normalizar explícitamente ciudades anteriores al editar, conservar filtros y herramientas y abrir direcciones en Google Maps o Waze.

## 2. Producto
Entrega independiente del plan aprobado de mejoras de Planes y Calendario. Criterio de aceptación: Importar países y ciudades GeoNames, seleccionar país y ciudad opcionales, normalizar explícitamente ciudades anteriores al editar, conservar filtros y herramientas y abrir direcciones en Google Maps o Waze.

## 3. UX/UI
### 3.1 Experiencia
Plantilla: detalle y editor

País, ciudad y dirección son opcionales. Al editar un plan con ciudad anterior, se elige un equivalente o se quita el texto explícitamente.

### 3.2 Visual
```text
País [Seleccionar]  Ciudad [Buscar]
Dirección [                     ]
```

### 3.3 Escritura
Usar las etiquetas del boceto y los textos del plan aprobado.

## 4. Diseño técnico
Los catálogos usan código ISO e ID de GeoNames. El selector consulta ciudades del país por texto y el servidor valida la relación. El comando geography:import procesa el snapshot en una transacción. city conserva la etiqueta de compatibilidad y las herramientas resuelven la ciudad por ID o nombre inequívoco. El enlace de dirección usa los datos disponibles y abre el proveedor elegido.

Archivos: app/Actions/SavePlan.php, app/Console/Commands/ImportGeography.php, app/Models, app/Services/Geography, app/Mcp/Tools/Plan, app/Livewire/Plan, app/Livewire/Home/Panel/PlanEditor.php, database/migrations, resources/views, resources/css/m3/modules/_plans.css, public/css/app.css, docs/integrations/geography.md, tests/Feature, tests/Browser

## 5. Pruebas
Pruebas de búsqueda, importación, direcciones, datos históricos y herramientas. Playwright móvil y escritorio.

## 6. Documentación
Documentar la fuente, atribución y carga del catálogo.

## 7. Tareas
- [x] T1 · Importar países y ciudades GeoNames, seleccionar país y ciudad opcionales, normalizar explícitamente ciudades anteriores al editar, conservar filtros y herramientas y abrir direcciones en Google Maps o Waze — Resp: developer — Archivos: app/Actions/SavePlan.php, app/Console/Commands/ImportGeography.php, app/Models, app/Services/Geography, app/Mcp/Tools/Plan, app/Livewire/Plan, app/Livewire/Home/Panel/PlanEditor.php, database/migrations, resources/views, resources/css/m3/modules/_plans.css, public/css/app.css, docs/integrations/geography.md, tests/Feature, tests/Browser — Hecho cuando: los criterios y las pruebas dirigidas estén verificados

## 8. Bitácora de implementación (developer)

- T1. La prueba de importación repetida y rollback pasó. Se procesó dos veces el snapshot oficial en SQLite aislado, con 252 países y 237.315 ciudades. Pasaron las pruebas de validación, enlaces, compatibilidad y herramientas de MiSo. Guía de carga en docs/integrations/geography.md.
- Validación común: 29 pruebas PHP dirigidas, 4 escenarios Playwright y npm run build. ui:conformance conserva tres hallazgos previos en Game y Mi día. El CLI instalado carece de context y activity, por lo que el registro de evidencia es manual.

## 9. Preguntas abiertas, decisiones e historial
- LECCIÓN: ninguna.
- 2026-11-18 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-18 — Aprobado por el usuario (Compuerta 2)
- 2026-11-18 — Movido a in-progress/ (En desarrollo)
- 2026-11-18 — Implementación terminada → En verificación
- 2026-11-18 — Cerrado por el usuario (Compuerta 3) → done/

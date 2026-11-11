# CHG-064 · Guardianes en Comidas, Relaciones, Planes y Vehículos
**Estado:** En verificación
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-10 · **Aprobado:** 2026-11-10 · **Cerrado:** —
**Relacionado:** CHG-057, CHG-058, CHG-062, CHG-063 (guardianes)

## 0. En palabras simples
- Qué vas a ver distinto: Comidas, Relaciones, Planes y Vehículos tienen guardián con consejos: qué comida toca hoy, cumpleaños que vienen, alguien con quien no hablas hace tiempo, planes pendientes por estrenar o el próximo mantenimiento del carro. En Relaciones y Planes aparece arriba de su panel derecho; en Comidas y Vehículos estrenan panel derecho.
- Cómo comprobarlo tú: abre cada módulo en escritorio → panel derecho con el guardián; toca recargar → otro consejo; toca al guardián → su ficha sin salir del módulo.

## 1. Resumen
Cuatro proveedores nuevos para `GuardianAdvisor`; `GuardianCard` en el rail existente de Relaciones y Planes, y rail nuevo (solo guardián) en la página principal de Comidas y Vehículos.

## 2. Producto (product-manager)
- Problema / usuario: completar los guardianes en todos los módulos de uso diario.
- Incluye: guardián y consejos en `/meals/weekly`, `/relationships`, `/plans`, `/vehicles`; afinidades; rail nuevo en Comidas y Vehículos.
- No incluye: otras vistas de esos módulos (compras, inventario, ficha de persona/plan/vehículo); acciones desde el globo.
- Criterios de aceptación (Dado/Cuando/Entonces):
  1. Afinidades: Comidas planta/bicho; Relaciones hada/normal; Planes volador/tierra; Vehículos acero/eléctrico; sin afinidad, el de mayor nivel.
  2. Comidas: próxima comida planificada hoy (tipo y hora); comidas de hoy sin consumir pasada su hora; días de la semana sin plan (si ≥ 2).
  3. Relaciones: cumpleaños en ≤ 7 días (nombre y cuándo); persona con la que no registras eventos hace ≥ 60 días (la de más tiempo entre las que tienen ≥ 2 eventos).
  4. Planes: plan agendado en ≤ 7 días; cantidad de planes pendientes nunca visitados (≥ 3) y uno al azar sugerido; último plan visitado hace ≥ 30 días.
  5. Vehículos: mantenimiento del plan activo vencido o que vence en ≤ 14 días (por fecha) con nombre del vehículo; días desde la última carga de combustible/energía si ≥ 21.
  6. En Relaciones y Planes el guardián va primero en su panel existente; en Comidas y Vehículos el panel nuevo solo aparece con guardián.
  7. Botón de otro consejo, refresco cada 5 min y ficha en modal funcionan igual que en los demás módulos.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: módulo con panel contextual (rail).
- Flujo y cantidad de pasos: 0 pasos; 1 clic para otro consejo o ficha.
- Qué contiene cada pantalla y qué es lo más importante: Relaciones/Planes: guardián encima de sus tarjetas actuales; Comidas/Vehículos: panel solo con "Tu guardián".
- Estados (vacío / carga / error / éxito / validaciones): con guardián; sin guardián → como hoy.
- Información necesaria y momento de presentación: ninguna adicional.
- Accesibilidad de uso (teclado, foco, errores): igual que CHG-057/062.

```
Relaciones (rail existente):
┌─ 👥 Resumen ──────────────────────────┐
│░ (◕‿◕) Clefairy · Nv 3              ░│
│░  "Ana cumple años el viernes."  [↻]░│
│ Activas                         24    │
└───────────────────────────────────────┘
Vehículos (rail nuevo):
┌─ Garaje ─────────────────────────────┬─ ✨ Tu guardián ──────────┐
│ (vehículos, igual que hoy)           │░ (◕‿◕) Magnemite · Nv 2 ░│
│                                      │░ "Cambio de aceite del   ░│
│                                      │░  Mazda en 6 días." [↻]  ░│
└──────────────────────────────────────┴──────────────────────────┘
```

### 3.2 Visual (ui-designer)
- Distribución: Relaciones → dentro del widget "Resumen" (primero); Planes → al inicio de su primera tarjeta del rail; Comidas/Vehículos → `x-context-widget title="Tu guardián" icon="bi-stars"`. `variant="top"` en < 1200 px en los cuatro.
- Componentes existentes a reutilizar (ruta): `livewire:game.guardian-card`, `x-context-widget`, `.game-guardian*`.
- Jerarquía, color, tipografía y espaciado (tokens): los de CHG-055/057.
- Responsive y contraste: igual que los demás módulos.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: globo del guardián.
- Textos finales (config `game.guardian_advice`):
  - meals.next: "Hoy toca {meal} a las {time}."
  - meals.pending: "{meal} de hoy sigue sin marcar; ¿ya comiste?"
  - meals.unplanned: "{days} días de esta semana aún no tienen plan de comidas."
  - relationships.birthday: "{person} cumple años {when}."
  - relationships.distance: "Hace {days} días no registras nada con {person}; ¿un saludo?"
  - plans.soon: "{plan} está agendado {when}."
  - plans.pending: "Tienes {count} planes por estrenar; ¿qué tal {plan}?"
  - plans.idle: "Tu último plan fue hace {days} días; ¿salimos?"
  - vehicles.maintenance: "{task} de {vehicle} {when}."
  - vehicles.fuel: "Hace {days} días no registras carga en {vehicle}."
- Reglas de docs/UX-WRITING.md aplicadas: tuteo, cálido, sin culpa; ≤ 90 caracteres; sin palabras de `MissionProposalValidator::penaltyWord`.
- Criterios de aceptación de los textos: test de plantillas ampliado a los 4 módulos.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar:
  - `app/Services/Game/Guardian/MealAdvice.php` (`MealPlanEntry` date/meal_type/scheduled_time/status/consumed_at), `RelationshipAdvice.php` (`Relationship` birthday_date/full_name/nickname, `RelationshipEvent` relationship_id/event_date), `PlanAdvice.php` (`Plan` status/scheduled_on/title, `PlanVisit` visited_on), `VehicleAdvice.php` (`Vehicle` name, `VehicleMaintenancePlan` interval_days/baseline_date/active + plantilla, `VehicleEnergyLog` recorded_on). ≤ 4 consultas cada uno; reusar cálculos de vencimiento existentes del módulo de vehículos si los hay.
  - `GuardianAdvisor`: registrar `meals`, `relationships`, `plans`, `vehicles`.
  - `config/game.php`: afinidades y textos. `config/modules.php`: `rail => true` + `rail_routes` en meals (`meals.weekly`) y vehicles (`vehicles`).
  - Vistas: `relationship/relationship-index` y `plan/plan-index` (card en el rail existente + `top`); `meal/meal-weekly` y `vehicle/vehicle-index` (rail nuevo condicionado a `GuardianCard::visibleFor()` + `top`; si `vehicle-index` no usa `x-module-shell`, se envuelve como las demás vistas para poder declarar el rail).
- Enfoque y patrones a seguir: contrato `candidates()` + trait `Advice`.
- Riesgos y mitigación: datos personales de contactos → solo nombres ya visibles en la app, escapados; vehículo sin `x-module-shell` → envolver sin cambiar su contenido; rail vacío → condicionado.
- Qué NO tocar: lógica de comidas, inventario, relaciones, planes y mantenimiento.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Reglas de Comidas, Relaciones, Planes y Vehículos (criterios 2-5) | Feature | Alto | tests/Feature/Game/GuardianAdviceTest.php |
| Afinidades y montaje en las 4 páginas; panel nuevo solo con guardián (criterios 1, 6) | Feature | Alto | tests/Feature/Game/ModuleGuardianTest.php |
| Plantillas ≤ 90 y sin palabras prohibidas | Feature | Bajo | GuardianAdviceTest |
- Comando para verificar: `php artisan test tests/Feature/Game/GuardianAdviceTest.php tests/Feature/Game/ModuleGuardianTest.php`
- Regresión a vigilar: tests de Meal, Relationship, Plan, Vehicle y ModuleExperience/ProtectedScreenContract.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/game.md` → "Consejos del guardián": 4 módulos nuevos y afinidades.

## 7. Tareas
- [x] T1 · Guardián con consejos en Relaciones y Planes, dentro de su panel actual — Resp: developer — Archivos: app/Services/Game/Guardian/RelationshipAdvice.php, app/Services/Game/Guardian/PlanAdvice.php, app/Services/Game/Guardian/GuardianAdvisor.php, config/game.php, resources/views/livewire/relationship/relationship-index.blade.php, resources/views/livewire/plan/plan-index.blade.php, tests/Feature/Game/GuardianAdviceTest.php, tests/Feature/Game/ModuleGuardianTest.php — Hecho cuando: criterios 1, 3, 4 y 7 pasan
- [x] T2 · Guardián con consejos en Comidas y Vehículos, con panel derecho nuevo — Resp: developer — Archivos: app/Services/Game/Guardian/MealAdvice.php, app/Services/Game/Guardian/VehicleAdvice.php, app/Services/Game/Guardian/GuardianAdvisor.php, config/game.php, config/modules.php, resources/views/livewire/meal/meal-weekly.blade.php, resources/views/livewire/vehicle/vehicle-index.blade.php, tests/Feature/Game/GuardianAdviceTest.php, tests/Feature/Game/ModuleGuardianTest.php — Hecho cuando: criterios 2, 5 y 6 pasan y el build compila
- [ ] T3 · Verificar criterios de aceptación — Resp: qa-tester — Hecho cuando: los 7 criterios pasan
- [x] T4 · Actualizar documentación — Resp: tech-writer — Archivos: docs/game.md — Hecho cuando: docs al día

## 8. Bitácora de implementación (developer)
- 2026-11-10 — Inicio (start --force: había otros CHG En desarrollo).
- T1 — `RelationshipAdvice` (2 consultas; cumpleaños por `birthday_month`/`birthday_day`, ajustado al último día del mes) y `PlanAdvice` (2 consultas). Guardián dentro del widget "Resumen" de Relaciones y al inicio de la primera tarjeta del panel de Planes; `variant="top"` en ambos.
- T2 — `MealAdvice` (1 consulta; etiquetas de `Dashboard::MEAL_TYPES`) y `VehicleAdvice` (3 consultas; reusa `VehicleMaintenanceStatus::forPlan` sin proyección por uso). Panel nuevo en `/meals/weekly` y `/vehicles` condicionado a `GuardianCard::visibleFor()`. `vehicle-index` pasa de `div.lt-page` + `x-page-header` a `x-module-shell module="vehicles"` (título y subtítulo salen de config/modules.php, mismos textos).
- Corrección durante el desarrollo: el `rail` de Vehículos se había insertado por error en el bloque `home` de config/modules.php (la búsqueda encontró primero `'vehicles'` en la navegación); se movió a su módulo.
- T4 — docs/game.md actualizado.
- Tests: GuardianAdviceTest 22 + ModuleGuardianTest 15 → 37/37. Game + Meal/Relationship/Plan/Vehicle/Home + Ui/ModuleExperience: 600/622; las 22 fallas existen igual sin este cambio (Relaciones y Planes por aislamiento entre usuarios, HomeDashboard, contratos de UI, NewSources). Build OK.

## 9. Preguntas abiertas, decisiones e historial
- LECCIÓN(developer): al insertar claves en config/modules.php por script, ubicar el bloque del módulo por su arquetipo/título, no por `'clave' => [`, porque la misma clave existe en `navigation` — evidencia: el rail de Vehículos terminó en `home` (CHG-064) · destino sugerido: docs/game.md (cómo agregar un guardián).
- 2026-11-10 — Decisiones (Alex): crear panel derecho donde no existe (Comidas, Vehículos); afinidades temáticas propuestas.
- 2026-11-10 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-10 — Aprobado por el usuario (Compuerta 2)
- 2026-11-10 — Movido a in-progress/ (En desarrollo)
- 2026-11-10 — Implementación terminada → En verificación

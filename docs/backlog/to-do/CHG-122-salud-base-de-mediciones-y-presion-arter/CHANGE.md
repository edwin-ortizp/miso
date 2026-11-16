# CHG-122 · Salud: base de mediciones y presión arterial
**Estado:** Borrador
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-16 · **Aprobado:** — · **Cerrado:** —
**Relacionado:** CHG-123, CHG-124, CHG-125, CHG-126, CHG-127, CHG-128

## 0. En palabras simples
- Qué vas a ver distinto: en Salud aparece una pestaña «Mediciones» donde guardas tu presión arterial (sistólica y diastólica) con fecha y hora, ves tu historial y recibes un aviso neutro si un valor sale del rango de referencia. MiSo registra y muestra tendencias; no diagnostica.
- Cómo comprobarlo tú: entra a Salud → Mediciones, registra «120/80», mira que aparezca en el historial; registra «160/100» y comprueba que se marque como fuera de rango con la invitación a consultar a tu profesional de salud.

## 1. Resumen
Hoy Salud solo guarda eventos (citas, síntomas, vacunas) y seguimiento de intensidad; no hay valores medidos con unidad. Este CHG crea la base común para todas las mediciones: la tabla `health_measurements`, un catálogo de tipos con unidad y rango de referencia, el formulario de captura, el historial, el aviso «MiSo no es un dispositivo médico» y las herramientas MCP genéricas. La presión arterial es la primera métrica y valida el esqueleto; glucosa, creatinina, peso, etc. se agregan después como entradas del catálogo (CHG-123, 124, 127).

## 2. Producto (product-manager)
- Problema / usuario: la persona quiere llevar en MiSo sus mediciones de salud y ver su evolución, y hoy no tiene dónde.
- Incluye:
  - Registrar, editar y eliminar mediciones de presión arterial (sistólica, diastólica, pulso opcional, notas).
  - Historial con filtro por fecha.
  - Marca neutra de «fuera del rango de referencia».
  - Aviso «no es dispositivo médico» visible en la pestaña.
  - Herramientas MCP `log-health-measurement-tool` y `list-health-measurements-tool`.
- No incluye: diagnóstico, recomendaciones de tratamiento, recordatorios o alertas programadas, gráficas de tendencia (CHG-128), otras métricas (CHG-123 a 127), importación desde dispositivos.
- Criterios de aceptación (Dado/Cuando/Entonces):
  - CA1. Dado un usuario autenticado, cuando registra 120/80 mmHg, entonces queda guardada con su fecha y hora y aparece en su historial.
  - CA2. Dado un valor fuera del rango de referencia, cuando se muestra, entonces lleva una marca informativa sin lenguaje de diagnóstico y con remisión al profesional de salud.
  - CA3. Dado otro usuario, cuando consulta sus mediciones por UI o MCP, entonces nunca ve las del primero.
  - CA4. Dado un valor imposible (diastólica mayor que sistólica, o fuera de límites fisiológicos), cuando guarda, entonces se rechaza con un mensaje que indica qué corregir.
  - CA5. Dado el asistente vía MCP, cuando registra una presión, entonces el resultado coincide con el de la UI.

## 3. UX/UI
<!-- Sin pantallas: escribe "No aplica — motivo" en cada parte. Con UI, approve exige un boceto ASCII en un bloque de código. Si hay flujo, agrega un diagrama en un bloque mermaid. -->
### 3.1 Experiencia (ux-designer)
- Pendiente: flujo, boceto ASCII (escritorio y móvil), estados y accesibilidad. Partir de UX-001/UX-003 de `docs/backlog/UX-DESIGN.md` (registro con un toque, campos avanzados plegados).

### 3.2 Visual (ui-designer)
- Pendiente. Reutilizar FAB + `x-ui.form-dialog`, `x-ui.management-card` y el acento del módulo desde `config/modules.php`.

### 3.3 Escritura (ux-writer)
- Pendiente. Tono respetuoso, sin humor (`docs/UX-WRITING.md`, fila de salud). Definir el texto de «fuera de rango» y del aviso de no dispositivo médico.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar:
  - Migración `create_health_measurements_table`: `uuid id`, `user_id`, `type`, `value`, `value2` nullable, `unit`, `measured_at`, `context` nullable, `notes`, `health_event_id` nullable (FK compuesta con `user_id`), `source`, `source_key`. Índice `[user_id, type, measured_at]` con nombre corto explícito.
  - `app/Models/HealthMeasurement.php` (`BelongsToUser`, `HasUuids`) con catálogo `TYPES` (etiqueta, unidad, límites fisiológicos, rango de referencia). Relación en `User`.
  - `app/Actions/LogHealthMeasurement.php` compartida entre UI y MCP (modelo: `LogDrink`).
  - `app/Livewire/Health/HealthMeasurements.php` y vista en `resources/views/livewire/health/`; ruta en `routes/web.php`; pestaña en `config/modules.php`.
  - MCP en `app/Mcp/Tools/Health/`; registro en `LifeTrackerServer`, `ToolCatalog::MODULES`, `DeleteEntryTool`, `GetRecentActivityTool`; morph map en `AppServiceProvider`.
- Enfoque y patrones a seguir: ver `EnergyEntry`/`DrinkLog` y `HealthModuleTest`. Validar en servidor.
- Riesgos y mitigación:
  - Un rango mal definido puede sonar a diagnóstico. Se muestra como «rango de referencia general», sin adjetivos clínicos.
  - Datos sensibles sin política de cifrado (ver §9).
- Qué NO tocar: `health_events`/`health_logs` y su escala de intensidad.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Guardar y listar presión (CA1) | Feature | Livewire | `HealthMeasurementsTest` |
| Fuera de rango marcado (CA2) | Feature | Livewire | `HealthMeasurementsTest` |
| Aislamiento entre usuarios (CA3) | Feature | Livewire + MCP | `HealthMeasurementsTest`, `LifeTrackerHealthMeasurementMcpTest` |
| Validación de límites (CA4) | Feature | Livewire | `HealthMeasurementsTest` |
| Paridad UI/MCP (CA5) | Feature | MCP | `LifeTrackerHealthMeasurementMcpTest` |
| Esquema por usuario | Feature | Esquema | `MultitenancySchemaTest` |
- Comando para verificar: `php artisan test --filter=HealthMeasurement`, `php artisan ui:conformance`, `npm run build`.
- Regresión a vigilar: `HealthModuleTest`, `LifeTrackerContextMcpTest`.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/PRODUCT.md` (ya actualizado en la preparación de este CHG), `docs/UX-WRITING.md` (texto de fuera de rango), `docs/mockup-system/mockups/health/`.

## 7. Tareas
<!-- Primero lo que cambia para el usuario; el detalle técnico después. -->
- [ ] T1 · Registrar y ver presión arterial en Salud → Mediciones — Resp: developer — Modelo: estandar — Archivos: migración, `HealthMeasurement`, `LogHealthMeasurement`, `HealthMeasurements`, vista, ruta, `config/modules.php` — Hecho cuando: CA1, CA2 y CA4 pasan en pruebas.
- [ ] T2 · El asistente puede registrar y consultar mediciones — Resp: developer — Modelo: estandar — Archivos: `app/Mcp/Tools/Health/*`, `LifeTrackerServer`, `ToolCatalog`, `DeleteEntryTool` — Hecho cuando: CA3 y CA5 pasan en pruebas.
- [ ] T3 · Pruebas y verificación en móvil y escritorio — Resp: developer — Modelo: estandar — Archivos: `tests/Feature/*` — Hecho cuando: los comandos de §5 pasan.

## 8. Bitácora de implementación (developer)

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-16 — Creado en to-do/ (Borrador, nivel M). Decisiones del usuario: un CHG por métrica sobre esta base; enfoque «registro y tendencias» (sin diagnóstico ni recordatorios); aviso «no es dispositivo médico».
- Abierta: ¿cifrar los valores (`encrypted`, como `Relationship.document_number`)? Impide ordenar o promediar en SQL.
- Abierta: ¿marcar las mediciones como sensibles para ocultarlas de consultas amplias de MCP (patrón `is_sensitive`)?
- Abierta: ¿rangos de referencia fijos o configurables por persona (edad, sexo, condición)?
- Nota: `docs/DOCUMENTATION.md` está citado en `CLAUDE.md` pero no existe.

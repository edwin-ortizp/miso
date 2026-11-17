# CHG-145 · Huecos y cruces de agenda en el MCP
**Estado:** Borrador
**Nivel:** M
**Modelo sugerido:** razonamiento
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-17 · **Aprobado:** — · **Cerrado:** —
**Relacionado:** CHG-142, CHG-144
**Depende de:** CHG-142 (servicio de huecos) y CHG-144 (horario fijo).

## 0. En palabras simples
- Qué vas a ver distinto: podrás pedirle a Claude cosas como «encuéntrame un hueco para esta tarea», «¿qué tareas se me cruzan esta semana?» o «ayúdame a reorganizar mi agenda», y Claude lo hará con tus datos reales, sin mover las tareas de horario fijo (reuniones).
- Cómo comprobarlo tú: pídele «¿qué se me cruza mañana?» y compara con tu calendario; pídele un hueco de 1 hora el jueves y verifica que respeta tu horario de planificar.

## 1. Resumen
El MCP ya permite crear y actualizar tareas con fechas, pero no sabe dónde hay espacio ni qué choca con qué. Se añaden herramientas de solo lectura sobre el mismo servicio que usa la UI (`TaskSlotFinder`, CHG-142): buscar huecos y detectar cruces, indicando qué tareas se pueden mover y cuáles son de horario fijo. La reorganización la propone Claude y la ejecuta con `update-task-tool` tras la confirmación de la persona.

## 2. Producto (product-manager)
- Problema / usuario: quiero reorganizar mi agenda con Claude; hoy no puede ver huecos ni cruces, y no sabe qué es inamovible.
- Incluye:
  - Herramienta `find-task-slots-tool` (nombre a confirmar): dada una duración (o una tarea), un rango y preferencia de momento, devuelve huecos libres respetando el horario para planificar.
  - Herramienta `list-schedule-conflicts-tool`: en un rango, devuelve pares/grupos de tareas con horas que se solapan, con campo `movable` (falso si es horario fijo) y las fuentes que ocupan (tareas, comidas, medicamentos).
  - Descripciones y esquema que expliquen formato de fechas, zona horaria (America/Bogota) y que las tareas de horario fijo no se mueven.
  - Actualizar las instrucciones del servidor (`LifeTrackerServer`) con el flujo: ver cruces → proponer huecos → pedir confirmación → `update-task-tool`.
  - Incluir las nuevas herramientas en `ToolCatalog` (grupo Tareas) para que aparezcan en Ajustes → Servidor MCP.
- No incluye: herramienta que mueva varias tareas de una vez; escritura automática sin confirmación; calendarios externos; cambios de la UI.
- Criterios de aceptación (Dado/Cuando/Entonces):
  - Dado un rango, cuando pido huecos de 60 min, entonces recibo huecos dentro de mi jornada, sin cruzar nada ocupado.
  - Dado un rango con tareas solapadas, cuando pido cruces, entonces recibo los grupos con cada tarea, su hora y si es movible.
  - Dada una tarea de horario fijo, cuando se consulta, entonces aparece `movable=false` y nunca se propone moverla.
  - Dado otro usuario, cuando consulta, entonces solo ve los suyos.
  - Dados rangos inválidos o demasiado grandes, cuando se piden, entonces se responde con un error claro.

## 3. UX/UI
No aplica — no hay pantalla nueva. La experiencia es la conversación con Claude.
### 3.1 Experiencia (ux-designer)
- Flujo y cantidad de pasos: No aplica — el flujo conversacional va en las instrucciones del servidor.
- Qué contiene cada pantalla y qué es lo más importante: No aplica.
- Estados (vacío / carga / error / éxito / validaciones): No aplica a UI; las respuestas de error de las herramientas son claras y sin culpa.
- Información necesaria y momento de presentación: No aplica.
- Accesibilidad de uso: No aplica.
### 3.2 Visual (ui-designer)
- No aplica — sin cambios visuales (solo aparece el nombre de las herramientas en la lista de Ajustes → Servidor MCP).
### 3.3 Escritura (ux-writer)
- Ubicación y estado: descripciones de herramientas y mensajes de error.
- Textos finales (variables y variantes): descripciones en español, con ejemplo de formato `YYYY-MM-DD HH:MM`; error: «El rango es demasiado grande. Pide como máximo {n} días.»
- Reglas de docs/UX-WRITING.md aplicadas: tuteo, claridad, sin culpa.
- Criterios de aceptación de los textos: la descripción dice explícitamente qué es «movable».

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar: crear `app/Mcp/Tools/Task/FindTaskSlotsTool.php` y `ListScheduleConflictsTool.php`; registrar en `app/Mcp/Servers/LifeTrackerServer.php` (`$tools`, instrucciones) y `app/Mcp/Support/ToolCatalog.php`; extender `TaskSlotFinder` con la consulta de cruces si el CHG-142 no la incluye; actualizar la descripción de `UpdateTaskTool`.
- Enfoque y patrones a seguir: herramientas de solo lectura; lógica solo en el servicio compartido; límites de rango; respetar presupuestos de consultas (CHG-135); `ListTasksTool` gana filtro por rango de fechas si hace falta para el flujo.
- Riesgos y mitigación: respuestas muy largas → límite de huecos y de grupos; diferencias entre UI y MCP → mismas pruebas del servicio; zona horaria → todo en America/Bogota.
- Qué NO tocar: UI, esquema (el campo de horario fijo lo pone el CHG-144).

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Huecos respetan jornada y ocupación | Feature | 1 | `LifeTrackerTaskSlotsMcpTest` |
| Cruces agrupados y campo `movable` | Feature | 1 | `LifeTrackerTaskSlotsMcpTest` |
| Fijas no se proponen como movibles | Feature | 1 | `LifeTrackerTaskSlotsMcpTest` |
| Aislamiento entre usuarios | Feature | 2 | `LifeTrackerTaskSlotsMcpTest` |
| Validación de rangos | Feature | 2 | `LifeTrackerTaskSlotsMcpTest` |
| Herramientas en el catálogo | Feature | 2 | prueba de `ToolCatalog` |
- Comando para verificar: `php artisan test --filter=Mcp`, suite contra MySQL con `timeout`, `git diff --check`.
- Regresión a vigilar: `LifeTrackerTaskMcpTest`, `BackendQueryBudgetTest`.

## 6. Documentación (tech-writer)
- Docs a actualizar: documentación del MCP de MiSo (lista de herramientas y flujo de reorganización).

## 7. Tareas
- [ ] T1 · Herramienta para buscar huecos — Resp: developer — Modelo: razonamiento — Archivos: `FindTaskSlotsTool.php` — Hecho cuando: devuelve huecos correctos con límites.
- [ ] T2 · Herramienta para listar cruces con `movable` — Resp: developer — Modelo: razonamiento — Archivos: `ListScheduleConflictsTool.php`, `TaskSlotFinder` — Hecho cuando: agrupa cruces y marca las fijas.
- [ ] T3 · Registro, catálogo e instrucciones del servidor — Resp: developer — Modelo: estandar — Archivos: `LifeTrackerServer.php`, `ToolCatalog.php` — Hecho cuando: aparecen en Ajustes → Servidor MCP y las instrucciones describen el flujo.
- [ ] T4 · Pruebas y documentación — Resp: developer — Modelo: estandar — Archivos: tests, docs — Hecho cuando: pasa la sección 5.

## 8. Bitácora de implementación (developer)

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-17 — Creado a partir de la decisión del usuario: el MCP debe poder encontrar huecos y detectar qué tareas se cruzan para ayudar a reorganizar la agenda; las tareas con agenda bloqueada (reuniones) no se mueven.
- 2026-11-17 — Decisión delegada por el usuario («identifica tú la mejor manera»): nombres `find-task-slots-tool` y `list-schedule-conflicts-tool`.

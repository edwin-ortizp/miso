# CHG-187 · Reprogramación confirmada de tareas desde el MCP
**Estado:** Borrador
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-19 · **Aprobado:** — · **Cerrado:** —
**Relacionado:** CHG-186

## 0. En palabras simples
Revisa una propuesta de horarios y duraciones antes de aplicarla. Verifica que puedes intercambiar dos tareas y que un conflicto nuevo impide guardar todo el lote.

## 1. Resumen
Segunda entrega. Requiere CHG-186 y añade vista previa y aplicación transaccional.

## 2. Producto
Solo se permiten tareas propias, incompletas y flexibles. Se permite asignar o ajustar duración. La propuesta muestra el vencimiento sin hora que reemplazará, igual que Buscar un hueco. El usuario confirma en la conversación.

## 3. UX/UI
No aplica. Las herramientas se consumen desde clientes MCP y se mantienen las pantallas existentes.

## 4. Diseño técnico
preview-task-reschedule-tool recibe 1 a 20 tareas por ID o código e inicio y fin local. Valida el lote, muestra antes y después y emite un identificador opaco vinculado al usuario por 15 minutos. apply-task-reschedule-tool requiere identificador y confirm=true. Reutilizar PlanningHours, TaskSlotFinder y ScheduleTaskInSlot. Excluir los bloques previos del lote y comprobar margen entre nuevos bloques. Serializar por usuario con Buscar un hueco, bloquear filas en orden estable y releer estados, jornada y ocupación en una transacción. Rechazar cambios obsoletos o conflictos sin escrituras parciales. Consumir el identificador tras aplicar. Conservar update-task-tool y dirigir la reorganización a las herramientas nuevas.

## 5. Pruebas
Verificar cambio de duración, intercambio, vista previa sin escrituras, conflictos sobrevenidos, tareas fijas y completadas, rollback, identificadores ajenos, vencidos y reutilizados, y aplicaciones concurrentes. Ejecutar pruebas afectadas en SQLite y MySQL de pruebas.

## 6. Documentación
Actualizar docs/integrations/agenda-mcp.md con el flujo de propuesta y aprobación.

## 7. Tareas
- [ ] T1 · Implementar vista previa y validación de lote — Resp: developer — Hecho cuando: la propuesta devuelve cambios exactos sin modificar tareas.
- [ ] T2 · Aplicar el lote autorizado — Resp: developer — Hecho cuando: guarda todos los cambios juntos tras revalidar y rechaza conflictos y repeticiones.
- [ ] T3 · Verificar reprogramación — Resp: qa-tester — Hecho cuando: pasan los casos de integridad, permisos y concurrencia.
- [ ] T4 · Documentar el flujo de aprobación — Resp: tech-writer — Hecho cuando: la guía muestra cómo revisar y confirmar el lote.

## 8. Bitácora de implementación

## 9. Preguntas abiertas, decisiones e historial
- Alcance acordado en planificación. LECCIÓN: ninguna.

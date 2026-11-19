# CHG-186 · Agenda consolidada y disponibilidad en el MCP
**Estado:** Borrador
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-19 · **Aprobado:** — · **Cerrado:** —
**Relacionado:** CHG-187

## 0. En palabras simples
Consulta qué tienes agendado y encuentra espacios dentro de tu jornada desde un cliente MCP. Verifica un día que incluya una reserva y un plan con hora.

## 1. Resumen
Primera entrega. Consolidar calendario y disponibilidad para el usuario autenticado.

## 2. Producto
Las ocho capas de la web están disponibles. Salud se incluye con títulos protegidos y el ciclo solo bajo petición explícita y si está habilitado. El filtro de capas afecta solo la consulta.

## 3. UX/UI
No aplica. Se conserva la interfaz web existente y se publican respuestas estructuradas MCP.

## 4. Diseño técnico
Extraer la consulta compartida de TaskCalendar con aislamiento por usuario, sin duplicar evaluaciones vinculadas a tareas. list-calendar-agenda-tool acepta fechas inclusivas (hoy por defecto, máximo 31 días), capas e include_cycle. Devuelve zona, orden, tipo, título, horario, día completo, ocupación y capacidad de mover desde MCP. TaskSlotFinder comparte intervalos con la agenda: tareas incompletas con inicio a una hora, comidas, tomas de medicamento anónimas de 15 minutos, reservas efectivas y planes pendientes o programados con intervalo efectivo. Preservar excepciones de reservas, jornada, margen, parámetros existentes y cruces con al menos una tarea. Añadir intervalos libres completos compatibles con la duración solicitada.

## 5. Pruebas
Verificar capas, privacidad, duplicados, límites, autenticación MCP, aislamiento, excepciones de reservas, estados de planes y paridad entre web y MCP. Ejecutar suites afectadas en SQLite y MySQL de pruebas.

## 6. Documentación
Actualizar docs/integrations/agenda-mcp.md con capas, ocupación y consulta de huecos.

## 7. Tareas
- [ ] T1 · Compartir fuentes y ocupación — Resp: developer — Hecho cuando: web, agenda y huecos comparten reglas y respetan sus filtros.
- [ ] T2 · Exponer agenda y huecos — Resp: developer — Hecho cuando: las herramientas devuelven el contrato acordado y están en el catálogo.
- [ ] T3 · Verificar paridad y permisos — Resp: qa-tester — Hecho cuando: pasan los escenarios de consulta y disponibilidad.
- [ ] T4 · Actualizar guía de agenda — Resp: tech-writer — Hecho cuando: explica capas, privacidad y bloqueos.

## 8. Bitácora de implementación

## 9. Preguntas abiertas, decisiones e historial
- Alcance acordado en planificación. LECCIÓN: ninguna.

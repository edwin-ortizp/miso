# CHG-171 · Mostrar candado junto al horario fijo
**Estado:** Cerrado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-18 · **Aprobado:** 2026-11-18 · **Cerrado:** 2026-11-18
**Relacionado:** —

## 0. En palabras simples
Un candado acompaña las fechas y horas de tareas con Horario fijo.

## 1. Resumen
Indicador visual en los horarios de tareas con bloqueo activo.

## 3. UX/UI
Plantilla: vistas y tarjetas existentes.
```
[candado] 18 nov., 09:00–09:45
```

## 4. Diseño técnico
Reutilizar Bootstrap Icons y el estilo existente. Aplicar en Lista, Planificación, Calendario, Inicio, Relaciones y agenda del panel. Usar el valor persistido de Horario fijo.

## 5. Pruebas
Verificar render de tareas fijas y flexibles y regresión de las acciones existentes.

## 7. Tareas
- [x] T1 · Mostrar el candado de horario fijo — Resp: developer — Archivos: resources/views, resources/js/task-calendar.js, app/Support/WallPanelData.php, tests/Feature/Task/TaskFixedScheduleTest.php, tests/Browser/task-editor.interaction.spec.js, public/js generado — Hecho cuando: se verifica §5.

## 8. Bitácora de implementación (developer)
- 2026-11-18 — Componente de candado compartido y alineación con la hora en Calendario. El panel recibe el estado persistido y se actualiza tras guardar.
- 2026-11-18 — Pasaron 34 pruebas PHP en SQLite y 24 en MariaDB, 25 pruebas JavaScript y la interacción de activar y desactivar en el listado sin recargar. Build y git diff --check correctos. Una prueba de desvinculación de Relaciones falló, fuera del flujo de horarios. Conformidad UI conserva tres hallazgos de otras vistas.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-18 — Aprobado por el usuario (Compuerta 2)
- 2026-11-18 — Movido a in-progress/ (En desarrollo)
- LECCIÓN: ninguna.
- 2026-11-18 — Implementación terminada → En verificación
- 2026-11-18 — Cerrado por el usuario (Compuerta 3) → done/

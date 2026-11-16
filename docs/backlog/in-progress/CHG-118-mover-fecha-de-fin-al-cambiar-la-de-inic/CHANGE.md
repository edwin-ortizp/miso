# CHG-118 · Mover fecha de fin al cambiar la de inicio de una tarea
**Estado:** En verificación
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-16 · **Aprobado:** 2026-11-16 · **Cerrado:** —
**Relacionado:** CHG-107 (editor de tareas)

## 0. En palabras simples
- Qué vas a ver distinto: al cambiar la fecha de inicio de una tarea, la fecha de fin se mueve los mismos días. Si la tarea era del 10 al 10 de octubre y pones inicio el 12, queda del 12 al 12 (antes quedaba inicio 12 y fin 10). Una tarea de varios días conserva su duración.
- Cómo comprobarlo tú: abre una tarea con inicio y fin el 10, cambia el inicio al 12 → el fin pasa a 12. Cambia solo el fin → el inicio no se mueve.

## 1. Resumen
En el editor de tareas, mover solo el inicio dejaba un rango inválido (fin antes del inicio). El inicio ahora desplaza el fin la misma cantidad de días; cambiar el fin no toca el inicio.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
Plantilla: formulario modal canónico existente (Programación); sin cambios visuales.
```
Fecha inicio [12/10]   Fecha fin [12/10]   ← el fin sigue al inicio
```
Reglas: con inicio y fin previos, el fin se desplaza los mismos días (conserva la duración); sin fin no se inventa uno; si el fin quedara antes del nuevo inicio se alinea con él; vaciar el inicio no cambia el fin; las horas no se tocan.

## 4. Diseño técnico
Hook `updatingStartDate` en `app/Livewire/Task/TaskList.php` (lo hereda `TaskEditor`): usa el inicio previo y el nuevo valor, con fechas `Y-m-d` en `Carbon`. Sin migraciones ni cambios de API.

## 5. Pruebas
`tests/Feature/TaskStartDateShiftTest.php`: mismo día, varios días, solo fin, sin fin/fin anterior y vaciar inicio. Comando: `php artisan test --filter=TaskStartDateShiftTest`.

## 7. Tareas
- [x] T1 · El fin sigue al inicio al editar una tarea — Resp: developer — Modelo: estandar — Archivos: app/Livewire/Task/TaskList.php, tests/Feature/TaskStartDateShiftTest.php — Hecho cuando: los casos de §5 pasan.

## 8. Bitácora de implementación (developer)
- 2026-11-16 — Hook añadido y tests escritos. **Sin ejecutar** (entorno cloud con PHP 8.3, el lock exige 8.4). No se tocó el editor masivo (sin campos de fecha). Pendiente: correr el test y probar a mano.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-16 — Creado en to-do/ (Borrador, nivel S) a pedido del usuario.
- LECCIÓN: ninguna.
- 2026-11-16 — Aprobado por el usuario (Compuerta 2)
- 2026-11-16 — Movido a in-progress/ (En desarrollo)
- 2026-11-16 — Implementación terminada → En verificación

# CHG-119 · Objetivos: alinear fecha límite si el inicio la supera
**Estado:** En verificación
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-16 · **Aprobado:** 2026-11-16 · **Cerrado:** —
**Relacionado:** CHG-118 (mismo ajuste en Tareas), CHG-111

## 0. En palabras simples
- Qué vas a ver distinto: en un objetivo, si pones una fecha de inicio posterior a la fecha límite, la fecha límite se ajusta sola al día de inicio (antes salía un error al guardar). Si el inicio es anterior a la fecha límite, no se toca.
- Cómo comprobarlo tú: objetivo con fecha límite 10 de oct; pon inicio el 12 → la fecha límite pasa a 12. Con límite el 31 de dic e inicio el 12 de oct → sigue el 31 de dic.

## 1. Resumen
A diferencia de Tareas (CHG-118), la fecha límite de un objetivo es una meta fija: mover el inicio no la desplaza; solo se corrige cuando quedaría antes del inicio. Decisión del usuario.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
Plantilla: formulario modal canónico de Objetivos (Información básica); sin cambios visuales.
```
Fecha inicio [12/10]   Fecha límite [12/10]   ← solo si el inicio la superaba
```

## 4. Diseño técnico
Trait `App\Livewire\Concerns\AlignsGoalDates` (hook `updatedStartDate`) usado por `GoalIndex` y `GoalDetail`. La validación `after_or_equal:startDate` se conserva. Sin migraciones.

## 5. Pruebas
`tests/Feature/GoalStartDateAlignTest.php`: inicio posterior alinea; inicio anterior no toca; sin límite no inventa. Comando: `php artisan test --filter=GoalStartDateAlignTest`.

## 7. Tareas
- [x] T1 · La fecha límite se alinea cuando el inicio la supera — Resp: developer — Modelo: estandar — Archivos: app/Livewire/Concerns/AlignsGoalDates.php, app/Livewire/Goal/GoalIndex.php, app/Livewire/Goal/GoalDetail.php, tests/Feature/GoalStartDateAlignTest.php — Hecho cuando: los casos de §5 pasan.

## 8. Bitácora de implementación (developer)
- 2026-11-16 — Implementado. **Sin ejecutar** tests (entorno cloud PHP 8.3 vs lock 8.4). Pendiente correr el test y probar a mano.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-16 — Creado a pedido del usuario; decisión: solo corregir si queda inválida.
- LECCIÓN: ninguna.
- 2026-11-16 — Aprobado por el usuario (Compuerta 2)
- 2026-11-16 — Movido a in-progress/ (En desarrollo)
- 2026-11-16 — Implementación terminada → En verificación

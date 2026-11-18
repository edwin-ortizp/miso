# CHG-155 · Abrir rutina desde un hábito pendiente
**Estado:** En verificación
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-17 · **Aprobado:** 2026-11-17 · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
- Cada hábito pendiente de hoy tiene un botón ▶ a la derecha.
- Pulsa ▶ en el segundo hábito de una franja. El modo de enfoque debe abrir en ese hábito.

## 1. Resumen
Abrir la rutina existente en el hábito elegido, conservando la cola y el inicio por franja.

## 3. UX/UI
### 3.1 Experiencia
Plantilla: seguimiento diario.
```text
[☐  Lavarme los dientes · 2 min · 20:00]   [▶]
```
▶ es independiente del botón de completar y se llama «Empezar rutina en {hábito}».

## 5. Pruebas
Verificar la posición inicial, el orden, los hábitos no disponibles y la interacción de ambos botones.

## 7. Tareas
- [x] T1 · Abrir la rutina desde el hábito elegido — Resp: developer — Archivos: app/Livewire/Habit/HabitTracker.php, resources/views/livewire/habit/habit-tracker.blade.php, resources/css/m3/modules/_habits.css, tests/Feature/HabitRoutineTest.php — Hecho cuando: el atajo abre en el hábito pendiente elegido y pasan las pruebas afectadas

## 8. Bitácora de implementación (developer)

- Botón de enfoque al extremo derecho y selección inicial por ID dentro de la cola existente.
- Pasaron 16 pruebas en SQLite y 16 en MariaDB mediante el driver MySQL. Compilación y `git diff --check` correctos. Conformidad reportó desviaciones en Juego, Inicio y Tareas. Revisión visual pendiente.

## 9. Preguntas abiertas, decisiones e historial
- LECCIÓN: ninguna.
- 2026-11-17 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-17 — Aprobado por el usuario (Compuerta 2)
- 2026-11-17 — Movido a in-progress/ (En desarrollo)
- 2026-11-17 — Implementación terminada → En verificación

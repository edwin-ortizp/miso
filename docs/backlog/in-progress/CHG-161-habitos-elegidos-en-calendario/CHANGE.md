# CHG-161 · Hábitos elegidos en calendario
**Estado:** En verificación
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-17 · **Aprobado:** 2026-11-17 · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
Elegir hábitos diarios y distinguir pendientes desde hoy y completados históricos.

## 1. Resumen
Entrega conjunta de calendario aprobada en conversación.

## 2. Producto
Elegir hábitos diarios y distinguir pendientes desde hoy y completados históricos. Cada capa es opcional y de uso personal.

## 3. UX/UI
### 3.1 Experiencia
Plantilla: calendario y formulario contextual del módulo.
```text
Hábitos → Editar → [✓] Mostrar en el calendario → Guardar
```
### 3.2 Visual
Reutiliza filtros, FAB y diálogo canónico. Verifica móvil y escritorio.
### 3.3 Escritura
Usa las etiquetas del boceto y del plan aprobado.

## 4. Diseño técnico
Archivos: app/Models/HabitDefinition.php, app/Livewire/Habit, resources/views/livewire/habit, app/Livewire/Task/TaskCalendar.php, app/Services/Calendar, resources/js/task-calendar.js, resources/views/livewire/task/task-calendar.blade.php, database/migrations, tests. Fuentes acotadas por rango y usuario, según el plan aprobado. Mantener el cálculo de huecos actual.

## 5. Pruebas
Pruebas de Laravel, JavaScript, accesibilidad, aislamiento y rendimiento según el plan aprobado.

## 6. Documentación
Actualizar la descripción del calendario en docs/design-system.md.

## 7. Tareas
- [x] T1 · Elegir hábitos diarios y distinguir pendientes desde hoy y completados históricos. — Resp: developer — Archivos: app/Models/HabitDefinition.php, app/Livewire/Habit, resources/views/livewire/habit, app/Livewire/Task/TaskCalendar.php, app/Services/Calendar, resources/js/task-calendar.js, resources/views/livewire/task/task-calendar.blade.php, database/migrations, tests — Hecho cuando: los criterios de la capa y sus regresiones pasan.

## 8. Bitácora de implementación
- Preferencia por hábito, estados por fecha y filtro persistente por cuenta. Pruebas de Laravel, JavaScript y navegador. Dos consultas para la capa activa; ocultarla no hace una solicitud.

Medición local con SQLite y datos ficticios en 36 días. Capas anteriores: 1,42 ms y 6 consultas. Con hábitos y reservas: 42,37 ms, 10 consultas y 177 kB JSON para 600 eventos. Con Salud: 45,20 ms, 12 consultas y 182 kB para 620 eventos. Son medianas del servidor sin transporte ni render.

## 9. Preguntas abiertas, decisiones e historial
- Plan aprobado en conversación.
- 2026-11-17 — Aprobado por el usuario (Compuerta 2)
- 2026-11-17 — Movido a in-progress/ (En desarrollo)
- LECCIÓN: ninguna.
- 2026-11-17 — Implementación terminada → En verificación

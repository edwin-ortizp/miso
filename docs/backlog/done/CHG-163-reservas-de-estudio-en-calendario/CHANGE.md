# CHG-163 · Reservas de estudio en calendario
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-17 · **Aprobado:** 2026-11-17 · **Cerrado:** 2026-11-18
**Relacionado:** —

## 0. En palabras simples
Crear y gestionar reservas puntuales y recurrentes desde Formación y Calendario.

## 1. Resumen
Entrega conjunta de calendario aprobada en conversación.

## 2. Producto
Crear y gestionar reservas puntuales y recurrentes desde Formación y Calendario. Cada capa es opcional y de uso personal.

## 3. UX/UI
### 3.1 Experiencia
Plantilla: calendario y formulario contextual del módulo.
```text
FAB → Reservar tiempo de estudio → Fecha y hora → Guardar
```
### 3.2 Visual
Reutiliza filtros, FAB y diálogo canónico. Verifica móvil y escritorio.
### 3.3 Escritura
Usa las etiquetas del boceto y del plan aprobado.

## 4. Diseño técnico
Archivos: app/Models, app/Services/Calendar, app/Livewire, resources/views/livewire, resources/js/task-calendar.js, database/migrations, routes/web.php, tests. Fuentes acotadas por rango y usuario, según el plan aprobado. Mantener el cálculo de huecos actual.

## 5. Pruebas
Pruebas de Laravel, JavaScript, accesibilidad, aislamiento y rendimiento según el plan aprobado.

## 6. Documentación
Actualizar la descripción del calendario en docs/design-system.md.

## 7. Tareas
- [x] T1 · Crear y gestionar reservas puntuales y recurrentes desde Formación y Calendario. — Resp: developer — Archivos: app/Models, app/Services/Calendar, app/Livewire, resources/views/livewire, resources/js/task-calendar.js, database/migrations, routes/web.php, tests — Hecho cuando: los criterios de la capa y sus regresiones pasan.

## 8. Bitácora de implementación
- Reservas puntuales y recurrentes, excepciones, edición y cancelación desde Formación y Calendario. Pruebas de Laravel, MariaDB aislada y navegador a 390 y 1440 px. El buscador de huecos conserva sus fuentes.

## 9. Preguntas abiertas, decisiones e historial
- Plan aprobado en conversación.
- 2026-11-17 — Aprobado por el usuario (Compuerta 2)
- 2026-11-17 — Movido a in-progress/ (En desarrollo)
- LECCIÓN: ninguna.
- 2026-11-17 — Implementación terminada → En verificación
- 2026-11-18 — Cerrado por el usuario (Compuerta 3) → done/

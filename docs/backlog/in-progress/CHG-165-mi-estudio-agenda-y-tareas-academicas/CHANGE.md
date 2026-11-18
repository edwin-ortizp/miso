# CHG-165 · Mi estudio agenda y tareas académicas
**Estado:** En desarrollo
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-17 · **Aprobado:** 2026-11-17 · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
- Qué vas a ver distinto: Mi estudio reúne las entregas y los próximos parciales por materia.
- Cómo comprobarlo tú: Abre Formación y guarda una tarea desde el FAB.

## 1. Resumen
Mi estudio reúne las entregas y los próximos parciales por materia. Forma parte del plan Mi estudio aprobado por Edwin, con entrega integrada.

## 2. Producto (product-manager)
- Problema / usuario: estudiantes de pregrado y posgrado necesitan gestionar sus compromisos y resultados.
- Incluye: Agenda, filtros, tareas con fecha y sin fecha, detalle de materia.
- Criterios de aceptación: El dashboard y las tareas académicas funcionan desde escritorio y móvil.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: lista y detalle dentro del shell.
- Flujo: Mi estudio → Agregar tarea → Guardar → Agenda.
- Mostrar estados inicial, cargando, vacío, error recuperable y éxito. Conservar el foco y proporcionar errores por campo.

```mermaid
flowchart LR
    A[Mi estudio] --> B[Agregar tarea]
    B --> C[Agenda de la materia]
```

### 3.2 Visual (ui-designer)
- Usar componentes canónicos y tokens Material 3. En móvil, apilar el contenido y conservar accesos de 44 px. Revisar tema claro y oscuro.

```text
Mi estudio | Formación / Semestre
Agenda                 Materias que requieren atención
Hoy · Entrega de Física                  + Agregar tarea
```

### 3.3 Escritura (ux-writer)
- Usar títulos descriptivos, etiquetas concretas y mensajes neutrales en español colombiano.

## 4. Diseño técnico (software-architect)
- Archivos: Learning dashboard y detalle, vistas Blade, estilos de Formación, rutas y configuración; relaciones de tareas, migraciones, pruebas y docs.
- Enfoque: conservar la jerarquía actual, verificar el dueño al leer y escribir, y mantener consultas acotadas.
- Compatibilidad: se conservan los contratos vigentes de reservas, salud, hábitos y cursos.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| El dashboard y las tareas académicas funcionan desde escritorio y móvil. | Funcional | Aplicación | Learning y Calendario |
- Comando: php artisan test --filter='Learning|TaskCalendar|PersonalCalendar'; npm run build; php artisan ui:conformance.
- Validar persistencia en MySQL y revisar interacción a 390, 768 y 1440 px.

## 6. Documentación (tech-writer)
- Actualizar docs/learning.md y, para la capa, docs/design-system.md.

## 7. Tareas
- [ ] T1 · Mi estudio reúne las entregas y los próximos parciales por materia. — Resp: developer — Modelo: estandar — Archivos: Learning dashboard y detalle, vistas Blade, estilos de Formación, rutas y configuración; relaciones de tareas, migraciones, pruebas y docs. — Hecho cuando: El dashboard y las tareas académicas funcionan desde escritorio y móvil.

## 8. Bitácora de implementación (developer)

- 2026-11-17 — Creada la entrada Mi estudio con agenda paginada, filtros por formación y semestre, captura rápida y acceso al editor de Tareas. El guardado transaccional crea la tarea y su evaluación en una sola operación.

## 9. Preguntas abiertas, decisiones e historial
- Alcance confirmado en la conversación y ejecución solicitada con «Implement the plan».
- 2026-11-17 — Aprobado por el usuario (Compuerta 2)
- 2026-11-17 — Movido a in-progress/ (En desarrollo)
- LECCIÓN(arquitectura): Mantener claves explícitas en los editores Livewire anidados conserva el borrador cuando el panel que los contiene se actualiza. Verificado al abrir el FAB inmediatamente después de cambiar el semestre. · destino sugerido: docs/ARCHITECTURE.md

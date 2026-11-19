# CHG-160 · Accesos de creación en los menús de tablas
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-17 · **Aprobado:** 2026-11-17 · **Cerrado:** 2026-11-18
**Relacionado:** —

## 0. En palabras simples
En Tareas abre ⋮ y selecciona Nueva tarea o Crear múltiples tareas.

## 1. Resumen
Agregar accesos directos a los formularios desde las tablas de gestión.

## 2. Producto
Mostrar todas las altas pertinentes a los registros de la tabla. Conservar el FAB y el compañero.

## 3. UX/UI
### 3.1 Experiencia
Plantilla: tabla de gestión existente. Menú → formulario sobre la misma pantalla.
### 3.2 Visual
```text
Tareas                      ⋮
             Nueva tarea
             Crear múltiples tareas
             ----------------------
             Actualizar tabla
```
### 3.3 Escritura
Reutilizar etiquetas existentes.

## 4. Diseño técnico
Prop createActions en management-card. Compartir las definiciones existentes con el FAB en las vistas correspondientes.

## 5. Pruebas
ManagementCardTest, TaskListFiltersTest y ui:conformance. Revisar interacción y foco.

## 6. Documentación
Actualizar docs/design-system.md con el acceso complementario.

## 7. Tareas
- [x] T1 · Ofrecer las altas de cada tabla en su menú — Resp: developer — Archivos: resources/views, tests/Feature/Ui/ManagementCardTest.php, docs/design-system.md — Hecho cuando: el menú abre los formularios pertinentes.

## 8. Bitácora
Implementación solicitada por el usuario.
- El menú de gestión comparte los formularios con el FAB. 53 pruebas dirigidas y 21 pruebas JS del compañero pasaron. Chromium verificó las altas de Tareas a 1440 y 390 px y la creación por el compañero. La conformidad conserva las desviaciones previas y la prueba de vista Flujo falla también con la tarjeta original.
- La versión local del CLI carece de comandos context y activity.

## 9. Preguntas abiertas, decisiones e historial
Alcance y opciones confirmados por el usuario.
- LECCIÓN: ninguna.
- 2026-11-17 — Aprobado por el usuario (Compuerta 2)
- 2026-11-17 — Movido a in-progress/ (En desarrollo)
- 2026-11-17 — Implementación terminada → En verificación
- 2026-11-18 — Cerrado por el usuario (Compuerta 3) → done/

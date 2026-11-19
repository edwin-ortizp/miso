# CHG-182 · Modernizar el modal de reservas de estudio
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-18 · **Aprobado:** 2026-11-18 · **Cerrado:** 2026-11-18
**Relacionado:** —

## 0. En palabras simples
- Qué vas a ver distinto: tres pestañas y un buscador para vincular tareas.
- Cómo comprobarlo tú: abre una reserva desde Formación o Calendario, busca dos tareas y navega entre pestañas antes de guardar.

## 1. Resumen
Modernizar el editor compartido y sustituir el listado completo por búsqueda remota.

## 2. Producto (product-manager)
- Incluye creación, edición y cancelación con las reglas de recurrencia existentes.
- Al buscar se ofrecen tareas pendientes propias de cualquier formación. Al editar aparecen también las tareas ya vinculadas que se completaron.
- Fuera de alcance: migraciones y cambios en la agenda.

## 3. UX/UI
### 3.1 Experiencia
Plantilla: diálogo de formulario sobre Calendario o Formación.
El borrador se conserva entre secciones y los errores llevan a su pestaña. Cambiar el alcance de un borrador modificado requiere confirmación.
### 3.2 Visual
Usar el diálogo con secciones y sus controles responsive.
```text
Reservar tiempo de estudio
General          | Título / Formación / Unidad
Horario          |
Tareas vinculadas|
Cancelar                              Guardar
```
### 3.3 Escritura
Usar las etiquetas General, Horario y Tareas vinculadas. Indicar cómo buscar y cuándo precisar el término.

## 4. Diseño técnico (software-architect)
- Editar StudyReservationEditor y su Blade, el combobox compartido y su contrato, los estilos de campos y el contraste de navegación del diálogo compartido, y las pruebas relacionadas.
- Separar búsqueda de selección. Validar la disponibilidad de los nuevos IDs y permitir los ya vinculados al alcance activo.

## 5. Pruebas (qa-tester)
- Comando para verificar: php artisan test --filter=PersonalCalendarTest, pruebas de navegador de reservas, npm run build y php artisan ui:conformance.
- Cubrir recurrencia, permisos, búsqueda acotada, selección múltiple, errores y responsive.

## 6. Documentación (tech-writer)
- Describir el modo múltiple remoto en docs/design-system.md.

## 7. Tareas
- [x] T1 · Organizar la reserva en tres pestañas y buscar tareas pendientes — Resp: developer — Archivos: editor de reservas, combobox, pruebas y sistema de diseño — Hecho cuando: funcionan creación y edición con las reglas y controles acordados.

## 8. Bitácora de implementación (developer)
- T1 implementada. El diálogo conserva el borrador entre pestañas, confirma cambios de alcance y dirige al primer grupo con errores. El combobox carga hasta 20 coincidencias por nombre y conserva los chips seleccionados entre búsquedas.
- Verificación: 10 pruebas y 137 aserciones en SQLite y MariaDB, cuatro pruebas Playwright entre Formación y Calendario, incluyendo Axe en el modal móvil y viewport reducido. Build correcto, conformidad idéntica a la línea base y diff sin problemas de formato.
- La CLI instalada admite validación y transiciones. Los subcomandos context y activity no están disponibles en esta versión.

## 9. Preguntas abiertas, decisiones e historial
- Alcance confirmado con el usuario. El plan se aprobó al solicitar la implementación.
- 2026-11-18 — Aprobado por el usuario (Compuerta 2)
- 2026-11-18 — Movido a in-progress/ (En desarrollo)
- LECCIÓN: ninguna.
- 2026-11-18 — Implementación terminada → En verificación
- 2026-11-18 — Cerrado por el usuario (Compuerta 3) → done/

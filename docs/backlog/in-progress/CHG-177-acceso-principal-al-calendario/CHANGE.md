# CHG-177 · Acceso principal al calendario
**Estado:** En verificación
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-18 · **Aprobado:** 2026-11-18 · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
- Qué vas a ver distinto: Abrir la ruta de calendario existente desde Productividad y la barra inferior, conservando la pestaña en Tareas y la configuración de visibilidad.
- Cómo comprobarlo tú: Abre Planes o Calendario y prueba el recorrido descrito.

## 1. Resumen
Abrir la ruta de calendario existente desde Productividad y la barra inferior, conservando la pestaña en Tareas y la configuración de visibilidad.

## 2. Producto
Entrega independiente del plan aprobado de mejoras de Planes y Calendario. Criterio de aceptación: Abrir la ruta de calendario existente desde Productividad y la barra inferior, conservando la pestaña en Tareas y la configuración de visibilidad.

## 3. UX/UI
### 3.1 Experiencia
Plantilla: panel lateral y navegación inferior

Calendario se encuentra en Productividad y en el lugar de Estadísticas de la barra inferior. Estadísticas sigue en Módulos y Tareas conserva su pestaña Calendario.

### 3.2 Visual
```text
Mi día   Hábitos   Tareas   Calendario   Módulos
```

### 3.3 Escritura
Usar las etiquetas del boceto y los textos del plan aprobado.

## 4. Diseño técnico
Los accesos usan tasks.calendar. El acceso principal no depende de que Tareas esté visible. Se respeta la preferencia de función y se muestra Estadísticas como alternativa inferior cuando corresponda. La pestaña permanece activa al actualizar el calendario por Livewire.

Archivos: config/modules.php, app/Services/Experience/PersonalExperience.php, app/Livewire/Task/TaskCalendar.php, resources/views/components/app-navigation.blade.php, resources/views/layouts/app.blade.php, resources/views/livewire/task, tests/Feature/PlanDetailsAndCalendarTest.php, tests/Browser

## 5. Pruebas
Pruebas de preferencias y destino activo. Playwright accede al mismo calendario desde ambos recorridos y comprueba la barra inferior a 320 y 390 px.

## 6. Documentación
No aplica — recorrido cubierto por la especificación y las pruebas.

## 7. Tareas
- [x] T1 · Abrir la ruta de calendario existente desde Productividad y la barra inferior, conservando la pestaña en Tareas y la configuración de visibilidad — Resp: developer — Archivos: config/modules.php, app/Services/Experience/PersonalExperience.php, app/Livewire/Task/TaskCalendar.php, resources/views/components/app-navigation.blade.php, resources/views/layouts/app.blade.php, resources/views/livewire/task, tests/Feature/PlanDetailsAndCalendarTest.php, tests/Browser — Hecho cuando: los criterios y las pruebas dirigidas estén verificados

## 8. Bitácora de implementación (developer)

- T1. Pasaron las pruebas de visibilidad, destino activo y accesos al mismo calendario. Playwright verificó ambas entradas y el ancho de la barra inferior móvil a 320 y 390 px.
- Validación común: 29 pruebas PHP dirigidas, 4 escenarios Playwright y npm run build. ui:conformance conserva tres hallazgos previos en Game y Mi día. El CLI instalado carece de context y activity, por lo que el registro de evidencia es manual.

## 9. Preguntas abiertas, decisiones e historial
- LECCIÓN: ninguna.
- 2026-11-18 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-18 — Aprobado por el usuario (Compuerta 2)
- 2026-11-18 — Movido a in-progress/ (En desarrollo)
- 2026-11-18 — Implementación terminada → En verificación

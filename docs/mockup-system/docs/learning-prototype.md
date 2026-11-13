# Formación y reservas · CHG-096

## Abrir el prototipo

Desde la raíz del repositorio:

```powershell
python ../mockup-system/build.py --project docs/mockup-system learning
python -m http.server 8766 --bind 127.0.0.1 --directory docs/mockup-system/dist
```

Abrir http://127.0.0.1:8766/learning.html?v=hierarchy2. Los cambios son ficticios y viven en memoria: recargar, reiniciar o cambiar de escenario los descarta. No hay backend, migraciones ni acceso a datos personales.

Si hay un compilador `--watch` activo sobre el mismo candidato, usar la compilación aislada (sin detener el observador):

```powershell
python docs/mockup-system/mockups/learning/tests/build-isolated.py --all
```

Esta utilidad copia las fuentes a una carpeta temporal, ejecuta el motor y su verificación, y copia de vuelta únicamente HTML publicados por el compilador. No modifica documentos generados a mano.

## Qué representa cada cosa

- **Formación**: algo que estudias. Se puede crear una Carrera universitaria, Especialización, Maestría, Doctorado, Curso o Ruta personal.
- **Tipo**: distingue esas opciones y adapta el título del formulario y el nombre del campo. No crea una estructura vacía obligatoria.
- **Categoría**: clasifica formaciones. Agilidad puede agrupar cursos de Scrum y Kanban; no contiene materias ni evaluaciones.
- **Contenido**: tiene una ubicación concreta dentro de una formación. Una carrera o posgrado permite periodos y materias directas; un periodo contiene materias; una materia contiene temas. Un curso contiene módulos y estos temas. Una ruta personal contiene etapas, cursos o temas; una etapa admite cursos y temas.
- **Evaluación**: pertenece al curso o unidad desde donde se crea. No se ofrece desde el listado general ni desde un periodo académico.
- **Reserva**: tiempo planificado independiente de tareas y vencimientos, relacionado opcionalmente con la formación y unidad actuales.

El tipo de una formación con contenido queda bloqueado durante la edición para no invalidar la estructura existente. Cambiar el nombre y la categoría sigue siendo posible.

## Recorridos de revisión

1. Elegir «Sin formaciones» en la configuración del prototipo. El compañero explica las opciones y señala el FAB. Su acción principal «Agregar formación» abre el tipo sin seleccionar; la flecha ofrece las seis opciones concretas.
2. Crear una carrera universitaria. Entrar con «Consultar», agregar «Semestre 1» y después una materia. Verificar la ubicación en las migas de navegación. También se pueden agregar materias directamente a la carrera.
3. Crear una maestría y organizar sus periodos y materias. No es necesario crear toda la estructura de una vez.
4. Crear un Curso de Scrum y elegir Agilidad en Categoría. Dentro del curso agregar módulos y temas. La categoría no aparece como un nivel navegable.
5. Crear una Ruta personal de inglés, agregar etapas y temas. Vincular el objetivo «Aprender inglés» desde Relaciones en el formulario de la formación. Se pueden vincular hábitos existentes en esa misma sección.
6. En una materia, curso, módulo, etapa o tema, usar las acciones de su FAB para agregar tareas, materiales o evaluaciones, o vincular tareas y hábitos existentes. Las tareas nuevas se vinculan a la ubicación actual, no al primer elemento de la formación.
7. Registrar evaluaciones con notas pendientes o cero. El resultado solo es final con todas las notas y ponderaciones que sumen 100 %. La escala se configura en Calificación y debe coincidir dentro de la unidad.
8. Reservar tiempo de estudio desde su contexto. Elegir tareas es opcional y una misma tarea puede aparecer en varias reservas. En la sección Reservas se muestran los próximos 28 días; Calendario expande cualquier rango consultado.
9. Editar o cancelar «Esta ocurrencia» o «Toda la serie». Editar la serie reemplaza su patrón y reinicia sus excepciones; el formulario lo explica. Las reservas empiezan y terminan el mismo día; los cruces están permitidos.
10. Abrir Calendario mediante el enlace contextual o la pestaña de Tareas. Filtros conserva Tareas, Reservas, Planes, Cumpleaños y Comidas. Ocultar reservas no las elimina. Arrastrar una reserva cambia esa ocurrencia y conserva las tareas; arrastrar una tarea reprograma explícitamente esa tarea.
11. Desde las acciones de una tarea, iniciar foco. Pomodoro se abre como módulo independiente. Iniciar/pausar/terminar o «Simular 25 min» registra una sesión sin terminar tareas ni marcar hábitos. Seguimiento agrega sesiones sin duplicarlas cuando una tarea está vinculada varias veces.

## Componentes y navegación

- El menú lateral conduce a los módulos existentes. Formación no incorpora pestañas globales de Objetivos, Hábitos ni Pomodoro.
- Las pestañas del detalle pertenecen a su contenido: Contenido, Tareas, Materiales, Evaluaciones, Reservas y Seguimiento según el nivel.
- FAB dividido canónico y menús de fila `row_split`: Consultar como principal; editar y ordenar en su menú. En móvil se utiliza la variante compacta existente.
- Formularios con secciones laterales: Información básica, Organización y Relaciones; Programación/Repetición/Tareas vinculadas para reservas; Información básica/Calificación para evaluaciones. El contexto se hereda y se muestra en cada diálogo.
- Los errores abren la sección que necesita corrección. Cancelar, Escape y cerrar descartan el borrador. Cabecera y footer se conservan accesibles; el editor de tareas sigue siendo el compartido.
- En el estado vacío, el guardián ofrece orientación. Con registros, la ayuda de contexto se consulta desde el icono discreto junto al título. Se conserva la integración de compañero favorito desarrollada por CHG-097, que es independiente del equipo semanal.

## Validación

```powershell
node docs/mockup-system/mockups/learning/tests/acceptance.mjs
node docs/mockup-system/mockups/learning/tests/favorite.mjs
```

La aceptación levanta un servidor temporal y prueba la creación desde cero de carrera, maestría, Scrum e inglés, sus jerarquías, formularios, materiales, evaluaciones y conexiones. Capturas e informes quedan en `dist/learning-review/` (ignorados por Git). Incluye 14 vistas responsive, menú de fila y formulario en escritorio/móvil, teclado y auditoría Axe de cuatro vistas y un formulario.

Las verificaciones anteriores de CHG-096 corresponden a la primera versión y no justificaban su jerarquía. Esta revisión sustituye ese diseño y sus recorridos de aceptación. La equivalencia píxel a píxel con una sesión autenticada de producción sigue sin certificarse; se reutilizan los componentes y contratos visuales del repositorio.

Los assets de la aplicación no cambian: el prototipo usa su propio compilador. El resultado conocido de `php artisan ui:conformance` de la aplicación se registra por separado y no se presenta como una validación aprobada del prototipo.

## Evidencia final de esta revisión · 2026-11-12

- Compilación aislada completa: 20 HTML publicados por el motor y 80 comprobaciones aisladas aprobadas.
- Aceptación de jerarquía: carrera con periodo y materia, materia directa, maestría, Scrum en Agilidad y ruta personal con etapas/temas creados desde cero; formularios, vínculos, materiales, evaluaciones y reservas aprobados.
- 14 vistas responsive (1440 × 1000 y 375 × 812), menús dentro del viewport, captura de modales con secciones, cancelación y teclado aprobados.
- Axe WCAG A/AA y 2.1 AA: cuatro vistas y un formulario móvil sin violaciones.
- Prueba de compañero favorito CHG-097 aprobada: selección, continuidad, foco, perfil, reinicio, escenarios, teclado, accesibilidad, responsive y funcionamiento offline.
- Sin errores de JavaScript ni solicitudes externas en la aceptación. Sintaxis JS y `git diff --check` aprobados.
- `php artisan ui:conformance`: mismas 228 desviaciones inventariadas de producción, salida 1. Este cambio no modifica las vistas Blade señaladas y no corrige esa deuda ajena al prototipo.
- Pendiente revisión del usuario de la experiencia corregida. No se implementó el backend ni se certificó equivalencia píxel a píxel con una sesión autenticada de producción.

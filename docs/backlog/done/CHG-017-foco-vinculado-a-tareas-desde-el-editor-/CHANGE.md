# CHG-017 · Foco vinculado a tareas desde el editor compartido
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-04 · **Aprobado:** 2026-11-04 · **Cerrado:** 2026-11-10
**Relacionado:** Depende de CHG-015; ejecutar después de CHG-016; referencias CHG-007, CHG-011, CHG-012

## 0. En palabras simples
Desde una tarea guardada podrás iniciar un temporizador o registrar tiempo ya trabajado con duración sugerida editable. Foco estará en la columna derecha incluso al editar, sin guardar ni perder cambios pendientes de la tarea.
Las tareas nuevas deben guardarse antes de usar Foco. El tiempo registrado no completa la tarea y sus notas no sustituyen la descripción.

## 1. Resumen
Tercer incremento aprobado: conectar Foco con sesiones reales y asociación opcional a Task. Reutilizar motor Pomodoro, formularios y almacenamiento existentes. Actualmente el panel usa taskId como contexto, pero PomodoroSession no lo persiste; requiere migración aditiva y relación validada por propietario.

## 2. Producto (product-manager)
- Incluye Foco lateral en consumidores del editor individual, dos acciones temporizador/manual, sugerencia editable, resumen sesiones/minutos y vínculo persistido. Bulk permanece aparte.
- Excluye completar tarea al registrar foco, estimar trabajo automáticamente, inferir asociaciones históricas, cambiar metas/pausas o reglas de temporizadores sin tarea.
- Tarea nueva sin ID persistido: acceso deshabilitado con Guarda la tarea antes de usar Foco. No crear tarea implícita ni reservar ID persistido de borrador cancelable.
- Tarea existente con borrador pendiente: usar su ID real. Abrir/cerrar/registrar foco conserva campos, sección, scroll y pendientes. Confirmar foco no guarda la tarea.
- Sugerir intervalo horario válido de inicio-fin; en su defecto estimación. Todo el día/multidía sin intervalo horario usa estimación, no convertir días enteros en foco. Si no hay duración, pedirla. El borrador puede sugerir duración/contexto sin guardar esos cambios.
- Si hay ciclo activo, recuperarlo e indicar tarea/contexto: no reiniciar, reasociar ni iniciar otro silenciosamente.

## 3. UX/UI (ux-designer)
- Bloque Foco en columna derecha con resumen y menú Iniciar temporizador / Registrar tiempo trabajado, disponible en consulta y edición. Mantener fondo/divisor y apilado de CHG-015.
- Reutilizar formularios/temporizador existentes con tarea visible, duración editable y notas independientes. Registro manual pide fecha/inicio/fin de trabajo pasado; una sugerencia no crea tiempo futuro.
- Regresar al mismo modo/sección/borrador y disparador. Error de foco no presenta Tarea guardada ni resetea propiedades. Timer puede continuar al cerrar editor de tarea; cancelar un borrador no cancela un ciclo.
- Carga/contexto inválido, tarea ajena/eliminada, ciclo activo y error/reintento tienen mensajes concretos y foco accesible. No inventar un widget desconectado del temporizador global/barra/panel.
- Resumen suma sesiones confirmadas asociadas, no minutos planificados. Mostrar claramente tarea del ciclo activo y del registro.

## 4. Diseño técnico (software-architect)
### Archivos y superficies
- app/Models/PomodoroSession.php, app/Models/Task.php y migración aditiva nueva en database/migrations/.
- app/Livewire/Pomodoro/PomodoroTimer.php, app/Livewire/Home/Panel/FocusTimer.php, app/Livewire/StatusBar/StatusBar.php; app/Services/FocusSessionService.php si se extrae persistencia común.
- resources/js/pomodoro-timer.js; vistas resources/views/livewire/pomodoro/pomodoro-timer.blade.php, home/panel/focus-timer.blade.php, status-bar/status-bar.blade.php; TaskEditor y su partial individual. Reutilizar host de Foco/motor, no crear otro motor por editor.
- Pruebas de §5, docs/integrations/task-focus.md y docs/design-system.md.
### Datos y compatibilidad
- Añadir task_id nullable con tipo/longitud/collation compatibles con UUID de Task, FK a tasks ON DELETE SET NULL, índice para user_id/task_id/date y task_title nullable de hasta 255 caracteres como snapshot. Sesiones existentes siguen null; sin asociación inferida por descripción ni backfill.
- Relación belongsTo Task y hasMany PomodoroSession. Borrar tarea conserva fecha/tiempo/notas/snapshot. Recurrencia no reasigna sesiones anteriores: vínculo al ID original y resumen acumulado de ese ID; atribución por ocurrencia/serie queda fuera de alcance.
- Extender guardados con taskId opcional default null, compatible con clientes sin tarea. Servidor exige autenticación y comprueba sesión/tarea por user_id explícito; snapshot desde tarea autorizada, no texto arbitrario. Proteger lecturas, agregaciones y escrituras aunque un cliente mande otro UUID.
- Reutilizar o extraer validación/persistencia de PomodoroTimer. Manual conserva origen manual, fin posterior a inicio, no futuro, máximo 16 h y notas hasta 1000 caracteres. Timer conserva límites actuales 1 minuto–24 h, validación de reloj y client_token idempotente; no cambiar reglas unificando límites.
- Reintento de token no duplica sesión ni cambia asociación confirmada. Validar propietario antes de devolver resultado idempotente; no aceptar token ajeno ni reasociación del ciclo. Editar sesión mantiene origen/asociación salvo acción explícita autorizada.
- Comandos del motor admiten taskId/contexto/duración inicial opcionales. Mantener clave de almacenamiento y ciclo actuales; estado antiguo sin taskId sigue recuperándose. Barra global, panel y módulo observan el mismo ciclo. No montar un temporizador autónomo en cada TaskEditor.
- Host disponible en pantallas consumidoras sin duplicar ciclos ni cambiar navegación. Un ciclo activo se muestra/recupera en lugar de ejecutar start para la nueva tarea. Contexto visual puede usar el borrador; vínculo persistido siempre apunta a tarea ya autorizada.
- Agregar resumen por usuario/tarea y sesiones confirmadas sin bucles de carga de sesiones. Guardar foco refresca resumen/Pomodoro/barra/panel con eventos existentes sin recargar TaskEditor ni su borrador.
### Migración y activación
Preparar y validar up/down en MySQL desechable con datos ficticios, incluyendo SET NULL/conservación. No migrar BD habitual ni desplegar sin autorización separada. Activación: primero migración, luego código que usa columnas nuevas; documentar dependencia. No introducir flags ni infraestructura nuevos por defecto.

## 5. Pruebas (qa-tester)
- TaskFocusAssociationTest: tarea/sesión propia, ajena, ausente, eliminada y sin autenticación; sesiones antiguas null, snapshot, borrar conserva foco, recurrente no reasigna, sumatorios aislados.
- Manual: tiempo pasado/cruce de día según formulario existente, 16 h, errores negativos/fin anterior/futuro, notas independientes y no completar tarea. Conservar origen manual al editar.
- Timer: 1 minuto–24 h, reintento/token repetido sin doble sesión, token ajeno o cambio taskId rechazados, recuperar estado viejo, ciclo activo no reasociado, fallo de red/reintento. Ampliar PomodoroTimerTest, PanelFocusSessionTest y JS del temporizador.
- Browser 390/768/1440 y altura baja: Foco lateral, tarea nueva bloqueada hasta guardar, sugerencia desde intervalo/estimación/ausente/allDay, manual/timer con borrador pendiente, retorno/cancelación/foco/scroll, mismo ciclo en barra/panel/editor y resumen actualizado.
- Migración en MySQL desechable antes de proponer ejecución real. Ejecutar PHP/JS afectados, Browser acotado, npm run build, php artisan ui:conformance y git diff --check. Distinguir validación local de migración/activación pendiente.

## 6. Documentación (tech-writer)
Crear docs/integrations/task-focus.md y enlazar desde documentación pertinente y design-system: relación opcional, sugerencia, independencia del borrador, nuevas tareas requieren guardar, ciclo único, límites, conservación al borrar y migración/activación. Documentar compatibilidad de sesiones antiguas y autorización pendiente para migración habitual.

## 7. Tareas
- [x] T1 · Asociar sesiones a tareas conservando el historial — Resp: dev-implementer — Modelo: estandar — Archivos: app/Models/PomodoroSession.php, app/Models/Task.php, database/migrations/*_add_task_reference_to_pomodoro_sessions.php, tests/Feature/TaskFocusAssociationTest.php — Hecho cuando: relación nullable/owner/snapshot y SET NULL probados, migración validada en MySQL desechable sin tocar BD habitual.
- [x] T2 · Guardar foco con motor y validaciones actuales — Resp: dev-implementer — Modelo: estandar — Archivos: app/Livewire/Pomodoro/PomodoroTimer.php, app/Livewire/Home/Panel/FocusTimer.php, app/Livewire/StatusBar/StatusBar.php, app/Services/FocusSessionService.php, tests/Feature/PomodoroTimerTest.php, tests/Feature/PanelFocusSessionTest.php, tests/Feature/TaskFocusAssociationTest.php — Hecho cuando: taskId opcional compatible, aislamiento, límites y timer idempotente sin modificar notas/tarea.
- [x] T3 · Usar Foco lateral sin perder borrador — Resp: dev-implementer — Modelo: estandar — Archivos: resources/views/livewire/task/partials/edit-task-dialog.blade.php, resources/views/livewire/task/task-editor.blade.php, app/Livewire/Task/TaskEditor.php, resources/js/pomodoro-timer.js, resources/views/livewire/home/panel/focus-timer.blade.php, resources/views/livewire/pomodoro/pomodoro-timer.blade.php, resources/views/livewire/status-bar/status-bar.blade.php — Hecho cuando: sugerencia editable, tarea nueva bloqueada, host compartido/ciclo activo recuperado y resumen/retorno sin perder campos.
- [x] T4 · Verificar vínculo, ciclo único e interacción responsive — Resp: qa-tester — Archivos: tests/Feature/TaskFocusAssociationTest.php, tests/Feature/PomodoroTimerTest.php, tests/Feature/PanelFocusSessionTest.php, tests/JavaScript/pomodoro-timer.test.js, tests/Browser/task-focus.interaction.spec.js, tests/Browser/support/task-focus-fixture.php — Hecho cuando: §5 pasa sin doble ciclo/registro ni fuga y se documenta migración real pendiente de autorización.
- [x] T5 · Documentar Foco y activación — Resp: tech-writer — Archivos: docs/integrations/task-focus.md, docs/design-system.md — Hecho cuando: guía coherente, límites/sesiones antiguas y pasos de migración claros; evidencia/pendientes en §8.

## 8. Bitácora de implementación (dev-implementer)
- 2026-11-04 — Implementados vínculo nullable, snapshot y relaciones; T1 permanece pendiente de validar up/down y SET NULL en MySQL desechable. La migración no se aplicó a la base habitual.
- 2026-11-04 — T2–T3: asociación opcional validada por propietario, ciclo compartido, sugerencia editable, registro manual y resumen lateral. TaskFocusAssociationTest: 3/7; PomodoroTimerTest: 11/80; PanelFocusSessionTest: 5/74; JS Pomodoro: 6 aprobados. Browser y aislamiento ampliado pendientes en T4.
- 2026-11-04 — T5: guía de activación y design-system actualizados. Build correcto; ui:conformance sin deuda nueva. Foco queda deshabilitado en el editor mientras no exista la columna.


- Revisión QA 2026-11-10 sobre HEAD 62351bb865ea8b1233ef8757b59feed2870a8c5e. Alex confirma funcionamiento en producción (aceptación funcional del usuario, no ejecución técnica contra producción). Suite focal PHP: 301/302; el fallo es CHG-027. Regresión adicional: 54/58, cuatro fallos existentes de shell/preview informados aparte. JS 44/44 y form-editor 3/3. Build correcto con aviso de chunk; conformance global falla por CHG-058 y deuda de CHG-054. CHG-059 concurrente excluido; no se alteró código funcional.
- BLOQUEADO: MySQL 8.4.3 desechable, puerto 13329/base miso_closure_focus_test: up, legado nullable, SET NULL y snapshot correctos; down falla SQLSTATE HY000/1553 al retirar pomodoro_task_date_index, requerido por la FK de user_id. T1/T4 permanecen pendientes. Servidor aislado detenido. Evidencia: storage/framework/testing/closure-qa-focus-mysql.log. No se tocó la BD habitual.

- 2026-11-10 — T1 cerrada: down() restaura un índice simple de user_id (si MySQL lo absorbió en pomodoro_task_date_index) antes de retirar el compuesto. MySQL 8.4.3 desechable (puerto 13330): up → down solo de esta migración → up correctos; columnas retiradas e índice pomodoro_sessions_user_id_foreign restaurado. Servidor detenido; BD habitual intacta. Pest focal 19/19. Archivo: database/migrations/2026_11_04_000001_add_task_reference_to_pomodoro_sessions.php.

- 2026-11-10 — QA T4: re-verificación independiente en MySQL 8.4.3 desechable (puerto 13331): migrate completo → rollback de la migración → up → down → up, todo correcto; tras down quedan solo PRIMARY, client_token_unique y user_id_foreign y 0 columnas task*. Servidor detenido, BD habitual intacta. Pest focal 19/19; npm run test:js 47/47. Resto de §5 (suite focal 301/302 con fallo ajeno CHG-027, JS, build, aceptación funcional de Alex) ya validado en la revisión previa del mismo día. Sin hallazgos nuevos. LECCIÓN(qa-tester): ninguna.

## 9. Preguntas abiertas, decisiones e historial
- LECCIÓN(developer): ninguna — la regla de probar down() en MySQL desechable ya existía y detectó el fallo. → descartada: no relevante
- 2026-11-04 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-04 — Usuario aprobó plan consolidado y registro de tres CHG, sin implementar. Foco requiere tarea guardada; en tarea existente usa ID persistido aunque el borrador siga pendiente.
- Orden: CHG-015 → CHG-016 → CHG-017. La migración real requiere aprobación separada; no ejecutar ahora.
- Team Lead aplicó skills de Producto/Arquitectura/QA/Documentación; UX/UI consultado mediante agente independiente. Lineamientos generales/backlogs por rol ausentes; fuentes vigentes: código/tests/design-system.
- Perfil estandar de implementación y razonamiento de diseño; sin seleccionar proveedor ni modificar TEAM_MODELS.md.
- 2026-11-04 — Aprobado por el usuario (Compuerta 2)
- 2026-11-04 — Movido a in-progress/ (En desarrollo)
- 2026-11-10 — Implementación terminada → En verificación
- 2026-11-10 — Cerrado por el usuario (Compuerta 3) → done/

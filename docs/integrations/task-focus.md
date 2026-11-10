# Foco vinculado a tareas

El editor individual permite iniciar un ciclo de foco o registrar tiempo ya trabajado para una tarea guardada. El registro de foco se guarda por separado: no guarda el borrador del editor ni completa la tarea. En una tarea nueva se debe guardar antes de usar Foco.

La lista de tareas ofrece las mismas dos acciones en el menú ▾ de cada fila pendiente: «Iniciar enfoque» abre el modal del panel con la tarea cargada y arranca el ciclo; «Registrar tiempo trabajado» abre el formulario manual con esa tarea vinculada. Ambas reutilizan el host global de foco y respetan el ciclo activo existente.

El tiempo sugerido proviene del intervalo con horas de inicio y fin, o de la estimación cuando no hay un intervalo horario válido. Se puede modificar antes de iniciar. El registro manual conserva las reglas existentes: fin posterior al inicio, sin tiempo futuro, máximo 16 horas y notas independientes de hasta 1000 caracteres. El temporizador mantiene el intervalo de un minuto a 24 horas y la protección contra reintentos duplicados.

La barra global, el panel y el módulo Pomodoro comparten el mismo ciclo. Si ya hay un ciclo activo, se muestra su contexto sin reasignarlo a otra tarea. El resumen cuenta únicamente sesiones confirmadas de la tarea; en tareas recurrentes se acumula por el ID original, sin atribución por ocurrencia.

La relación es opcional. Las sesiones anteriores quedan sin tarea; no se infiere ninguna asociación de sus notas. La sesión guarda el título de la tarea como referencia y, si se elimina la tarea, conserva el tiempo, las notas y ese título.

## Activación

La migración `2026_11_04_000001_add_task_reference_to_pomodoro_sessions` agrega el vínculo y debe validarse en MySQL desechable antes de aplicarse. La base habitual y el despliegue requieren autorización separada. Aplicar primero la migración y después activar el código que utilice el vínculo. Mientras la columna no exista, la acción de Foco del editor informa que la base necesita actualizarse; las sesiones sin tarea conservan su flujo anterior.

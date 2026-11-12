# Primera experiencia y personalización

Implementación local CHG-091–094. No constituye evidencia de retención ni certifica el entorno publicado.

## Recorrido

Una cuenta creada por el registro web entra en `/bienvenida`. Debe confirmar contexto e intención, módulos y un hábito propio; puede añadir un objetivo o una tarea y termina con un resumen. Puede cerrar sesión y retomar el último paso confirmado. El perfil permanece disponible. Las cuentas anteriores no quedan bloqueadas y pueden abrir la guía desde Inicio o Personalizar módulos.

El registro ya no copia la rutina personal predeterminada ni los hábitos a evitar. Conserva los catálogos técnicos existentes. No se modifican hábitos anteriores. El FAB abre los formularios compartidos y los reintentos actualizan el elemento ya creado, sin duplicarlo.

## Preferencias

Ajustes → Personalizar módulos conserva Mi día, Ajustes, perfil y personalización. La selección se aplica al menú lateral, navegación inferior, búsqueda de módulos y tarjetas/acciones del Inicio. Ocultar no elimina datos, bloquea enlaces directos ni detiene recordatorios, procesos, APIs o MCP. Los grupos vacíos desaparecen. El resumen semanal combinado solo aparece con todos los módulos visibles; la tendencia combinada exige Hábitos e Hidratación. No se cambian reglas de juego ni preferencias de avisos.

Datos y progreso se guardan en `module_settings`, clave `personal-experience`, siempre con usuario explícito. Sin nuevas migraciones. El bloqueo por usuario serializa confirmaciones y reintentos. Los eventos solo guardan nombres técnicos y fechas; contexto e intención son preferencias del usuario, no se copian al informe agregado.

## Medición local

Ejecutar `php artisan experience:report` produce un agregado de solo lectura por contexto (study/work/both/other/pending); usuarios anteriores que abren voluntariamente la guía van en existing. No imprime IDs, correos, nombres, intenciones ni textos de hábitos.

- Inicio, cada paso confirmado, hábito creado y finalización: primera fecha de cada hito en events.
- Primera acción real: primera actividad elegible registrada por el juego, cumplimiento de hábito, tarea completada o avance de objetivo. Crear configuraciones o visitar pantallas no cuenta. La señal registra un primer uso histórico; deshacerlo no borra el hito.
- D1: visita en el día calendario siguiente al comienzo de la guía. D1–7: al menos una visita entre los días 1 y 7. Se reutiliza user_active_days y solo se incluyen denominadores con la ventana completa, en la zona horaria de la app.
- Usuarios con cumplimiento: personas con al menos un hábito actualmente marcado como completado desde el comienzo de la guía. Se presenta separado de las visitas y no mide todavía adherencia respecto a frecuencia planificada.

No hay objetivos porcentuales fijados ni proveedor externo. El informe requiere acceso operativo al servidor; no se expone como endpoint público. Los días activos pueden incluir sesiones de configuración, por eso no se presentan como crecimiento personal.

## Prueba de dos semanas

Reclutar voluntariamente entre 6 y 10 participantes, repartidos entre estudiantes y empleados; admitir quienes estudian y trabajan. No enviar invitaciones automáticamente.

1. Antes del uso: preguntar por una dificultad reciente, cómo intentó resolverla y qué herramienta usa; registrar el contexto sin asumir que una ocupación es un nicho.
2. Primera sesión: pedir registrarse, elegir módulos y preparar un hábito propio. Observar sin explicar dónde pulsar; registrar tiempo, errores, ayuda necesaria y si entiende cuándo marcar cumplimiento. Pedir que explique cómo cambiar módulos y salir.
3. Días 1–7: observar hitos y regreso, y recoger brevemente obstáculos. Distinguir uso espontáneo de visitas motivadas por contacto del investigador.
4. Día 7: preguntar qué acción concreta facilitó MiSo y qué le sobró o confundió. Comparar estudiantes y empleados, conservando también casos negativos.
5. Día 14: revisar continuidad, cumplimiento y utilidad percibida. Identificar combinaciones de problema, contexto y módulos que merecen una prueba más enfocada.

Registrar una fila por participante con un código de estudio, contexto, resultado de configuración, ayuda, retorno, utilidad y obstáculos. Mantener cualquier contacto fuera del informe técnico. El tamaño de muestra sirve para aprender; no demuestra diferencias estadísticas entre nichos. Decidir la siguiente iteración por problemas repetidos y acciones útiles observadas, sin prometer engagement garantizado.

## Verificación y publicación

Pruebas PHP: `php artisan test --filter=PersonalExperienceTest`. Navegador: `npx playwright test -c tests/Browser/personal-experience.config.js`, con SQLite desechable y cuentas ficticias. Build y ui:conformance según instrucciones del repositorio.

El despliegue debe publicar backend y assets conjuntamente y refrescar las cachés de rutas/vistas/configuración según el procedimiento habitual. No hay migraciones nuevas, cron ni servicios externos. Verificar en una sesión privada del entorno publicado registro → guía → Inicio → perfil → salir. Esa comprobación y la prueba con participantes permanecen pendientes hasta contar con el entorno y las personas.

## Resultado de la validación local (2026-11-11)

- 14 pruebas PHP focalizadas correctas, incluyendo registro sin rutina copiada, acceso a preferencias, reintentos, aislamiento y privacidad del informe.
- 3 recorridos Chromium correctos a 390, 768 y 1440 px. Guía y cuenta revisadas con axe, Escape/foco y posición tras actualización Livewire. Sin errores JavaScript. Capturas en `storage/framework/testing/playwright/personal-experience`.
- Build y diff check correctos. Sin JavaScript nuevo de producto; se prueba la integración mediante Playwright.
- Regresión adicional: 48 de 49 pruebas correctas. El único fallo exige `md-module-shell` en HomeDashboardTest; se reprodujo usando las vistas de HEAD en un directorio temporal, por lo que es previo a este cambio.
- Conformidad UI ejecutada: 235 desviaciones, inventario de 345; sigue fallando por excesos en archivos existentes ajenos a la nueva superficie. Sin nuevas infracciones de esta entrega y sin modificar la baseline.

CHG-091–094 quedan en verificación. La publicación y el estudio con personas no se han realizado.

## Corrección visual de la bienvenida

La guía usa un marco enfocado con marca y cuenta, sin navegación a módulos todavía bloqueados. Un escenario ilustrado de cielo y camino, la ball CSS existente y un mapa de cinco paradas sostienen la temática de primera aventura. La ilustración es decorativa y no entrega compañeros ni recompensas. Las opciones de contexto son radios con estado visible; los módulos usan sus iconos y acentos canónicos, con cuatro opciones iniciales y acceso a las restantes. El resumen convierte las instrucciones en tres próximos pasos.

Se mantiene un único layout responsive: escenario lateral en escritorio y compacto arriba en móvil. Respeta movimiento reducido, mantiene los formularios compartidos y conserva el estado y la obligatoriedad de la guía. El popover de cuenta usa la variante canónica anchored, evitando overrides de componentes base en CSS de módulo.

# Primera experiencia y personalización

Implementación local CHG-091–094. No constituye evidencia de retención ni certifica el entorno publicado.

## Recorrido

Actualización local CHG-116: una cuenta creada por registro entra en `/bienvenida`, con cuatro pasos: contexto combinable → necesidad guiada → hábito → resumen. El contexto admite trabajo, estudio y cuidados simultáneos, o «Ninguna de estas» de forma exclusiva. La necesidad se elige entre cuidado cotidiano, organización, aprendizaje, algo personal y «Todavía no sé». No se exige redactar una intención.

Cada necesidad ofrece tres ideas. El contexto solo modifica el orden; «Ver más ideas» permite explorar el catálogo completo. Elegir una tarjeta no crea registros. El FAB «Preparar hábito» abre el diálogo canónico con nombre y momento precargados; «Crear el mío» permite escribir un nombre propio con ejemplos. El formulario muestra «Todos los días» y no pide hora, duración, días, objetivos, acciones automáticas ni recordatorios. Crear no equivale a completar y no concede recompensas. Mi día muestra pendientes del bloque horario actual; Hábitos permite ver el registro diario completo.

Se guardan los pasos confirmados, se mantiene el acceso a perfil y cierre de sesión y se retoma tras volver. Las cuentas existentes pueden usar «Volver a Mi día» y reutilizar un hábito propio. Al editarlo aquí solo cambian nombre y momento: se conservan duración, hora y acciones asociadas. Elegir otra necesidad no sustituye el hábito guardado; preparar otro conserva el anterior.

La selección de módulos y la creación de objetivos se realizan después, desde sus pantallas habituales. Las cuentas nuevas mantienen los módulos iniciales vigentes (Hábitos, Objetivos y Tareas); las preferencias anteriores se conservan y no se activan módulos según ocupación.

### Compatibilidad con la guía anterior

Al abrir la bienvenida se adapta únicamente el estado de ese usuario a `guide_version: 2`, en `module_settings` bajo `personal-experience`. No hay migración ni actualización masiva. Se conservan intención, módulos, registros y eventos anteriores. Los contextos antiguos se convierten a selecciones combinables; una guía incompleta con contexto confirmado retoma en necesidad y mantiene su hábito. Una guía completada sigue completada y muestra resumen con salida. Los nuevos hitos de pasos se distinguen mediante `v2_step_1` a `v2_step_3`. `context` sigue derivándose como study/work/both/other para mantener el informe compatible; cuidados sin estudio/trabajo se agrupan en other.

Las cuentas nuevas siguen sin recibir una rutina personal copiada. Los reintentos recuperan el ID guardado bajo el bloqueo por usuario. Se revalida la propiedad de hábitos al reutilizar, editar, avanzar y finalizar.

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
2. Primera sesión: pedir registrarse, elegir contexto y necesidad y preparar un hábito propio. Observar sin explicar dónde pulsar; registrar tiempo, errores, ayuda necesaria y si entiende cuándo marcar cumplimiento. Pedir que explique cómo cambiar módulos y salir.
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

La guía conserva un marco enfocado con marca y cuenta, sin navegación a módulos todavía bloqueados. El escenario ilustrado de cielo y camino y la ball CSS existente acompañan cuatro paradas; no entregan compañeros ni recompensas. Contexto usa casillas, necesidad e ideas usan radios. El resumen explica cuándo marcar el hábito. La configuración de módulos se ofrece después en Ajustes.

Un único layout responsive mantiene escenario lateral en escritorio y compacto arriba en móvil. Respeta movimiento reducido; el editor breve usa el diálogo canónico con protección de borrador y retorno de foco. El menú de cuenta mantiene su variante anclada.

## Validación local CHG-116 (2026-11-15)

- PHP: `php artisan test --filter=PersonalExperienceTest` → 14 pruebas correctas, 170 assertions. Incluye adaptación antigua, contexto múltiple, catálogo, validación, reintentos desde snapshots antiguos, aislamiento y conservación de ajustes ocultos de hábitos reutilizados.
- Browser: `npx playwright test -c tests/Browser/personal-experience.config.js --grep "new user journey|existing guided"` → 4/4. Cuenta nueva a 390/768/1440 px y cuenta existente a 390 px; axe en contexto, necesidad, diálogo, resumen y preferencia oscura del navegador (la bienvenida conserva la paleta clara vigente); foco de título, Escape, retorno de foco, recarga, creación propia y nombre Unicode largo. Sin errores JS en los tres recorridos nuevos.
- Capturas revisadas: welcome-390.png, editor-390.png, welcome-1440.png, needs-768.png y existing-dark-390.png en `storage/framework/testing/playwright/personal-experience/`. No se actualizan baselines visuales globales.
- `npm run build` correcto. Conformidad global: 203 desviaciones frente a inventario de 345; falla por excesos en cuatro superficies ajenas (day-tile-info, game-collection, checkin-sheet y task-list). No señala infracciones del onboarding; no se modifica baseline.
- Pint focalizado y `git diff --check` del cambio. CLI local sin comandos context/activity: lineamientos leídos directamente y evidencia registrada en el CHG.
- No hay migraciones nuevas ni cambios de datos reales. Publicación, comprobación en entorno publicado y pruebas con participantes siguen pendientes.

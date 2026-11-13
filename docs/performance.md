# Rendimiento de navegación y formularios

Diagnóstico local, 12–13 de septiembre de 2026. Alcance: Salud, lista de Tareas y ciclo de vida compartido. No se desplegó ni se activó instrumentación en producción.

## Causas demostradas y correcciones

1. **Abrir/cerrar un formulario hacía trabajo de página completa.** Salud invocaba `openForm`/`closeForm` en `HealthIndex`: cada petición volvía a consultar y renderizar historial, relaciones y estadísticas. Con 120 eventos sintéticos, abrir tenía una mediana de unos 1,92 s y cerrar 1,95 s. Dentro del middleware se medían unos 1.078 ms de servidor frente a 7 ms de SQL y 11 consultas: SQL no explica el costo principal observado. El resto incluye arranque de PHP, transporte, hidratación y renderizado; no se atribuye todo a la red.
2. **El historial de Salud no estaba acotado.** Se paginó a 25 eventos, con orden estable y acceso a las páginas anteriores. Los filtros vuelven a la primera página. Las relaciones se cargan para esa página; no se elimina historial. Los logs de cada evento siguen completos: si llegan a ser muy grandes, deben medirse por separado antes de acotarlos.
3. **Estado de interfaz mezclado con estado de servidor.** `HealthEditor` y `TaskEditor` tienen un límite de renderizado independiente y reutilizan las operaciones existentes. `ltFormEditor` abre y cierra localmente con Alpine; un alta no necesita petición. Editar abre primero la superficie, muestra carga/error y solicita el registro al componente hijo. Guardar valida y conserva el aislamiento por usuario; al terminar notifica una vez a la lista para refrescarla. La actualización de la lista después de guardar puede requerir una segunda petición: es intencional y no bloquea la apertura visual.
4. **Sincronización innecesaria de borradores.** Campos sin dependencia de servidor usan `wire:model` diferido; las condiciones de Salud y el resumen son locales. Los filtros de Salud tienen un borrador separado que se envía en conjunto al pulsar Aplicar. En Tareas se conservan peticiones de programación que recalculan fechas/duración y la vista previa Markdown que se genera en el servidor, pero ya no renderizan la lista. No se afirma que todos los campos deban evitar el backend.
5. **Transición y recursos globales.** Se retiró la transición que mantenía una captura pendiente mientras llegaba la navegación. Ahora se anima brevemente el contenido recibido, respetando movimiento reducido. Los listeners de conexión, instalación y media query se liberan al destruir su componente. El observador de gráficos vive en `documentElement`, que no se reemplaza al navegar; ignora mutaciones internas de ApexCharts, destruye gráficos retirados y comprueba que el elemento siga conectado tras la importación asíncrona.

Se conserva `wire:navigate`. Cada ruta sigue ejecutando Laravel y devolviendo HTML de página: no es un router que consulte únicamente datos JSON ni una recarga parcial del servidor. Livewire sustituye el contenido en el navegador y reutiliza recursos y su historial. La evidencia no justifica introducir otro router.

## Método y límites

- Chromium headless, viewport 1440×900, PHP mediante `artisan serve`, SQLite desechable, assets de producción locales, Debugbar desactivada, sin latencia artificial y service worker bloqueado. No son mediciones del hosting MySQL de producción.
- Para apertura/cierre se captura el clic y se observa el cambio de visibilidad en `requestAnimationFrame`, dentro del navegador. Se espera explícitamente esa muestra; no se mezcla con el tiempo de espera de Playwright. Mide el primer frame donde cambia la geometría visible, no la finalización física del repintado en pantalla.
- 20 repeticiones por conjunto: 6 o 120 eventos, cuatro logs por evento y otras tantas tareas. Los datos se restablecieron antes de la medición final de apertura/cierre. La batería adicional de navegación se ejecutó después de pruebas funcionales y tenía un registro sintético extra en el conjunto pequeño; sus comparaciones SPA/recarga usan los mismos datos y URL entre sí.
- La navegación adicional mide clic → `livewire:navigated` → dos frames. La recarga usa el origen temporal de Navigation Timing. Las columnas `tasks-navigate`, `tasks-reload` y `*-field` del primer script incluyen esperas de automatización y **no se usan para comparar routers**. `*-field` incluye deliberadamente 700 ms de observación: su utilidad es comprobar peticiones, no medir latencia de escritura. En la línea base el ensayo de campo no disparó `.blur`; no se presenta como evidencia de mejora en ese campo.
- `Server-Timing` registra duración de aplicación, SQL y cantidad de consultas de la conexión predeterminada, sin SQL, bindings, cuerpos ni datos personales. No incluye todo el arranque de Laravel ni conexiones adicionales. El listener se retira incluso ante una excepción; solo funciona con opt-in y entorno local/testing.
- `navigation.json` conserva bytes de respuesta y Resource Timing. `Content-Length` no estaba disponible en la línea base: los ceros de `bytes` allí significan desconocido, no respuestas vacías. Las tareas largas del primer script son contadores acumulados, no duración atribuible exclusivamente a la acción.
- El equipo y el repositorio tuvieron actividad concurrente durante la sesión. Hubo un valor extremo de 24,26 s en una recarga de la batería adicional; las medianas/p95 se informan sin ocultar esa limitación. No se infiere de ese valor una causa de aplicación.

## Apertura y cierre: comparación

Los resultados finales se publican en [performance-results.json](performance-results.json). Mediana calculada con los dos valores centrales y p95 por rango más cercano. La línea base no tenía muestras visuales ausentes.

| Conjunto | Acción | Antes: mediana / p95 (ms) | Después: mediana / p95 (ms) | Peticiones antes → después |
|---|---|---:|---:|---:|
| 6 eventos | Abrir Salud | 472.8 / 533.3 | 29.9 / 30.8 | 1 → 0 |
| 6 eventos | Cerrar Salud | 474.6 / 498.9 | 28.8 / 30.2 | 1 → 0 |
| 6 eventos | Abrir Tareas | 447.4 / 527.1 | 28.9 / 39.3 | 1 → 0 |
| 6 eventos | Cerrar Tareas | 428.4 / 479.6 | 29.4 / 30.3 | 1 → 0 |
| 120 eventos | Abrir Salud | 1917.4 / 2111.6 | 29.6 / 31.3 | 1 → 0 |
| 120 eventos | Cerrar Salud | 1943.2 / 2234.6 | 28.2 / 29.4 | 1 → 0 |
| 120 eventos | Abrir Tareas | 437.8 / 454.0 | 30.9 / 39.4 | 1 → 0 |
| 120 eventos | Cerrar Tareas | 417.7 / 434.9 | 28.2 / 29.8 | 1 → 0 |

Las altas y cierres visuales de ambos editores no requieren peticiones. Completar los campos diferidos tampoco hace peticiones; las pruebas PHP verifican que el editor no consulte listas ni estadísticas al actualizar un borrador.

## Navegación, edición y filtros

Resultados de la batería adicional, 20 repeticiones; tiempos en ms, red local y misma ruta/datos para SPA y recarga:

| Conjunto | Operación | Mediana aproximada | p95 |
|---|---|---:|---:|
| Pequeño | Navegación interna a Tareas | 528 | 856 |
| Pequeño | Recarga de Tareas | 551 | 1.720 |
| Grande | Navegación interna a Tareas | 516 | 637 |
| Grande | Recarga de Tareas | 585 | 804 |
| Pequeño | Superficie de edición de Salud | 28 | 30 |
| Grande | Superficie de edición de Salud | 27 | 29 |
| Pequeño | Aplicar filtros, hasta respuesta observada | 509 | 541 |
| Grande | Aplicar filtros, hasta respuesta observada | 737 | 765 |

Primera navegación/recarga: 651/713 ms en el conjunto pequeño y 542/605 ms en el grande. En visitas posteriores, medianas de 515/546 y 516/585 ms respectivamente. El p95 de solo 19 visitas posteriores es el máximo según el estimador elegido; no es comparable sin más con el p95 de las 20 muestras. No son cargas frías de assets: el usuario sintético ya inició sesión y visitó Salud.

La respuesta mediana de edición de Salud fue de unos 77 KB frente a unos 237/585 KB al aplicar filtros a la página pequeña/grande. El formulario está aislado de la lista, pero aún devuelve su HTML completo; una optimización posterior puede convertir la carga de edición en datos sin render cuando una traza demuestre su beneficio. La superficie ya no espera esa respuesta para aparecer. En Tareas, la batería final midió la superficie de edición con mediana de 29,85 ms y p95 de 32,1/32,6 ms (pequeño/grande), con una petición para cargar el registro.

Las medianas favorecen ligeramente a la navegación interna en estas condiciones. La variabilidad, los assets reutilizados y la diferencia de entorno impiden declarar que SPA sea universalmente más rápida. No se midió el delta del service worker ni de Debugbar: se excluyeron para aislar la arquitectura. El worker actual espera escritura/recorte de su caché en navegaciones tradicionales; su costo debe medirse por separado antes de modificarlo.

## Navegación prolongada y otros módulos

Tras 30 ciclos Salud → Tareas → Salud, con recolección de basura antes de las muestras, desde el ciclo 5 hasta el 30 se mantuvieron **174.016 nodos retenidos, 2.402 listeners, cuatro documentos y un gráfico activo**. El heap JS fue aproximadamente 31,36–31,60 MB. Los nodos incluyen documentos retenidos por historial, no solo el DOM visible. Hay calentamiento inicial y retención acotada; no hubo crecimiento sostenido de listeners/gráficos ni errores JavaScript en esta secuencia. No equivale a una prueba de toda la aplicación o de varias horas.

La revisión estática encontró el mismo patrón de apertura desde el servidor en Ejercicio, Objetivos, Relaciones, Ánimo y Planes, además de otras vistas de Tareas. No se migraron: necesitan sus propias trazas y pruebas de guardado/edición. El middleware de autenticación y el arranque de proveedores se ejecutan por petición; no se detectó una petición externa obligatoria de guard/loader al abrir los dos formularios analizados. No se ha medido cada middleware por separado.

Prioridad posterior: medir páginas y relaciones voluminosas; extender la separación de editores a los módulos con mayor latencia demostrada; medir el arranque de PHP/hosting y el worker. No se agregó caché de consultas ni de respuestas como solución.

## Reproducción local

En PowerShell, desde la raíz, preparar **solo** la base desechable:

```powershell
$env:APP_ENV='testing'
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE=(Join-Path $PWD 'storage/framework/testing/performance.sqlite')
$env:DB_URL=''
$env:SESSION_DRIVER='file'
$env:CACHE_STORE='array'
$env:DEBUGBAR_ENABLED='false'
$env:MEASURE_PERFORMANCE='true'
php scripts/performance-fixtures.php
npm run build
php artisan serve --host=127.0.0.1 --port=8031 --no-reload
```

El fixture ejecuta `migrate:fresh` únicamente si el entorno es testing y SQLite termina en `performance.sqlite`. No apuntarlo a una base real. No modifica `.env` ni persiste estas variables fuera de la sesión.

En otra terminal, ejecutar **secuencialmente**, sin otras suites simultáneas:

```powershell
node scripts/measure-performance.mjs after-final
node scripts/measure-navigation.mjs
node scripts/verify-editors.mjs
```

Los scripts de medición aceptan `PERF_URL` exclusivamente localhost/127.0.0.1 y `PERF_REPETITIONS` (20 por defecto). El verificador usa el puerto 8031 y guarda registros sintéticos; ejecutarlo después de medir o volver a sembrar. Los JSON completos y capturas quedan en `storage/framework/testing/performance/`, ignorado por Git; el resumen versionado conserva los resultados de esta sesión.

## Validación y pendientes externos

- 47 pruebas PHP de `EditorPerformanceTest`, `HealthModuleTest` y `TaskViewsTest`: pasan (257 aserciones). Dos pruebas del middleware: pasan (6 aserciones). Se actualizaron las pruebas de edición/alta masiva para usar el editor y sus campos actuales, manteniendo las aserciones sobre los datos guardados.
- Build y dos pruebas JavaScript existentes: pasan. Conformidad: 372 desviaciones frente a baseline 404, sin deuda nueva.
- Verificador de formularios: guardar, editar, alta tras edición, error de validación, borrador limpio, Escape, foco y cierre a 390/768/1440 px; enlace `?edit=` de Tareas y retirada del parámetro sin petición; atrás/adelante en filtros y recurrencia local; sin solicitudes al escribir borradores. Comprobación axe de etiquetas/ARIA/identificadores sobre el formulario de Salud: pasa.
- Suite general: ocho pruebas de interacción y seis de accesibilidad de arquetipos pasan. **24 pruebas de accesibilidad del catálogo fallan por contraste preexistente**: principalmente `<code>` rosa sobre el fondo del catálogo (4,26:1) y Cancelar del diálogo (4,43:1). No se cambió la paleta para ocultar estos fallos ni se afirma que toda la suite sea verde.
- Producción: los hashes públicos de JS/CSS consultados eran distintos de los locales. No se verificó el código PHP desplegado ni se instrumentó producción. Los tiempos iniciales de 1–2 s observados con la herramienta son aproximaciones; no se usan como antes/después del hosting. La aplicación publicada no se considera validada por estas mediciones locales.

## Consultas por petición (CHG-095, 11–12 de noviembre de 2026)

Medición local con datos reales del usuario (MySQL), cada ruta GET dentro de una transacción revertida (`storage/framework/testing/measure-routes.php`, no versionado). Con tablas de cientos de filas el SQL total por página es menor de 100 ms: **el costo estaba en el número de consultas y en trabajo repetido en PHP, no en índices**.

| Ruta | Antes | Después | Causa corregida |
|---|---:|---:|---|
| `/meals/suggestions` | 976 | 26 | N+1: un `find` con ofertas por ingrediente y por receta → carga por lotes en `MealNeeds` |
| `/negative-habits` | 105 | 24 | la vista leía el catálogo de categorías dos veces por hábito |
| `/tasks/flow` | 93 | 27 | cuatro consultas por categoría → dos lecturas para todo el tablero |
| `/` | 84 | 66 | bloque del juego y ajustes repetidos |
| `/tasks/list` | 73 | 42 | estados y catálogo de categorías repetidos por cada componente de la página |
| `/cierre` | 69 | 32 | guardián pedido cinco veces |
| `/health` | 66 | 41 | expansión de la categoría «salud» repetida |
| `/habits` | 57 | 32 | guardián pedido cuatro veces |
| `/settings` (solo shell) | 20 | 16 | lectura de la semana del juego |
| `/journal/life` | 18 (≈1,75 s de PHP) | 17 | ~4.700 celdas con dos `translatedFormat` y un `route()` cada una → se arman en PHP con fechas nativas |

Causas de fondo y cómo quedaron:

1. **Leer la semana del juego bloqueaba y escribía.** `WeekPlanner::forWeek` abría una transacción con `SELECT users … FOR UPDATE` en cada render (de 1 a 5 veces por página). Ahora lee sin candado y solo entra a la transacción cuando hay que crear la semana o llenar un hueco del equipo.
2. **Lecturas idénticas repetidas dentro de una petición.** `GameSettings`, `ModuleGuardian` y `RequestMemo` (catálogo de categorías de tareas) son `scoped` y recuerdan lo leído **solo durante la petición**; `AppServiceProvider` los vacía en cuanto se guarda o borra cualquiera de sus orígenes. No es caché persistente ni hay TTL.
3. **Introspección de esquema en rutas calientes.** Se retiró `Schema::hasColumn` de la lista y el editor de tareas; `ResolvesCategoryCatalog` conserva `Schema::hasTable` (lo necesitan migraciones antiguas) pero deja de preguntarlo en cada guardado.
4. **Doble `UPDATE` por tarea.** La revisión CalDAV se suma en `updating`, dentro del mismo `UPDATE`.
5. **Cron de notificaciones.** Antes de las 9:00 (Bogotá) ya no recorre cumpleaños, tareas y planes que igual descartaba; no recarga al usuario por cada recordatorio.

Presupuestos fijados en `tests/Feature/BackendQueryBudgetTest.php`. Suite completa: 1.466 de 1.515 pasan; los 48 fallos son los mismos de la línea base sin estos cambios (ninguno nuevo).

Analizado y no cambiado a propósito:

- **188 `whereDate()` sobre columnas `DATE`.** Impiden usar índice, pero en SQLite (pruebas) las fechas se guardan con hora y quitarlos cambia resultados; con este volumen no compensa.
- **Índices nuevos.** Sin evidencia de consultas lentas.
- **`CACHE_STORE=database` y `SESSION_DRIVER=database`.** Suman 3–4 consultas a cada petición (sesión leída dos veces y escrita, y el `Cache::add` de `TrackActiveDay`). Es configuración de entorno: en producción conviene `CACHE_STORE=file` y, si el hosting lo permite, `SESSION_DRIVER=file`.
- **Escrituras del juego en GET** (hitos de racha, reto diario): idempotentes y por diseño.
- **Shell (16 consultas fijas).** Quedan como siguientes candidatos los ajustes de Pomodoro leídos dos veces (barra de estado y guardián) y los contadores del menú.

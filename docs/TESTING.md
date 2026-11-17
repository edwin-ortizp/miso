# Testing

## Verificación visual de componentes flotantes

- En cambios de menús o popovers, verificar con el menú abierto su ancho y su anclaje al elemento que lo abre, además de que no se salga de los límites del viewport; repetir la verificación después de cada actualización de Livewire para detectar si se pierden los estilos de posición.

## Verificación visual de tarjetas y rejillas

- En cambios de tarjetas o rejillas, revisar en móvil y escritorio con pocos y muchos elementos, textos largos y estados pendientes y completados; comprobar, además de los desbordamientos, el aprovechamiento del espacio (alturas forzadas, grandes vacíos sin función, acciones separadas sin necesidad y columnas que no aprovechan el ancho), la legibilidad y el acceso por teclado a la ayuda contextual.

## Verificación de la suite contra MySQL (driver de producción)

- En cambios con pruebas automatizadas, correr la suite también contra MySQL (`DB_CONNECTION=mysql DB_DATABASE=<base>_test vendor/bin/pest`): sqlite en verde no certifica producción. Tratar como hallazgos las fallas de dialecto (índices/FK en migraciones, `strict mode` en inserts, unicidad) aunque las clases no sean del cambio. Corregido según CHG-020/CHG-122: en modelos nuevos, todo campo escrito con `create`/`update` debe estar en `$fillable`, y probar el valor leído de la base, no solo el asignado.
- El runner de Pest no termina el proceso al acabar la suite: ejecutarlo envuelto en `timeout` y leer el resultado del log (`{"tool":"pest","result":...}`), no esperar la salida del comando.

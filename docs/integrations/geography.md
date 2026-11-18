# Catálogo geográfico

Planes usa países y ciudades de [GeoNames](https://download.geonames.org/export/dump/), con atribución bajo [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/). La cobertura corresponde a la selección de ciudades y centros administrativos de `cities500`.

## Carga y actualización

1. Descarga `countryInfo.txt`, `admin1CodesASCII.txt` y `cities500.zip` del mismo snapshot y guárdalos en un directorio local. Conserva la fecha y los archivos utilizados en el despliegue.
2. Aplica las migraciones pendientes.
3. Con la extensión ZIP de PHP habilitada, ejecuta `php artisan geography:import /ruta/al/directorio`.
4. Comprueba que el comando informa países y ciudades procesados y que un plan permite seleccionar Colombia y buscar Popayán antes de habilitar el formulario en producción.

El comando procesa el archivo por lotes en una transacción. Volver a ejecutarlo actualiza las etiquetas y los datos de búsqueda sin duplicar filas ni borrar identificadores usados por planes. La aplicación consulta el catálogo local durante la edición. Las direcciones solo se envían a Google Maps o Waze si se elige el servicio desde el detalle.

Los planes anteriores conservan su ciudad como texto hasta que se editen. En el formulario se puede seleccionar su equivalente o quitar la ciudad anterior. En las herramientas de MiSo, `country_code` admite el código ISO de dos letras y `city_id` el identificador de GeoNames. El campo `city` sigue disponible y se resuelve cuando su nombre es inequívoco en el catálogo. El país, la ciudad y la dirección son opcionales.

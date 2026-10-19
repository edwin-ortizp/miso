# Editor Markdown de MiSo

El partial `resources/views/partials/markdown-editor.blade.php` utiliza el componente
portable `x-markdown::editor` de `edwin-ortizp/laravel-markdown`.
Actualmente utiliza una copia local `0.2.0-dev`, preparada para una futura v0.2.0.
Ya no utiliza `markdown::profiles.miso`. Las ocho herramientas y la vista previa
pertenecen al paquete; no se duplican sus botones ni el motor de formato.

## Adaptación local

- `resources/js/markdown-adapter.js` registra el puente Alpine de MiSo: traduce
  write/preview a edit/preview y sincroniza cambios externos del contenido.
- Conserva los ids, los campos Livewire y la actualización diferida o inmediata.
- Los recursos del paquete se cargan antes del bundle Livewire para admitir
  editores montados posteriormente o dentro de teleport.
- La clase del textarea conserva el atajo Ctrl/Cmd+L existente para checklists.
- El estilo Material se adapta exclusivamente en el patrón CSS Markdown de MiSo.
- El uso compartido cubre tareas y diario mediante el mismo partial.

La vista previa ahora es la del paquete. Se acepta su dialecto histórico y su soporte
limitado de tablas frente al CommonMark anterior. El texto guardado sigue siendo
Markdown; no se convierten datos ni se cambian permisos o acciones de guardado.

## Validación anterior (0.1.0)

Pest Browser: `php vendor/bin/pest tests/Browser/Componentes/MarkdownTest.php`.
Requiere APP_ENV=testing, SQLite :memory:, DB_URL vacío y configuración sin caché.
El fixture usa el bundle de MiSo y estado Livewire sintético, sin crear modelos.
Desactiva service workers para impedir que el precache visite rutas de aplicación.

Resultado: 7 pruebas y 72 aserciones correctas. Incluye las ocho herramientas,
Unicode, selección, ambos modos de sincronización, varios editores, cancelación,
reapertura, montaje posterior, morph, teclado y ancho móvil de 390px.
Captura móvil: tests/Browser/Screenshots/miso-markdown-mobile.png (artefacto local).
`npm run build`, Pint de los PHP afectados y `php artisan ui:conformance`: correctos.
Sin dependencias nuevas, cambios en vendor, migraciones, commits ni publicaciones.

## Herramientas con iconos

MiSo adapta visualmente los ocho botones del paquete mediante Bootstrap Icons.
Conserva los nombres originales en aria-label y title, y los iconos son decorativos
(aria-hidden). Editar / Vista previa mantienen texto visible. Un observador acotado
al editor reaplica la presentación tras morph y se desconecta al destruir su scope.
La lógica de inserción continúa siendo la del paquete; no se modifica vendor.
Las pruebas verifican etiquetas, clic sobre el icono, persistencia tras reapertura
y morph, fuente de iconos, ancho móvil y las ocho acciones de formato.


## Scroll y ampliación (0.2.0-dev local)

El paquete limita el cuerpo del editor, mantiene la barra visible y aporta el botón
Ampliar / Restaurar con iconos SVG propios. No necesita Bootstrap Icons para esa
función. La ampliación usa el mismo nodo en la capa superior del navegador; conserva
el formulario y Livewire, atrapa el foco y restaura con Escape antes de cerrar el
modal anfitrión. Cerrar/retirar el formulario libera inert y el bloqueo de scroll.
Sin Popover API se oculta el botón; edición y scroll siguen disponibles.

MiSo calcula `--lm-content-max-height` a partir del área disponible del modal y
observa tamaño del editor, modal, ventana y visualViewport. El contenido intermedio
de `.lt-cm-body` ahora tiene min-height:0 y overflow:auto: cabecera y acciones quedan
fuera del scroll. No se duplican la lógica de edición ni la ampliación del paquete.

Composer usa `../laravel-markdown`, override local `0.2.0-dev` y `symlink:false`.
Solo cambió esa entrada del lock respecto al inicio de este trabajo. Para incorporar
futuros cambios de la fuente a la copia instalada:

```powershell
composer reinstall edwin-ortizp/laravel-markdown --no-interaction --no-scripts
php artisan vendor:publish --tag=markdown-assets --force
```

Compilar con `npm run build` cuando cambien también el adaptador o estilos de MiSo.
No editar vendor ni public/vendor manualmente. No se ha publicado v0.2.0.

### Reversión a la versión pública 0.1.0

Retirar únicamente el repositorio path de este paquete en composer.json, conservar
otros repositorios si existen y cambiar su require a `0.1.0`. Después:

```powershell
composer update edwin-ortizp/laravel-markdown --no-interaction --no-scripts
php artisan vendor:publish --tag=markdown-assets --force
```

Cambiar la URL anticipada de editor.js en app-scripts a `?v=0.1.0` y recompilar MiSo.
El partial y los iconos siguen siendo compatibles con 0.1.0; desaparece la ampliación.
La corrección del contenedor del modal puede conservarse. La copia de archivos al
inicio del trabajo está en storage/framework/testing/markdown-expand-backup; no
restaurar archivos completos si contienen cambios posteriores de otros trabajos.
No requiere SQL ni conversiones del contenido.


Validación final de 0.2.0-dev: **12 pruebas Browser, 131 aserciones correctas**.
Incluye modo ampliado, mismo nodo/selección, formulario Blade independiente,
expandable:false, fallback sin Popover, scroll de contenido largo, cierre del
ancestro, desmontaje, Escape/foco, morph, guardado inmediato/diferido y viewport
móvil/pantalla baja con redimensionamiento. No se probó un teclado físico móvil;
se comprobó la adaptación al viewport desde Browser.

Build, Pint dirigido, Composer validate y ui:conformance correctos (264 desviaciones
frente a baseline 345; sin deuda nueva). Las capturas sintéticas se conservan bajo
storage/framework/testing/markdown-{modal,expanded}-{1280,390}.png.

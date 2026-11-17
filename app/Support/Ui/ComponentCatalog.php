<?php

namespace App\Support\Ui;

class ComponentCatalog
{
    /**
     * Metadatos de cada componente canónico, agrupados por capa del sistema.
     *
     * @var array<string, array{layer: string, title: string, description: string, usage: string}>
     */
    public const ENTRIES = [
        'action' => [
            'layer' => 'primitives',
            'title' => 'Acción',
            'description' => 'Botón o enlace de acción con variantes de énfasis, tonos semánticos, tamaños y estados de carga.',
            'usage' => '<x-ui.action variant="filled" tone="neutral" size="md" icon="bi-plus-lg" wire:click="create">Nueva tarea</x-ui.action>',
        ],
        'icon-action' => [
            'layer' => 'primitives',
            'title' => 'Acción de icono',
            'description' => 'Acción compacta que siempre declara un nombre accesible.',
            'usage' => '<x-ui.icon-action icon="bi-pencil" label="Editar tarea" wire:click="edit" />',
        ],
        'destructive-action' => [
            'layer' => 'primitives',
            'title' => 'Acción destructiva',
            'description' => 'Tratamiento común de las acciones que eliminan información, con confirmación proporcional al riesgo.',
            'usage' => '<x-ui.destructive-action label="Eliminar tarea" action="delete" risk="material" message="Se elimina de forma permanente." />',
        ],
        'field' => [
            'layer' => 'primitives',
            'title' => 'Campo',
            'description' => 'Campo de texto con etiqueta, ayuda, error y relaciones accesibles.',
            'usage' => '<x-ui.field name="title" label="Título" help="Máximo 80 caracteres" wire:model="title" />',
        ],
        'select' => [
            'layer' => 'primitives',
            'title' => 'Selector',
            'description' => 'Selección de una opción entre varias, con las mismas relaciones accesibles que el campo.',
            'usage' => '<x-ui.select name="status" label="Estado" placeholder="Todos" :options="$statuses" wire:model.live="status" />',
        ],
        'textarea' => [
            'layer' => 'primitives',
            'title' => 'Área de texto',
            'description' => 'Entrada de varias líneas para contenido largo.',
            'usage' => '<x-ui.textarea name="notes" label="Notas" rows="3" wire:model="notes" />',
        ],
        'switch' => [
            'layer' => 'primitives',
            'title' => 'Interruptor',
            'description' => 'Activa o desactiva una opción binaria; reemplaza al checkbox suelto en formularios.',
            'usage' => '<x-ui.switch name="nextPurchase" label="Incluir en lista de compras" wire:model="nextPurchase" />',
        ],
        'choice-chips' => [
            'layer' => 'primitives',
            'title' => 'Selector de opciones',
            'description' => 'Selector táctil de opciones cortas (días de la semana, síntomas, escalas) con botones de 44 px y aria-pressed; se enlaza a una propiedad de Livewire.',
            'usage' => '<x-ui.choice-chips name="weekdays" label="Días de la semana" shape="round" :options="$weekdayNames" :short="$weekdayShort" />',
        ],
        'favorite-toggle' => [
            'layer' => 'primitives',
            'title' => 'Marcar favorito',
            'description' => 'Botón de estrella con aria-pressed para marcar o desmarcar un elemento de un catálogo como favorito personal.',
            'usage' => '<x-ui.favorite-toggle :favorite="$isFavorite" label="Agua" wire:click="toggleFavorite(\'drinks\', \'{{ $id }}\')" />',
        ],
        'chip' => [
            'layer' => 'primitives',
            'title' => 'Chip',
            'description' => 'Filtro, asistencia, entrada o sugerencia con estado seleccionado y tonos semánticos.',
            'usage' => '<x-ui.chip variant="filter" :selected="$isToday" wire:click="filterToday">Hoy</x-ui.chip>',
        ],
        'badge' => [
            'layer' => 'primitives',
            'title' => 'Badge',
            'description' => 'Conteo o marcador breve asociado a otro elemento.',
            'usage' => '<x-ui.badge tone="danger" label="3 vencidas">3</x-ui.badge>',
        ],
        'card' => [
            'layer' => 'primitives',
            'title' => 'Card',
            'description' => 'Superficie contenedora con encabezado, contenido y acciones.',
            'usage' => '<x-ui.card variant="outlined" title="Resumen" icon="bi-stars">Contenido</x-ui.card>',
        ],
        'progress' => [
            'layer' => 'primitives',
            'title' => 'Progreso',
            'description' => 'Indicador determinado o indeterminado que transmite su valor por custom property.',
            'usage' => '<x-ui.progress :value="$done" :max="$total" tone="success" label="Avance" valueText="60%" />',
        ],
        'skeleton' => [
            'layer' => 'primitives',
            'title' => 'Skeleton',
            'description' => 'Silueta de carga que ocupa el sitio del contenido mientras se resuelve la petición, en lugar de dejar la pantalla en blanco.',
            'usage' => '<x-ui.skeleton variant="list" :lines="6" label="Cargando tareas" wire:loading.delay wire:target="search" />',
        ],
        'icon' => [
            'layer' => 'primitives',
            'title' => 'Icono',
            'description' => 'Iconografía con tono semántico, decorativa u operable por lectores de pantalla.',
            'usage' => '<x-ui.icon name="bi-droplet" tone="info" />',
        ],
        'filter-bar' => [
            'layer' => 'patterns',
            'title' => 'Búsqueda y filtros',
            'description' => 'Patrón canónico de búsqueda, limpieza y rail de chips.',
            'usage' => '<x-ui.filter-bar search="search" placeholder="Buscar tareas" label="Filtros">
    <x-slot:chips>
        <x-ui.chip variant="filter" :selected="$today">Hoy</x-ui.chip>
    </x-slot:chips>
</x-ui.filter-bar>',
        ],
        'filter-menu' => [
            'layer' => 'patterns',
            'title' => 'Menú de filtro',
            'description' => 'Chip con lista de opciones y estado seleccionado.',
            'usage' => '<x-ui.filter-menu name="status" label="Estado" :options="$statuses" :selected="$status" />',
        ],
        'metric' => [
            'layer' => 'patterns',
            'title' => 'Métrica',
            'description' => 'Valor destacado con etiqueta, unidad, apoyo y ausencia de datos.',
            'usage' => '<x-ui.metric label="Hidratación" value="2350" unit="ml" support="Sobre la meta" tone="success" />',
        ],
        'metric-grid' => [
            'layer' => 'patterns',
            'title' => 'Rejilla de métricas',
            'description' => 'Agrupación responsive de métricas equivalentes.',
            'usage' => '<x-ui.metric-grid label="Resumen">
    <x-ui.metric label="Tareas" value="12" />
</x-ui.metric-grid>',
        ],
        'section' => [
            'layer' => 'patterns',
            'title' => 'Sección',
            'description' => 'Bloque con encabezado, descripción y acciones propias.',
            'usage' => '<x-ui.section title="Resumen" description="Últimos siete días" :level="2">
    Contenido
</x-ui.section>',
        ],
        'list' => [
            'layer' => 'patterns',
            'title' => 'Lista',
            'description' => 'Colección de elementos con estructura compartida.',
            'usage' => '<x-ui.list label="Tareas">
    <x-ui.list-item headline="Comprar pan" />
</x-ui.list>',
        ],
        'list-item' => [
            'layer' => 'patterns',
            'title' => 'Elemento de lista',
            'description' => 'Elemento con regiones inicial, principal y final.',
            'usage' => '<x-ui.list-item headline="Comprar pan" supporting="Hoy" href="/tasks/1">
    <x-slot:trailing><x-ui.icon-action icon="bi-pencil" label="Editar" /></x-slot:trailing>
</x-ui.list-item>',
        ],
        'dialog' => [
            'layer' => 'patterns',
            'title' => 'Diálogo',
            'description' => 'Superficie modal con foco contenido y retorno al activador.',
            'usage' => '<x-ui.dialog state="showForm" title="Nueva tarea">
    Contenido
    <x-slot:actions><x-ui.action wire:click="save">Guardar</x-ui.action></x-slot:actions>
</x-ui.dialog>',
        ],
        'form-dialog' => [
            'layer' => 'patterns',
            'title' => 'Modal de formulario',
            'description' => 'Modal para crear o editar: cabecera y footer con divisoria, secciones laterales colapsables (no es un wizard), expandir/restaurar y Cancelar a la izquierda / Guardar a la derecha.',
            'usage' => '<x-ui.form-dialog :open="$showForm" close="closeForm" submit-action="save" title="Registrar evento" icon="bi-heart-pulse" :sections="$sections">
    <x-ui.form-dialog-section name="basic" title="Información básica">…</x-ui.form-dialog-section>
</x-ui.form-dialog>',
        ],
        'form-dialog-section' => [
            'layer' => 'patterns',
            'title' => 'Sección de modal de formulario',
            'description' => 'Bloque de campos de un modal de formulario; se muestra según la sección activa sin perder los datos.',
            'usage' => '<x-ui.form-dialog-section name="details" title="Detalles adicionales" description="Contexto opcional.">…</x-ui.form-dialog-section>',
        ],
        'fab' => [
            'layer' => 'patterns',
            'title' => 'FAB de creación',
            'description' => 'Única forma de exponer crear, registrar o agregar: extendido en escritorio, solo ícono en móvil, menú de opciones cuando hay varias. Siempre abre x-ui.form-dialog.',
            'usage' => '<x-ui.fab label="Registrar evento" icon="bi-plus-lg" action="openForm" :actions="$followUpActions" />',
        ],
        'menu' => [
            'layer' => 'patterns',
            'title' => 'Menú de acciones',
            'description' => 'Menú de más opciones (⋮) para acciones secundarias de un elemento.',
            'usage' => '<x-ui.menu label="Más opciones de Dolor lumbar">
    <x-ui.menu-item icon="bi-pencil" wire:click="edit">Editar</x-ui.menu-item>
    <x-ui.menu-divider />
    <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="delete">Eliminar</x-ui.menu-item>
</x-ui.menu>',
        ],
        'menu-item' => [
            'layer' => 'patterns',
            'title' => 'Opción de menú',
            'description' => 'Acción dentro de un menú, con icono y tono destructivo opcional.',
            'usage' => '<x-ui.menu-item icon="bi-check2-circle" wire:click="complete">Marcar como completado</x-ui.menu-item>',
        ],
        'row-actions' => [
            'layer' => 'patterns',
            'title' => 'Acciones por fila',
            'description' => 'Acciones de un registro en vistas de gestión: botón dividido en escritorio y un único ⋮ en card compacta o móvil, con la acción principal incluida. Sin gestos de deslizar.',
            'usage' => '<x-ui.row-actions label="Más acciones de Preparar propuesta">
    <x-slot:primary wire:click="complete">Completar</x-slot:primary>
    <x-ui.menu-item icon="bi-pencil" wire:click="edit">Editar</x-ui.menu-item>
    <x-ui.menu-divider />
    <x-ui.menu-item icon="bi-trash" tone="danger" wire:click="delete">Eliminar</x-ui.menu-item>
</x-ui.row-actions>',
        ],
        'applied-filters' => [
            'layer' => 'patterns',
            'title' => 'Filtros aplicados',
            'description' => 'Franja de chips removibles y «Limpiar filtros» para el slot strip de la card de gestión. Un filtro por defecto (p. ej. «Fecha: Hoy») se declara igual que cualquier otro.',
            'usage' => '<x-slot:strip>
    <x-ui.applied-filters :filters="$activeFilters" />
</x-slot:strip>',
        ],
        'menu-divider' => [
            'layer' => 'patterns',
            'title' => 'Divisor de menú',
            'description' => 'Separa grupos de acciones; se usa antes de las destructivas.',
            'usage' => '<x-ui.menu-divider />',
        ],
        'management-card' => [
            'layer' => 'patterns',
            'title' => 'Card de gestión',
            'description' => 'Contenedor estándar de las vistas que consultan y administran varios registros: header con ícono, título, contador, búsqueda, filtros y ⋮; contenido; footer con rango, filas por página y paginación. La creación sigue en el FAB.',
            'usage' => '<x-ui.management-card title="Cronología de salud" icon="bi-clock-history" count="(9 / 25)" search="search" search-placeholder="Buscar eventos" :active-filters="2" :paginator="$events" noun="eventos"><x-slot:filters>…</x-slot:filters><x-slot:menu>…</x-slot:menu>…</x-ui.management-card>',
        ],
        'popover' => [
            'layer' => 'patterns',
            'title' => 'Popover',
            'description' => 'Superficie anclada con título, cierre y acciones; se usa para el panel único de filtros.',
            'usage' => '<x-ui.popover state="filtersOpen" title="Filtros">…<x-slot:actions>…</x-slot:actions></x-ui.popover>',
        ],
        'multi-select' => [
            'layer' => 'primitives',
            'title' => 'Selección múltiple',
            'description' => 'Campo outlined que abre una lista de checkboxes enlazada a una propiedad array.',
            'usage' => '<x-ui.multi-select name="types" label="Tipo" :options="$types" all-label="Todos los tipos" />',
        ],
        'combobox' => [
            'layer' => 'primitives',
            'title' => 'Select con buscador',
            'description' => 'Campo outlined que filtra sus opciones sin distinguir mayúsculas ni tildes; flechas, Enter y Escape.',
            'usage' => '<x-ui.combobox name="categoryId" label="Categoría" :options="$categories" placeholder="Sin categoría" wire:model="categoryId" />',
        ],
        'inline-fields' => [
            'layer' => 'primitives',
            'title' => 'Campos en línea',
            'description' => 'Campos secundarios plegados como texto; al pulsarlos se muestran sus controles y se pliegan al hacer clic fuera.',
            'usage' => '<x-ui.inline-fields :summary="[\'Fuente: Manual\', \'Fecha: 12/10/2026\']"><x-ui.field name="date" label="Fecha" type="date" /></x-ui.inline-fields>',
        ],
        'sheet' => [
            'layer' => 'patterns',
            'title' => 'Hoja',
            'description' => 'Superficie modal inferior o lateral con el mismo contrato de foco.',
            'usage' => '<x-ui.sheet state="showSheet" title="Registrar" placement="bottom">Contenido</x-ui.sheet>',
        ],
        'snackbar' => [
            'layer' => 'patterns',
            'title' => 'Snackbar',
            'description' => 'Confirmación no modal que anuncia el cambio sin robar el foco.',
            'usage' => '<x-ui.snackbar state="saved">Registro guardado</x-ui.snackbar>',
        ],
        'state' => [
            'layer' => 'patterns',
            'title' => 'Estados de datos',
            'description' => 'Estados inicial, cargando, vacío, sin resultados filtrados y error recuperable.',
            'usage' => '<x-ui.state variant="filtered-empty" message="Ninguna tarea coincide.">
    <x-slot:actions><x-ui.action variant="outlined" wire:click="clearFilters">Limpiar filtros</x-ui.action></x-slot:actions>
</x-ui.state>',
        ],
        'flow-steps' => [
            'layer' => 'archetypes',
            'title' => 'Pasos de flujo guiado',
            'description' => 'Progreso de un flujo guiado con el paso actual anunciado.',
            'usage' => '<x-ui.flow-steps :steps="$steps" :current="$step" label="Reflexión guiada" />',
        ],
    ];

    public const LAYERS = [
        'primitives' => 'Primitivos',
        'patterns' => 'Patrones',
        'archetypes' => 'Arquetipos',
    ];

    /**
     * Componentes canónicos publicados en `resources/views/components/ui`.
     *
     * @return list<string>
     */
    public static function components(): array
    {
        $files = glob(resource_path('views/components/ui/*.blade.php')) ?: [];

        $names = array_map(
            fn (string $file) => str_replace('.blade.php', '', basename($file)),
            $files,
        );

        sort($names);

        return $names;
    }

    /**
     * @return array<string, array<string, array{layer: string, title: string, description: string, usage: string}>>
     */
    public static function byLayer(): array
    {
        $grouped = array_fill_keys(array_keys(self::LAYERS), []);

        foreach (self::ENTRIES as $name => $entry) {
            $grouped[$entry['layer']][$name] = $entry;
        }

        return $grouped;
    }

    public static function has(string $name): bool
    {
        return isset(self::ENTRIES[$name]);
    }

    public static function entry(string $name): array
    {
        return self::ENTRIES[$name] + ['name' => $name];
    }

    public static function examplePartial(string $name): string
    {
        return "ui.catalog.entries.{$name}";
    }
}

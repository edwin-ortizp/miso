<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rutas analizadas
    |--------------------------------------------------------------------------
    |
    | El escáner recorre todas las vistas Blade del proyecto. `exclude` acepta
    | patrones `fnmatch` relativos a `resources/views`.
    |
    */

    'paths' => [
        'views' => 'resources/views',
    ],

    'exclude' => [
        'vendor/*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Baseline de deuda visual
    |--------------------------------------------------------------------------
    |
    | Inventario inicial de desviaciones. Se compara por archivo y regla: una
    | contribución puede conservar deuda no tocada, pero no aumentarla.
    |
    */

    'baseline' => 'resources/ui/conformance-baseline.json',

    /*
    |--------------------------------------------------------------------------
    | Baseline del contrato de capas CSS
    |--------------------------------------------------------------------------
    |
    | Overrides de módulo sobre selectores base heredados de la organización
    | anterior. Se reducen a medida que cada módulo se migra a variantes.
    |
    */

    'layer_baseline' => 'resources/ui/css-layer-baseline.json',

    /*
    |--------------------------------------------------------------------------
    | Custom properties dinámicas admitidas
    |--------------------------------------------------------------------------
    |
    | Únicos valores que un atributo `style` puede declarar. Sirven para
    | transmitir valores calculados que no pueden expresarse con variantes
    | discretas (porcentajes, offsets de calendario, color de catálogo).
    |
    */

    'dynamic_custom_properties' => [
        // Porcentaje de avance de un indicador de progreso.
        '--md-progress-value',
        // Altura de una barra en una serie temporal compacta.
        '--md-bar-size',
    ],

    /*
    |--------------------------------------------------------------------------
    | Reglas de conformidad
    |--------------------------------------------------------------------------
    |
    | Cada regla declara un identificador, la descripción del incumplimiento y
    | los patrones detectados. `patterns` son expresiones regulares evaluadas
    | línea a línea; `rule` describe el detector especial cuando no basta con
    | una coincidencia textual.
    |
    */

    'rules' => [

        'retired-pattern' => [
            'description' => 'Clase o patrón retirado del sistema de diseño.',
            'detector' => 'patterns',
            'patterns' => [
                '/\bmd-chip-select\b/' => 'Usa el patrón canónico de chip menu en lugar de un select nativo.',
                '/\bmd-chip-group\b/' => 'Usa `md-chip-rail` para agrupar chips.',
                '/\bmd-chip--selected\b/' => 'Usa la clase `selected` sobre `md-chip`.',
                '/\bform-control\b/' => 'Usa el campo canónico `x-ui.field`.',
                '/\bform-select\b/' => 'Usa el campo canónico `x-ui.select`.',
                '/\binput-group\b/' => 'Usa el campo canónico con slots de icono.',
                '/\btext-muted\b/' => 'Usa la variante tipográfica semántica del sistema.',
                '/\btext-danger\b/' => 'Usa la variante semántica de error del sistema.',
            ],
        ],

        'direct-color' => [
            'description' => 'Valor de color directo en lugar de un token semántico.',
            'detector' => 'patterns',
            'patterns' => [
                '/#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3}(?:[0-9a-fA-F]{2})?)?\b(?![\w-]*\s*\{)/' => 'Sustituye el color literal por un token `--md-sys-color-*` o `--md-custom-color-*`.',
                '/\brgba?\(\s*\d/' => 'Sustituye el color literal por un token del sistema.',
                '/\bhsla?\(\s*\d/' => 'Sustituye el color literal por un token del sistema.',
            ],
        ],

        'local-component-substitution' => [
            'description' => 'Control local que sustituye a un componente canónico.',
            'detector' => 'patterns',
            'patterns' => [
                '/<select\b(?![^>]*\bmd-select\b)/' => 'Usa `x-ui.select` o el chip menu canónico.',
                '/<dialog\b/' => 'Usa el patrón canónico de diálogo.',
                '/<input\b[^>]*placeholder="Buscar(?![^>]*md-search-bar__input)/' => 'Usa el patrón canónico de búsqueda.',
                '/<button\b[^>]*class="(?![^"]*\bmd-)/' => 'Usa el primitivo de acción canónico.',
                '/<table\b(?![^>]*\bmd-table\b)/' => 'Usa el patrón canónico de lista o tabla.',
            ],
        ],

        'create-action-pattern' => [
            'description' => 'Acción de creación o modal de formulario fuera del patrón FAB + x-ui.form-dialog.',
            'detector' => 'patterns',
            'patterns' => [
                '/<x-ui\.dialog[^>]*title="[^"]*(?:Nuev[oa]|Crear|Agregar|Registrar)/' => 'Los formularios de creación usan `x-ui.form-dialog`, no `x-ui.dialog`.',
                '/md-dialog--(?:large|wide)/' => 'Los formularios usan `x-ui.form-dialog` con secciones.',
                '/<button[^>]*wire:click="open[A-Za-z]*Form"[^>]*class="[^"]*md-btn-(?:filled|filled-tonal|tonal|outlined)/' => 'La acción de crear es el FAB canónico (`x-ui.fab` / `x-module-actions`).',
            ],
        ],

        'inline-style' => [
            'description' => 'Declaración `style` no permitida en una vista.',
            'detector' => 'inline-style',
            'message' => 'Sustituye la declaración por una variante del sistema o una custom property dinámica admitida.',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Excepciones documentadas
    |--------------------------------------------------------------------------
    |
    | Excepciones duraderas con motivo y alcance. `path` es un patrón `fnmatch`
    | relativo a la raíz del proyecto y `rules` la lista de reglas exentas.
    |
    */

    'exceptions' => [
        [
            'path' => 'resources/views/components/app-navigation.blade.php',
            'rules' => ['local-component-substitution'],
            'reason' => 'Menú de navegación canónico compartido por ambos layouts; el scrim pertenece al marco de aplicación.',
            'scope' => 'Solo el chrome del menú y su overlay.',
        ],
        [
            'path' => 'resources/views/livewire/home/wall-panel.blade.php',
            'rules' => ['create-action-pattern'],
            'reason' => 'El panel de pared permite registros rápidos con un toque por decisión explícita de producto.',
            'scope' => 'Solo registros rápidos de hidratación, ánimo y energía; las demás altas usan FAB y form-dialog.',
        ],
        [
            'path' => 'resources/views/components/ui/*',
            'rules' => ['inline-style', 'local-component-substitution'],
            'reason' => 'Estas vistas definen los componentes canónicos y sus clases se resuelven desde props semánticas.',
            'scope' => 'Solo el directorio de primitivos y patrones del sistema; las pantallas siguen sujetas a las reglas.',
        ],
        [
            'path' => 'resources/views/layouts/*',
            'rules' => ['direct-color'],
            'reason' => 'El layout declara el color de tema del navegador y los iconos de la PWA.',
            'scope' => 'Metaetiquetas del documento, nunca superficies de la interfaz.',
        ],
        [
            'path' => 'resources/views/components/create-modal.blade.php',
            'rules' => ['local-component-substitution'],
            'reason' => 'El botón de paso del raíl (`.lt-cm-step`) es un control de navegación interna del propio '.
                'modal estándar, no una acción de contenido: no encaja en el contrato semántico de `x-ui.icon-action`.',
            'scope' => 'Solo los botones del raíl de pasos.',
        ],
        [
            'path' => 'resources/views/offline.blade.php',
            'rules' => ['direct-color'],
            'reason' => 'La pagina que sirve el service worker sin conexion declara su propio `theme-color`, y una '.
                'metaetiqueta del documento no puede resolver una custom property de CSS.',
            'scope' => 'Solo la metaetiqueta `theme-color`; la interfaz de la pagina usa tokens del sistema.',
        ],
        [
            'path' => 'resources/views/layouts/app.blade.php',
            'rules' => ['local-component-substitution'],
            'reason' => 'El marco de aplicación (AppFrame v2: sidebar, topbar, scrim, bottom-nav) usa sus propios '.
                'primitivos `lt-*` definidos en archetypes/_app-frame.css, no acciones de contenido: no encajan '.
                'en el contrato semántico de `x-ui.icon-action` (tone/size/emphasis) ni en el patrón de búsqueda '.
                'de `md-search-bar`, que asume una barra de búsqueda de contenido, no el buscador global del shell.',
            'scope' => 'Solo los controles de chrome del layout: alternar sidebar, scrim, buscador global, atajo de módulos en la barra inferior.',
        ],
    ],

];

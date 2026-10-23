<x-catalog.example title="Variantes" description="Contorno, relleno y elevación para distintos niveles de énfasis.">
    <x-ui.card variant="outlined" title="Resumen" icon="bi-stars">Registro constante durante la semana.</x-ui.card>
    <x-ui.card variant="filled" title="Próximo cuidado" icon="bi-heart-pulse" iconTone="secondary">Revisión anual en dos semanas.</x-ui.card>
    <x-ui.card variant="elevated" title="Objetivo" icon="bi-flag" iconTone="tertiary">Sostener la rutina de lectura.</x-ui.card>
</x-catalog.example>

<x-catalog.example title="Con acciones y contenido extenso" description="Las acciones viven en su propia región y el texto largo no desborda.">
    <x-ui.card variant="outlined" :title="\App\Support\Ui\CatalogFixtures::LONG_TEXT" icon="bi-car-front">
        {{ \App\Support\Ui\CatalogFixtures::LONG_TEXT }}

        <x-slot:actions>
            <x-ui.action variant="text">Posponer</x-ui.action>
            <x-ui.action variant="tonal">Planificar</x-ui.action>
        </x-slot:actions>
    </x-ui.card>
</x-catalog.example>

<x-catalog.example title="Sin encabezado" description="Una card puede ser solo contenido.">
    <x-ui.card variant="outlined">Sin encabezado ni acciones.</x-ui.card>
</x-catalog.example>

<x-catalog.example title="Panel de pared: paleta contextual" description="Tokens exclusivos del panel aprobado: fondo crema, superficie cálida, cabecera índigo y siguiente acción verde. El modo noche conserva la jerarquía y el contraste.">
    @foreach ([false => 'Claro', true => 'Noche'] as $night => $label)
        <div class="wall-frame" @if($night) data-panel-night @endif>
            <h3 class="md-title-medium">{{ $label }}</h3>
            <div class="wall-now"><strong>Ahora · 07:42</strong><span>Hábitos, hidratación, tareas y foco</span></div>
            <div class="wall-next"><strong>Lo próximo</strong><span>Una tarea programada</span></div>
            <x-ui.card variant="outlined" title="Seguimiento">Los formularios y controles conservan sus componentes canónicos.</x-ui.card>
        </div>
    @endforeach
</x-catalog.example>

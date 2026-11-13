@component('layouts.app', ['title' => 'Aviso no disponible'])
    <x-ui.card><h1 class="md-title-large">Este aviso ya no está disponible</h1>
        <p>Puede haber sido eliminado o pertenecer a otra cuenta.</p>
        <x-ui.action :href="route('home')">Volver a Inicio</x-ui.action>
    </x-ui.card>
@endcomponent

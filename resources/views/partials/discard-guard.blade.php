<div x-data="ltDiscardGuard" @miso-discard-request.window="ask($event)">
    <x-ui.dialog standard state="open" title="Tienes cambios sin guardar" close-expression="finish(false)">
        <p>Si sales, se descartarán los cambios de este formulario.</p>
        <x-slot:actions>
            <x-ui.action variant="tonal" @click="finish(false)">Seguir editando</x-ui.action>
            <x-ui.action @click="finish(true)">Descartar cambios</x-ui.action>
        </x-slot:actions>
    </x-ui.dialog>
</div>

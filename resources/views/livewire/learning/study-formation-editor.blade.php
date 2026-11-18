<div>
    <x-ui.form-dialog :open="$open" title="Agregar formación" close="close" submit-action="save"
        id="study-formation-dialog" icon="bi-mortarboard" module="learning" :focus-errors="true">
        <x-ui.field name="title" label="Nombre de la formación" wire:model="title" maxlength="160" required />
        <x-ui.select name="type" label="Tipo de formación" wire:model="type" :options="config('learning.types')" required />
    </x-ui.form-dialog>
</div>

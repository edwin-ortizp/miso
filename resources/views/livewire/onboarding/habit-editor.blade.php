<div>
    <x-ui.form-dialog :open="$showForm" close="closeForm" submit-action="save" id="onboarding-habit-dialog"
                      title="Preparar mi hábito" icon="bi-check2-square" submit="Guardar hábito" module="habits">
        <div class="d-flex flex-column gap-3">
            <x-ui.field name="name" label="Nombre" :required="true" wire:model="name"
                        help="Por ejemplo: tomar agua o leer unos minutos." />
            <x-ui.select name="timeOfDay" label="Momento del día" :required="true"
                         :selected="$timeOfDay" :options="$timesOfDay" wire:model="timeOfDay" />
            <p>Todos los días. Puedes ajustar este hábito después.</p>
        </div>
    </x-ui.form-dialog>
</div>

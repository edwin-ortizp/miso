<x-module-shell module="meals" title="Ajustes de Comidas" archetype="settings">
    @if ($message)<p role="status" class="md-card-filled p-3">{{ $message }}</p>@endif
    <div class="module-settings-workspace meal-schedules-workspace">
        <form wire:submit="saveSchedules" class="md-card-elevated p-3 meal-schedule-form">
            <h2 class="md-title-large">Horarios</h2>
            <p>Se aplican a las comidas sin horario puntual. Las duraciones se indican en minutos.</p>
            @foreach (['weekday' => 'Lunes a viernes', 'weekend' => 'Sábado y domingo'] as $profile => $label)
                <fieldset class="border-0 p-0 mb-3">
                    <legend class="md-title-medium">{{ $label }}</legend>
                    @foreach (\App\Services\Meal\MealSchedule::TYPES as $type => $name)
                        <div class="meal-schedule-row" wire:key="schedule-{{ $profile }}-{{ $type }}">
                            <div class="meal-schedule-row__label">{{ $name }}</div>
                            <div class="meal-schedule-row__field"><x-ui.field type="time" :name="'schedules.'.$profile.'.'.$type.'.time'" :label="'Hora de '.$name" wire:model="schedules.{{ $profile }}.{{ $type }}.time" required /></div>
                            <div class="meal-schedule-row__field"><x-ui.field type="number" :name="'schedules.'.$profile.'.'.$type.'.duration'" :label="'Minutos de '.$name" min="1" max="1440" wire:model="schedules.{{ $profile }}.{{ $type }}.duration" required /></div>
                        </div>
                    @endforeach
                </fieldset>
            @endforeach
            <button type="submit" class="md-btn md-btn-filled" wire:loading.attr="disabled">Guardar horarios</button>
        </form>
        <x-settings-sections label="Secciones de Ajustes de Comidas" :items="\App\Livewire\Meal\MealSettings::SECTIONS" :current="$section" />
    </div>
</x-module-shell>

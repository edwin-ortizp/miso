<x-module-shell module="meals" title="Ajustes de Comidas" archetype="settings">
    @if ($message)<p role="status" class="md-card-filled p-3">{{ $message }}</p>@endif
    <div class="meal-settings-workspace meal-schedules-workspace">
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
        <nav class="meal-settings-nav" aria-label="Secciones de Ajustes de Comidas">
            <a href="{{ route('meals.settings') }}" wire:navigate class="meal-settings-nav__item {{ $section === 'stores' ? 'is-active' : '' }}" @if ($section === 'stores') aria-current="page" @endif><i class="bi bi-shop" aria-hidden="true"></i> Tiendas</a>
            <a href="{{ route('meals.settings.categories') }}" wire:navigate class="meal-settings-nav__item {{ $section === 'categories' ? 'is-active' : '' }}" @if ($section === 'categories') aria-current="page" @endif><i class="bi bi-tags" aria-hidden="true"></i> Categorías de productos</a>
            <a href="{{ route('meals.settings.locations') }}" wire:navigate class="meal-settings-nav__item {{ $section === 'locations' ? 'is-active' : '' }}" @if ($section === 'locations') aria-current="page" @endif><i class="bi bi-geo-alt" aria-hidden="true"></i> Ubicaciones</a>
            <a href="{{ route('meals.settings.schedules') }}" wire:navigate class="meal-settings-nav__item {{ $section === 'schedules' ? 'is-active' : '' }}" @if ($section === 'schedules') aria-current="page" @endif><i class="bi bi-clock" aria-hidden="true"></i> Horarios</a>
        </nav>
    </div>
</x-module-shell>

@if ($guided ?? false)
    @php
        $startingKeys = ['habits', 'goals', 'tasks', 'pomodoro'];
        $startingModules = array_intersect_key($catalog, array_flip($startingKeys));
        $otherModules = array_diff_key($catalog, $startingModules);
    @endphp
    <fieldset class="experience-module-picks">
        <legend>Un buen punto de partida</legend>
        <p class="experience-note">Mi día, Ajustes y tu perfil siempre estarán disponibles.</p>
        <div class="experience-module-grid">
            @foreach ($startingModules as $key => $item)
                @include('livewire.onboarding.module-tile')
            @endforeach
        </div>
        <details class="experience-more-modules">
            <summary><span><i class="bi bi-grid" aria-hidden="true"></i> Explorar más módulos</span><small>{{ count($otherModules) }} opciones para tu día a día</small></summary>
            <div class="experience-module-grid">
                @foreach ($otherModules as $key => $item)
                    @include('livewire.onboarding.module-tile')
                @endforeach
            </div>
        </details>
        @error('modules')<p class="experience-error" role="alert">{{ $message }}</p>@enderror
    </fieldset>
@else
<fieldset class="experience-modules">
    <legend class="md-title-medium">Tus módulos</legend>
    <p class="md-body-small">Mi día, Ajustes y tu perfil siempre están disponibles.</p>
    @foreach ($catalog as $key => $item)
        <label class="md-card-outlined experience-module" wire:key="module-{{ $key }}">
            <span class="md-checkbox experience-module-check"><input type="checkbox" wire:model="modules" value="{{ $key }}" aria-label="{{ $item['label'] }}" aria-describedby="module-description-{{ $key }}"><span>{{ $item['label'] }}</span></span>
            <span id="module-description-{{ $key }}" class="md-body-small">{{ $item['description'] }}</span>
        </label>
    @endforeach
    @error('modules')<p class="md-field-error" role="alert">{{ $message }}</p>@enderror
</fieldset>
@endif

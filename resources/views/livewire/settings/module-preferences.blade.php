<section class="md-card-elevated">
    <h2 class="md-title-large">Personalizar módulos</h2>
    <p>Elige lo que quieres ver en el menú y en Mi día. Puedes cambiarlo cuando quieras.</p>
    <p class="md-body-small">Ocultar conserva tus datos y el acceso mediante enlaces. Los recordatorios que configuraste siguen activos; puedes cambiarlos en Notificaciones.</p>
    <form wire:submit="save">
        @include('livewire.onboarding.module-choices')

        @php $withFeatures = array_filter($catalog, fn ($item, $module) => in_array($module, $modules, true) && ! empty($features[$module]), ARRAY_FILTER_USE_BOTH); @endphp
        @if ($withFeatures)
            <section class="md-module-features" aria-labelledby="module-features-title">
                <h3 class="md-title-medium" id="module-features-title">Funciones de cada módulo</h3>
                <p class="md-body-small">Apaga lo que no uses y desaparece de las pestañas y de Mi día. Tus datos se conservan.</p>
                @foreach ($withFeatures as $module => $item)
                    <fieldset class="md-card-outlined md-module-features__group" wire:key="features-{{ $module }}">
                        <legend class="md-title-small">{{ $item['label'] }}</legend>
                        @foreach ($features[$module] as $feature => $definition)
                            @php $key = $module.'.'.$feature; $enabled = ! in_array($key, $featuresOff, true); @endphp
                            <div wire:key="feature-{{ $key }}-{{ $enabled ? 'on' : 'off' }}">
                                <x-ui.switch :name="'feature-'.$key" :id="'feature-'.str_replace('.', '-', $key)" :label="$definition['label']"
                                             :help="$definition['description']" :checked="$enabled" wire:click="toggleFeature('{{ $key }}')" />
                            </div>
                        @endforeach
                    </fieldset>
                @endforeach

                @if ($pendingOff)
                    <div class="md-card-outlined md-module-features__confirm" role="alertdialog" aria-labelledby="feature-confirm-title" aria-describedby="feature-confirm-text">
                        <p class="md-title-small" id="feature-confirm-title">Apagar «{{ $this->featureLabel($pendingOff, $experience) }}»</p>
                        <p id="feature-confirm-text">También se apagará {{ $this->labelList($pendingDependents, $experience) }}. Tus datos se conservan.</p>
                        <div class="md-module-features__actions">
                            <x-ui.action variant="text" type="button" wire:click="cancelOff">Cancelar</x-ui.action>
                            <x-ui.action type="button" wire:click="confirmOff">Apagar</x-ui.action>
                        </div>
                    </div>
                @endif
                @if ($notice)
                    <p class="md-body-small" role="status">{{ $notice }}</p>
                @endif
                @error('features')<p class="md-field-error" role="alert">{{ $message }}</p>@enderror
            </section>
        @endif
        <x-ui.action class=" mt-3" type="submit" wire:loading.attr="disabled">Guardar preferencias</x-ui.action>
    </form>
    <a class="md-btn-text mt-3" href="{{ route('onboarding') }}" wire:navigate>Recorrer la guía de MiSo</a>
</section>

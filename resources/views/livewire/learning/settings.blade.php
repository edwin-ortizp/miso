<x-module-shell module="learning" class="learning-page" archetype="settings">
    @if ($section === 'categories')
        <x-slot:actions><x-module-actions :primary="['label' => 'Crear categoría', 'icon' => 'bi-plus-lg', 'action' => 'openCategoryForm']" /></x-slot:actions>
    @endif
    @if ($message)<p role="status" class="learning-feedback">{{ $message }}</p>@endif
    @if ($categoryMessage)<p role="status" class="learning-feedback">{{ $categoryMessage }}</p>@endif

    <div class="module-settings-workspace">
        <div>
        @if ($section === 'categories')
            @include('livewire.category.partials.section')
        @else

    <form wire:submit="save" class="learning-vocabulary" id="learning-vocabulary">
        <x-ui.card variant="outlined" title="Vocabulario" icon="bi-spellcheck">
            <div class="d-flex flex-column gap-4">
                <p class="md-body-medium mb-0">Cómo llamas a cada parte de tu formación. Cambia las palabras que prefieras; lo que dejes vacío vuelve al nombre por defecto.</p>

                @foreach ($vocab->typeLabels() as $type => $typeLabel)
                    <fieldset class="learning-vocabulary__group" wire:key="vocabulary-{{ $type }}">
                        <legend class="md-title-small">{{ $typeLabel }}</legend>
                        @foreach ($vocab->kinds($type) as $kind)
                            @php $default = $vocab->defaultTerm($type, $kind); @endphp
                            <div class="md-field-pair" wire:key="vocabulary-{{ $type }}-{{ $kind }}">
                                <x-ui.field :name="'vocabulary.'.$type.'.'.$kind.'.singular'" :label="'Singular · '.$default['singular']" maxlength="30" wire:model="vocabulary.{{ $type }}.{{ $kind }}.singular" />
                                <x-ui.field :name="'vocabulary.'.$type.'.'.$kind.'.plural'" :label="'Plural · '.$default['plural']" maxlength="30" wire:model="vocabulary.{{ $type }}.{{ $kind }}.plural" />
                            </div>
                        @endforeach
                        @php $kinds = $vocab->kinds($type); @endphp
                        <p class="md-body-small learning-vocabulary__preview">
                            Vista previa: {{ implode(' · ', array_map(fn ($kind) => $vocab->addLabel($uid, $type, $kind), $kinds)) }}
                        </p>
                    </fieldset>
                @endforeach

                <div class="d-flex justify-content-between gap-2 flex-wrap">
                    <x-ui.action variant="text" type="button" wire:click="restore">Restaurar valores</x-ui.action>
                    <x-ui.action type="submit" wire:loading.attr="disabled">Guardar</x-ui.action>
                </div>
            </div>
        </x-ui.card>
    </form>
        @endif
        </div>
        <x-settings-sections label="Secciones de Ajustes de Formación" :items="$sections" :current="$section" />
    </div>
</x-module-shell>

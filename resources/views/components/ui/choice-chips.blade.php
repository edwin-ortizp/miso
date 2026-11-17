@aware(['validationState' => null])
@props([
    'name',
    'label',
    'options' => [],
    'short' => [],
    'multiple' => true,
    'shape' => 'pill',
    'help' => null,
    'error' => null,
    'local' => false,
    'live' => false,
    'selected' => null,
])

@php
    abort_unless(in_array($shape, ['pill', 'round'], true), 500, "Forma de selector no soportada: {$shape}");

    $groupId = \App\Support\Ui\FieldIdentity::for($name, $label);
    $message = $error ?? (isset($errors) ? $errors->first($name) : null);
    $describedBy = trim(($help ? $groupId.'-help ' : '').($message ? $groupId.'-error' : ''));
@endphp

{{--
    Selector táctil de opciones cortas (días de la semana, síntomas, escalas 1-5).
    Enlazado a una propiedad de Livewire: arreglo si `multiple`, valor simple si no.
    Cada opción es un botón con aria-pressed y objetivo mínimo de 44 px.
--}}
<fieldset {{ $attributes->class(['md-choice-chips', 'md-choice-chips--'.$shape, 'md-error' => $message]) }}
          x-data="{
              multiple: @js((bool) $multiple),
              selected: @if ($local) @js($selected ?? ($multiple ? [] : '')) @else $wire.entangle('{{ $name }}'){{ $live ? '.live' : '' }} @endif,
              isOn(value) {
                  return this.multiple ? (this.selected ?? []).map(String).includes(String(value)) : String(this.selected ?? '') === String(value)
              },
              toggle(value) {
                  if (! this.multiple) { this.selected = String(this.selected ?? '') === String(value) ? '' : value; return }
                  const current = this.selected ?? []
                  this.selected = this.isOn(value) ? current.filter((item) => String(item) !== String(value)) : [...current, value]
              },
          }"
          @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif>
    <legend class="md-label-medium">{{ $label }}</legend>
    <div class="md-choice-chips__rail" role="group" aria-label="{{ $label }}">
        @foreach ($options as $value => $optionLabel)
            <button type="button"
                    class="md-choice-chip"
                    :class="{ 'is-selected': isOn(@js((string) $value)) }"
                    :aria-pressed="isOn(@js((string) $value)) ? 'true' : 'false'"
                    @if ($shape === 'round') aria-label="{{ $optionLabel }}" title="{{ $optionLabel }}" @endif
                    @click="toggle(@js((string) $value))">
                @if ($shape === 'pill')<i class="bi bi-check2 md-choice-chip__check" aria-hidden="true"></i>@endif
                <span>{{ $shape === 'round' ? ($short[$value] ?? mb_substr($optionLabel, 0, 1)) : $optionLabel }}</span>
            </button>
        @endforeach
    </div>
    @if ($help)<p class="md-body-small md-choice-chips__help" id="{{ $groupId }}-help">{{ $help }}</p>@endif
    @if ($message)<p class="md-body-small md-choice-chips__error" id="{{ $groupId }}-error" role="alert">{{ $message }}</p>@endif
</fieldset>

@aware(['validationState' => null])
@props([
    'name' => null,
    'label',
    'labelExpression' => null,
    'type' => 'text',
    'id' => null,
    'value' => null,
    'placeholder' => ' ',
    'help' => null,
    'error' => null,
    'required' => false,
    'disabled' => false,
    'icon' => null,
])

@php
    $fieldId = $id ?? \App\Support\Ui\FieldIdentity::for($name, $label);
    $message = $error ?? ($name && isset($errors) ? $errors->first($name) : null);
    $describedBy = collect([
        $help ? "{$fieldId}-help" : null,
        $message ? "{$fieldId}-error" : null,
    ])->filter()->implode(' ');
@endphp

<div class="md-text-field md-field @if ($icon) md-field--icon @endif @if ($message) md-error @endif" @if($message && $validationState) :class="{ 'md-error': {{ $validationState }} }" @endif>
    @if ($icon)<i class="bi {{ $icon }} md-field__icon" aria-hidden="true"></i>@endif
    <input type="{{ $type }}"
           id="{{ $fieldId }}"
           @if ($name) name="{{ $name }}" @endif
           value="{{ $value }}"
           placeholder="{{ $placeholder ?: ' ' }}"
           {{ $attributes->class(['md-field__control']) }}
           @required($required)
           @disabled($disabled)
           @if ($message) aria-invalid="true" @if($validationState) :aria-invalid="{{ $validationState }} ? 'true' : null" @endif @endif
           @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif>
    <label for="{{ $fieldId }}" @if($labelExpression) x-text="{{ $labelExpression }}" @endif>{{ $label }}@if ($required)<span aria-hidden="true"> *</span>@endif</label>
    @if ($help)
        <p id="{{ $fieldId }}-help" class="md-supporting-text">{{ $help }}</p>
    @endif
    @if ($message)
        <p id="{{ $fieldId }}-error" class="md-supporting-text" role="alert" @if($validationState) x-show="{{ $validationState }}" @endif>{{ $message }}</p>
    @endif
</div>

<x-ui.form-dialog module="learning" :open="$showForm" close="closeForm" submit-action="save" id="learning-editor" :title="($editingId ? 'Editar ' : 'Agregar ').mb_strtolower($dialogLabel)" icon="bi-mortarboard"
    :sections="['basic' => ['label' => 'Información básica', 'icon' => 'bi-mortarboard', 'error' => $errors->hasAny(['draft.title', 'draft.type', 'draft.description', 'draft.status', 'draft.kind'])], 'organization' => ['label' => 'Organización', 'icon' => 'bi-diagram-3', 'error' => $errors->hasAny(['draft.category', 'draft.institution', 'draft.start_date', 'draft.end_date', 'draft.credits'])]]">
    <div class="learning-editor" x-init="section = @js($formSection)" @learning-form-invalid.window="section = $event.detail.section; $nextTick(() => $el.querySelector('[aria-invalid=true]')?.focus())">
        <x-ui.form-dialog-section name="basic" title="Información básica">
            <div class="learning-fields">
                @if ($editor === 'formation')
                    <x-ui.select name="draft.type" label="Tipo de formación" :required="true" :options="\App\Models\LearningFormation::TYPES" placeholder="¿Qué vas a estudiar?" wire:model.live="draft.type" :disabled="$typeLocked" />
                    @if ($typeLocked)<p>El tipo se conserva porque esta formación ya tiene contenido organizado.</p>@endif
                @else
                    <p>Dentro de: {{ $formation->title }}@if($editorParent) / {{ $editorParentLabel }}@endif</p>
                @endif
                <x-ui.field name="draft.title" label="Nombre" :required="true" maxlength="160" wire:model="draft.title" />
                <x-ui.textarea name="draft.description" label="Descripción" wire:model="draft.description" rows="3" />
                <x-ui.select name="draft.status" label="Estado" :options="\App\Models\LearningFormation::STATES" wire:model="draft.status" />
            </div>
        </x-ui.form-dialog-section>
        <x-ui.form-dialog-section name="organization" title="Organización">
            <div class="learning-fields">
                @if ($editor === 'formation')
                    <x-ui.category-select name="draft.category" label="Categoría" placeholder="Sin categoría" :options="$categories" wire:model="draft.category" />
                    <p>La categoría agrupa formaciones. Agrega los periodos y contenidos dentro de cada formación.</p>
                    <x-ui.field name="draft.institution" label="Institución o plataforma" maxlength="255" wire:model="draft.institution" />
                @endif
                <div class="md-field-pair">
                    <x-ui.field name="draft.start_date" label="Fecha de inicio" type="date" wire:model="draft.start_date" />
                    <x-ui.field name="draft.end_date" label="Fecha final" type="date" wire:model="draft.end_date" />
                </div>
                @if ($editor === 'unit' && $editorKind === 'subject')<x-ui.field name="draft.credits" label="Créditos (opcional)" type="number" min="0" max="100" step="0.5" wire:model="draft.credits" />@endif
            </div>
        </x-ui.form-dialog-section>
    </div>
</x-ui.form-dialog>

@php
    $basicErrors = $errors->hasAny(['planTitle', 'planType', 'planCategory', 'planCity', 'planAddress', 'planScheduledOn', 'planEndsOn', 'planNotes']);
    $linkErrors = $errors->hasAny(['planLinks', 'planLinks.*.url', 'planLinks.*.label']);
    $imageErrors = $errors->hasAny(['planImages', 'planImages.*']);
@endphp

<x-ui.form-dialog :open="$showPlanForm" close="closePlanForm" submit-action="savePlan" id="plan-dialog"
                  :title="$editingPlanId ? 'Editar plan' : 'Agregar plan'" icon="bi-map"
                  :sections="[
                      'basic' => ['label' => 'Información básica', 'icon' => 'bi-file-earmark-text', 'error' => $basicErrors],
                      'reminders' => ['label' => 'Recordatorios', 'icon' => 'bi-bell', 'error' => $errors->hasAny(['reminders', 'reminders.*'])],
                      'images' => ['label' => 'Imágenes', 'icon' => 'bi-images', 'error' => $imageErrors],
                      'people' => ['label' => 'Personas y círculos', 'icon' => 'bi-people'],
                      'links' => ['label' => 'Enlaces', 'icon' => 'bi-link-45deg', 'error' => $linkErrors],
                  ]">
    <x-ui.form-dialog-section name="basic" title="Información básica" description="El plan existe una sola vez y se comparte con quien quieras.">
        <div class="md-form-dialog__grid">
            <div class="md-form-dialog__full">
                <x-ui.field name="planTitle" label="Nombre" :required="true" wire:model.blur="planTitle" />
            </div>
            <x-ui.select name="planType" label="Tipo" :options="$typeOptions" :selected="$planType" icon="bi-tag" :required="true" wire:model="planType" />
            <x-ui.category-select name="planCategoryId" label="Categoría" :options="$planCategoryOptions" :selected="$planCategoryId" placeholder="Sin categoría" icon="bi-bookmark" wire:model.live="planCategoryId" />
            <x-ui.field name="planCity" label="Ciudad" icon="bi-geo-alt" wire:model.blur="planCity" />
            <x-ui.field name="planAddress" label="Dirección" wire:model.blur="planAddress" />
            <x-ui.field name="planScheduledOn" type="date" label="Fecha (opcional)" wire:model.blur="planScheduledOn" />
            <x-ui.field name="planEndsOn" type="date" label="Hasta (opcional)" wire:model.blur="planEndsOn" />
            <div class="md-form-dialog__full">
                <x-ui.textarea name="planNotes" label="Notas" rows="3" wire:model.blur="planNotes" />
            </div>
            <div class="md-form-dialog__full">
                <x-ui.multi-select name="planTags" label="Etiquetas" :options="$planTagOptions" all-label="Sin etiquetas" icon="bi-tags" />
            </div>
        </div>
    </x-ui.form-dialog-section>

    <x-ui.form-dialog-section name="reminders" title="Recordatorios">
        @include('livewire.notifications.reminder-fields')
    </x-ui.form-dialog-section>
    <x-ui.form-dialog-section name="images" title="Imágenes" description="Pega la URL de cada imagen; no se descarga. La primera es la principal y es la que se muestra en listados y detalle.">
        <div class="plan-images-form">
            @foreach ($planImages as $index => $url)
                <div class="plan-images-form__row" wire:key="plan-image-{{ $index }}">
                    <span class="plan-images-form__preview" aria-hidden="true">
                        <i class="bi bi-image"></i>
                        @if (filter_var($url, FILTER_VALIDATE_URL))
                            <img src="{{ $url }}" alt="" referrerpolicy="no-referrer" x-data x-on:error="$el.remove()">
                        @endif
                    </span>
                    <x-ui.field name="planImages.{{ $index }}" type="url" :label="$index === 0 ? 'Imagen principal' : 'Imagen '.($index + 1)" icon="bi-link-45deg" wire:model.blur="planImages.{{ $index }}" />
                    <x-ui.icon-action icon="bi-arrow-up" :label="'Subir imagen '.($index + 1)" :disabled="$index === 0" wire:click="movePlanImageUp({{ $index }})" />
                    <x-ui.icon-action icon="bi-trash" :label="'Quitar imagen '.($index + 1)" wire:click="removePlanImage({{ $index }})" />
                </div>
            @endforeach
            <div>
                <x-ui.action variant="tonal" icon="bi-plus-lg" wire:click="addPlanImage">Añadir imagen</x-ui.action>
            </div>
        </div>
    </x-ui.form-dialog-section>

    <x-ui.form-dialog-section name="people" title="Personas y círculos" description="Asócialo a uno o más círculos y a las personas con quienes quieres hacerlo.">
        <div class="md-form-dialog__grid">
            <div class="md-form-dialog__full">
                <x-ui.multi-select name="planCircles" label="Círculos" :options="$circleOptions" all-label="Sin círculos" icon="bi-diagram-3" />
            </div>
            <div class="md-form-dialog__full">
                <x-ui.multi-select name="planPeople" label="Personas" :options="$peopleOptions" all-label="Sin personas" icon="bi-people" />
            </div>
        </div>
    </x-ui.form-dialog-section>

    <x-ui.form-dialog-section name="links" title="Enlaces" description="Instagram, TikTok, Google Maps o cualquier página de referencia.">
        <div class="plan-links-form">
            @foreach ($planLinks as $index => $link)
                <div class="plan-links-form__row" wire:key="plan-link-{{ $index }}">
                    <x-ui.field name="planLinks.{{ $index }}.url" type="url" label="Enlace" icon="bi-link-45deg" wire:model.blur="planLinks.{{ $index }}.url" />
                    <x-ui.field name="planLinks.{{ $index }}.label" label="Descripción (opcional)" wire:model.blur="planLinks.{{ $index }}.label" />
                    <x-ui.icon-action icon="bi-trash" label="Quitar enlace" wire:click="removePlanLink({{ $index }})" />
                </div>
            @endforeach
            <div>
                <x-ui.action variant="tonal" icon="bi-plus-lg" wire:click="addPlanLink">Añadir enlace</x-ui.action>
            </div>
        </div>
    </x-ui.form-dialog-section>
</x-ui.form-dialog>

<x-ui.form-dialog :open="$showVisitForm" close="closeVisitForm" submit-action="saveVisit" id="plan-visit-dialog"
                  title="Registrar plan realizado" icon="bi-check2-circle">
    <div class="md-form-dialog__grid">
        <x-ui.field name="visitDate" type="date" label="Fecha" :required="true" wire:model="visitDate" />
        <div class="md-form-dialog__full">
            <x-ui.multi-select name="visitPeople" label="Con quién fuiste" :options="$peopleOptions" all-label="Elige una o más personas" icon="bi-people" />
        </div>
        <div class="md-form-dialog__full">
            <x-ui.textarea name="visitComment" label="Comentario (opcional)" rows="3" wire:model="visitComment" />
        </div>
    </div>
</x-ui.form-dialog>

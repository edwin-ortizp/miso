    <x-ui.form-dialog :open="$showForm" close="closeForm" submit-action="save" id="goal-dialog"
                      :title="$editingId ? 'Editar objetivo' : 'Agregar objetivo'" icon="bi-flag"
                      :submit="$editingId ? 'Actualizar' : 'Guardar'"
                      :sections="[
                          'basic' => ['label' => 'Información básica', 'icon' => 'bi-flag', 'error' => $errors->hasAny(['title', 'startDate', 'dueDate'])],
                          'reminders' => ['label' => 'Recordatorios', 'icon' => 'bi-bell', 'error' => $errors->hasAny(['reminders', 'reminders.*'])],
                          'kpi' => ['label' => 'Indicador (KPI)', 'icon' => 'bi-graph-up-arrow', 'error' => $errors->hasAny(['kpiName', 'kpiUnit', 'kpiStartValue', 'kpiTargetValue'])],
                      ]">
        <x-ui.form-dialog-section name="basic" title="Información básica">
            <div class="d-flex flex-column gap-3">
                <x-ui.field name="title" label="Título" :required="true" wire:model="title" />
                <x-ui.textarea name="description" label="Descripción" rows="3" wire:model="description" />
                <x-ui.select name="formStatus" label="Estado" :selected="$formStatus"
                             :options="['active' => 'Activo', 'completed' => 'Completado', 'abandoned' => 'Abandonado']"
                             wire:model="formStatus" />
                <div class="md-field-pair">
                    <x-ui.field name="startDate" label="Fecha inicio" type="date" wire:model="startDate" />
                    <x-ui.field name="dueDate" label="Fecha límite" type="date" wire:model="dueDate" />
                </div>
            </div>
        </x-ui.form-dialog-section>

        <x-ui.form-dialog-section name="reminders" title="Recordatorios">
            @include('livewire.notifications.reminder-fields')
        </x-ui.form-dialog-section>
        <x-ui.form-dialog-section name="kpi" title="Indicador (KPI)" description="Opcional: compara el avance real con el ritmo esperado.">
            <div class="d-flex flex-column gap-3">
                <label class="goal-kpi-toggle">
                    <input wire:model.live="kpiEnabled" type="checkbox">
                    <span><i class="bi bi-graph-up-arrow" aria-hidden="true"></i> Configurar KPI único</span>
                </label>

                @if ($kpiEnabled)
                    <div class="goal-kpi-form">
                        <x-ui.field name="kpiName" label="Nombre del KPI" wire:model="kpiName" />
                        <x-ui.field name="kpiUnit" label="Unidad" wire:model="kpiUnit" />
                        <x-ui.select name="kpiDirection" label="Dirección" :selected="$kpiDirection"
                                     :options="['increase' => 'Aumentar hasta la meta', 'decrease' => 'Reducir hasta la meta']"
                                     wire:model="kpiDirection" />
                        <div class="md-field-pair">
                            <x-ui.field name="kpiStartValue" label="Valor inicial" type="number" step="0.01" wire:model="kpiStartValue" />
                            <x-ui.field name="kpiTargetValue" label="Valor objetivo" type="number" step="0.01" wire:model="kpiTargetValue" />
                        </div>
                        <p class="goal-kpi-form__hint">Con fechas de inicio y límite, el detalle comparará el avance real con el ritmo esperado.</p>
                    </div>
                @endif
            </div>
        </x-ui.form-dialog-section>
    </x-ui.form-dialog>

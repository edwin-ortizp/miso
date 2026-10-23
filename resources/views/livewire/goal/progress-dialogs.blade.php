    <x-ui.form-dialog :open="$showEntryForm" close="$set('showEntryForm', false)" submit-action="saveEntry" id="goal-entry-dialog"
                      :title="$editingEntryId ? 'Editar avance' : 'Registrar avance'" icon="bi-journal-plus">
        <div class="d-flex flex-column gap-3">
            <x-ui.textarea name="entryText" label="¿Qué avanzaste?" rows="4" :required="true" wire:model="entryText" />
            <x-ui.field name="entryDate" label="Fecha" type="date" wire:model="entryDate" />
            <label class="goal-kpi-toggle"><input wire:model="entryIsMilestone" type="checkbox"><span><i class="bi bi-star" aria-hidden="true"></i> Marcar como hito</span></label>
        </div>
    </x-ui.form-dialog>

    <x-ui.form-dialog :open="$showNumericForm" close="$set('showNumericForm', false)" submit-action="saveNumericEntry" id="goal-numeric-dialog"
                      :title="$editingNumericId ? 'Editar medición' : 'Registrar medición'" icon="bi-graph-up-arrow">
        <div class="d-flex flex-column gap-3">
            <x-ui.field name="numericValue" :label="'Valor '.($kpi['unit'] ?? '')" type="number" step="0.01" :required="true" wire:model="numericValue" />
            <x-ui.field name="numericDate" label="Fecha" type="date" wire:model="numericDate" />
            <x-ui.textarea name="numericNote" label="Nota opcional" rows="2" wire:model="numericNote" />
        </div>
    </x-ui.form-dialog>


<x-ui.dialog :standard="$panelDialogs ?? false" state="showRecurringDialog" title="Programar próxima repetición" close-expression="$wire.cancelRecurringCompletion()">

                <p class="md-body-medium">La tarea actual se marcará como completada y se creará la siguiente ocurrencia.</p>
                <div class="md-text-field">
                    <input type="date" wire:model="nextOccurrenceDate" placeholder=" " id="next-occurrence-date">
                    <label for="next-occurrence-date">Próxima fecha</label>
                    @error('nextOccurrenceDate')
                        <div class="md-supporting-text" style="color: var(--md-sys-color-error);">{{ $message }}</div>
                    @enderror
                </div>
            <x-slot:actions>
                <button wire:click="cancelRecurringCompletion" class="md-btn-text">Cancelar</button>
                <button wire:click="confirmRecurringCompletion" class="md-btn-filled"><i class="bi bi-check-lg"></i> Completar y programar</button>
            </x-slot:actions>
</x-ui.dialog>

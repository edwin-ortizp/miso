{{-- CHG-072: bienvenida al volver tras una ausencia. Una vez por regreso, sin culpa. --}}
<div>
    @if ($visible)
        <section class="md-card-elevated welcome-back mb-3" aria-labelledby="welcome-back-title" x-data x-init="$el.querySelector('h2')?.focus()">
            @if ($absence)
                <div class="d-flex gap-3 align-items-start">
                    @if ($guardian && $guardian['image'])
                        <img src="{{ $guardian['image'] }}" width="72" height="72" alt="" class="welcome-back__guardian">
                    @endif
                    <div class="flex-grow-1">
                        <h2 id="welcome-back-title" class="md-title-large mb-1" tabindex="-1">Qué bueno verte{{ $name ? ', '.$name : '' }}.</h2>
                        <p class="md-body-medium mb-3">¿Retomamos con algo pequeño?</p>

                        @if ($behind > 0)
                            <p class="md-title-small mb-2">{{ $behind === 1 ? '1 tarea se quedó atrás' : $behind.' tareas se quedaron atrás' }}</p>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <x-ui.action type="button" variant="tonal" wire:click="moveToToday" wire:loading.attr="disabled">Pasar a hoy</x-ui.action>
                                <x-ui.action type="button" variant="outlined" wire:click="$toggle('choosingDate')">Elegir fecha</x-ui.action>
                                <x-ui.action type="button" variant="outlined" wire:click="clearDates" wire:loading.attr="disabled">Dejar sin fecha</x-ui.action>
                            </div>
                            @if ($choosingDate)
                                <form wire:submit="moveToDate" class="d-flex flex-wrap gap-2 align-items-end mb-2">
                                    <x-ui.field name="targetDate" label="Nueva fecha" type="date" wire:model="targetDate" :error="$errors->has('targetDate') ? 'Elige hoy o una fecha futura.' : null" />
                                    <x-ui.action type="submit">Mover</x-ui.action>
                                </form>
                            @endif
                            <a href="{{ route('tasks.list') }}?via=return" wire:navigate class="md-body-small d-inline-block mb-3">Revisarlas una por una</a>
                        @endif

                        <p class="md-body-medium mb-2">
                            <a href="{{ route('habits', ['date' => $absence['first_missed']]) }}&via=return" wire:navigate>¿Hiciste algún hábito estos días? Márcalo</a>
                        </p>

                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <span class="md-body-medium">Para empezar:</span>
                            <x-ui.action type="button" variant="tonal" wire:click="addWater" wire:loading.attr="disabled">💧 Un vaso de agua</x-ui.action>
                            <x-ui.action type="button" variant="text" wire:click="dismiss">Ir a mi día</x-ui.action>
                        </div>
                    </div>
                </div>
            @endif

            @if ($message)
                <p class="md-body-medium mb-0 mt-2" role="status" aria-live="polite">
                    {{ $message }}
                    @if ($undo)
                        <button type="button" class="md-btn-text" wire:click="undoLast">Deshacer</button>
                    @endif
                </p>
            @endif
        </section>
    @endif
</div>

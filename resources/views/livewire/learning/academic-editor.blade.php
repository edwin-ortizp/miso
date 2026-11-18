@php
    use App\Services\Learning\GradeProjection;
    $title = match ($dialog) {
        'task' => 'Agregar tarea', 'evaluation' => $entry ? 'Editar evaluación' : 'Agregar evaluación',
        'score' => $entry?->score !== null ? 'Editar nota' : 'Registrar nota',
        'book' => 'Configurar calificación', 'cut' => $editing ? 'Editar corte' : 'Agregar corte',
        'simulate' => 'Simular notas', default => '',
    };
@endphp
<div>
    <x-ui.form-dialog :open="$dialog !== ''" :title="$title" id="learning-academic-dialog" icon="bi-journal-check" module="learning"
        close="close" :submit-action="$dialog === 'simulate' ? 'simulate' : 'save'"
        :submit="$dialog === 'simulate' ? 'Calcular' : 'Guardar'" :protect-changes="$dialog !== 'simulate'" :focus-errors="true">
        @if ($subject && ($context || $dialog !== 'task'))
            <p class="learning-academic-context">{{ $subject->title }}</p>
        @endif

        @if (in_array($dialog, ['task', 'evaluation', 'cut'], true))
            <x-ui.field name="draft.title" label="Título" wire:model="draft.title" :maxlength="$dialog === 'cut' ? 160 : 255"
                :disabled="filled($entry?->task_id)" required />
        @endif

        @if ($dialog === 'task')
            @if (! $context)
                <x-ui.select name="unitId" label="Materia" wire:model.live="unitId" :options="$subjects" placeholder="Elige una materia" required />
            @endif
            <x-ui.field name="draft.date" label="Fecha de entrega (opcional)" type="date" wire:model="draft.date" />
            <label class="md-checkbox learning-academic-toggle">
                <input type="checkbox" wire:model.live="graded"> Cuenta para la nota
            </label>
            @if ($graded && ! $book)
                <p class="md-supporting-text">Elige la materia para indicar cómo cuenta la entrega.</p>
            @endif
        @elseif ($dialog === 'evaluation')
            @if ($entry?->task_id)
                <p class="md-supporting-text">El título y la fecha se editan desde la tarea vinculada.</p>
            @else
                <x-ui.select name="draft.kind" label="Tipo de evaluación" wire:model="draft.kind"
                    :options="['exam' => 'Parcial', 'work' => 'Trabajo', 'other' => 'Otra evaluación']" />
                <div class="learning-academic-fields">
                    <x-ui.field name="draft.date" label="Fecha (opcional)" type="date" wire:model="draft.date" />
                    <x-ui.field name="draft.time" label="Hora (opcional)" type="time" wire:model="draft.time" />
                </div>
            @endif
        @endif

        @if ($gradeFields && $book)
            @if ($book->mode === 'cuts')
                <x-ui.select name="draft.cut_id" label="Corte" wire:model="draft.cut_id" :options="$cuts->pluck('title', 'id')->all()" placeholder="Por asignar" />
            @endif
            <x-ui.field name="draft.weight" :label="$book->mode === 'cuts' ? 'Peso dentro del corte (%)' : 'Peso en la materia (%)'"
                wire:model="draft.weight" inputmode="decimal" help="Puedes dejarlo pendiente." />
        @endif

        @if (in_array($dialog, ['score', 'evaluation'], true) && $book)
            @if ($dialog === 'score')
                <p>{{ $entry?->task_title ?? $entry?->title }}</p>
            @endif
            <x-ui.field name="draft.score" label="Nota" wire:model="draft.score" inputmode="decimal"
                :help="'Escala de '.GradeProjection::number($book->minimum).' a '.GradeProjection::number($book->maximum).'. En blanco queda pendiente.'" />
        @elseif ($dialog === 'book')
            <x-ui.select name="draft.mode" label="Esquema" wire:model="draft.mode" :options="['direct' => 'Pesos directos', 'cuts' => 'Por cortes']" />
            <div class="learning-academic-fields">
                <x-ui.field name="draft.minimum" label="Nota mínima de la escala" wire:model="draft.minimum" inputmode="decimal" required />
                <x-ui.field name="draft.maximum" label="Nota máxima de la escala" wire:model="draft.maximum" inputmode="decimal" required />
            </div>
            <x-ui.field name="draft.passing" label="Mínimo para pasar" wire:model="draft.passing" inputmode="decimal" required />
        @elseif ($dialog === 'cut')
            <x-ui.field name="draft.weight" label="Peso del corte en la materia (%)" wire:model="draft.weight" inputmode="decimal" required />
        @elseif ($dialog === 'simulate' && $book)
            <p class="md-supporting-text">Prueba notas de {{ GradeProjection::number($book->minimum) }} a {{ GradeProjection::number($book->maximum) }}. Puedes dejar campos vacíos.</p>
            @foreach ($entries->whereNull('score') as $pending)
                <div wire:key="simulation-{{ $pending->id }}">
                    <x-ui.field :name="'scenario.'.$pending->id" :label="$pending->task_title ?? $pending->title"
                        :wire:model="'scenario.'.$pending->id" inputmode="decimal"
                        :help="($pending->weight === null ? 'Peso pendiente' : GradeProjection::percent($pending->weight / 100))
                            .($pending->cut_id ? ' de '.($cuts->get($pending->cut_id)?->title ?? 'su corte') : ' de la materia')" />
                </div>
            @endforeach
            @error('simulation') <p role="alert">{{ $message }}</p> @enderror
            <div role="status" aria-live="polite">
                @if ($simulation)
                    @php($finished = in_array($simulation['state'], ['passed', 'failed'], true))
                    <p class="learning-academic-forecast">
                        {{ $finished ? 'Nota simulada '.GradeProjection::number($simulation['value']).' · '.($simulation['state'] === 'passed' ? 'Alcanza el mínimo' : 'Queda por debajo del mínimo') : GradeProjection::headline($simulation) }}
                    </p>
                    @if ($simulation['rounding'])
                        <p class="md-supporting-text">El resultado antes de redondear queda por debajo del mínimo.</p>
                    @endif
                @endif
            </div>
            <x-ui.action variant="text" wire:click="resetScenario">Restablecer notas</x-ui.action>
        @endif
    </x-ui.form-dialog>
</div>

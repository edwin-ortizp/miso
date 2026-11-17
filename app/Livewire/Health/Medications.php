<?php

namespace App\Livewire\Health;

use App\Actions\LogMedicationIntake;
use App\Actions\SaveMedication;
use App\Livewire\Concerns\WithManagementCard;
use App\Models\Medication;
use App\Models\MedicationIntake;
use App\Support\MedicationSchedule;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * CHG-133: medicamentos programados y sus tomas. «Hoy» muestra lo que toca; el historial lista lo
 * tomado u omitido. MiSo registra; no recomienda dosis ni detecta interacciones.
 */
#[Layout('layouts.app')]
#[Title('Medicamentos')]
class Medications extends Component
{
    use WithManagementCard;

    public const PERIODS = [
        'all' => 'Todo',
        '7' => 'Últimos 7 días',
        '30' => 'Últimos 30 días',
        '90' => 'Últimos 90 días',
    ];

    public const SORTS = [
        'recent' => 'Más reciente',
        'oldest' => 'Más antiguo',
    ];

    #[Url(as: 'period', history: true, except: 'all')]
    public string $period = 'all';

    #[Url(as: 'sort', history: true, except: 'recent')]
    public string $sort = 'recent';

    // Diálogo de medicamento
    public bool $showForm = false;
    public ?string $editingId = null;
    public string $name = '';
    public string $dose_amount = '';
    public string $dose_unit = '';
    public string $form = '';
    public string $frequency_type = 'daily';
    /** @var list<string> Horarios HH:MM, uno por selector de hora. */
    public array $times = ['08:00'];
    public string $interval_hours = '8';
    /** @var list<string> */
    public array $weekdays = [];
    public string $starts_on = '';
    public string $ends_on = '';
    public string $instructions = '';
    public string $notes = '';
    public bool $is_sensitive = false;

    // Diálogo de toma puntual
    public bool $showAdHoc = false;
    public string $adhocName = '';
    public string $adhocDoseAmount = '';
    public string $adhocDoseUnit = '';
    public string $adhocTakenAt = '';
    public string $adhocNotes = '';

    // Diálogo para omitir una toma programada
    public bool $showSkip = false;
    public string $skipMedicationId = '';
    public string $skipScheduledFor = '';
    public string $skipReason = '';

    public function updatedPeriod(): void
    {
        if (! array_key_exists($this->period, self::PERIODS)) {
            $this->period = 'all';
        }
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        if (! array_key_exists($this->sort, self::SORTS)) {
            $this->sort = 'recent';
        }
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('period');
        $this->resetPage();
    }

    public function applyFilters(string $period): void
    {
        $this->period = $period;
        $this->updatedPeriod();
    }

    // --- Tomas de hoy -------------------------------------------------------------------------

    public function take(string $medicationId, string $scheduledFor): void
    {
        LogMedicationIntake::take(Medication::findOrFail($medicationId), Carbon::parse($scheduledFor, config('app.timezone')), now(), ['source' => 'app']);
    }

    public function openSkip(string $medicationId, string $scheduledFor): void
    {
        $this->skipMedicationId = $medicationId;
        $this->skipScheduledFor = $scheduledFor;
        $this->skipReason = '';
        $this->showSkip = true;
    }

    public function closeSkip(): void
    {
        $this->showSkip = false;
        $this->reset('skipMedicationId', 'skipScheduledFor', 'skipReason');
        $this->resetErrorBag();
    }

    public function saveSkip(): void
    {
        $this->validate(['skipReason' => ['nullable', 'string', 'max:255']], [
            'skipReason.max' => 'El motivo es demasiado largo (máximo 255 caracteres).',
        ]);

        LogMedicationIntake::skip(
            Medication::findOrFail($this->skipMedicationId),
            Carbon::parse($this->skipScheduledFor, config('app.timezone')),
            $this->skipReason === '' ? null : $this->skipReason,
            ['source' => 'app'],
        );
        $this->closeSkip();
    }

    public function undo(string $intakeId): void
    {
        LogMedicationIntake::undo(MedicationIntake::findOrFail($intakeId));
    }

    public function deleteIntake(string $id): void
    {
        MedicationIntake::where('id', $id)->delete();
    }

    // --- Medicamentos -------------------------------------------------------------------------

    public function openForm(): void
    {
        $this->resetForm();
        $this->starts_on = now()->toDateString();
        $this->showForm = true;
    }

    public function edit(string $id): void
    {
        $medication = Medication::findOrFail($id);

        $this->resetForm();
        $this->editingId = $medication->id;
        $this->name = $medication->name;
        $this->dose_amount = $medication->dose_amount !== null ? rtrim(rtrim(number_format($medication->dose_amount, 2, '.', ''), '0'), '.') : '';
        $this->dose_unit = (string) $medication->dose_unit;
        $this->form = (string) $medication->form;
        $this->frequency_type = $medication->frequency_type;
        $this->times = array_values($medication->times ?? ['08:00']);
        $this->interval_hours = (string) ($medication->interval_hours ?? 8);
        $this->weekdays = array_map('strval', $medication->weekdays ?? []);
        $this->starts_on = $medication->starts_on->toDateString();
        $this->ends_on = $medication->ends_on?->toDateString() ?? '';
        $this->instructions = (string) $medication->instructions;
        $this->notes = (string) $medication->notes;
        $this->is_sensitive = $medication->is_sensitive;
        $this->showForm = true;
    }

    /** Añade un selector de hora; por defecto, dos horas después del último horario. */
    public function addTime(): void
    {
        if (count($this->times) >= 12) {
            return;
        }
        $last = end($this->times) ?: '06:00';
        $next = Carbon::createFromFormat('H:i', preg_match('/^\d{2}:\d{2}$/', $last) ? $last : '06:00')->addHours(2);
        $this->times[] = $next->format('H:i');
    }

    public function removeTime(int $index): void
    {
        if (count($this->times) <= 1) {
            return;
        }
        unset($this->times[$index]);
        $this->times = array_values($this->times);
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $times = array_values(array_filter(array_map(fn ($time) => trim((string) $time), $this->times), fn ($time) => $time !== ''));

        $data = $this->remapErrors(fn () => SaveMedication::validate([
            'name' => $this->name,
            'dose_amount' => $this->dose_amount === '' ? null : str_replace(',', '.', $this->dose_amount),
            'dose_unit' => $this->dose_unit === '' ? null : $this->dose_unit,
            'form' => $this->form === '' ? null : $this->form,
            'frequency_type' => $this->frequency_type,
            'times' => $times,
            'interval_hours' => $this->frequency_type === 'every_hours' && $this->interval_hours !== '' ? $this->interval_hours : null,
            'weekdays' => $this->frequency_type === 'weekdays' ? $this->weekdays : null,
            'starts_on' => $this->starts_on === '' ? null : $this->starts_on,
            'ends_on' => $this->ends_on === '' ? null : $this->ends_on,
            'instructions' => $this->instructions === '' ? null : $this->instructions,
            'notes' => $this->notes === '' ? null : $this->notes,
            'is_sensitive' => $this->is_sensitive,
        ]), []);

        SaveMedication::handle($data, $this->editingId ? Medication::findOrFail($this->editingId) : null);

        $this->closeForm();
    }

    public function finish(string $id): void
    {
        SaveMedication::finish(Medication::findOrFail($id));
    }

    public function resume(string $id): void
    {
        SaveMedication::resume(Medication::findOrFail($id));
    }

    public function deleteMedication(string $id): void
    {
        Medication::where('id', $id)->delete();
    }

    // --- Toma puntual -------------------------------------------------------------------------

    public function openAdHoc(): void
    {
        $this->resetErrorBag();
        $this->reset('adhocName', 'adhocDoseAmount', 'adhocDoseUnit', 'adhocNotes');
        $this->adhocTakenAt = now()->format('Y-m-d\TH:i');
        $this->showAdHoc = true;
    }

    public function closeAdHoc(): void
    {
        $this->showAdHoc = false;
        $this->resetErrorBag();
    }

    public function saveAdHoc(): void
    {
        $this->validate(['adhocTakenAt' => ['required', 'date']], [
            'adhocTakenAt.required' => 'Indica cuándo la tomaste.',
            'adhocTakenAt.date' => 'Indica una fecha y hora válidas.',
        ]);

        $data = $this->remapErrors(fn () => LogMedicationIntake::validate([
            'name' => $this->adhocName,
            'dose_amount' => $this->adhocDoseAmount === '' ? null : str_replace(',', '.', $this->adhocDoseAmount),
            'dose_unit' => $this->adhocDoseUnit === '' ? null : $this->adhocDoseUnit,
            'notes' => $this->adhocNotes === '' ? null : $this->adhocNotes,
        ]), ['name' => 'adhocName', 'dose_amount' => 'adhocDoseAmount', 'dose_unit' => 'adhocDoseUnit', 'notes' => 'adhocNotes']);

        LogMedicationIntake::adHoc($data, Carbon::parse($this->adhocTakenAt, config('app.timezone')), 'app');
        $this->closeAdHoc();
    }

    /**
     * Las acciones validan con las claves del dominio (times, name…); aquí cada error cae en el
     * campo de la pantalla que lo origina (times, adhocName…).
     *
     * @param  array<string, string>  $map
     */
    private function remapErrors(\Closure $validate, array $map): array
    {
        try {
            return $validate();
        } catch (ValidationException $e) {
            $errors = [];
            foreach ($e->errors() as $key => $messages) {
                $root = explode('.', $key)[0];
                $errors[$map[$root] ?? $key] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }
    }

    private function resetForm(): void
    {
        $this->resetErrorBag();
        $this->editingId = null;
        $this->name = '';
        $this->dose_amount = '';
        $this->dose_unit = '';
        $this->form = '';
        $this->frequency_type = 'daily';
        $this->times = ['08:00'];
        $this->interval_hours = '8';
        $this->weekdays = [];
        $this->starts_on = '';
        $this->ends_on = '';
        $this->instructions = '';
        $this->notes = '';
        $this->is_sensitive = false;
    }

    public function render()
    {
        $today = MedicationSchedule::forDay(now());
        $medications = Medication::query()->orderByDesc('is_active')->orderBy('name')->get();

        $history = MedicationIntake::query()
            ->when($this->period !== 'all', fn ($q) => $q->where(fn ($q) => $q
                ->where('taken_at', '>=', now()->subDays((int) $this->period)->startOfDay())
                ->orWhere('scheduled_for', '>=', now()->subDays((int) $this->period)->startOfDay())));
        $totalHistory = MedicationIntake::count();
        $history = $history
            ->orderByRaw('COALESCE(taken_at, scheduled_for, created_at) '.($this->sort === 'oldest' ? 'asc' : 'desc'))
            ->paginate($this->perPage());

        return view('livewire.health.medications', [
            'today' => $today,
            'guardianContext' => \App\Services\Game\Guardian\HealthContext::medications($today),
            'takenToday' => $today->whereIn('state', ['taken', 'skipped'])->count(),
            'medications' => $medications,
            'history' => $history,
            'totalHistory' => $totalHistory,
            'activeFilters' => $this->period !== 'all'
                ? [['key' => 'period', 'value' => null, 'label' => 'Periodo: '.self::PERIODS[$this->period], 'icon' => 'bi-calendar-event']]
                : [],
            'periods' => self::PERIODS,
            'sorts' => self::SORTS,
            'frequencies' => Medication::FREQUENCIES,
            'weekdayOptions' => Medication::WEEKDAYS,
        ]);
    }
}

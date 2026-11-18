<?php

namespace App\Support\Habits;

use App\Models\HabitAction as HabitActionSetting;
use App\Models\HabitCompletion;
use App\Models\HabitDefinition;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Ejecuta —y deshace— la acción asociada a un hábito.
 *
 * Vive fuera de HabitGamificationService a propósito: un fallo registrando en otro
 * módulo nunca debe tumbar el completado del hábito, así que esto corre después de
 * que la completion ya está confirmada y captura sus propios errores.
 */
class HabitActionRunner
{
    /** Forma neutra del resultado, para que el contrato de toggle() no cambie de claves. */
    public const EMPTY = [
        'requiresInput' => false,
        'promptFields' => [],
        'actionResult' => null,
    ];

    public function __construct(private readonly HabitActionRegistry $registry) {}

    /**
     * @return array{requiresInput: bool, promptFields: array<int, array<string, mixed>>, actionResult: ?string}
     */
    public function afterComplete(HabitDefinition $habit, HabitCompletion $completion, CarbonInterface $date): array
    {
        $setting = $this->settingFor($habit);
        $handler = $setting?->handler();

        if (! $setting || ! $handler) {
            return self::EMPTY;
        }

        if ($setting->asksForInput()) {
            return [
                'requiresInput' => true,
                'promptFields' => array_map(
                    fn (HabitActionField $field) => $field->toArray(),
                    $handler->promptFields($setting->config ?? []),
                ),
                'actionResult' => null,
            ];
        }

        return array_merge(self::EMPTY, [
            'actionResult' => $this->run($handler, $setting, $habit, $completion, $date, []),
        ]);
    }

    /**
     * Segundo paso del modo «prompt»: el usuario (o un agente) ya respondió.
     *
     * @param  array<string, mixed>  $input
     */
    public function resolve(HabitDefinition $habit, HabitCompletion $completion, CarbonInterface $date, array $input): ?string
    {
        $setting = $this->settingFor($habit);
        $handler = $setting?->handler();

        if (! $setting || ! $handler) {
            return null;
        }

        return DB::transaction(function () use ($handler, $setting, $habit, $completion, $date, $input): ?string {
            $current = HabitCompletion::query()->lockForUpdate()->findOrFail($completion->id);
            if (! $current->completed || $current->actionable_id) {
                return null;
            }

            return $this->run($handler, $setting, $habit, $current, $date, $this->validated($handler, $input));
        });
    }

    /** Deshace el registro que dejó la acción, si lo hubo. */
    public function afterUncomplete(HabitCompletion $completion): ?string
    {
        if (! $completion->actionable_type) {
            return null;
        }

        $record = $completion->actionable;
        $completion->forceFill(['actionable_type' => null, 'actionable_id' => null])->save();

        if (! $record) {
            return null;
        }

        try {
            $record->delete();

            return 'También quité el registro que se había creado.';
        } catch (\Throwable $exception) {
            Log::warning('No se pudo deshacer la acción de un hábito.', [
                'completion_id' => $completion->id,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Valida lo respondido contra los campos que la acción declara preguntar.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function validated(HabitAction $handler, array $input): array
    {
        $rules = [];
        $labels = [];

        foreach ($handler->promptFields() as $field) {
            $rules[$field->key] = $field->rules;
            $labels[$field->key] = $field->label;
        }

        if ($rules === []) {
            return [];
        }

        return Validator::make($input, $rules, [], $labels)->validate();
    }

    private function settingFor(HabitDefinition $habit): ?HabitActionSetting
    {
        $setting = $habit->action()->first();

        return $setting?->enabled ? $setting : null;
    }

    /** @param array<string, mixed> $input */
    private function run(
        HabitAction $handler,
        HabitActionSetting $setting,
        HabitDefinition $habit,
        HabitCompletion $completion,
        CarbonInterface $date,
        array $input,
    ): ?string {
        try {
            $result = $handler->execute($habit, $date, $setting->config ?? [], $input);
        } catch (\Throwable $exception) {
            Log::warning('Falló la acción asociada a un hábito.', [
                'habit_id' => $habit->id,
                'action_key' => $setting->action_key,
                'exception' => $exception->getMessage(),
            ]);

            return 'Marqué el hábito, pero no pude crear el registro en '.$handler::moduleLabel().'.';
        }

        if ($result->record) {
            $completion->forceFill([
                'actionable_type' => $result->record->getMorphClass(),
                'actionable_id' => $result->record->getKey(),
            ])->save();
        }

        return $result->message;
    }
}

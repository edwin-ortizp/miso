<?php

namespace App\Mcp\Tools\Health;

use App\Actions\LogSleep;
use App\Support\SleepProgress;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Registra un periodo de sueño del usuario (una noche o una siesta): slept_at (cuándo se durmió) y woke_at (cuándo despertó), ambos en formato YYYY-MM-DD HH:MM; quality opcional de 1 (muy mala) a 5 (muy buena). Calcula la duración y la compara con la meta de horas del usuario. Rechaza horarios imposibles o que se crucen con otro registro; repetir exactamente el mismo registro no lo duplica. No diagnostica: si duerme poco, informa con neutralidad. is_sensitive lo oculta de consultas amplias.')]
class LogSleepTool extends Tool
{
    public function handle(Request $request): Response
    {
        $input = $request->validate([
            'slept_at' => ['required', 'date'],
            'woke_at' => ['required', 'date'],
            'quality' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_sensitive' => ['nullable', 'boolean'],
        ]);

        $log = LogSleep::handle(LogSleep::validate($input), 'mcp');
        $goal = SleepProgress::goalMinutes(Auth::user());

        return Response::text(
            "Sueño registrado: {$log->durationText()} ({$log->slept_at->format('Y-m-d H:i')} → {$log->woke_at->format('Y-m-d H:i')})"
            .($log->quality ? ", calidad {$log->quality}/5" : '').'. '
            .SleepProgress::goalMessage($log->durationMinutes(), $goal).' ('.\App\Models\SleepLog::formatMinutes($goal).' de meta).'
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'slept_at' => $schema->string()->description('Cuándo se durmió (YYYY-MM-DD HH:MM).')->required(),
            'woke_at' => $schema->string()->description('Cuándo despertó (YYYY-MM-DD HH:MM).')->required(),
            'quality' => $schema->integer()->description('Cómo durmió, de 1 (muy mal) a 5 (muy bien). Opcional.'),
            'notes' => $schema->string()->description('Notas adicionales.'),
            'is_sensitive' => $schema->boolean()->description('Oculta el registro de consultas amplias.'),
        ];
    }
}

<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** CHG-067: retención D1/D7/D30 y registros por día activo a partir de `user_active_days`. */
class RetentionReport extends Command
{
    protected $signature = 'miso:retention {--days=30 : Ventana de días a analizar}';

    protected $description = 'Muestra retención D1/D7/D30, canales de entrada y registros por día activo';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $today = CarbonImmutable::today();
        $from = $today->subDays($days - 1)->toDateString();

        $rows = DB::table('user_active_days')->where('date', '>=', $from)->get(['user_id', 'date', 'first_channel', 'records_count']);
        if ($rows->isEmpty()) {
            $this->info("Sin días activos en los últimos {$days} días.");

            return self::SUCCESS;
        }

        $active = $rows->groupBy('user_id')->map(fn ($r) => $r->pluck('date')->map(fn ($d) => substr((string) $d, 0, 10))->flip());

        $retention = [];
        foreach ([1, 7, 30] as $n) {
            $eligible = 0;
            $returned = 0;
            foreach ($rows as $row) {
                $target = CarbonImmutable::parse($row->date)->addDays($n);
                if ($target->greaterThan($today)) {
                    continue;
                }
                $eligible++;
                $returned += isset($active[$row->user_id][$target->toDateString()]) ? 1 : 0;
            }
            $retention[] = ["D{$n}", $eligible, $eligible ? round(100 * $returned / $eligible, 1).' %' : '—'];
        }

        $this->info("Últimos {$days} días · usuarios activos: ".$active->count().' · días activos: '.$rows->count()
            .' · registros por día activo: '.round($rows->avg('records_count'), 2));
        $this->table(['Retención', 'Días base', 'Volvieron'], $retention);
        $this->table(['Canal de entrada', 'Días'], $rows->countBy('first_channel')->map(fn ($c, $k) => [$k, $c])->values()->all());

        return self::SUCCESS;
    }
}

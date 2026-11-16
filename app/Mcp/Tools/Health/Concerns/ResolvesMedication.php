<?php

namespace App\Mcp\Tools\Health\Concerns;

use App\Models\Medication;
use Illuminate\Support\Str;

trait ResolvesMedication
{
    /**
     * Busca un medicamento del usuario por id o por nombre (parcial, sin distinguir mayúsculas).
     * Devuelve el modelo o un texto de error en español para mostrar al asistente.
     *
     * @return Medication|string
     */
    protected function findMedication(string $reference, bool $onlyActive = false): Medication|string
    {
        $query = Medication::query()->when($onlyActive, fn ($q) => $q->where('is_active', true));

        if (Str::isUuid($reference)) {
            return (clone $query)->find($reference) ?? "No se encontró ningún medicamento con id {$reference}.";
        }

        $matches = (clone $query)->where('name', 'like', '%'.$reference.'%')->orderBy('name')->get();

        if ($matches->isEmpty()) {
            return "No se encontró ningún medicamento llamado «{$reference}». Si es una toma puntual, envía name en lugar de medication.";
        }

        $exact = $matches->first(fn (Medication $m) => mb_strtolower($m->name) === mb_strtolower($reference));
        if ($exact) {
            return $exact;
        }

        if ($matches->count() > 1) {
            return 'Hay varios medicamentos que coinciden con «'.$reference.'»: '
                .$matches->map(fn (Medication $m) => "{$m->name} (id: {$m->id})")->implode('; ').'. Indica cuál con su id.';
        }

        return $matches->first();
    }
}

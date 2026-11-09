<?php

namespace App\Console\Commands;

use App\Models\ShoppingItem;
use App\Models\User;
use App\Services\Meal\InventoryLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InitializeInventoryLedger extends Command
{
    protected $signature = 'inventory:initialize-ledger
                            {--user=* : IDs de usuario; sin esta opción revisa todos}
                            {--apply : Registrar las aperturas; sin esta opción solo previsualiza}';

    protected $description = 'Revisa o registra saldos de apertura sin cambiar existencias. Suspender escrituras de inventario durante la aplicación.';

    public function handle(InventoryLedger $ledger): int
    {
        if (! Schema::hasColumn('meal_inventory_operations', 'document_lines')) {
            $this->error('Sin cambios: falta aplicar la migración del motor de inventario.');

            return self::FAILURE;
        }
        try {
            $requested = $this->option('user');
            Validator::make(['users' => $requested], ['users' => ['array'], 'users.*' => ['required', 'integer', 'min:1']])->validate();
            $users = User::withoutGlobalScopes()->orderBy('id');
            if ($requested !== []) {
                $users->whereIn('id', $requested);
            }
            $userIds = $users->pluck('id')->all();
            if ($requested !== [] && count($userIds) !== count(array_unique(array_map('intval', $requested)))) {
                $this->error('Sin cambios: uno de los usuarios solicitados no existe.');

                return self::FAILURE;
            }
            $selection = [];
            $rows = [];
            foreach ($userIds as $userId) {
                $ids = ShoppingItem::withoutGlobalScopes()->where('user_id', $userId)->orderBy('id')->pluck('id')->all();
                if ($ids !== []) {
                    $selection[$userId] = $ids;
                    array_push($rows, ...$ledger->previewInitialization($userId, $ids));
                }
            }
            $labels = ['pending' => 'Pendiente', 'initialized' => 'Ya inicializado', 'blocked' => 'Bloqueado'];
            $this->table(['Usuario', 'Producto ID', 'Ingrediente', 'Unidad', 'Existencias', 'Estado', 'Detalle'],
                array_map(fn ($row) => [$row['user_id'], $row['shopping_item_id'], $row['name'], $row['unit'] ?? 'Sin unidad',
                    $row['stock'], $labels[$row['status']], $row['message']], $rows));
            $counts = array_count_values(array_column($rows, 'status'));
            $this->info('Productos: '.count($rows).'; pendientes: '.($counts['pending'] ?? 0).'; inicializados: '.($counts['initialized'] ?? 0).'; bloqueados: '.($counts['blocked'] ?? 0).'.');
            if (($counts['blocked'] ?? 0) > 0) {
                $this->error('Sin cambios: resuelve los productos bloqueados antes de aplicar las aperturas.');

                return self::FAILURE;
            }
            if (! $this->option('apply')) {
                $this->info('Previsualización: no se escribió ningún dato. Usa --apply para registrar las aperturas.');

                return self::SUCCESS;
            }
            if ($rows === []) {
                $this->info('No hay productos que inicializar.');

                return self::SUCCESS;
            }
            $this->warn('Aplicación: las escrituras de inventario deben estar pausadas y los adaptadores deben activarse juntos.');
            // Preserve the reviewed selection. Any failure rolls back the whole invocation,
            // including earlier users; initialize revalidates current balances under locks.
            DB::transaction(function () use ($selection, $ledger) {
                foreach ($selection as $userId => $ids) {
                    $ledger->initialize($userId, $ids, ['channel' => 'system', 'reason' => 'Saldo de apertura']);
                }
            }, 3);
            $this->info('Inicialización terminada. Las existencias y los movimientos anteriores se conservaron.');

            return self::SUCCESS;
        } catch (ValidationException $exception) {
            $this->error('Sin cambios: '.implode(' ', $exception->validator->errors()->all()));

            return self::FAILURE;
        }
    }
}

<?php

namespace App\Events\Meal;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class InventoryMovementRecorded implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $userId,
        public string $operationId,
        public string $movementId,
        public array $origin,
    ) {}
}

<?php

namespace App\Mcp\Tools\Shopping\Concerns;

use App\Models\ShoppingItem;
use App\Models\StorageLocation;
use Illuminate\Validation\Rule;

trait InteractsWithInventory
{
    protected function storageLocationRules(?ShoppingItem $item = null): array
    {
        return ['sometimes', 'nullable', 'uuid', Rule::exists('storage_locations', 'id')->where(function ($query) use ($item) {
            $query->where('user_id', auth()->id())->where(function ($query) use ($item) {
                $query->whereNull('archived_at');
                if ($item?->storage_location_id) {
                    $query->orWhere('id', $item->storage_location_id);
                }
            });
        })];
    }

    protected function inventorySummary(ShoppingItem $item): array
    {
        $item->loadMissing('storageLocation.parent');

        return [
            'id' => $item->id,
            'name' => $item->name,
            'base_unit' => $item->base_unit,
            'stock' => $item->stock,
            'storage_location_id' => $item->storage_location_id,
            'storage_location' => $item->storageLocation ? $this->locationSummary($item->storageLocation) : null,
        ];
    }

    protected function locationSummary(StorageLocation $location): array
    {
        return [
            'id' => $location->id,
            'name' => $location->name,
            'parent_id' => $location->parent_id,
            'path' => $location->path_label,
            'archived' => $location->isArchived(),
        ];
    }
}

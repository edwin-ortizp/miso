<?php

namespace App\Mcp\Tools\Shopping;

use App\Mcp\Tools\Shopping\Concerns\InteractsWithInventory;
use App\Models\StorageLocation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Lista las ubicaciones de almacenamiento del usuario y su jerarquía (por ejemplo Cocina › Nevera). Solo activas por defecto; no crea ni modifica lugares.')]
class ListStorageLocationsTool extends Tool
{
    use InteractsWithInventory;

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate(['include_archived' => ['sometimes', 'boolean']]);
        $locations = StorageLocation::where('user_id', auth()->id())->with('parent')
            ->when(! ($data['include_archived'] ?? false), fn ($query) => $query->active())
            ->orderBy('name')->orderBy('id')->get();

        return Response::structured(['locations' => $locations->map(fn ($location) => $this->locationSummary($location))->all()]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['include_archived' => $schema->boolean()->description('Incluir ubicaciones archivadas. Por defecto false.')];
    }
}

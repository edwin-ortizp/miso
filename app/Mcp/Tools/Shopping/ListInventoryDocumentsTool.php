<?php

namespace App\Mcp\Tools\Shopping;

use App\Services\Meal\InventoryHistory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[IsReadOnly]
#[Description('Consulta documentos de inventario del usuario. Filtra por fecha efectiva, tipo, canal, ingrediente o referencia; no modifica existencias.')]
class ListInventoryDocumentsTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        abort_unless(auth()->id(), 401);
        try {
            return Response::structured(app(InventoryHistory::class)->documents((int) auth()->id(), $request->all()));
        } catch (ModelNotFoundException|NotFoundHttpException) {
            return Response::error('Registro de inventario no disponible.');
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('Fecha efectiva inicial YYYY-MM-DD, inclusiva.'),
            'to' => $schema->string()->description('Fecha efectiva final YYYY-MM-DD, inclusiva.'),
            'type' => $schema->string()->enum(array_keys(InventoryHistory::TYPES)),
            'channel' => $schema->string()->enum(array_keys(InventoryHistory::CHANNELS)),
            'item_id' => $schema->string()->description('UUID del ingrediente actual o histórico.'),
            'search' => $schema->string()->description('ID, referencia, motivo o nombre histórico del ingrediente.'),
            'page' => $schema->integer()->min(1),
            'per_page' => $schema->integer()->min(1)->max(100)->description('50 por defecto; máximo 100.'),
        ];
    }
}

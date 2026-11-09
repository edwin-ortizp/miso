<?php

namespace App\Mcp\Tools\Shopping;

use App\Services\Meal\InventoryHistory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[IsReadOnly]
#[Description('Consulta el Kardex de un ingrediente actual o fusionado. Devuelve stock actual separado, apertura, tramos y saldos persistidos; legado sin saldo devuelve null. Solo lectura.')]
class ListInventoryMovementsTool extends ListInventoryDocumentsTool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        abort_unless(auth()->id(), 401);
        $data = $request->validate(['item_id' => ['required', 'uuid']]);
        try {
            return Response::structured(app(InventoryHistory::class)->movements((int) auth()->id(), $data['item_id'], $request->all()));
        } catch (ModelNotFoundException|NotFoundHttpException) {
            return Response::error('Registro de inventario no disponible.');
        }
    }

    public function schema(JsonSchema $schema): array
    {
        $fields = parent::schema($schema);
        unset($fields['search']);
        $fields['item_id'] = $schema->string()->required()->description('UUID del ingrediente actual o histórico.');

        return $fields;
    }
}

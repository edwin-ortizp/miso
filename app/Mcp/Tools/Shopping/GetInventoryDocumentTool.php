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
#[Description('Abre el snapshot completo de un documento de inventario: cabecera, líneas originales, saldos y correcciones. Solo lectura; hasta 100 líneas por página.')]
class GetInventoryDocumentTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        abort_unless(auth()->id(), 401);
        $data = $request->validate(['operation_id' => ['required', 'uuid'], 'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        try {
            return Response::structured(app(InventoryHistory::class)->document((int) auth()->id(), $data['operation_id'], $data + ['per_page' => 100]));
        } catch (ModelNotFoundException|NotFoundHttpException) {
            return Response::error('Registro de inventario no disponible.');
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return ['operation_id' => $schema->string()->required()->description('UUID completo del documento.'),
            'page' => $schema->integer()->min(1), 'per_page' => $schema->integer()->min(1)->max(100)->description('100 por defecto.')];
    }
}

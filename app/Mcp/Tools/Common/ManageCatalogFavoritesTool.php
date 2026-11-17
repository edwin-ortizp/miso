<?php

namespace App\Mcp\Tools\Common;

use App\Services\Catalog\CatalogPicker;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Lista o cambia los favoritos personales de un catálogo (bebidas o ejercicios). Los favoritos los marca la persona y van primero al registrar; los «más usados» se calculan por separado y solo se sugieren, nunca se convierten en favoritos solos.')]
class ManageCatalogFavoritesTool extends Tool
{
    public function handle(Request $request): Response
    {
        abort_unless(auth()->check(), 401);

        $data = $request->validate([
            'domain' => ['required', Rule::in(array_keys(config('catalog_picker.domains', [])))],
            'action' => ['required', Rule::in(['list', 'set', 'unset'])],
            'id' => ['required_if:action,set,unset', 'nullable', 'string', 'max:36'],
        ]);

        $picker = app(CatalogPicker::class);
        $domain = $data['domain'];

        if ($data['action'] !== 'list') {
            if (! $picker->set($domain, $data['id'], $data['action'] === 'set')) {
                if ($data['action'] === 'set') {
                    return Response::error('No encontré ese elemento en tu catálogo.');
                }
            }
        }

        $row = fn ($item) => ['id' => $item->id, 'name' => $item->name];

        return Response::text(json_encode([
            'favorites' => $picker->favorites($domain)->map($row)->values()->all(),
            'most_used_suggestions' => $picker->suggestions($domain, 5)->map($row)->values()->all(),
        ], JSON_UNESCAPED_UNICODE));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->enum(array_keys(config('catalog_picker.domains', [])))->required(),
            'action' => $schema->string()->enum(['list', 'set', 'unset'])->required(),
            'id' => $schema->string()->description('Id del elemento del catálogo para set o unset.'),
        ];
    }
}

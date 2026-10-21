<?php

namespace App\Mcp\Tools\Task;

use App\Actions\ManageTaskCategories;
use App\Mcp\Tools\Task\Concerns\InteractsWithTaskFields;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Crea, renombra o elimina una categoría de tareas del usuario. Al eliminar, sus tareas pasan a reassign_to o quedan sin categoría; confirma con el usuario antes de eliminar.')]
class ManageTaskCategoryTool extends Tool
{
    use InteractsWithTaskFields;

    public function handle(Request $request, ManageTaskCategories $categories): Response
    {
        $data = $request->validate([
            'action' => ['required', 'string', Rule::in(['create', 'rename', 'delete'])],
            'category' => ['required_unless:action,create', 'nullable', 'string', 'max:80'],
            'name' => ['required_unless:action,delete', 'nullable', 'string', 'max:60'],
            'parent_id' => ['nullable', 'uuid'],
            'code_prefix' => ['sometimes', 'required', 'string'],
            'reassign_to' => ['nullable', 'string', 'max:80'],
        ]);

        try {
            if ($data['action'] === 'create') {
                $category = $categories->create(Auth::user(), $data['name'], null, $data['parent_id'] ?? null, $data['code_prefix'] ?? null);

                return Response::text("Categoría \"{$category->name}\" creada (key: {$category->key}).");
            }

            $category = $this->resolveCategory($data['category']);
            if (! $category) {
                return Response::error($this->categoryNotFound($data['category']));
            }

            if ($data['action'] === 'rename') {
                $previous = $category->name;
                $categories->update($category, $data['name'], null, array_key_exists('parent_id', $data) ? $data['parent_id'] : $category->parent_id, $data['code_prefix'] ?? null);

                return Response::text("Categoría \"{$previous}\" renombrada a \"{$category->name}\". Sus tareas la conservan.");
            }

            $target = null;
            if (filled($data['reassign_to'] ?? null)) {
                $target = $this->resolveCategory($data['reassign_to']);
                if (! $target) {
                    return Response::error($this->categoryNotFound($data['reassign_to']));
                }
            }

            $moved = $categories->delete($category, $target);
        } catch (InvalidArgumentException|ValidationException $exception) {
            return Response::error($exception->getMessage());
        }

        $destination = $target ? "pasaron a \"{$target->name}\"" : 'quedaron sin categoría';

        return Response::text("Categoría \"{$category->name}\" eliminada. {$moved} tareas {$destination}.");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['create', 'rename', 'delete'])
                ->description('Operación a realizar.')
                ->required(),
            'category' => $schema->string()
                ->description('Key o nombre de la categoría existente (rename y delete).'),
            'name' => $schema->string()
                ->description('Nombre de la nueva categoría o nuevo nombre (create y rename).'),
            'code_prefix' => $schema->string()->description('Prefijo propio de 2 a 5 letras, único por usuario.'),
            'parent_id' => $schema->string()->nullable()->description('UUID de la categoría padre; null la convierte en raíz.'),
            'reassign_to' => $schema->string()
                ->description('Al eliminar: key o nombre de la categoría que recibe sus tareas. Si se omite, quedan sin categoría.'),
        ];
    }
}

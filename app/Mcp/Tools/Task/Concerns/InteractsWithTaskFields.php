<?php

namespace App\Mcp\Tools\Task\Concerns;

use App\Models\TaskCategory;
use App\Models\TaskStatus;
use App\Services\TaskRecurrenceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Campos comunes a crear y actualizar tareas desde MCP: categoría del catálogo del usuario,
 * fechas (con o sin hora) y recurrencia.
 */
trait InteractsWithTaskFields
{
    private const PRIORITIES = [
        'urgent-important', 'not-urgent-important', 'urgent-not-important', 'not-urgent-not-important',
    ];

    private const SIZES = ['XS', 'S', 'M', 'L', 'XL'];

    /** Reglas de los campos editables compartidos. */
    protected function fieldRules(): array
    {
        return [
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'string', 'max:60'],
            'priority' => ['nullable', 'string', Rule::in(self::PRIORITIES)],
            'size' => ['nullable', 'string', Rule::in(self::SIZES)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'estimated_time' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'is_private' => ['nullable', 'boolean'],
            'schedule_locked' => ['nullable', 'boolean'],
            'recurrence' => ['nullable', 'string', Rule::in(['none', ...TaskRecurrenceService::PATTERNS])],
            'recurrence_interval' => ['nullable', 'integer', 'min:1', 'max:365'],
            'rrule' => ['nullable', 'string', 'max:255'],
            'add_external_refs' => ['nullable', 'array', 'max:20'],
            'add_external_refs.*.provider' => ['required', 'string', 'max:40'],
            'add_external_refs.*.type' => ['required', 'string', 'max:40'],
            'add_external_refs.*.id' => ['required', 'string', 'max:255'],
            'add_external_refs.*.url' => ['nullable', 'string', 'max:2048'],
            'add_external_refs.*.label' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** Esquema de un arreglo de referencias externas. */
    protected function externalRefsSchema(JsonSchema $schema, string $description, bool $withDetails = true): mixed
    {
        $properties = [
            'provider' => $schema->string()->description('Sistema externo en minúsculas: jira, google_calendar, gesthor...')->required(),
            'type' => $schema->string()->description('Tipo de elemento: issue, event, task...')->required(),
            'id' => $schema->string()->description('Identificador en ese sistema, p. ej. "SGX-323".')->required(),
        ];

        if ($withDetails) {
            $properties['url'] = $schema->string()->description('Enlace opcional al elemento.');
            $properties['label'] = $schema->string()->description('Etiqueta opcional.');
        }

        return $schema->array()->items($schema->object($properties))->description($description);
    }

    /** Busca la categoría por key o por nombre (sin distinguir mayúsculas). */
    protected function resolveCategory(string $value): ?TaskCategory
    {
        $value = trim($value);

        return Auth::user()->taskCategories()->where('key', $value)->first()
            ?? Auth::user()->taskCategories()->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])->first();
    }

    /** Busca el estado por id o por nombre (sin distinguir mayúsculas). */
    protected function resolveStatus(string $value): ?TaskStatus
    {
        return TaskStatus::resolve(Auth::id(), $value);
    }

    protected function statusNotFound(string $value): string
    {
        $available = TaskStatus::ensureFor(Auth::id())
            ->map(fn (TaskStatus $status) => "{$status->name} ({$status->type})")->implode(', ');

        return "El estado \"{$value}\" no existe. Disponibles: {$available}. Puedes crearlo con manage-task-status-tool.";
    }

    /**
     * Atributo status_id para un estado no terminado. Pasar a done se hace con complete-task-tool,
     * que calcula XP y programa recurrencias.
     *
     * @throws InvalidArgumentException
     */
    protected function statusAttribute(string $value): string
    {
        $status = $this->resolveStatus($value);

        if (! $status) {
            throw new InvalidArgumentException($this->statusNotFound($value));
        }

        if ($status->type === TaskStatus::DONE) {
            throw new InvalidArgumentException("\"{$status->name}\" es el estado terminado: usa complete-task-tool para completar la tarea (calcula XP y programa recurrencias).");
        }

        return $status->id;
    }

    /** @return array{id: string, name: string, type: string}|null */
    protected function statusPayload(?TaskStatus $status): ?array
    {
        return $status ? ['id' => $status->id, 'name' => $status->name, 'type' => $status->type] : null;
    }

    protected function categoryNotFound(string $value): string
    {
        $available = Auth::user()->taskCategories()->orderBy('sort_order')->pluck('name', 'key')
            ->map(fn ($name, $key) => "{$name} ({$key})")->implode(', ');

        return "La categoría \"{$value}\" no existe. Disponibles: {$available}. Puedes crearla con manage-task-category-tool.";
    }

    /**
     * Traduce los campos validados a atributos de Task. Solo incluye las claves presentes,
     * así un null explícito limpia el valor y la ausencia lo conserva.
     *
     * @throws InvalidArgumentException con un mensaje apto para el usuario.
     */
    protected function taskAttributes(array $data, TaskRecurrenceService $recurrence): array
    {
        $attributes = [];

        foreach (['description', 'priority', 'size', 'estimated_time'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }

        if (array_key_exists('is_private', $data)) {
            $attributes['is_private'] = (bool) $data['is_private'];
        }

        if (array_key_exists('schedule_locked', $data)) {
            $attributes['schedule_locked'] = (bool) $data['schedule_locked'];
        }

        if (filled($data['status'] ?? null)) {
            $attributes['status_id'] = $this->statusAttribute($data['status']);
        }

        if (array_key_exists('category', $data)) {
            if (blank($data['category'])) {
                $attributes['category'] = null;
            } elseif ($category = $this->resolveCategory($data['category'])) {
                $attributes['category'] = $category->key;
            } else {
                throw new InvalidArgumentException($this->categoryNotFound($data['category']));
            }
        }

        foreach (['start', 'end'] as $edge) {
            $field = "{$edge}_date";
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
                // "YYYY-MM-DD" es un día completo; con hora, un momento concreto.
                $attributes["{$edge}_is_date"] = $data[$field] === null || strlen(trim($data[$field])) <= 10;
            }
        }

        if (array_key_exists('recurrence', $data) || filled($data['rrule'] ?? null)) {
            $pattern = $data['recurrence'] ?? 'custom';

            if ($pattern === 'none' && blank($data['rrule'] ?? null)) {
                $attributes['is_recurrent'] = false;
                $attributes['recurrence'] = null;
            } else {
                $attributes['is_recurrent'] = true;
                $attributes['recurrence'] = $recurrence->buildRecurrence(
                    $pattern === 'none' ? 'custom' : $pattern,
                    (int) ($data['recurrence_interval'] ?? 1),
                    $data['rrule'] ?? null,
                );
            }
        }

        return $attributes;
    }

    protected function fieldSchema(JsonSchema $schema): array
    {
        return [
            'description' => $schema->string()
                ->description('Descripción en markdown. Admite listas de subtareas con "- [ ]" y "- [x]".'),
            'category' => $schema->string()
                ->description('Key o nombre de una categoría del usuario (consulta list-task-categories-tool). Vacío deja la tarea sin categoría.'),
            'status' => $schema->string()
                ->description('Nombre o id de un estado del usuario (consulta manage-task-status-tool con action=list). Tipos: todo (por hacer/borrador), ready (listo para ejecutar), in_progress (en curso). Para terminar una tarea usa complete-task-tool.'),
            'priority' => $schema->string()
                ->enum(self::PRIORITIES)
                ->description('Cuadrante de Eisenhower de la tarea.'),
            'size' => $schema->string()
                ->enum(self::SIZES)
                ->description('Tamaño estimado: XS (<30 min), S (30 min-1 h), M (1-2 h), L (2-4 h), XL (>4 h).'),
            'start_date' => $schema->string()
                ->description('Fecha de inicio "YYYY-MM-DD" (día completo) o "YYYY-MM-DD HH:MM" (con hora).'),
            'end_date' => $schema->string()
                ->description('Fecha límite "YYYY-MM-DD" o "YYYY-MM-DD HH:MM".'),
            'estimated_time' => $schema->integer()
                ->description('Tiempo estimado en minutos.'),
            'is_private' => $schema->boolean()
                ->description('Si la tarea es privada.'),
            'schedule_locked' => $schema->boolean()
                ->description('Horario fijo (p. ej. una reunión): true impide moverla. Una tarea con horario fijo no cambia de fecha hasta que antes se envíe schedule_locked=false.'),
            'recurrence' => $schema->string()
                ->enum(['none', ...TaskRecurrenceService::PATTERNS])
                ->description('Recurrencia: daily, weekly, monthly, custom (cada N días) o none para quitarla. Se combina con recurrence_interval.'),
            'recurrence_interval' => $schema->integer()
                ->description('Cada cuántos días/semanas/meses se repite (por defecto 1).'),
            'rrule' => $schema->string()
                ->description('Regla iCalendar avanzada, p. ej. "FREQ=WEEKLY;BYDAY=MO,TH" o "FREQ=MONTHLY;BYMONTHDAY=5". Tiene prioridad sobre recurrence.'),
            'add_external_refs' => $this->externalRefsSchema($schema, 'Referencias a elementos equivalentes en sistemas externos (p. ej. [{"provider":"jira","type":"issue","id":"SGX-323"}]). No se duplican; MiSo no se conecta a esos sistemas.'),
        ];
    }
}

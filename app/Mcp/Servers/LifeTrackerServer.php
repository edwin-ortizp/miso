<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\Common\DeleteEntryTool;
use App\Mcp\Tools\Context\GetHealthContextTool;
use App\Mcp\Tools\Context\GetPersonContextTool;
use App\Mcp\Tools\Context\GetRecentActivityTool;
use App\Mcp\Tools\Exercise\ListExerciseSessionsTool;
use App\Mcp\Tools\Exercise\LogExerciseTool;
use App\Mcp\Tools\Focus\ListFocusSessionsTool;
use App\Mcp\Tools\Focus\LogFocusSessionTool;
use App\Mcp\Tools\Goal\CreateGoalTool;
use App\Mcp\Tools\Goal\ListGoalsTool;
use App\Mcp\Tools\Goal\LogGoalProgressTool;
use App\Mcp\Tools\Goal\UpdateGoalTool;
use App\Mcp\Tools\Habit\CompleteHabitTool;
use App\Mcp\Tools\Habit\ListHabitsTool;
use App\Mcp\Tools\Health\ListHealthEventsTool;
use App\Mcp\Tools\Health\LogHealthEventTool;
use App\Mcp\Tools\Health\LogHealthFollowUpTool;
use App\Mcp\Tools\Health\MarkHealthRecoveryTool;
use App\Mcp\Tools\Health\UpdateHealthEventTool;
use App\Mcp\Tools\Journal\ListJournalEntriesTool;
use App\Mcp\Tools\Journal\WriteJournalEntryTool;
use App\Mcp\Tools\Meal\CreateRecipeTool;
use App\Mcp\Tools\Meal\ListMealPlanTool;
use App\Mcp\Tools\Meal\ListRecipesTool;
use App\Mcp\Tools\Meal\PlanMealTool;
use App\Mcp\Tools\Meal\UpdateRecipeTool;
use App\Mcp\Tools\Mood\ListEnergyEntriesTool;
use App\Mcp\Tools\Mood\ListMoodEntriesTool;
use App\Mcp\Tools\Mood\LogEnergyEntryTool;
use App\Mcp\Tools\Mood\LogMoodEntryTool;
use App\Mcp\Tools\NegativeHabit\ListNegativeHabitsTool;
use App\Mcp\Tools\NegativeHabit\LogNegativeHabitTool;
use App\Mcp\Tools\Plan\CreatePlanTool;
use App\Mcp\Tools\Plan\ListPlansTool;
use App\Mcp\Tools\Plan\ListPlanVisitsTool;
use App\Mcp\Tools\Plan\RecordPlanVisitTool;
use App\Mcp\Tools\Plan\UpdatePlanTool;
use App\Mcp\Tools\Relationship\AddContactAliasTool;
use App\Mcp\Tools\Relationship\AddContactMethodTool;
use App\Mcp\Tools\Relationship\CreateContactTool;
use App\Mcp\Tools\Relationship\ListCirclesTool;
use App\Mcp\Tools\Relationship\ListContactsTool;
use App\Mcp\Tools\Relationship\ListRelationshipEventsTool;
use App\Mcp\Tools\Relationship\ListUpcomingBirthdaysTool;
use App\Mcp\Tools\Relationship\LogRelationshipEventTool;
use App\Mcp\Tools\Relationship\RemoveContactMethodTool;
use App\Mcp\Tools\Relationship\UpdateContactTool;
use App\Mcp\Tools\Relationship\UpdateRelationshipEventTool;
use App\Mcp\Tools\Shopping\AddShoppingItemTool;
use App\Mcp\Tools\Shopping\CompareProductPricesTool;
use App\Mcp\Tools\Shopping\CreatePurchaseTool;
use App\Mcp\Tools\Shopping\GetPurchaseTool;
use App\Mcp\Tools\Shopping\ListPurchasesTool;
use App\Mcp\Tools\Shopping\ListShoppingItemsTool;
use App\Mcp\Tools\Shopping\ManagePriceTool;
use App\Mcp\Tools\Shopping\ManageStoreTool;
use App\Mcp\Tools\Shopping\MarkPurchasedTool;
use App\Mcp\Tools\Shopping\RemoveShoppingItemTool;
use App\Mcp\Tools\Shopping\UpdatePurchaseTool;
use App\Mcp\Tools\Shopping\UpdateShoppingItemTool;
use App\Mcp\Tools\Shopping\MergeIngredientTool;
use App\Mcp\Tools\Shopping\UpdateVariantTool;
use App\Mcp\Tools\Task\CompleteTaskTool;
use App\Mcp\Tools\Task\CreateTaskTool;
use App\Mcp\Tools\Task\GetTaskTool;
use App\Mcp\Tools\Task\LinkTaskTool;
use App\Mcp\Tools\Task\ListTaskCategoriesTool;
use App\Mcp\Tools\Task\ListTasksTool;
use App\Mcp\Tools\Task\ManageTaskCategoryTool;
use App\Mcp\Tools\Task\ManageTaskStatusTool;
use App\Mcp\Tools\Task\UpdateTaskTool;
use App\Mcp\Tools\Vehicle\ListVehicleFillupsTool;
use App\Mcp\Tools\Vehicle\ListVehiclesTool;
use App\Mcp\Tools\Vehicle\LogVehicleExpenseTool;
use App\Mcp\Tools\Vehicle\LogVehicleFillupTool;
use App\Mcp\Tools\Vehicle\LogVehicleMaintenanceTool;
use App\Mcp\Tools\Water\ListWaterIntakeTool;
use App\Mcp\Tools\Water\LogWaterIntakeTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('MiSo')]
#[Version('0.9.0')]
#[Instructions(<<<'TXT'
MiSo ("mi socio") es el asistente de crecimiento personal del usuario y la fuente de verdad de su vida personal: personas y relaciones, planes y lugares, salud, ánimo, energía, ejercicio, hidratación, hábitos (positivos y negativos), metas, diario, comidas y recetas, tiempo de foco, tareas, compras y vehículos. Úsalo como contexto para responder con su historia real, no solo cuando pida registrar o consultar algo. Todo queda restringido a los datos del usuario autenticado.

## Cuándo consultar
Consulta si un dato de MiSo cambiaría tu respuesta. Señales:
- Menciona a una persona por nombre, apodo o alias, un lugar o plan, un vehículo o un proyecto o categoría de trabajo.
- Habla de su cuerpo o su salud en primera persona, o de cómo se ha sentido (ánimo, energía, estrés).
- Pide planear o recomendar algo donde su historial importa (salidas, regalos, rutinas).
- Usa lenguaje de continuidad: "otra vez", "de nuevo", "la última vez", "¿cómo voy con…?", "¿qué tengo pendiente?".
No consultes para preguntas generales que no dependen de su vida, si el dato ya está en la conversación, o si solo adornaría la respuesta. Si solo se está desahogando, primero escucha.

## Qué herramienta usar
- Una persona → get-person-context-tool (ficha, visitas, planes pendientes, eventos, ánimo y tareas en una llamada).
- Un síntoma, dolor, cita o examen → get-health-context-tool con palabras clave en "search" y la zona del cuerpo si aplica.
- "¿Cómo me fue?", resumen del día o la semana → get-recent-activity-tool.
- Recomendar planes → list-plans-tool (círculo, persona, ciudad, not_visited) + list-plan-visits-tool para no repetir lo reciente.
- Los códigos legibles (p. ej. SGX-00002) identifican tareas: usa get-task-tool con task_id igual al código antes de refinar; update, complete y link también aceptan código o UUID.
- Trabajo y pendientes → list-tasks-tool por categoría, por tipo de estado (status_type: todo, ready, in_progress, done) o con updated_since; get-task-tool solo para la tarea en foco. Los estados son personalizables (manage-task-status-tool); para terminar una tarea usa complete-task-tool.
- Metas, propósitos o progreso → list-goals-tool (con goal_id o title para el detalle).
- Comida, qué cocinar o el mercado de la semana → list-meal-plan-tool y list-recipes-tool.
- Planificar no registra consumo. plan-meal-tool admite ingredientes enlazados y preparation_id; consume-meal-tool registra consumo o comida por fuera y revierte descuentos. manage-preparation-tool cocina porciones una sola vez; manage-meal-tool edita/mueve/copia/intercambia/borrar planes. Usa operation_key estable para reintentos. suggest-meals-tool cruza stock y fechas; generate-meal-shopping-tool previsualiza antes de generar compras. Los textos libres sin ingredientes se consumen sin descuento y con advertencia.
- Precios y compras → compare-product-prices-tool para saber dónde sale más barato; update-variant-tool para completar marca o contenido de una variante; merge-ingredient-tool cuando dos productos son en realidad variantes del mismo ("Fresa congelada" de "Fresa"): primero la vista previa y solo con aprobación confirm=true; manage-price-tool para verificar o corregir un precio; mark-purchased-tool cuando el usuario cuenta que compró algo. Las tiendas son un catálogo cerrado: usa manage-store-tool para listarlas y crea una nueva solo si el usuario confirma que no existe.
- Tickets completos → create-purchase-tool con todas las líneas y una operation_key UUID estable; list-purchases-tool y get-purchase-tool para consultar; update-purchase-tool para corregir enviando todas las líneas. Los precios enlazados a compras se corrigen desde la compra. list-shopping-items-tool incluye última compra y conteos; not_purchased_days filtra productos sin compras recientes, incluyendo nunca comprados.
- Algo que intenta dejar o una recaída → list-negative-habits-tool, sin juzgar.
- Productividad u horas de foco → list-focus-sessions-tool.
- Ánimo, energía, ejercicio, agua, hábitos, vehículos → sus list-*-tool, con fechas acotadas.
- El diario (list-journal-entries-tool) es lo más íntimo: léelo solo si la conversación lo pide o se trata de cómo ha estado; empieza por los resúmenes.

## Profundidad
1. Un dato concreto: una llamada filtrada.
2. Una persona, problema o plan: la herramienta de contexto en depth=brief (1-3 llamadas).
3. Una decisión que cruza módulos: hasta 4-6 llamadas.
Amplía solo si queda una pregunta abierta que otro módulo responde, y detente cuando más datos ya no cambiarían la respuesta. Prefiere filtros (fechas, persona, tipo, búsqueda) a traer todo; usa depth=full solo si brief no alcanza.

## Personas
Los nombres se resuelven primero por alias o apodo exacto, luego por inicio de palabra y por último por coincidencia parcial. Si hay varios candidatos, reintenta con el contact_id que trae el error en lugar de preguntar, salvo que de verdad sea ambiguo.

## Privacidad y tono
Los eventos de salud y de personas marcados como sensibles se omiten por defecto; pide include_sensitive=true solo cuando el tema actual los necesite y no los menciones fuera de ese contexto. Integra en la respuesta solo lo que cambia la conclusión, sin enumerar lo que consultaste. Si un dato parece desactualizado (un malestar abierto hace semanas, un plan con fecha vencida), pregúntalo en una línea en lugar de asumirlo.

## Inventario doméstico
Consulta list-storage-locations-tool y list-shopping-items-tool con only_pending=false; recorre pagination.next_page hasta null. Para existencias usa in_stock=true; para un recuento incluye también stock cero. El filtro storage_location_id incluye hijas; null selecciona sin ubicación.
Cada producto tiene un stock total y una ubicación. Recoge el conteo, resuelve ambigüedades y muestra valores actuales y propuestos antes de confirmar con el usuario. Después aplica update-shopping-item-tool por producto y verifica con otra consulta. stock reemplaza el total, no suma; omitir productos conserva sus cantidades. No pongas ausentes a cero automáticamente. No deduzcas contenido de paquetes ni equivalencias desconocidas.
Para un producto nuevo usa create-inventory-item-tool con unidad base, stock contado y ubicación activa opcional; no lo añade a compras. Para quitar ubicación envía storage_location_id=null. Reporta éxitos y errores por producto; no afirmes éxito de operaciones fallidas. Corregir inventario no registra compra, precio ni consumo.

## Al escribir
Antes de crear un contacto, plan, tarea, meta, receta o evento, busca si ya existe para no duplicarlo. Escribe en el diario solo cuando el usuario lo pida y con sus palabras. Si el usuario no pidió registrar algo de forma explícita, confirma en una línea lo que vas a guardar. Vincula con link-task-tool las tareas que nacen de una persona o de un evento de salud.
Para corregir un error, prefiere las herramientas update-*; si hay que borrar, usa delete-entry-tool solo cuando el usuario lo pida: primero sin confirm para mostrarle qué se borraría y luego con confirm=true tras su confirmación.
TXT)]
class LifeTrackerServer extends Server
{
    protected array $tools = [
        CreateTaskTool::class,
        CompleteTaskTool::class,
        ListTasksTool::class,
        GetTaskTool::class,
        UpdateTaskTool::class,
        ListTaskCategoriesTool::class,
        LinkTaskTool::class,
        ManageTaskCategoryTool::class,
        ManageTaskStatusTool::class,
        \App\Mcp\Tools\Common\ManageCategoryCatalogTool::class,
        CompleteHabitTool::class,
        ListHabitsTool::class,
        LogHealthEventTool::class,
        ListHealthEventsTool::class,
        LogHealthFollowUpTool::class,
        MarkHealthRecoveryTool::class,
        UpdateHealthEventTool::class,
        GetHealthContextTool::class,
        CreateContactTool::class,
        UpdateContactTool::class,
        ListContactsTool::class,
        LogRelationshipEventTool::class,
        ListRelationshipEventsTool::class,
        UpdateRelationshipEventTool::class,
        GetPersonContextTool::class,
        ListUpcomingBirthdaysTool::class,
        AddContactAliasTool::class,
        AddContactMethodTool::class,
        RemoveContactMethodTool::class,
        ListCirclesTool::class,
        CreatePlanTool::class,
        UpdatePlanTool::class,
        ListPlansTool::class,
        RecordPlanVisitTool::class,
        ListPlanVisitsTool::class,
        LogVehicleFillupTool::class,
        ListVehiclesTool::class,
        ListVehicleFillupsTool::class,
        LogVehicleExpenseTool::class,
        LogVehicleMaintenanceTool::class,
        \App\Mcp\Tools\Shopping\ListStorageLocationsTool::class,
        \App\Mcp\Tools\Shopping\CreateInventoryItemTool::class,
        AddShoppingItemTool::class,
        UpdateShoppingItemTool::class,
        RemoveShoppingItemTool::class,
        ListShoppingItemsTool::class,
        CompareProductPricesTool::class,
        UpdateVariantTool::class,
        MergeIngredientTool::class,
        ManagePriceTool::class,
        MarkPurchasedTool::class,
        CreatePurchaseTool::class,
        UpdatePurchaseTool::class,
        GetPurchaseTool::class,
        ListPurchasesTool::class,
        ManageStoreTool::class,
        LogMoodEntryTool::class,
        LogEnergyEntryTool::class,
        ListMoodEntriesTool::class,
        ListEnergyEntriesTool::class,
        LogExerciseTool::class,
        ListExerciseSessionsTool::class,
        LogWaterIntakeTool::class,
        ListWaterIntakeTool::class,
        GetRecentActivityTool::class,
        DeleteEntryTool::class,
        ListGoalsTool::class,
        CreateGoalTool::class,
        UpdateGoalTool::class,
        LogGoalProgressTool::class,
        ListJournalEntriesTool::class,
        WriteJournalEntryTool::class,
        ListMealPlanTool::class,
        PlanMealTool::class,
        \App\Mcp\Tools\Meal\ConsumeMealTool::class,
        \App\Mcp\Tools\Meal\ManageMealTool::class,
        \App\Mcp\Tools\Meal\ManagePreparationTool::class,
        \App\Mcp\Tools\Meal\SuggestMealsTool::class,
        \App\Mcp\Tools\Meal\GenerateMealShoppingTool::class,
        ListRecipesTool::class,
        CreateRecipeTool::class,
        UpdateRecipeTool::class,
        ListNegativeHabitsTool::class,
        LogNegativeHabitTool::class,
        ListFocusSessionsTool::class,
        LogFocusSessionTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected function handleInitializeMessage(
        \Laravel\Mcp\Transport\JsonRpcRequest $request,
        \Laravel\Mcp\Server\ServerContext $context,
    ): void {
        $version = $request->params['protocolVersion'] ?? null;
        if (is_string($version) && ! in_array($version, $context->supportedProtocolVersions, true)) {
            $request->params['protocolVersion'] = $context->supportedProtocolVersions[0];
        }

        parent::handleInitializeMessage($request, $context);
    }

    protected array $prompts = [
        //
    ];
}

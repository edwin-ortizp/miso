<?php

use App\Livewire\Game\GameCollection;
use App\Livewire\Task\TaskList;
use App\Models\Game\GameCompanion;
use App\Models\Game\GameSpecies;
use App\Models\Task;
use App\Models\User;
use App\Services\Game\FavoriteCompanion;
use App\Services\Game\GameSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// CHG-100: chat del compañero en lugar del FAB.

function chatCompanion(User $user, string $name, array $types = ['fuego'], int $level = 1): GameCompanion
{
    $species = GameSpecies::create(['number' => random_int(1, 99999), 'name' => $name, 'line_key' => 'chat-'.$name, 'stage' => 1, 'rarity' => 'common', 'types' => $types, 'active' => true]);

    return GameCompanion::withoutGlobalScopes()->create(['user_id' => $user->id, 'species_id' => $species->id, 'line_key' => 'chat-'.$name, 'level' => $level, 'first_met_at' => now()]);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('usa el favorito guardado con su tema de tipo', function () {
    chatCompanion($this->user, 'Uno');
    $favorite = chatCompanion($this->user, 'Dos', ['agua']);
    app(GameSettings::class)->save($this->user->id, ['favorite_companion_id' => $favorite->id]);

    $chat = app(FavoriteCompanion::class)->forChat($this->user->id);

    expect($chat['name'])->toBe('Dos')
        ->and($chat['favorite'])->toBeTrue()
        ->and($chat['type_class'])->toBe('game-type--agua');
});

it('sin favorito usa un compañero estable durante el día', function () {
    chatCompanion($this->user, 'Uno');
    chatCompanion($this->user, 'Dos');
    $service = app(FavoriteCompanion::class);

    $first = $service->forChat($this->user->id);
    $service->flush();

    expect($first['favorite'])->toBeFalse()
        ->and($service->forChat($this->user->id)['companion_id'])->toBe($first['companion_id']);
});

it('vuelve al botón tradicional sin colección o si el usuario lo prefiere', function () {
    expect(app(FavoriteCompanion::class)->forChat($this->user->id))->toBeNull();

    chatCompanion($this->user, 'Uno');
    app(GameSettings::class)->save($this->user->id, ['creation_style' => 'traditional']);

    expect(app(FavoriteCompanion::class)->forChat($this->user->id))->toBeNull();
});

it('ignora un favorito de otro usuario', function () {
    $other = User::factory()->create();
    $foreign = chatCompanion($other, 'Ajeno');
    chatCompanion($this->user, 'Propio');
    app(GameSettings::class)->save($this->user->id, ['favorite_companion_id' => $foreign->id]);

    expect(app(FavoriteCompanion::class)->forChat($this->user->id)['name'])->toBe('Propio');
});

it('marca un favorito desde la colección', function () {
    $companion = chatCompanion($this->user, 'Uno');

    Livewire::test(GameCollection::class)->call('makeFavorite', $companion->id);

    expect(app(GameSettings::class)->for($this->user->id)['favorite_companion_id'])->toBe($companion->id);
});

it('crea una o varias tareas rápido desde el chat', function () {
    $component = Livewire::test(TaskList::class);

    expect($component->call('quickCreateTasks', 'Comprar pan')->effects['returns'][0] ?? null)->toMatchArray(['ok' => true, 'count' => 1]);
    $component->call('quickCreateTasks', "Uno\n\n  Dos  \nTres");

    expect(Task::where('user_id', $this->user->id)->count())->toBe(4)
        ->and(Task::where('user_id', $this->user->id)->pluck('title')->all())->toContain('Comprar pan', 'Dos');
});

it('rechaza un envío vacío sin crear tareas', function () {
    Livewire::test(TaskList::class)->call('quickCreateTasks', "  \n ");

    expect(Task::where('user_id', $this->user->id)->count())->toBe(0);
});

it('muestra el chat en lugar del FAB y el FAB con presentación tradicional', function () {
    chatCompanion($this->user, 'Chispa');

    $this->get(route('tasks.list'))->assertOk()->assertSee('Nueva tarea con Chispa', false)->assertSee('lt-chat__launcher', false);

    app(GameSettings::class)->save($this->user->id, ['creation_style' => 'traditional']);
    $this->get(route('tasks.list'))->assertOk()->assertDontSee('lt-chat__launcher', false)->assertSee('md-fab-split', false);
});

it('renderiza el chat con acciones rápidas en Agua y Ánimo', function () {
    chatCompanion($this->user, 'Gota', ['agua']);

    $this->get('/water/daily')->assertOk()->assertSee('lt-chat__launcher', false)->assertSee('game-type--agua', false);
    $this->get('/mood')->assertOk()->assertSee('lt-chat__launcher', false);
});

it('crea tareas rápidas para hoy o mañana con hora', function () {
    $this->travelTo(now()->setTime(10, 0));
    Livewire::test(TaskList::class)
        ->set('quickWhen', 'tomorrow')->set('quickTime', '08:30')
        ->call('quickCreateTasks', 'Llamar al banco')
        ->assertSet('quickTime', '')->assertSet('quickWhen', 'tomorrow');

    $task = Task::where('user_id', $this->user->id)->firstOrFail();
    expect($task->start_date->format("Y-m-d H:i"))->toBe(now()->addDay()->format("Y-m-d")." 08:30");
});

it('ignora una categoría que no existe', function () {
    Livewire::test(TaskList::class)->set('quickCategory', 'no-existe')->call('quickCreateTasks', 'Algo');

    expect(Task::where('user_id', $this->user->id)->first()->category)->toBeNull();
});

it('registra energía de un toque y valida el nivel', function () {
    $component = Livewire::test(\App\Livewire\Mood\MoodTracker::class);
    $component->call('quickEnergy', 4);
    $component->call('quickEnergy', 9);

    expect(\App\Models\EnergyEntry::where('user_id', $this->user->id)->pluck('level')->all())->toBe([4]);
});

it('el chat no se reemplaza en cada render', function () {
    chatCompanion($this->user, 'Chispa');

    $this->get(route('tasks.list'))->assertOk()
        ->assertSee('wire:ignore wire:key="companion-chat-tasks"', false)
        ->assertSee('Tócame para anotar algo', false);
});


it('filtra desde el chat sin quitar búsqueda ni categoría y cuenta la lista actual', function () {
    \App\Models\TaskCategory::create(['name' => 'Trabajo', 'key' => 'trabajo']);
    Task::create(['title' => 'Preparar hoy', 'category' => 'trabajo', 'start_date' => today()]);
    Task::create(['title' => 'Preparar mañana', 'category' => 'trabajo', 'start_date' => today()->addDay()]);
    Task::create(['title' => 'Otra hoy', 'start_date' => today()]);
    $component = Livewire::test(TaskList::class)->set('categoryFilter', ['trabajo'])->set('search', 'Preparar');
    $result = $component->call('chatFilter', 'hoy')->effects['returns'][0];
    expect($result)->toMatchArray(['ok' => true, 'total' => 1]);
    $component->assertSet('dateFilter', ['hoy'])->assertSet('categoryFilter', ['trabajo'])->assertSet('search', 'Preparar');
    expect($component->call('chatFilter', '')->effects['returns'][0])->toMatchArray(['ok' => true, 'total' => 2]);
    $component->assertSet('dateFilter', []);
    expect($component->call('chatFilter', 'invalido')->effects['returns'][0]['ok'])->toBeFalse();
});

it('solo acepta órdenes existentes y conserva los filtros del chat', function () {
    Task::create(['title' => 'Hoy', 'start_date' => today()]);
    $component = Livewire::test(TaskList::class)->set('dateFilter', ['hoy']);
    foreach (array_keys(TaskList::SORTS) as $sort) {
        expect($component->call('chatSort', $sort)->effects['returns'][0])->toMatchArray(['ok' => true, 'total' => 1]);
        $component->assertSet('sort', $sort)->assertSet('dateFilter', ['hoy']);
    }
    expect($component->call('chatSort', 'drop table tasks')->effects['returns'][0]['ok'])->toBeFalse();
    $component->assertSet('sort', 'title');
});

it('propone una pendiente de la lista actual por vencimiento y luego prioridad', function () {
    $this->travelTo(today()->setTime(12, 0));
    $old = Task::create(['title' => 'Pendiente antigua', 'start_date' => today()->subDays(2), 'priority' => 'not-urgent-not-important']);
    $urgent = Task::create(['title' => 'Urgente vencida', 'end_date' => today()->subDay(), 'priority' => 'urgent-important']);
    $today = Task::create(['title' => 'Pendiente hoy', 'start_date' => today(), 'priority' => 'urgent-important']);
    $future = Task::create(['title' => 'Pendiente futura', 'start_date' => today()->addDay(), 'priority' => 'urgent-important']);
    $component = Livewire::test(TaskList::class);
    expect($component->call('chatNext')->effects['returns'][0]['id'])->toBe($urgent->id);
    $component->set('dateFilter', ['hoy']);
    expect($component->call('chatNext')->effects['returns'][0]['id'])->toBe($today->id);
    $component->set('dateFilter', [])->set('search', 'futura');
    expect($component->call('chatNext')->effects['returns'][0]['id'])->toBe($future->id);
    $component->set('search', 'no existe');
    expect($component->call('chatNext')->effects['returns'][0])->toMatchArray(['ok' => true, 'empty' => true]);
});

it('no propone ni abre ni completa tareas ajenas o que dejaron de estar en la lista', function () {
    $other = User::factory()->create();
    $this->actingAs($other);
    $foreign = Task::create(['title' => 'Ajena']);
    $this->actingAs($this->user);
    $mine = Task::create(['title' => 'Mía', 'start_date' => today()]);
    $component = Livewire::test(TaskList::class);
    expect($component->call('chatNext')->effects['returns'][0]['id'])->toBe($mine->id);
    foreach (['chatOpen', 'chatComplete'] as $method) {
        expect($component->call($method, $foreign->id)->effects['returns'][0]['ok'])->toBeFalse();
    }
    expect($foreign->fresh()->completed)->toBeFalse();
    $component->set('dateFilter', ['sin-fecha']);
    foreach (['chatOpen', 'chatComplete'] as $method) {
        expect($component->call($method, $mine->id)->effects['returns'][0]['ok'])->toBeFalse();
    }
    expect($mine->fresh()->completed)->toBeFalse();
});

it('completa la propuesta solo una vez y rechaza una tarea ya completada', function () {
    $task = Task::create(['title' => 'Completar']);
    $component = Livewire::test(TaskList::class);
    expect($component->call('chatOpen', $task->id)->effects['returns'][0])->toMatchArray(['ok' => true, 'id' => $task->id]);
    expect($component->call('chatComplete', $task->id)->effects['returns'][0]['ok'])->toBeTrue();
    expect($task->fresh()->completed)->toBeTrue();
    expect($component->call('chatComplete', $task->id)->effects['returns'][0]['ok'])->toBeFalse();
    expect($component->call('chatNext')->effects['returns'][0])->toMatchArray(['ok' => true, 'empty' => true]);
});

it('pide confirmar la recurrencia sin anunciar una tarea completada', function () {
    $task = Task::create(['title' => 'Rutina', 'is_recurrent' => true, 'start_date' => today(), 'recurrence' => ['customDays' => 7]]);
    $component = Livewire::test(TaskList::class);
    expect($component->call('chatComplete', $task->id)->effects['returns'][0])->toMatchArray(['ok' => true, 'pending' => true]);
    $component->assertSet('showRecurringCompletion', true)->assertSet('recurringTaskId', $task->id);
    expect($task->fresh()->completed)->toBeFalse();
});

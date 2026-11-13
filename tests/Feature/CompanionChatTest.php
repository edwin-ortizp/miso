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

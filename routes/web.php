<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Ui\CatalogController;
use App\Livewire\Exercise\ExerciseDaily;
use App\Livewire\Exercise\ExerciseSettings;
use App\Livewire\Exercise\ExerciseStatistics;
use App\Livewire\Goal\GoalDetail;
use App\Livewire\Goal\GoalIndex;
use App\Livewire\Habit\HabitSettings;
use App\Livewire\Habit\HabitTracker;
use App\Livewire\Habit\HabitWeekly;
use App\Livewire\Health\HealthBodyMap;
use App\Livewire\Health\HealthIndex;
use App\Livewire\Home\Dashboard;
use App\Livewire\Journal\JournalEntries;
use App\Livewire\Journal\JournalLifeCalendar;
use App\Livewire\Journal\JournalLifeWeek;
use App\Livewire\Journal\JournalSummary;
use App\Livewire\Meal\MealIngredients;
use App\Livewire\Meal\MealProductCompare;
use App\Livewire\Meal\MealPurchases;
use App\Livewire\Meal\MealPurchaseShow;
use App\Livewire\Meal\MealRecipes;
use App\Livewire\Meal\MealShopping;
use App\Livewire\Meal\MealSettings;
use App\Livewire\Meal\MealWeekly;
use App\Livewire\Mood\MoodSettings;
use App\Livewire\Mood\MoodTracker;
use App\Livewire\NegativeHabit\NegativeHabitWeekly;
use App\Livewire\Plan\PlanIndex;
use App\Livewire\Plan\PlanShow;
use App\Livewire\Pomodoro\PomodoroSettings;
use App\Livewire\Pomodoro\PomodoroTimer;
use App\Livewire\Relationship\RelationshipBirthdays;
use App\Livewire\Relationship\RelationshipEvents;
use App\Livewire\Relationship\RelationshipHistory;
use App\Livewire\Relationship\RelationshipIndex;
use App\Livewire\Relationship\RelationshipPlans;
use App\Livewire\Relationship\RelationshipShow;
use App\Livewire\Relationship\RelationshipTasks;
use App\Livewire\Settings\SettingsPage;
use App\Livewire\Statistics\StatisticsDashboard;
use App\Livewire\Task\TaskCalendar;
use App\Livewire\Task\TaskFlow;
use App\Livewire\Task\TaskGantt;
use App\Livewire\Task\TaskKanban;
use App\Livewire\Task\TaskList;
use App\Livewire\Task\TaskPlanning;
use App\Livewire\Task\TaskProgress;
use App\Livewire\Task\TaskSettings;
use App\Livewire\Vehicle\VehicleCatalog;
use App\Livewire\Vehicle\VehicleExpenses;
use App\Livewire\Vehicle\VehicleFuel;
use App\Livewire\Vehicle\VehicleIndex;
use App\Livewire\Vehicle\VehicleMaintenance;
use App\Livewire\Vehicle\VehicleServices;
use App\Livewire\Vehicle\VehicleShow;
use App\Livewire\Water\WaterCalendar;
use App\Livewire\Water\WaterDaily;
use App\Livewire\Water\WaterRange;
use App\Livewire\Water\WaterSettings;
use App\Livewire\Water\WaterWeekly;
use Illuminate\Support\Facades\Route;

Route::redirect('/.well-known/caldav', '/dav/');

// Auth routes (guests only)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);
});

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// Catálogo del sistema de diseño: solo existe en desarrollo y pruebas.
if (app()->environment(['local', 'testing'])) {
    Route::get('/ui-catalog', [CatalogController::class, 'index'])->name('ui.catalog');
    Route::get('/ui-catalog/arquetipo/{archetype}', [CatalogController::class, 'archetype'])->name('ui.catalog.archetype');
    Route::get('/ui-catalog/{component}', [CatalogController::class, 'show'])->name('ui.catalog.show');
}

// Protected routes
// Pagina que sirve el service worker cuando se navega a una pantalla que nunca
// se visito y no hay conexion. Es publica a proposito: sin red no hay sesion
// que comprobar.
Route::view('/offline', 'offline')->name('offline');

Route::middleware('auth')->group(function () {
    Route::get('/', Dashboard::class)->name('home');
    Route::get('/panel', \App\Livewire\Home\WallPanel::class)->name('panel');
    Route::redirect('/water', '/water/daily')->name('water');
    Route::get('/water/daily', WaterDaily::class)->name('water.daily');
    Route::get('/water/calendar', WaterCalendar::class)->name('water.calendar');
    Route::get('/water/weekly', WaterWeekly::class)->name('water.weekly');
    Route::get('/water/range', WaterRange::class)->name('water.range');
    Route::get('/water/settings', WaterSettings::class)->name('water.settings');
    Route::get('/exercise', ExerciseDaily::class)->name('exercise');
    Route::get('/exercise/statistics', ExerciseStatistics::class)->name('exercise.statistics');
    Route::get('/exercise/settings', ExerciseSettings::class)->name('exercise.settings');
    Route::get('/health', HealthIndex::class)->name('health');
    Route::get('/health/body', HealthBodyMap::class)->name('health.body');
    Route::get('/vehicles', VehicleIndex::class)->name('vehicles');
    Route::get('/vehicles/maintenance-catalog', VehicleCatalog::class)->name('vehicles.catalog');
    Route::get('/vehicles/{vehicle}', VehicleShow::class)->name('vehicles.show');
    Route::get('/vehicles/{vehicle}/fuel', VehicleFuel::class)->name('vehicles.fuel');
    Route::get('/vehicles/{vehicle}/maintenance', VehicleMaintenance::class)->name('vehicles.maintenance');
    Route::get('/vehicles/{vehicle}/services', VehicleServices::class)->name('vehicles.services');
    Route::get('/vehicles/{vehicle}/expenses', VehicleExpenses::class)->name('vehicles.expenses');
    Route::get('/habits', HabitTracker::class)->name('habits');
    Route::get('/habits/weekly', HabitWeekly::class)->name('habits.weekly');
    Route::get('/habits/settings', HabitSettings::class)->name('habits.settings');
    Route::get('/mood', MoodTracker::class)->name('mood');
    Route::get('/mood/settings', MoodSettings::class)->name('mood.settings');
    Route::get('/journal', JournalEntries::class)->name('journal');
    Route::get('/journal/summary', JournalSummary::class)->name('journal.summary');
    Route::get('/journal/life', JournalLifeCalendar::class)->name('journal.life');
    Route::get('/journal/life/week', JournalLifeWeek::class)->name('journal.life.week');
    Route::get('/pomodoro', PomodoroTimer::class)->name('pomodoro');
    Route::get('/pomodoro/settings', PomodoroSettings::class)->name('pomodoro.settings');
    Route::redirect('/meals', '/meals/weekly')->name('meals');
    Route::get('/meals/weekly', MealWeekly::class)->name('meals.weekly');
    Route::get('/meals/preparations', \App\Livewire\Meal\MealPreparations::class)->name('meals.preparations');
    Route::get('/meals/suggestions', \App\Livewire\Meal\MealSuggestions::class)->name('meals.suggestions');
    Route::get('/meals/recipes', MealRecipes::class)->name('meals.recipes');
    Route::get('/meals/ingredients', MealIngredients::class)->name('meals.ingredients');
    Route::get('/meals/settings', MealSettings::class)->name('meals.settings');
    Route::get('/meals/settings/categories', MealSettings::class)->defaults('section', 'categories')->name('meals.settings.categories');
    Route::get('/meals/shopping', MealShopping::class)->name('meals.shopping');
    Route::get('/meals/purchases', MealPurchases::class)->name('meals.purchases');
    Route::get('/meals/purchases/{purchase}', MealPurchaseShow::class)->name('meals.purchases.show');
    Route::get('/meals/ingredients/{item}/compare', MealProductCompare::class)->name('meals.compare');
    Route::redirect('/tasks', '/tasks/list')->name('tasks');
    Route::get('/tasks/list', TaskList::class)->name('tasks.list');
    Route::get('/tasks/gantt', TaskGantt::class)->name('tasks.gantt');
    Route::get('/tasks/calendar', TaskCalendar::class)->name('tasks.calendar');
    Route::get('/tasks/flow', TaskFlow::class)->name('tasks.flow');
    Route::get('/tasks/kanban', TaskKanban::class)->name('tasks.kanban');
    Route::get('/tasks/planning', TaskPlanning::class)->name('tasks.planning');
    Route::get('/tasks/progress', TaskProgress::class)->name('tasks.progress');
    Route::get('/tasks/settings', TaskSettings::class)->name('tasks.settings');
    Route::get('/tasks/settings/statuses', TaskSettings::class)->defaults('section', 'statuses')->name('tasks.settings.statuses');
    Route::get('/relationships', RelationshipIndex::class)->name('relationships');
    Route::get('/relationships/events', RelationshipEvents::class)->name('relationships.events');
    Route::get('/relationships/birthdays', RelationshipBirthdays::class)->name('relationships.birthdays');
    Route::get('/relationships/{relationship}', RelationshipShow::class)->name('relationships.show');
    Route::get('/relationships/{relationship}/plans', RelationshipPlans::class)->name('relationships.plans');
    Route::get('/relationships/{relationship}/history', RelationshipHistory::class)->name('relationships.history');
    Route::get('/relationships/{relationship}/tasks', RelationshipTasks::class)->name('relationships.tasks');
    Route::get('/plans/scheduled', \App\Livewire\Plan\PlanScheduled::class)->name('plans.scheduled');
    Route::get('/plans/history', \App\Livewire\Plan\PlanHistory::class)->name('plans.history');
    Route::get('/plans/settings', \App\Livewire\Plan\PlanSettings::class)->name('plans.settings');
    Route::get('/plans', PlanIndex::class)->name('plans');
    Route::get('/plans/{plan}', PlanShow::class)->name('plans.show');
    Route::get('/goals', GoalIndex::class)->name('goals');
    Route::get('/goals/{goal}', GoalDetail::class)->name('goals.show');
    Route::get('/statistics', StatisticsDashboard::class)->name('statistics');
    Route::get('/negative-habits', NegativeHabitWeekly::class)->name('negative-habits');
    Route::get('/categories/{domain}', \App\Livewire\Category\CategorySettings::class)->name('categories.settings');
    Route::get('/settings', SettingsPage::class)->name('settings');
    Route::get('/settings/{section}', SettingsPage::class)
        ->whereIn('section', ['body', 'security', 'oauth', 'integrations', 'notifications', 'mcp', 'logs', 'about'])
        ->name('settings.section');
});

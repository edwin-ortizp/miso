<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'full_name',
        'avatar_url',
        'life_expectancy_years',
        'current_weight_kg',
        'height_cm',
        'birth_date',
        'activity_level',
        'daily_water_goal',
        'daily_exercise_minutes',
        'theme_preference',
    ];

    public const THEME_PREFERENCES = ['system', 'light', 'dark'];

    protected $hidden = [
        'password', 'remember_token',
    ];

    /** Preferencia válida de tema; cualquier otro valor guardado se trata como «system». */
    public function themePreference(): string
    {
        return in_array($this->theme_preference, self::THEME_PREFERENCES, true) ? $this->theme_preference : 'system';
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'current_weight_kg' => 'decimal:2',
            'height_cm' => 'integer',
            'birth_date' => 'date',
            'daily_water_goal' => 'integer',
            'daily_exercise_minutes' => 'integer',
        ];
    }

    public function plans()
    {
        return $this->hasMany(Plan::class);
    }

    public function exerciseTypes()
    {
        return $this->hasMany(ExerciseType::class);
    }

    public function drinkTypes()
    {
        return $this->hasMany(DrinkType::class);
    }

    public function moodStates()
    {
        return $this->hasMany(MoodState::class);
    }

    public function habitCompletions()
    {
        return $this->hasMany(HabitCompletion::class);
    }

    public function habitDefinitions()
    {
        return $this->hasMany(HabitDefinition::class);
    }

    public function negativeHabitDefinitions()
    {
        return $this->hasMany(NegativeHabitDefinition::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function goals()
    {
        return $this->hasMany(Goal::class);
    }

    public function moodEntries()
    {
        return $this->hasMany(MoodEntry::class);
    }

    public function energyEntries()
    {
        return $this->hasMany(EnergyEntry::class);
    }

    public function drinkLogs()
    {
        return $this->hasMany(DrinkLog::class);
    }

    public function exerciseLogs()
    {
        return $this->hasMany(ExerciseLog::class);
    }

    public function pomodoroSessions()
    {
        return $this->hasMany(PomodoroSession::class);
    }

    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function integrationTokens()
    {
        return $this->hasMany(IntegrationToken::class);
    }

    public function shoppingItems()
    {
        return $this->hasMany(ShoppingItem::class);
    }

    public function shoppingItemAliases()
    {
        return $this->hasMany(ShoppingItemAlias::class);
    }

    public function recipes()
    {
        return $this->hasMany(Recipe::class);
    }

    public function mealPlanEntries()
    {
        return $this->hasMany(MealPlanEntry::class);
    }

    public function circles()
    {
        return $this->hasMany(Circle::class);
    }

    public function relationships()
    {
        return $this->hasMany(Relationship::class);
    }

    public function moodReflections()
    {
        return $this->hasMany(MoodReflection::class);
    }

    public function relationshipTags()
    {
        return $this->hasMany(RelationshipTag::class);
    }

    public function relationshipEvents()
    {
        return $this->hasMany(RelationshipEvent::class);
    }

    public function taskAssociations()
    {
        return $this->hasMany(TaskAssociation::class);
    }

    public function healthEvents()
    {
        return $this->hasMany(HealthEvent::class);
    }

    public function healthLogs()
    {
        return $this->hasMany(HealthLog::class);
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function taskCategories()
    {
        return $this->hasMany(TaskCategory::class);
    }

    public function taskStatuses()
    {
        return $this->hasMany(TaskStatus::class);
    }

    public function vehicleExpenseCategories()
    {
        return $this->hasMany(VehicleExpenseCategory::class);
    }
}

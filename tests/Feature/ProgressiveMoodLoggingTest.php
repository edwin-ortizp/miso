<?php

namespace Tests\Feature;

use App\Livewire\Home\Dashboard;
use App\Livewire\Journal\JournalMoodRail;
use App\Livewire\Mood\MoodTracker;
use App\Models\MoodEntry;
use App\Models\MoodState;
use App\Models\Relationship;
use App\Models\User;
use App\Support\DefaultMoodStates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProgressiveMoodLoggingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-04 09:00:00');
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        DefaultMoodStates::createFor($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return array<string, array{class-string}> */
    public static function surfaces(): array
    {
        return [
            'ánimo' => [MoodTracker::class],
            'inicio' => [Dashboard::class],
            'diario' => [JournalMoodRail::class],
        ];
    }

    private function surface(string $component)
    {
        return $component === JournalMoodRail::class
            ? Livewire::test($component, ['selectedDate' => '2026-08-04'])
            : Livewire::test($component);
    }

    #[DataProvider('surfaces')]
    public function test_one_tap_saves_an_entry_with_no_context_required(string $component): void
    {
        $state = MoodState::firstWhere('text', 'Frustración');

        $this->surface($component)
            ->call('saveMood', $state->id)
            ->assertHasNoErrors();

        $this->assertDatabaseCount('mood_entries', 1);

        $entry = MoodEntry::first();
        $this->assertSame($state->id, $entry->mood_state_id);
        $this->assertSame('Frustración', $entry->text);
        $this->assertSame('2026-08-04', $entry->date->toDateString());
        $this->assertSame('09:00', $entry->time);
        $this->assertNull($entry->intensity);
        $this->assertNull($entry->situation);
        $this->assertNull($entry->reflection);
    }

    #[DataProvider('surfaces')]
    public function test_the_confirmation_is_shown_but_never_blocks_the_page(string $component): void
    {
        $state = MoodState::firstWhere('text', 'Gratitud');

        $surface = $this->surface($component)
            ->call('saveMood', $state->id)
            ->assertSee('Registraste “Gratitud”')
            ->assertSee('Deshacer')
            ->assertSee('Añadir contexto')
            // The context sheet never opens by itself.
            ->assertSet('showMoodContext', false);

        // Ignoring it leaves the entry exactly as saved.
        $surface->call('dismissMoodConfirmation')->assertDontSee('Registraste “Gratitud”');
        $this->assertDatabaseCount('mood_entries', 1);
    }

    #[DataProvider('surfaces')]
    public function test_undo_removes_only_the_entry_the_confirmation_referred_to(string $component): void
    {
        $earlier = MoodEntry::factory()->create(['user_id' => $this->user->id, 'text' => 'Anterior']);
        $state = MoodState::firstWhere('text', 'Triste');

        $this->surface($component)
            ->call('saveMood', $state->id)
            ->call('undoLastMood')
            ->assertSet('lastMoodEntryId', null);

        $this->assertDatabaseCount('mood_entries', 1);
        $this->assertDatabaseHas('mood_entries', ['id' => $earlier->id]);
    }

    #[DataProvider('surfaces')]
    public function test_context_is_optional_and_saves_only_what_was_provided(string $component): void
    {
        $state = MoodState::firstWhere('text', 'Frustración');

        $this->surface($component)
            ->call('saveMood', $state->id)
            ->call('openMoodContext')
            ->assertSet('showMoodContext', true)
            ->set('contextSituation', 'Discutí con Alison')
            ->call('saveMoodContext')
            ->assertHasNoErrors()
            ->assertSet('showMoodContext', false);

        $entry = MoodEntry::first();
        $this->assertSame('Discutí con Alison', $entry->situation);
        $this->assertNull($entry->intensity, 'Intensity is never invented.');
        $this->assertCount(0, $entry->relationships);
    }

    #[DataProvider('surfaces')]
    public function test_the_full_catalog_is_one_action_away_from_the_compact_picker(string $component): void
    {
        $this->surface($component)
            ->assertDontSee('Preocupación')
            ->call('openMoodCatalog')
            ->assertSee('Preocupación')
            ->assertSee('Gratitud')
            ->assertSee('Alivio')
            ->set('moodCatalogSearch', 'aliv')
            ->assertSee('Alivio')
            ->assertDontSee('Preocupación');
    }

    #[DataProvider('surfaces')]
    public function test_an_emotion_chosen_from_the_catalog_saves_in_one_tap(string $component): void
    {
        $alivio = MoodState::firstWhere('text', 'Alivio');

        $this->surface($component)
            ->call('openMoodCatalog')
            ->call('logMood', $alivio->id)
            ->assertSet('showMoodCatalog', false);

        $this->assertDatabaseCount('mood_entries', 1);
        $this->assertSame('Alivio', MoodEntry::first()->text);
    }

    public function test_the_compact_picker_prioritizes_recent_and_frequent_emotions(): void
    {
        $rare = MoodState::firstWhere('text', 'Enfermo');
        $frequent = MoodState::firstWhere('text', 'Preocupación');

        MoodEntry::factory()->count(4)->forState($frequent)->create(['timestamp' => 1000]);
        MoodEntry::factory()->forState($rare)->create(['timestamp' => 5000]);

        $prioritized = app(\App\Support\MoodLogger::class)->prioritizedStates(3)->pluck('text')->all();

        $this->assertSame('Enfermo', $prioritized[0], 'The most recent emotion comes first.');
        $this->assertContains('Preocupación', $prioritized, 'The most used emotion stays in reach.');
    }

    public function test_intensity_and_relationships_are_stored_when_the_user_confirms_them(): void
    {
        $alison = Relationship::factory()->create(['user_id' => $this->user->id, 'full_name' => 'Alison']);
        $state = MoodState::firstWhere('text', 'Frustración');

        Livewire::test(MoodTracker::class)
            ->call('saveMood', $state->id)
            ->call('openMoodContext')
            ->assertSee('Alison')
            ->set('contextSituation', 'Discutí con Alison')
            ->call('setContextIntensity', 4)
            ->call('toggleContextRelationship', $alison->id)
            ->call('saveMoodContext')
            ->assertHasNoErrors();

        $entry = MoodEntry::first();
        $this->assertSame(4, $entry->intensity);
        $this->assertSame(['Alison'], $entry->relationships->pluck('full_name')->all());
    }

    public function test_a_suggested_relationship_is_not_linked_until_it_is_confirmed(): void
    {
        Relationship::factory()->create(['user_id' => $this->user->id, 'full_name' => 'Alison']);
        $state = MoodState::firstWhere('text', 'Frustración');

        Livewire::test(MoodTracker::class)
            ->call('saveMood', $state->id)
            ->call('openMoodContext')
            ->set('contextRelationshipSearch', 'Alison')
            ->assertSee('Alison')
            ->set('contextSituation', 'Discutí con Alison')
            ->call('saveMoodContext');

        $this->assertDatabaseCount('mood_entry_relationship', 0);
        $this->assertCount(0, MoodEntry::first()->relationships);
    }

    public function test_a_relationship_from_another_user_is_never_suggested_or_linked(): void
    {
        $other = User::factory()->create();
        $foreign = Relationship::factory()->create(['user_id' => $other->id, 'full_name' => 'Persona Ajena']);
        $state = MoodState::firstWhere('text', 'Triste');

        Livewire::test(MoodTracker::class)
            ->call('saveMood', $state->id)
            ->call('openMoodContext')
            ->assertDontSee('Persona Ajena')
            ->call('toggleContextRelationship', $foreign->id)
            ->assertSet('contextRelationshipIds', [])
            ->call('saveMoodContext');

        $this->assertDatabaseCount('mood_entry_relationship', 0);
    }

    public function test_the_history_lets_the_user_correct_the_emotion_without_losing_context(): void
    {
        $sad = MoodState::firstWhere('text', 'Triste');
        $worried = MoodState::firstWhere('text', 'Preocupación');
        $alison = Relationship::factory()->create(['user_id' => $this->user->id, 'full_name' => 'Alison']);

        $entry = MoodEntry::factory()->forState($sad)->create([
            'date' => '2026-08-04',
            'intensity' => 3,
            'situation' => 'Discutí con Alison',
        ]);
        $entry->linkRelationship($alison);

        Livewire::test(\App\Livewire\Mood\MoodEditor::class)
            ->call('open', 'edit', $entry->id)
            ->set('emotion', $worried->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('mode', '');

        $entry->refresh();
        $this->assertSame('Preocupación', $entry->text);
        $this->assertSame(3, $entry->intensity);
        $this->assertSame('Discutí con Alison', $entry->situation);
        $this->assertCount(1, $entry->relationships);
        // Correcting replaces the primary emotion; it never creates a second entry.
        $this->assertDatabaseCount('mood_entries', 1);
    }

    public function test_context_can_be_added_later_from_the_history(): void
    {
        $entry = MoodEntry::factory()->create(['user_id' => $this->user->id, 'date' => '2026-08-04']);

        Livewire::test(MoodTracker::class)
            ->call('openMoodContext', $entry->id)
            ->assertSet('contextEntryId', $entry->id)
            ->set('contextSituation', 'Mi papá me hizo un favor')
            ->call('setContextIntensity', 2)
            ->call('saveMoodContext');

        $entry->refresh();
        $this->assertSame('Mi papá me hizo un favor', $entry->situation);
        $this->assertSame(2, $entry->intensity);
    }

    public function test_an_intensity_outside_the_scale_is_rejected(): void
    {
        $entry = MoodEntry::factory()->create(['user_id' => $this->user->id, 'date' => '2026-08-04']);

        Livewire::test(MoodTracker::class)
            ->call('openMoodContext', $entry->id)
            ->set('contextIntensity', 9)
            ->call('saveMoodContext')
            ->assertHasErrors('contextIntensity');

        $this->assertNull($entry->fresh()->intensity);
    }
}

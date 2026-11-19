<?php

namespace Tests\Feature;

use App\Livewire\Relationship\RelationshipEvents;
use App\Livewire\Relationship\RelationshipIndex;
use App\Livewire\Relationship\RelationshipShow;
use App\Models\MoodEntry;
use App\Models\MoodReflection;
use App\Models\MoodState;
use App\Models\Relationship;
use App\Models\User;
use App\Support\DefaultMoodStates;
use App\Support\RelationshipEmotionalPatterns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class RelationshipEmotionalContextTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Relationship $alison;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-04 09:00:00');
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        DefaultMoodStates::createFor($this->user);

        $this->alison = Relationship::factory()->create([
            'user_id' => $this->user->id,
            'full_name' => 'Alison Restrepo',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function linkedEntry(string $emotion, array $attributes = [], ?Relationship $to = null): MoodEntry
    {
        $entry = MoodEntry::factory()
            ->forState(MoodState::firstWhere('text', $emotion))
            ->create(['date' => '2026-08-01', ...$attributes]);

        $entry->linkRelationship($to ?? $this->alison);

        return $entry;
    }

    public function test_one_entry_can_involve_several_people(): void
    {
        $dad = Relationship::factory()->create(['user_id' => $this->user->id, 'full_name' => 'Mi papá']);
        $entry = $this->linkedEntry('Gratitud', ['situation' => 'Mi papá me hizo un favor']);
        $entry->linkRelationship($dad);

        $this->assertEqualsCanonicalizing(['Alison Restrepo', 'Mi papá'], $entry->fresh()->relationships->pluck('full_name')->all());

        // A single entry is shared by both people; it is never duplicated per person.
        $this->assertDatabaseCount('mood_entries', 1);
    }

    public function test_one_backdated_record_is_visible_on_each_linked_contact_and_can_be_unlinked(): void
    {
        $other = Relationship::factory()->create(['user_id' => $this->user->id]);
        $third = Relationship::factory()->create(['user_id' => $this->user->id]);
        $emotion = MoodState::firstWhere('text', 'Frustración');
        $editor = Livewire::test(\App\Livewire\Mood\MoodEditor::class, ['contactId' => $this->alison->id])
            ->call('open', 'create')
            ->set('emotion', $emotion->id)
            ->set('date', '2026-08-03')
            ->set('situation', 'Discutí con Alison')
            ->set('contacts', [$other->id, $third->id])
            ->call('save')
            ->assertHasNoErrors();

        $entry = MoodEntry::sole();
        $this->assertSame('2026-08-03', $entry->date->toDateString());

        foreach ([$this->alison, $other, $third] as $person) {
            Livewire::test(\App\Livewire\Relationship\RelationshipMood::class, ['relationship' => $person->id])
                ->assertSee('Mood (1)')
                ->assertSee('Discutí con Alison');
            Livewire::test(\App\Livewire\Relationship\RelationshipHistory::class, ['relationship' => $person->id])
                ->call('setType', 'mood')
                ->assertSee('Discutí con Alison');
        }

        $editor->call('open', 'edit', $entry->id)
            ->set('contacts', [$other->id, $third->id])
            ->set('situation', 'Detalle revisado')
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test(RelationshipShow::class, ['relationship' => $this->alison->id])
            ->assertSee('Mood (0)')
            ->assertDontSee('Detalle revisado');
        $this->assertEqualsCanonicalizing([$other->id, $third->id], $entry->relationships()->pluck('relationships.id')->all());
        $this->assertSame('Detalle revisado', $entry->fresh()->situation);
    }

    public function test_editor_rejects_foreign_contacts_and_keeps_unlinked_entries_in_mood(): void
    {
        $foreign = Relationship::factory()->create(['user_id' => User::factory()->create()->id]);
        $entry = $this->linkedEntry('Frustración');
        $editor = Livewire::test(\App\Livewire\Mood\MoodEditor::class, ['contactId' => $this->alison->id])
            ->call('open', 'edit', $entry->id)
            ->set('contacts', [$foreign->id])
            ->call('save')
            ->assertHasErrors(['contacts.0']);

        $this->assertSame([$this->alison->id], $entry->relationships->modelKeys());

        $editor->set('contacts', [])->call('save')->assertHasNoErrors();
        $this->assertDatabaseCount('mood_entry_relationship', 0);
        $this->assertDatabaseHas('mood_entries', ['id' => $entry->id]);

        Livewire::test(\App\Livewire\Mood\MoodEditor::class)
            ->call('delete', $entry->id)
            ->assertDispatched('mood-editor-saved');
        $this->assertDatabaseMissing('mood_entries', ['id' => $entry->id]);
    }

    public function test_unlinking_keeps_the_entry_and_its_reflection(): void
    {
        $entry = $this->linkedEntry('Frustración');
        $reflection = new MoodReflection(['mood_entry_id' => $entry->id, 'automatic_thought' => 'Privado']);
        $reflection->user_id = $this->user->id;
        $reflection->save();

        $entry->unlinkRelationship($this->alison);

        $this->assertDatabaseHas('mood_entries', ['id' => $entry->id]);
        $this->assertDatabaseCount('mood_reflections', 1);
        $this->assertDatabaseCount('mood_entry_relationship', 0);
    }

    public function test_deleting_a_relationship_keeps_the_entries_and_reflections(): void
    {
        $entry = $this->linkedEntry('Frustración');
        $reflection = new MoodReflection(['mood_entry_id' => $entry->id, 'automatic_thought' => 'Privado']);
        $reflection->user_id = $this->user->id;
        $reflection->save();

        Livewire::test(RelationshipIndex::class)->call('delete', $this->alison->id);

        $this->assertDatabaseMissing('relationships', ['id' => $this->alison->id]);
        $this->assertDatabaseHas('mood_entries', ['id' => $entry->id]);
        $this->assertDatabaseCount('mood_reflections', 1);
        $this->assertDatabaseCount('mood_entry_relationship', 0);
    }

    public function test_emotional_entries_never_appear_in_the_global_events_view(): void
    {
        $this->linkedEntry('Frustración', ['situation' => 'Discutí con Alison']);

        Livewire::test(RelationshipEvents::class)
            ->set('periodFilter', 'all')
            ->assertDontSee('Frustración')
            ->assertDontSee('Discutí con Alison');

        // Linking never creates a timeline event.
        $this->assertDatabaseCount('relationship_events', 0);
    }

    public function test_emotional_entries_never_appear_on_the_relationship_list_cards(): void
    {
        $this->linkedEntry('Frustración', ['situation' => 'Discutí con Alison']);

        Livewire::test(RelationshipIndex::class)
            ->assertSee('Alison Restrepo')
            ->assertDontSee('Discutí con Alison')
            ->assertDontSee('Frustración');
    }

    public function test_patterns_count_emotions_and_show_the_sample_size(): void
    {
        $this->linkedEntry('Frustración', ['date' => '2026-08-01', 'intensity' => 4]);
        $this->linkedEntry('Frustración', ['date' => '2026-07-28', 'intensity' => 2]);
        $this->linkedEntry('Gratitud', ['date' => '2026-07-20']);

        $summary = RelationshipEmotionalPatterns::summarize($this->alison, 30);

        $this->assertSame(3, $summary['sample_size']);
        $this->assertSame('Frustración', $summary['emotions'][0]['text']);
        $this->assertSame(2, $summary['emotions'][0]['count']);
        $this->assertSame(2, $summary['intensity']['recorded']);
        $this->assertSame(3.0, $summary['intensity']['average']);
        $this->assertSame(1, $summary['intensity']['distribution'][4]);
    }

    public function test_the_period_limits_the_sample(): void
    {
        $this->linkedEntry('Frustración', ['date' => '2026-08-03']);
        $this->linkedEntry('Gratitud', ['date' => '2026-05-01']);

        $this->assertSame(1, RelationshipEmotionalPatterns::summarize($this->alison, 7)['sample_size']);
        $this->assertSame(1, RelationshipEmotionalPatterns::summarize($this->alison, 30)['sample_size']);
        $this->assertSame(2, RelationshipEmotionalPatterns::summarize($this->alison, 365)['sample_size']);
    }

    public function test_the_before_and_after_comparison_reports_its_own_sample_size(): void
    {
        $decreased = $this->linkedEntry('Frustración', ['date' => '2026-08-01', 'intensity' => 5]);
        $unchanged = $this->linkedEntry('Frustración', ['date' => '2026-08-02', 'intensity' => 3]);
        $this->linkedEntry('Gratitud', ['date' => '2026-08-03', 'intensity' => 2]);

        foreach ([[$decreased, 2], [$unchanged, 3]] as [$entry, $after]) {
            $reflection = new MoodReflection(['mood_entry_id' => $entry->id, 'intensity_after' => $after]);
            $reflection->user_id = $this->user->id;
            $reflection->save();
        }

        $shift = RelationshipEmotionalPatterns::summarize($this->alison, 30)['reflection_shift'];

        $this->assertSame(2, $shift['sample_size'], 'Only reflections with both numbers count.');
        $this->assertSame(1, $shift['decreased']);
        $this->assertSame(1, $shift['unchanged']);
        $this->assertSame(0, $shift['increased']);
    }
}

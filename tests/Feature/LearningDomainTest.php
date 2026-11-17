<?php

namespace Tests\Feature;

use App\Models\LearningFormation;
use App\Models\LearningUnit;
use App\Models\User;
use App\Models\UserCategory;
use App\Services\Learning\LearningRecords;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LearningDomainTest extends TestCase
{
    use RefreshDatabase;

    private function formation(User $user, string $type = 'degree'): LearningFormation
    {
        return app(LearningRecords::class)->saveFormation($user->id, null, ['type' => $type, 'title' => 'Mi formación', 'status' => 'in_progress', 'category' => '']);
    }

    private function unit(User $user, LearningFormation $formation, string $kind, ?LearningUnit $parent = null, string $title = 'Contenido'): LearningUnit
    {
        return app(LearningRecords::class)->saveUnit($user->id, $formation->id, null, $parent?->id, $kind, ['title' => $title, 'status' => 'pending']);
    }

    public function test_all_six_types_and_supported_hierarchies_are_persisted_without_generated_content(): void
    {
        $user = User::factory()->create();
        foreach (array_keys(LearningFormation::TYPES) as $type) {
            $formation = $this->formation($user, $type);
            $this->assertNull($formation->category);
            $this->assertSame(0, $formation->units()->count());
            if ($formation->kind === 'academic') {
                $period = $this->unit($user, $formation, 'period');
                $subject = $this->unit($user, $formation, 'subject', $period);
                $this->unit($user, $formation, 'topic', $subject);
                $this->unit($user, $formation, 'subject');
            } elseif ($type === 'course') {
                $module = $this->unit($user, $formation, 'module');
                $this->unit($user, $formation, 'topic', $module);
            } else {
                $stage = $this->unit($user, $formation, 'stage');
                foreach ([null, $stage] as $parent) {
                    $course = $this->unit($user, $formation, 'course', $parent);
                    $module = $this->unit($user, $formation, 'module', $course);
                    $this->unit($user, $formation, 'topic', $module);
                    $this->unit($user, $formation, 'topic', $parent);
                }
            }
        }
        $this->assertDatabaseCount('learning_formations', 6);
    }

    public function test_invalid_hierarchy_type_changes_and_reparenting_are_rejected(): void
    {
        $user = User::factory()->create();
        $formation = $this->formation($user);
        $subject = $this->unit($user, $formation, 'subject');
        $service = app(LearningRecords::class);
        foreach ([
            fn () => $this->unit($user, $formation, 'module'),
            fn () => $this->unit($user, $formation, 'period', $subject),
            fn () => $service->saveFormation($user->id, $formation->id, ['type' => 'course', 'title' => 'Changed', 'status' => 'pending']),
            fn () => $service->saveUnit($user->id, $formation->id, $subject->id, $subject->id, 'subject', ['title' => 'Changed', 'status' => 'pending']),
        ] as $action) {
            try {
                $action();
                $this->fail('Invalid hierarchy accepted');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        $this->assertSame('degree', $formation->fresh()->type);
        $this->assertNull($subject->fresh()->parent_id);
    }

    public function test_fields_dates_credits_and_manual_statuses_are_validated(): void
    {
        $user = User::factory()->create();
        $formation = $this->formation($user);
        $service = app(LearningRecords::class);
        foreach ([['title' => '  '], ['title' => str_repeat('a', 161)], ['status' => 'other'], ['start_date' => '2026-11-17', 'end_date' => '2026-11-16'], ['credits' => 0.3], ['credits' => 101]] as $invalid) {
            try {
                $service->saveUnit($user->id, $formation->id, null, null, 'subject', $invalid + ['title' => 'Math', 'status' => 'pending']);
                $this->fail('Invalid fields accepted');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        $subject = $service->saveUnit($user->id, $formation->id, null, null, 'subject', ['title' => ' Zero ', 'credits' => 0, 'status' => 'completed']);
        $this->assertSame('0.0', $subject->credits);
        $this->assertSame('Zero', $subject->title);
        $this->assertSame('in_progress', $formation->fresh()->status);
        $this->expectException(ValidationException::class);
        $service->saveUnit($user->id, $formation->id, null, null, 'period', ['title' => 'Semester', 'status' => 'pending', 'credits' => 2]);
    }

    public function test_explicit_user_scope_works_without_auth_and_with_another_authenticated_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $formation = $this->formation($owner);
        $unit = $this->unit($owner, $formation, 'subject');
        $otherFormation = $this->formation($other);
        $this->actingAs($other);
        $service = app(LearningRecords::class);
        foreach ([
            fn () => $service->formation($other->id, $formation->id),
            fn () => $service->saveFormation($other->id, $formation->id, ['type' => 'course', 'title' => 'Stolen', 'status' => 'pending']),
            fn () => $service->unit($owner->id, $otherFormation->id, $unit->id),
            fn () => $service->pin($other->id, 'unit', $unit->id, true),
            fn () => $service->delete($other->id, $formation->id),
            fn () => $service->move($other->id, $formation->id, $unit->id, 1),
        ] as $action) {
            try {
                $action();
                $this->fail('Foreign record accepted');
            } catch (ModelNotFoundException $e) {
                $this->assertNotEmpty($e->getModel());
            }
        }
        $this->assertSame($owner->id, $service->formation($owner->id, $formation->id)->user_id);
        $service->pin($owner->id, 'unit', $unit->id, true);
        $this->assertCount(1, $service->shortcuts($owner->id));
        $this->assertCount(0, $service->shortcuts($other->id));
    }

    public function test_only_empty_records_can_be_deleted_and_pins_are_cleaned(): void
    {
        $user = User::factory()->create();
        $formation = $this->formation($user);
        $subject = $this->unit($user, $formation, 'subject');
        $topic = $this->unit($user, $formation, 'topic', $subject);
        $service = app(LearningRecords::class);
        foreach ([null, $subject->id] as $id) {
            try {
                $service->delete($user->id, $formation->id, $id);
                $this->fail('Nonempty record deleted');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('deletion', $e->errors());
            }
        }
        $service->pin($user->id, 'unit', $topic->id, true);
        $service->delete($user->id, $formation->id, $topic->id);
        $this->assertDatabaseCount('learning_shortcuts', 0);
        $service->delete($user->id, $formation->id, $subject->id);
        $service->pin($user->id, 'formation', $formation->id, true);
        $service->delete($user->id, $formation->id);
        $this->assertDatabaseCount('learning_formations', 0);
        $this->assertDatabaseCount('learning_shortcuts', 0);
    }

    public function test_reordering_is_limited_to_siblings_and_does_not_modify_status(): void
    {
        $user = User::factory()->create();
        $formation = $this->formation($user);
        $a = $this->unit($user, $formation, 'subject', title: 'A');
        $b = $this->unit($user, $formation, 'subject', title: 'B');
        $topic = $this->unit($user, $formation, 'topic', $a);
        app(LearningRecords::class)->move($user->id, $formation->id, $b->id, -1);
        $this->assertSame([$b->id, $a->id], $formation->units()->whereNull('parent_id')->orderBy('position')->pluck('id')->all());
        app(LearningRecords::class)->move($user->id, $formation->id, $topic->id, -1);
        $this->assertSame($a->id, $topic->fresh()->parent_id);
        $this->assertSame('pending', $a->fresh()->status);
    }

    public function test_pins_are_idempotent_reinsert_at_end_and_resolve_renamed_origins(): void
    {
        $user = User::factory()->create();
        $formation = $this->formation($user);
        $a = $this->unit($user, $formation, 'subject', title: 'Same');
        $b = $this->unit($user, $formation, 'subject', title: 'Same');
        $service = app(LearningRecords::class);
        foreach ([$a, $a, $b] as $unit) {
            $service->pin($user->id, 'unit', $unit->id, true);
        }
        $this->assertDatabaseCount('learning_shortcuts', 2);
        $service->pin($user->id, 'unit', $a->id, false);
        $service->pin($user->id, 'unit', $a->id, true);
        $formation->update(['title' => 'Renamed']);
        $pins = $service->shortcuts($user->id);
        $this->assertSame([$b->id, $a->id], array_column($pins, 'id'));
        $this->assertSame('Renamed', $pins[0]['origin']);
        $this->assertDatabaseCount('learning_units', 2);
    }

    public function test_categories_are_private_and_cannot_be_deleted_while_used(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $category = UserCategory::create(['user_id' => $user->id, 'domain' => 'learning', 'key' => 'agile', 'name' => 'Agilidad']);
        $formation = app(LearningRecords::class)->saveFormation($user->id, null, ['title' => 'Scrum', 'type' => 'course', 'status' => 'in_progress', 'category' => $category->key]);
        try {
            $category->delete();
            $this->fail('Used category deleted');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
        $this->expectException(ValidationException::class);
        app(LearningRecords::class)->saveFormation($other->id, null, ['title' => 'Foreign', 'type' => 'course', 'status' => 'pending', 'category' => $category->key]);
    }
}

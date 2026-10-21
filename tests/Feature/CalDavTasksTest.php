<?php

namespace Tests\Feature;

use App\Livewire\Settings\SettingsPage;
use App\Models\IntegrationToken;
use App\Models\Task;
use App\Models\User;
use App\Support\DefaultTaskCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CalDavTasksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        User::created(function (User $user) {
            DefaultTaskCategories::createFor($user);
            $user->taskCategories()->create(['key' => 'trabajo', 'name' => 'Trabajo']);
        });
    }

    public function test_user_can_generate_rotate_and_revoke_a_caldav_password(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test(SettingsPage::class)->call('createOrRotateCalDavPassword');
        $password = $component->get('calDavPassword');

        $this->assertStringStartsWith(IntegrationToken::CALDAV_PREFIX, $password);
        $this->assertDatabaseHas('integration_tokens', [
            'user_id' => $user->id,
            'purpose' => 'caldav',
            'token_hash' => hash('sha256', $password),
            'revoked_at' => null,
        ]);

        $component->call('revokeCalDavPassword');
        $this->assertNotNull(IntegrationToken::query()->where('purpose', 'caldav')->firstOrFail()->revoked_at);
    }

    public function test_caldav_discovers_the_task_collection_and_creates_a_vtodo(): void
    {
        $user = User::factory()->create();
        $password = $this->calDavPassword($user);

        $this->call('PROPFIND', '/dav/', server: $this->davHeaders($user, $password, [
            'HTTP_DEPTH' => '1',
        ]))
            ->assertStatus(207)
            ->assertSee('calendars', false);

        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Tests//EN', 'BEGIN:VTODO',
            'UID:external-task-1', 'DTSTAMP:20260801T120000Z',
            'SUMMARY:Creada desde Tasks.org', 'DUE;VALUE=DATE:20260810',
            'CATEGORIES:personal', 'CLASS:PRIVATE', 'X-LIFETRACKER-ESTIMATED-MINUTES:60',
            'END:VTODO', 'END:VCALENDAR', '',
        ]);

        $created = $this->call(
            'PUT',
            '/dav/calendars/'.$user->email.'/tasks/external-task-1.ics',
            server: $this->davHeaders($user, $password, [
                'CONTENT_TYPE' => 'text/calendar; charset=utf-8',
            ]),
            content: $ics,
        );
        $created->assertCreated()->assertHeader('ETag');

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Creada desde Tasks.org',
            'category' => 'personal',
            'is_private' => true,
            'estimated_time' => 60,
            'caldav_uid' => 'external-task-1',
        ]);

        $updatedIcs = str_replace(
            ['SUMMARY:Creada desde Tasks.org', 'DUE;VALUE=DATE:20260810'],
            ['SUMMARY:Actualizada desde Tasks.org', 'DUE;VALUE=DATE:20260811'],
            $ics,
        );

        $this->call(
            'PUT',
            '/dav/calendars/'.$user->email.'/tasks/external-task-1.ics',
            server: $this->davHeaders($user, $password, [
                'CONTENT_TYPE' => 'text/calendar; charset=utf-8',
                'HTTP_IF_MATCH' => $created->headers->get('ETag'),
            ]),
            content: $updatedIcs,
        )
            ->assertNoContent()
            ->assertHeader('ETag');

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Actualizada desde Tasks.org',
            'end_date' => '2026-08-11 00:00:00',
            'caldav_uid' => 'external-task-1',
        ]);
    }

    public function test_caldav_root_can_be_opened_in_a_browser(): void
    {
        $user = User::factory()->create();
        $password = $this->calDavPassword($user);

        $this->get('/dav/')->assertUnauthorized();

        $this->withBasicAuth($user->email, $password)
            ->get('/dav/')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertSee('sabre/dav', false)
            ->assertDontSee('Create new folder', false);
    }

    public function test_caldav_credentials_cannot_read_another_users_tasks(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $password = $this->calDavPassword($other);
        $this->actingAs($owner);
        Task::create(['title' => 'Secreto']);
        auth()->logout();

        $this->call(
            'PROPFIND',
            '/dav/calendars/'.$owner->email.'/tasks/',
            server: $this->davHeaders($other, $password, ['HTTP_DEPTH' => '1']),
        )
            ->assertNotFound();
    }

    private function calDavPassword(User $user): string
    {
        [, $password] = IntegrationToken::issueFor($user, 'CalDAV', 'caldav', IntegrationToken::CALDAV_PREFIX);

        return $password;
    }

    private function davHeaders(User $user, string $password, array $headers = []): array
    {
        return $headers + [
            'HTTP_AUTHORIZATION' => 'Basic '.base64_encode($user->email.':'.$password),
        ];
    }
}

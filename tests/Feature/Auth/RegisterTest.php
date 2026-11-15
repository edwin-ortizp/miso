<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Experience\PersonalExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_renders_shared_refuge_and_password_guidance(): void
    {
        $this->get('/register')->assertOk()->assertSee('Tu próximo pequeño paso')
            ->assertSee('Crea tu cuenta')->assertSee('Usa 8 caracteres o más.')
            ->assertSee('autocomplete="name"', false)->assertSee('autocomplete="new-password"', false)
            ->assertSee('name="_token"', false)->assertSee(route('login'), false);
        $violations = array_filter(\App\Support\Ui\UiConformanceScanner::fromConfig()->scan(),
            fn ($v) => in_array($v->file, ['resources/views/auth/register.blade.php', 'resources/views/components/auth-shell.blade.php'], true));
        $this->assertSame([], array_values($violations));
    }

    public function test_valid_registration_hashes_password_creates_owned_defaults_and_starts_required_guide(): void
    {
        $this->withSession(['marker' => 'keep']);
        $before = session()->getId();
        $this->post('/register', $this->valid())->assertRedirect(route('onboarding'))->assertSessionHasNoErrors();
        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('safe-password', $user->password));
        $this->assertNotSame('safe-password', $user->password);
        $this->assertNotSame($before, session()->getId());
        $this->assertSame(10, $user->drinkTypes()->count());
        $this->assertGreaterThan(0, $user->taskCategories()->count());
        $this->assertTrue(app(PersonalExperience::class)->required($user->id));
        $this->assertDatabaseCount('habit_definitions', 0);
    }

    public function test_invalid_registration_preserves_name_email_but_never_passwords_and_creates_nothing(): void
    {
        $this->from('/register')->post('/register', ['name' => 'Ana', 'email' => 'invalid',
            'password' => 'short', 'password_confirmation' => 'different'])
            ->assertRedirect('/register')->assertSessionHasErrors(['email', 'password', 'password_confirmation'])
            ->assertSessionHasInput('name', 'Ana')->assertSessionHasInput('email', 'invalid')
            ->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('drink_types', 0);
        $this->assertGuest();
    }

    public function test_required_fields_and_lengths_are_enforced_on_direct_requests(): void
    {
        $this->post('/register', [])->assertSessionHasErrors(['name', 'email', 'password', 'password_confirmation']);
        $this->post('/register', array_replace($this->valid(), ['name' => str_repeat('á', 256)]))
            ->assertSessionHasErrors(['name' => 'Tu nombre puede tener hasta 255 caracteres.']);
        $this->post('/register', array_replace($this->valid(), ['email' => str_repeat('a', 245).'@example.test']))
            ->assertSessionHasErrors('email');
        $this->post('/register', array_replace($this->valid(), ['password_confirmation' => 'different-password']))
            ->assertSessionHasErrors(['password_confirmation' => 'Las contraseñas no coinciden. Revisa la confirmación.']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_existing_email_is_rejected_without_changing_the_existing_account(): void
    {
        $owner = User::factory()->create(['email' => 'new@example.test', 'name' => 'Original']);
        $hash = $owner->password;
        $this->from('/register')->post('/register', $this->valid())
            ->assertSessionHasErrors(['email' => 'Este correo ya está registrado. Inicia sesión con tu cuenta.']);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame('Original', $owner->fresh()->name);
        $this->assertSame($hash, $owner->fresh()->password);
        $this->assertGuest();
    }

    public function test_server_errors_render_on_the_matching_fields_after_redirect(): void
    {
        $this->followingRedirects()->from('/register')->post('/register', ['name' => 'Ana', 'email' => 'invalid',
            'password' => 'short', 'password_confirmation' => 'different'])
            ->assertOk()->assertSee('Ingresa un correo electrónico válido.')
            ->assertSee('Usa una contraseña de 8 caracteres o más.')
            ->assertSee('Las contraseñas no coinciden. Revisa la confirmación.')
            ->assertSee('id="reg-pw-conf-error"', false)->assertDontSee('value="short"', false);
    }

    public function test_seed_failure_rolls_back_account_and_defaults(): void
    {
        $this->mock(PersonalExperience::class)->shouldReceive('begin')->once()->andThrow(new \RuntimeException('seed failed'));
        $this->withoutExceptionHandling();
        try {
            $this->post('/register', $this->valid());
            $this->fail('Expected initialization to fail.');
        } catch (\RuntimeException $error) {
            $this->assertSame('seed failed', $error->getMessage());
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('drink_types', 0);
        $this->assertGuest();
    }

    public function test_malformed_field_types_return_errors_without_breaking_the_form(): void
    {
        $this->followingRedirects()->from('/register')->post('/register', [
            'name' => ['invalid'], 'email' => ['invalid'],
            'password' => ['invalid'], 'password_confirmation' => ['invalid'],
        ])->assertOk()->assertSee('Ingresa un nombre válido.')
            ->assertSee('Ingresa un correo electrónico válido.')
            ->assertSee('Ingresa una contraseña válida.')->assertSee('Ingresa una confirmación válida.');
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    private function valid(): array
    {
        return ['name' => 'Ana', 'email' => 'new@example.test', 'password' => 'safe-password', 'password_confirmation' => 'safe-password'];
    }
}

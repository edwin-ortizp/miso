<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_renders_progressive_form_and_native_bridge(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('Tu espacio para')->assertSee('Bienvenido a MiSo')
            ->assertSee('autocomplete="username"', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertSee('MisoNative', false)->assertSee('auth/login.js', false)
            ->assertSee('name="_token"', false)->assertSee(route('register'), false);
    }

    public function test_successful_login_regenerates_session_and_redirects_to_intended_destination(): void
    {
        $user = User::factory()->create(['password' => 'safe-password']);
        $this->withSession(['url.intended' => url('/tasks')]);
        $before = session()->getId();
        $this->post('/login', ['email' => $user->email, 'password' => 'safe-password'])
            ->assertRedirect(url('/tasks'))->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId());
    }

    public function test_remember_me_sets_the_recaller_cookie(): void
    {
        $user = User::factory()->create(['password' => 'safe-password']);
        $this->post('/login', ['email' => $user->email, 'password' => 'safe-password', 'remember' => 'on'])
            ->assertRedirect(route('home'))->assertCookie(Auth::guard()->getRecallerName());
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_and_unknown_account_have_the_same_recoverable_error(): void
    {
        $user = User::factory()->create(['password' => 'safe-password']);
        foreach ([$user->email, 'unknown@example.test'] as $email) {
            $this->from('/login')->post('/login', ['email' => $email, 'password' => 'wrong-password'])
                ->assertRedirect('/login')
                ->assertSessionHasErrors(['credentials' => 'El correo o la contraseña no coinciden. Revisa los datos e inténtalo de nuevo.'])
                ->assertSessionHasInput('email', $email)->assertSessionMissing('_old_input.password');
            $this->assertGuest();

        }
    }

    public function test_required_and_invalid_fields_are_validated_on_the_server(): void
    {
        $this->from('/login')->post('/login', [])
            ->assertSessionHasErrors(['email' => 'Ingresa tu correo electrónico.', 'password' => 'Ingresa tu contraseña.']);
        $this->get('/login')->assertOk()->assertSee('id="login-email-error"', false)->assertSee('id="login-pw-error"', false);
        $this->from('/login')->post('/login', ['email' => 'invalid', 'password' => 'x'])
            ->assertSessionHasErrors(['email' => 'Ingresa un correo electrónico válido.']);
        $this->assertGuest();
    }

    public function test_credential_error_is_accessible_and_password_is_never_rendered(): void
    {
        $this->followingRedirects()->from('/login')->post('/login', [
            'email' => 'unknown@example.test', 'password' => 'never-render-this-password',
        ])
            ->assertOk()->assertSee('data-login-server-error', false)
            ->assertSee('role="alert"', false)->assertSee('El correo o la contraseña no coinciden.')
            ->assertDontSee('never-render-this-password');
    }

    public function test_server_field_errors_render_after_redirect_and_login_has_no_conformance_violations(): void
    {
        $this->followingRedirects()->from('/login')->post('/login', [])
            ->assertOk()->assertSee('Ingresa tu correo electrónico.')->assertSee('Ingresa tu contraseña.');
        $violations = array_filter(\App\Support\Ui\UiConformanceScanner::fromConfig()->scan(),
            fn ($violation) => in_array($violation->file, ['resources/views/auth/login.blade.php', 'resources/views/auth/partials/refuge.blade.php'], true));
        $this->assertSame([], array_values($violations));
    }
}

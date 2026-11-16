<?php

namespace App\Livewire\Social;

use App\Models\User;
use App\Rules\Username;
use App\Services\Social\Friends;
use App\Services\Social\FriendNotices;
use App\Services\Social\SharedStreak;
use App\Services\Social\SocialSettings;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Amigos (CHG-121): mi código, solicitudes y lista de amigos. */
#[Layout('layouts.app')]
class FriendsIndex extends Component
{
    public bool $showAdd = false;
    public bool $showUsername = false;
    #[Url(as: 'codigo', except: '')]
    public string $lookup = '';
    public string $username = '';
    public string $message = '';

    public function mount(SocialSettings $settings): void
    {
        $this->username = (string) auth()->user()->username;
        // La primera vez, sin @usuario, se ofrece elegirlo (se puede cerrar con «Cancelar»).
        if ($this->username === '' && ! $settings->usernamePrompted(auth()->id())) {
            $settings->markUsernamePrompted(auth()->id());
            $this->showUsername = true;
        }
        // Un enlace de invitación abre el diálogo con el código ya escrito.
        $this->showAdd = $this->lookup !== '';
    }

    public function openAdd(): void
    {
        $this->resetValidation();
        $this->lookup = '';
        $this->showAdd = true;
    }

    public function closeAdd(): void
    {
        $this->showAdd = false;
        $this->lookup = '';
        $this->resetValidation();
    }

    public function sendRequest(Friends $friends): void
    {
        $this->resetValidation();
        $result = $friends->request(auth()->user(), $this->lookup, request()->ip());
        $error = match ($result) {
            Friends::INVALID => 'Escribe un código o un @usuario.',
            Friends::LIMIT_REACHED => 'Llegaste al máximo de 100 amigos.',
            Friends::THROTTLED => 'Probaste muchas veces. Espera un minuto e inténtalo de nuevo.',
            default => null,
        };
        if ($error) {
            $this->addError('lookup', $error);

            return;
        }
        // Misma respuesta exista o no la cuenta.
        $this->message = 'Listo. Si esa persona existe, le llegó tu solicitud.';
        $this->closeAdd();
    }

    public function accept(int $id, Friends $friends): void
    {
        $this->message = $friends->accept(auth()->user(), $id) ? 'Solicitud aceptada.' : 'No se pudo aceptar esa solicitud.';
    }

    public function decline(int $id, Friends $friends): void
    {
        $friends->decline(auth()->user(), $id);
        $this->message = 'Solicitud descartada.';
    }

    public function remove(int $id, Friends $friends): void
    {
        $friends->remove(auth()->user(), $id);
        $this->message = 'Amistad eliminada.';
    }

    public function block(int $id, Friends $friends): void
    {
        $friends->block(auth()->user(), $id);
        $this->message = 'Persona bloqueada. No verá nada tuyo.';
    }

    public function unblock(int $id, Friends $friends): void
    {
        $friends->unblock(auth()->user(), $id);
        $this->message = 'Persona desbloqueada.';
    }

    public function openUsername(): void
    {
        $this->resetValidation();
        $this->username = (string) auth()->user()->username;
        $this->showUsername = true;
    }

    public function closeUsername(): void
    {
        $this->showUsername = false;
        $this->resetValidation();
    }

    public function saveUsername(): void
    {
        $this->username = Username::normalize($this->username);
        $this->validate(['username' => ['required', new Username, Rule::unique('users', 'username')->ignore(auth()->id())]], [
            'username.unique' => 'Ese nombre de usuario ya está en uso.',
            'username.required' => 'Escribe un nombre de usuario.',
        ]);
        auth()->user()->update(['username' => $this->username]);
        $this->message = 'Nombre de usuario guardado.';
        $this->closeUsername();
    }

    public function regenerateCode(): void
    {
        auth()->user()->regenerateFriendCode();
        $this->message = 'Generamos un código nuevo. El anterior ya no funciona.';
    }

    public function toggleVisible(SocialSettings $settings): void
    {
        $settings->setVisible(auth()->id(), ! $settings->visible(auth()->id()));
    }

    public function render(Friends $friends, SocialSettings $settings, SharedStreak $shared, FriendNotices $notices)
    {
        /** @var User $me */
        $me = auth()->user();
        $friendList = $friends->friends($me)->map(fn (array $row) => $row + ['streak' => $shared->for($me, $row['id'])]);
        // Hito de pareja alcanzado: aviso en la campana, idempotente por par y hito (CHG-124).
        foreach ($friendList as $row) {
            if ($row['streak'] && ($milestone = $shared->milestoneReached($row['streak']['days']))) {
                $notices->sharedStreak($me, $row['id'], $milestone);
            }
        }

        return view('livewire.social.friends-index', [
            'module' => 'social',
            'code' => $me->ensureFriendCode(),
            'inviteUrl' => route('social.friends', ['codigo' => $me->ensureFriendCode()]),
            'incoming' => $friends->incoming($me),
            'outgoing' => $friends->outgoing($me),
            'friendList' => $friendList,
            'blockedList' => $friends->blocked($me),
            'visible' => $settings->visible($me->id),
        ]);
    }
}

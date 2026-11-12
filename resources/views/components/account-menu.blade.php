<div class="experience-account" x-data="{ accountOpen: false }"
     x-init="$watch('accountOpen', value => { if (!value) $refs.accountTrigger?.focus({ preventScroll: true }) })"
     @keydown.escape.window="if (accountOpen) { accountOpen = false; $refs.accountTrigger?.focus() }">
    <x-ui.action variant="text" class="lt-avatar" x-ref="accountTrigger" data-popover-trigger
            aria-label="Abrir menú de perfil" aria-haspopup="dialog" aria-controls="account-popover"
            ::aria-expanded="accountOpen.toString()" @click="accountOpen = !accountOpen; if(accountOpen) $nextTick(() => document.getElementById('account-popover')?.querySelector('a')?.focus())">
        @if (filled(auth()->user()->avatar_url))<img src="{{ auth()->user()->avatar_url }}" alt="" class="lt-avatar__photo" width="40" height="40">
        @else{{ \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}@endif
    </x-ui.action>
    <x-ui.popover state="accountOpen" title="Tu cuenta" variant="anchored" id="account-popover">
        <a href="{{ route('settings') }}" wire:navigate x-ref="profileLink" class="md-btn-text experience-account__action" @click="accountOpen = false"><i class="bi bi-person" aria-hidden="true"></i> Mi perfil</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-ui.action type="submit" variant="text" class="experience-account__action"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Cerrar sesión</x-ui.action>
        </form>
    </x-ui.popover>
</div>

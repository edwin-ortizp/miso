<div wire:poll.60s.visible="check"
     x-data="{ show: false, timer: null }"
     x-init="$watch('$wire.notice', () => { show = true; clearTimeout(timer); timer = setTimeout(() => show = false, 4500) })"
     class="game-notifier">
    <template x-if="show">
        <div class="game-notifier__wrap" wire:key="ball-notice-{{ $notice }}">
            <x-game.ball size="md" class="game-notifier__ball" />
            <x-ui.snackbar class="game-notifier__snackbar">
                ¡Ganaste una ball!
                <x-slot:actions>
                    <a href="{{ route('game.balls') }}" wire:navigate class="md-btn-text" x-on:click="show = false">Abrir</a>
                </x-slot:actions>
            </x-ui.snackbar>
        </div>
    </template>
</div>

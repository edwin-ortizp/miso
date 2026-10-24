@php
    $canViewLogs = \Illuminate\Support\Facades\Gate::allows('viewLogViewer')
        && \Illuminate\Support\Facades\Route::has('log-viewer.index');

    $groups = [
        null => [['profile', 'Perfil público', 'bi-person'], ['body', 'Datos físicos', 'bi-rulers']],
        'Acceso' => [['security', 'Contraseña y seguridad', 'bi-shield-lock'], ['oauth', 'Aplicaciones OAuth', 'bi-key']],
        'Integraciones' => [['integrations', 'Calendario y notas', 'bi-plug'], ['notifications', 'Notificaciones', 'bi-bell'], ['mcp', 'Servidor MCP', 'bi-robot']],
        'Sistema' => array_values(array_filter([
            $canViewLogs ? ['logs', 'Logs de la aplicación', 'bi-journal-text'] : null,
            ['about', 'Acerca de', 'bi-info-circle'],
        ])),
    ];
    $user = auth()->user();
    $initials = collect(explode(' ', $user->full_name ?? $user->name ?? ''))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
@endphp

<aside class="settings-aside">
    <div class="settings-aside__who">
        <span class="settings-aside__avatar" aria-hidden="true">{{ mb_strtoupper($initials) }}</span>
        <div>
            <strong>{{ $user->full_name ?? $user->name }}</strong>
            <span>Tu cuenta personal</span>
        </div>
    </div>
    <nav class="settings-nav" aria-label="Secciones de ajustes"
         x-data x-init="$el.querySelector('[aria-current]')?.scrollIntoView({ block: 'nearest', inline: 'center' })">
        @foreach ($groups as $label => $items)
            <div class="settings-nav__group">
                @if ($label)<span class="settings-nav__label">{{ $label }}</span>@endif
                @foreach ($items as [$key, $itemLabel, $icon])
                    <a href="{{ $key === 'profile' ? route('settings') : route('settings.section', $key) }}" wire:navigate
                       @class(['settings-nav__link', 'is-active' => $section === $key])
                       @if ($section === $key) aria-current="page" @endif>
                        <i class="bi {{ $icon }}" aria-hidden="true"></i><span>{{ $itemLabel }}</span>
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>
</aside>

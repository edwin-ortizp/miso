<meta name="miso-android" content="{{ json_encode(['account' => auth()->check() ? hash_hmac('sha256', (string) auth()->id(), config('app.key')) : null, 'status' => route('android.status'), 'register' => route('android.register'), 'revoke' => route('android.revoke'), 'notice' => url('/android/notifications')]) }}">
@if (app()->environment('local') && is_file(public_path('hot')))
    @vite('resources/js/app.js')
@else
    @php($bundleUrl = asset('js/app.js').'?v='.filemtime(public_path('js/app.js')))
    {{-- Livewire vuelve a ejecutar este script del head si cambia su atributo de versión. --}}
    <script data-bundle="{{ $bundleUrl }}">
        if (window.Livewire) window.location.reload();
    </script>
    @unless (request()->hasHeader('X-Livewire-Navigate'))
        <script type="module" src="{{ $bundleUrl }}"></script>
    @endunless
@endif

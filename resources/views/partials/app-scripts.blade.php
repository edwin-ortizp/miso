<meta name="miso-android" content="{{ json_encode(['account' => auth()->check() ? hash_hmac('sha256', (string) auth()->id(), config('app.key')) : null, 'status' => route('android.status'), 'register' => route('android.register'), 'revoke' => route('android.revoke'), 'notice' => url('/android/notifications')]) }}">
@if (app()->environment('local') && is_file(public_path('hot')))
    @vite('resources/js/app.js')
@else
    <script type="module" src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
@endif

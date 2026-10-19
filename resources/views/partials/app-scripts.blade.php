{{-- Load before Livewire starts so editors added later in dialogs also initialize. --}}
<script src="{{ asset('vendor/laravel-markdown/editor.js') }}?v=0.2.0"></script>
@if (app()->environment('local') && is_file(public_path('hot')))
    @vite('resources/js/app.js')
@else
    <script type="module" src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
@endif

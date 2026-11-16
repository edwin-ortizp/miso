{{-- Fija el tema antes del primer pintado para evitar el destello claro.
     `data-theme` queda siempre resuelto a `light` o `dark`; la preferencia
     (`system`, `light`, `dark`) vive en `data-theme-preference`. --}}
<script>
    (function () {
        var pref = @json(auth()->check() ? auth()->user()->themePreference() : 'system');
        var dark = pref === 'dark' || (pref === 'system' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
        var root = document.documentElement;
        root.dataset.themePreference = pref;
        root.dataset.theme = dark ? 'dark' : 'light';
    })();
</script>

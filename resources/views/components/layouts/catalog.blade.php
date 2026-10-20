<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('icons/icon-96x96.png') }}">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Catálogo del sistema de diseño' }}</title>
    @include('partials.app-styles')
    @include('partials.app-scripts')
</head>
<body class="md-catalog">
    <a href="#catalogo-contenido" class="md-catalog__skip">Ir al contenido</a>

    <div class="md-catalog__shell">
        <nav class="md-catalog__nav" aria-label="Componentes del sistema">
            <p class="md-catalog__brand md-title-medium">
                <a href="{{ route('ui.catalog') }}">Sistema de diseño</a>
            </p>

            @foreach ($layers as $layer => $entries)
                <p class="md-catalog__nav-title md-label-medium">{{ \App\Support\Ui\ComponentCatalog::LAYERS[$layer] }}</p>
                <ul class="md-catalog__nav-list">
                    @foreach ($entries as $name => $entry)
                        <li>
                            <a href="{{ route('ui.catalog.show', $name) }}"
                               class="md-catalog__nav-link @if (($current ?? null) === $name) active @endif"
                               @if (($current ?? null) === $name) aria-current="page" @endif>{{ $entry['title'] }}</a>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </nav>

        <main id="catalogo-contenido" class="md-catalog__main">
            {{ $slot }}
        </main>
    </div>
</body>
</html>

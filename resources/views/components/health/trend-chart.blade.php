@props([
    // Cada serie: ['name', 'key' (value|value2), 'dashed' (bool), 'square' (bool)].
    'series',
    // Puntos: ['date' => Carbon, 'offset' => int, 'value', 'value2', 'out_of_range' => bool].
    'points',
    'days' => 30,
    // Banda de referencia [min, max] (eje vertical) o null.
    'band' => null,
    'bandLabel' => null,
    // Línea de meta (mismo eje) o null.
    'goal' => null,
    'goalLabel' => null,
    'unit' => '',
    'label',
    'decimals' => 0,
])

@php
    // SVG del servidor: sin estilos en línea ni colores fijos (todo hereda `currentColor`), dos formas por punto
    // (círculo en rango, rombo fuera de rango) y trazo continuo o discontinuo por serie, para no depender del color.
    $w = 320; $h = 150; $left = 38; $right = count($series) > 1 ? 70 : 14; $top = 10; $bottom = 24;
    $plotW = $w - $left - $right; $plotH = $h - $top - $bottom;

    $values = [];
    foreach ($points as $p) {
        foreach ($series as $s) {
            if (($p[$s['key']] ?? null) !== null) { $values[] = (float) $p[$s['key']]; }
        }
    }
    if ($band) { array_push($values, (float) $band[0], (float) $band[1]); }
    if ($goal !== null) { $values[] = (float) $goal; }
    $min = $values ? min($values) : 0; $max = $values ? max($values) : 1;
    $pad = max(($max - $min) * 0.1, 0.5);
    $min -= $pad; $max += $pad;
    $span = max($max - $min, 0.0001);
    $x = fn (int $offset) => $left + ($days > 1 ? $offset / ($days - 1) : 0.5) * $plotW;
    $y = fn (float $v) => $top + (1 - ($v - $min) / $span) * $plotH;
    $fmt = fn ($n) => str_replace('.', ',', number_format((float) $n, $decimals, '.', ''));
    $summaryText = $label.': '.count($points).' '.(count($points) === 1 ? 'día con datos' : 'días con datos');
    $first = $points[0]['date'] ?? null; $last = $points[count($points) - 1]['date'] ?? null;
@endphp

<figure {{ $attributes->class(['md-trend']) }}>
    <svg viewBox="0 0 {{ $w }} {{ $h }}" width="100%" role="img" aria-label="{{ $summaryText }}" focusable="false">
        {{-- Banda de referencia: tenue, con etiqueta. --}}
        @if ($band)
            <rect x="{{ $left }}" y="{{ $y((float) $band[1]) }}" width="{{ $plotW }}" height="{{ max($y((float) $band[0]) - $y((float) $band[1]), 1) }}" fill="currentColor" fill-opacity="0.1" />
            @if ($bandLabel)
                <text x="{{ $left + 4 }}" y="{{ $y((float) $band[1]) + 10 }}" font-size="9" fill="currentColor" fill-opacity="0.7">{{ $bandLabel }}</text>
            @endif
        @endif
        {{-- Línea de meta. --}}
        @if ($goal !== null)
            <line x1="{{ $left }}" x2="{{ $left + $plotW }}" y1="{{ $y((float) $goal) }}" y2="{{ $y((float) $goal) }}" stroke="currentColor" stroke-opacity="0.5" stroke-width="1" stroke-dasharray="2 3" />
            @if ($goalLabel)
                <text x="{{ $left + $plotW - 2 }}" y="{{ $y((float) $goal) - 3 }}" font-size="9" text-anchor="end" fill="currentColor" fill-opacity="0.7">{{ $goalLabel }}</text>
            @endif
        @endif
        {{-- Ejes recesivos: mínimo y máximo del eje y, y primera y última fecha. --}}
        <line x1="{{ $left }}" x2="{{ $left + $plotW }}" y1="{{ $top + $plotH }}" y2="{{ $top + $plotH }}" stroke="currentColor" stroke-opacity="0.25" />
        <text x="{{ $left - 4 }}" y="{{ $top + 8 }}" font-size="9" text-anchor="end" fill="currentColor" fill-opacity="0.7">{{ $fmt($max) }}</text>
        <text x="{{ $left - 4 }}" y="{{ $top + $plotH }}" font-size="9" text-anchor="end" fill="currentColor" fill-opacity="0.7">{{ $fmt($min) }}</text>
        @if ($first)
            <text x="{{ $left }}" y="{{ $h - 6 }}" font-size="9" fill="currentColor" fill-opacity="0.7">{{ $first->translatedFormat('j M') }}</text>
            @if ($last && $last->ne($first))
                <text x="{{ $left + $plotW }}" y="{{ $h - 6 }}" font-size="9" text-anchor="end" fill="currentColor" fill-opacity="0.7">{{ $last->translatedFormat('j M') }}</text>
            @endif
        @endif

        @foreach ($series as $s)
            @php
                $line = collect($points)->filter(fn ($p) => ($p[$s['key']] ?? null) !== null)->values();
                $path = $line->map(fn ($p) => round($x($p['offset']), 1).','.round($y((float) $p[$s['key']]), 1))->implode(' ');
            @endphp
            @if ($line->count() > 1)
                <polyline points="{{ $path }}" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"
                          @if ($s['dashed'] ?? false) stroke-dasharray="4 3" @endif />
            @endif
            @foreach ($line as $p)
                @php
                    $cx = round($x($p['offset']), 1); $cy = round($y((float) $p[$s['key']]), 1);
                    $tip = $p['date']->translatedFormat('j M Y').': '.$s['name'].' '.$fmt($p[$s['key']]).' '.$unit.($p['out_of_range'] ? ' · fuera del rango de referencia' : '');
                @endphp
                @if ($p['out_of_range'] && $s['key'] === 'value')
                    <polygon points="{{ $cx }},{{ $cy - 5 }} {{ $cx + 5 }},{{ $cy }} {{ $cx }},{{ $cy + 5 }} {{ $cx - 5 }},{{ $cy }}" fill="currentColor"><title>{{ $tip }}</title></polygon>
                @elseif ($s['square'] ?? false)
                    <rect x="{{ $cx - 3.5 }}" y="{{ $cy - 3.5 }}" width="7" height="7" fill="currentColor"><title>{{ $tip }}</title></rect>
                @else
                    <circle cx="{{ $cx }}" cy="{{ $cy }}" r="3.5" fill="currentColor"><title>{{ $tip }}</title></circle>
                @endif
            @endforeach
            {{-- Etiqueta directa al final de la línea cuando hay más de una serie. --}}
            @if (count($series) > 1 && $line->isNotEmpty())
                @php $end = $line->last(); @endphp
                <text x="{{ round($x($end['offset']), 1) + 8 }}" y="{{ round($y((float) $end[$s['key']]), 1) + 3 }}" font-size="9" fill="currentColor">{{ $s['name'] }}</text>
            @endif
        @endforeach
    </svg>

    <figcaption class="md-body-small">
        @if (count($series) > 1)
            Leyenda:
            @foreach ($series as $s)
                {{ $s['name'] }} ({{ ($s['dashed'] ?? false) ? 'línea discontinua, cuadrados' : 'línea continua, círculos' }})@if (! $loop->last), @endif
            @endforeach
            ·
        @endif
        ◆ fuera del rango de referencia general
    </figcaption>

    <details>
        <summary class="md-body-small">Ver datos</summary>
        <table class="md-table md-table--stack">
            <thead>
                <tr>
                    <th scope="col">Día</th>
                    @foreach ($series as $s) <th scope="col">{{ $s['name'] }} ({{ $unit }})</th> @endforeach
                    <th scope="col">Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach (collect($points)->reverse()->take(60) as $p)
                    <tr>
                        <td class="md-table__date">{{ $p['date']->translatedFormat('j M Y') }}</td>
                        @foreach ($series as $s) <td>{{ ($p[$s['key']] ?? null) !== null ? $fmt($p[$s['key']]) : '—' }}</td> @endforeach
                        <td>{{ $p['out_of_range'] ? 'Fuera del rango de referencia general' : 'En rango' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </details>
</figure>

{{-- Avatar de un amigo (CHG-121): foto si la hay, iniciales si no. Solo decorativo; el nombre va al lado. --}}
@props(['name', 'url' => null])
@php($initials = \Illuminate\Support\Str::of($name)->explode(' ')->filter()->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->take(2)->implode(''))
@if ($url && str_starts_with($url, 'https://'))
    <img {{ $attributes->class(['md-relationship-profile__avatar']) }} src="{{ $url }}" alt="" width="40" height="40" loading="lazy" style="object-fit: cover">
@else
    <span {{ $attributes->class(['md-relationship-profile__avatar']) }} aria-hidden="true">{{ $initials }}</span>
@endif

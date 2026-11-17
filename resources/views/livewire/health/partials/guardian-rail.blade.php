<x-context-widget :title="$guardianContext['title']" :icon="config('modules.health.icon')" data-health-context="{{ $guardianContext['section'] }}">
    <livewire:game.guardian-card module="health" variant="rail" :section-context="$guardianContext" />
    @forelse ($guardianContext['facts'] as $fact)
        <p class="md-body-medium">{{ $fact }}</p>
    @empty
        <p class="md-body-medium">{{ $guardianContext['empty'] }}</p>
    @endforelse
</x-context-widget>

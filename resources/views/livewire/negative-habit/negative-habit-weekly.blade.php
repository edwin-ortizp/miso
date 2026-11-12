<x-module-shell module="negative-habits">
    <x-slot:controls>
        <x-date-navigator previous="previousWeek" next="nextWeek" today="thisWeek" previous-label="Semana anterior" next-label="Semana siguiente" today-label="Esta semana"
                          nav-label="Navegación por semana" :label="$weekStart->format('d M').' – '.$weekEnd->format('d M')" />
    </x-slot:controls>

    <livewire:game.guardian-card module="negative-habits" variant="top" />

    {{-- Weekly Summary --}}
    <x-ui.metric-grid label="Resumen de la semana" class="mb-3">
        <x-ui.metric label="Incidencias esta semana" :value="$weeklyCount" :tone="$weeklyCount > 0 ? 'danger' : 'success'" icon="bi-shield-exclamation" />
    </x-ui.metric-grid>

    {{-- Habits grouped by category --}}
    @php $grouped = $habits->getCollection()->groupBy('category'); @endphp

    <nav aria-label="Ajustes de categorías"><x-ui.action variant="text" icon="bi-diagram-3" :href="route('categories.settings', 'negative-habits')">Gestionar categorías</x-ui.action></nav>
    <x-ui.management-card id="negative-habits" title="Hábitos a evitar" icon="bi-shield-check" :count="'('.$habits->total().')'" :paginator="$habits" noun="hábitos">
    @foreach ($grouped as $category => $categoryHabits)
            <p class="md-mcard__section-label text-capitalize">{{ $categoryOptions[$category] ?? $category }}</p>
            @foreach ($categoryHabits as $habit)
                @php
                    $habitLogs = $logs->get($habit->id, collect());
                    $count = $habitLogs->count();
                @endphp
                <div class="md-list-item">
                    <div class="d-flex align-items-center gap-2 flex-grow-1">
                        <span class="fs-5" aria-hidden="true">{{ $habit->icon }}</span>
                        <div class="md-list-item-content">
                            <div class="md-list-item-headline">{{ $habit->name }}</div>
                            <x-ui.select :id="'habit-category-'.$habit->id" :label="'Categoría de '.$habit->name" :options="$categoryOptions" :selected="$habit->category" wire:change="assignCategory({{ $habit->id }}, $event.target.value)" />
                        </div>
                        @if ($count > 0)
                            <x-ui.badge tone="danger" :label="$count.' '.($count === 1 ? 'incidencia' : 'incidencias').' esta semana'">{{ $count }}</x-ui.badge>
                        @endif
                    </div>
                    <div class="md-list-item-trailing">
                        <x-ui.icon-action icon="bi-plus-lg" tone="danger" :label="'Registrar incidencia de '.$habit->name" wire:click="openLogForm({{ $habit->id }})" />
                    </div>
                </div>
            @endforeach
    @endforeach
    </x-ui.management-card>

    {{-- Log Dialog --}}
    @if ($showLogForm)
        <x-ui.form-dialog :open="$showLogForm" close="closeLogForm" title="Registrar incidencia" icon="bi-shield-exclamation"
                          submit="Registrar" submit-action="saveLog" id="negative-habit-log-form" wire:key="negative-habit-log-{{ $selectedHabitId }}">
            <x-ui.field name="note" label="Nota (opcional)" wire:model="note" />
        </x-ui.form-dialog>
    @endif
    {{-- CHG-063: panel lateral con el guardián (solo si hay guardián; si no, el módulo se ve como antes). --}}
    @if (\App\Livewire\Game\GuardianCard::visibleFor((int) auth()->id(), 'negative-habits'))
        <x-slot:rail>
            <x-context-widget title="Tu guardián" icon="bi-stars">
                <livewire:game.guardian-card module="negative-habits" variant="rail" />
            </x-context-widget>
        </x-slot:rail>
    @endif
</x-module-shell>

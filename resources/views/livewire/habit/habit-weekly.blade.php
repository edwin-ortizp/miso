<x-module-shell module="habits">
    <x-slot:controls>
        <x-date-navigator previous="previousWeek" next="nextWeek" today="thisWeek" previous-label="Semana anterior" next-label="Semana siguiente" today-label="Esta semana"
                          nav-label="Navegación por semana" :label="$weekStart->format('d M').' – '.$weekStart->copy()->endOfWeek()->format('d M')" />
    </x-slot:controls>

    <section class="habit-weekly-hero mb-3" aria-label="Resumen de progreso semanal">
        <div class="habit-weekly-hero__lead">
            <div class="habit-weekly-hero__eyebrow"><i class="bi bi-lightning-charge-fill"></i> PUNTOS DE LA SEMANA</div>
            @if ($completionPercentage === null)
                <div class="habit-weekly-hero__value">Aún no empieza</div>
                <p>Esta semana todavía no tiene días para evaluar.</p>
            @else
                <div class="habit-weekly-hero__value">{{ $completedCount }} <span>pts</span></div>
                <p>{{ $completedCount }}/{{ $possibleCount }} hábitos cumplidos en {{ $trackedDayCount }} día{{ $trackedDayCount === 1 ? '' : 's' }}.</p>
            @endif
        </div>

        <div class="habit-weekly-hero__progress">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-2">
                <span class="md-label-large">Meta semanal</span>
                <span class="habit-weekly-goal-number">{{ $weeklyGoal }}%</span>
            </div>
            @if ($completionPercentage === null)
                <span class="md-body-small">Se evaluará cuando comience la semana.</span>
            @else
                <div class="md-progress-linear md-progress-linear--success">
                    <div class="md-progress-linear-bar" style="width: {{ min($completionPercentage, 100) }}%"></div>
                </div>
                <div class="d-flex justify-content-between mt-2 md-body-small">
                    <span>{{ $completionPercentage }}% conseguido</span>
                    <span>
                        @if ($weekState === 'achieved')
                            <i class="bi bi-trophy-fill"></i> ¡Meta alcanzada!
                        @else
                            {{ max($weeklyGoal - $completionPercentage, 0) }}% para lograrla
                        @endif
                    </span>
                </div>
            @endif
        </div>

        <div class="habit-weekly-hero__streak">
            <i class="bi bi-fire"></i>
            <div>
                <div class="md-label-large">Mejor racha activa</div>
                <div class="habit-weekly-streak-value">{{ $bestStreak }} <span>días</span></div>
            </div>
        </div>
    </section>

    <section class="habit-insights mb-3" aria-labelledby="habit-insights-title">
        <div class="habit-insights__heading">
            <div>
                <div class="habit-insights__eyebrow"><i class="bi bi-stars" aria-hidden="true"></i> LECTURA LOCAL</div>
                <h2 id="habit-insights-title" class="md-title-large mb-1">Lo que cuenta tu semana</h2>
                <p class="md-body-small mb-0">Patrones calculados con tus registros, sin enviar información fuera de la aplicación.</p>
            </div>
            <span class="md-chip-tonal"><i class="bi bi-shield-check" aria-hidden="true"></i> Privado</span>
        </div>
        <div class="habit-insights__grid">
            @foreach ($insights as $insight)
                @php
                    $insightIcon = match ($insight['kind']) {
                        'consistent' => 'bi-gem',
                        'period' => 'bi-sunrise',
                        'focus' => 'bi-compass',
                        'future' => 'bi-calendar-event',
                        default => 'bi-flower1',
                    };
                @endphp
                <article class="habit-insight-card habit-insight-card--{{ $insight['tone'] }}">
                    <div class="habit-insight-card__icon" aria-hidden="true"><i class="bi {{ $insightIcon }}"></i></div>
                    <div class="habit-insight-card__label">{{ $insight['title'] }}</div>
                    <div class="habit-insight-card__value">{{ $insight['value'] }}</div>
                    <p>{{ $insight['message'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="md-card-outlined habit-weekly-goal mb-3">
        <div>
            <div class="md-title-small">Tu objetivo recurrente</div>
            <div class="md-body-small" style="color: var(--md-sys-color-on-surface-variant);">Se aplicará automáticamente a cada nueva semana.</div>
        </div>
        <form wire:submit="saveWeeklyGoal" class="d-flex align-items-center gap-2">
            <label for="weekly-goal" class="visually-hidden">Meta semanal en porcentaje</label>
            <input id="weekly-goal" wire:model="weeklyGoal" type="number" min="1" max="100" class="habit-weekly-goal-input" aria-describedby="weekly-goal-suffix">
            <span id="weekly-goal-suffix" class="md-label-large">%</span>
            <button type="submit" class="md-btn-text" style="min-height: 36px;">Guardar</button>
        </form>
    </section>

    <section class="md-card-elevated habit-weekly-table-card" style="padding: 0; overflow: hidden;">
        <div class="habit-weekly-table-heading">
            <div>
                <h2 class="md-title-medium mb-1">Mapa de constancia</h2>
                <p class="md-body-small mb-0" style="color: var(--md-sys-color-on-surface-variant);">Cada marca representa un hábito que ya cumpliste.</p>
            </div>
            <span class="md-chip-tonal"><i class="bi bi-eye"></i> Solo consulta</span>
        </div>
        <div class="habit-weekly-table-scroll">
            <table class="habit-weekly-table">
                <thead>
                    <tr>
                        <th scope="col" class="habit-weekly-table__habit">Hábito</th>
                        @foreach ($weekDates as $date)
                            <th scope="col" class="text-center {{ $date->isToday() ? 'is-today' : '' }} {{ $date->gt(now()) ? 'is-future' : '' }}">
                                <span class="d-block md-label-small text-uppercase">{{ $date->translatedFormat('D') }}</span>
                                <strong>{{ $date->format('d') }}</strong>
                            </th>
                        @endforeach
                        <th scope="col" class="text-center">Total</th>
                        <th scope="col" class="text-center">Racha</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($habitRows as $habit)
                        <tr wire:key="weekly-habit-{{ $habit['id'] }}">
                            <th scope="row" class="habit-weekly-table__habit">
                                <span class="habit-weekly-habit-icon">{{ $habit['icon'] }}</span>
                                <span class="text-truncate">{{ $habit['name'] }}</span>
                            </th>
                            @foreach ($habit['days'] as $day)
                                <td class="text-center {{ !$day['tracked'] ? 'is-future' : '' }}">
                                    @if ($day['completed'])
                                        <span class="habit-weekly-check" title="Cumplido"><i class="bi bi-check-lg"></i></span>
                                    @elseif ($day['tracked'])
                                        <span class="habit-weekly-miss" title="Sin completar">—</span>
                                    @else
                                        <span class="habit-weekly-pending" title="Día futuro"></span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-center">
                                <strong>{{ $habit['completed_count'] }}/{{ $trackedDayCount }}</strong>
                                @if ($habit['percentage'] !== null)
                                    <span class="d-block md-label-small" style="color: var(--md-sys-color-on-surface-variant);">{{ $habit['percentage'] }}%</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="habit-weekly-row-streak"><i class="bi bi-fire"></i> {{ $habit['streak'] }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center py-5" style="color: var(--md-sys-color-on-surface-variant);">Aún no tienes hábitos para mostrar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-module-shell>

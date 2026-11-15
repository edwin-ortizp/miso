<div class="experience-guide" data-module="home"
     x-data @onboarding-step-changed.window="$nextTick(() => $refs.stepTitle?.focus())">
    @php
        $stops = [1 => 'Tu día a día', 2 => 'Con qué empezar', 3 => 'Un hábito', 4 => 'A empezar'];

    @endphp
    <nav class="experience-route" aria-label="Tu recorrido de bienvenida">
        <ol>
            @foreach ($stops as $number => $label)
                <li @class(['is-current' => $number === $step, 'is-done' => $number < $step]) @if($number === $step) aria-current="step" @endif>
                    <span class="experience-route__dot" aria-hidden="true">@if($number < $step)<i class="bi bi-check-lg"></i>@else{{ $number }}@endif</span>
                    <span>{{ $label }}</span>
                    <span class="visually-hidden">{{ $number < $step ? 'Paso anterior' : ($number === $step ? 'Paso actual' : 'Próximo paso') }}</span>
                </li>
            @endforeach
        </ol>
    </nav>
    <div class="experience-adventure">
        <aside class="experience-world game-stage--cielo" aria-label="Tu compañero">
            <div class="experience-world__intro">
                <span class="experience-eyebrow"><i class="bi bi-stars" aria-hidden="true"></i> AQUÍ EMPIEZA NUESTRO EQUIPO</span>
                <p class="experience-world__title">Tu vida.<br> Tu ritmo.<br><em>Tu aventura.</em></p>
                <p class="experience-world__lead">Lo que quieres lograr empieza con algo pequeño, y muy tuyo.</p>
            </div>
            <div class="experience-landscape" aria-hidden="true">
                <span class="experience-landscape__sun"></span>
                <span class="experience-landscape__peak"></span>
                <span class="experience-landscape__hill experience-landscape__hill--far"></span>
                <span class="experience-landscape__hill experience-landscape__hill--near"></span>
                <span class="experience-landscape__path"></span>
                <span class="experience-landscape__spark experience-landscape__spark--one">✦</span>
                <span class="experience-landscape__spark experience-landscape__spark--two">✧</span>
                <div class="experience-guide-ball" data-module="game"><x-game.ball size="lg" /></div>
                <span class="experience-landscape__sign"><i class="bi bi-signpost-split"></i></span>
            </div>
            <div class="experience-companion">
                <span class="experience-companion__label"><i class="bi bi-chat-heart" aria-hidden="true"></i> De tu lado, desde el primer paso</span>
                <p>{{ [1 => '¿Empezamos? Yo ya me emocioné y todavía no hacemos nada.', 2 => 'No hace falta tener un gran plan. Podemos empezar con una idea pequeña.', 3 => 'Un hábito pequeño también es un gran comienzo. Vamos a darle sitio.', 4 => '¡Tenemos un comienzo! Yo ya estoy buscando dónde poner la palomita.'][$step] }}</p>
            </div>
        </aside>
        <section class="experience-workspace" wire:key="onboarding-step-{{ $step }}">
            <header class="experience-step-head">
                <span class="experience-step-count">PASO {{ $step }} DE 4 <span aria-hidden="true">/</span> {{ $stops[$step] }}</span>
                <h1 tabindex="-1" x-ref="stepTitle">{{ [1 => '¿Cómo es tu día a día?', 2 => '¿Con qué te gustaría empezar?', 3 => 'Un hábito pequeño', 4 => 'Ya tienes por dónde empezar'][$step] }}</h1>
                <p>{{ [1 => 'Puedes elegir varias opciones. Nos ayudarán a ordenar las ideas para ti.', 2 => 'Elige una opción. Después puedes explorar las demás.', 3 => 'Elige algo que puedas repetir cada día.', 4 => 'No hace falta configurarlo todo para empezar.'][$step] }}</p>
            </header>
            @if ($step === 1)
                <form wire:submit="next" class="experience-fields">
                    <fieldset class="experience-contexts">
                        <legend>Lo que forma parte de tu día</legend>
                        <div class="experience-context-grid">
                            @foreach ($contextChoices as $key => $choice)
                                <label class="experience-context">
                                    <input type="checkbox" wire:model.live="contexts" value="{{ $key }}" aria-label="{{ $choice['label'] }}" aria-describedby="context-hint-{{ $key }}">
                                    <span class="experience-context__icon" aria-hidden="true"><i class="bi {{ $choice['icon'] }}"></i></span>
                                    <span class="experience-context__body"><strong>{{ $choice['label'] }}</strong><small id="context-hint-{{ $key }}">{{ $choice['hint'] }}</small></span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    @error('contexts')<p class="experience-error" role="alert">{{ $message }}</p>@enderror
                    @error('contexts.*')<p class="experience-error" role="alert">{{ $message }}</p>@enderror
                    <div class="experience-step-actions"><x-ui.action type="submit" trailing-icon="bi-arrow-right" wire:loading.attr="disabled">Continuar</x-ui.action></div>
                </form>
            @elseif ($step === 2)
                <form wire:submit="next" class="experience-fields">
                    <fieldset class="experience-contexts">
                        <legend>Con qué te gustaría empezar</legend>
                        <div class="experience-need-grid">
                            @foreach ($needChoices as $key => $choice)
                                <label class="experience-context">
                                    <input type="radio" name="need" wire:model="need" value="{{ $key }}" aria-label="{{ $choice['label'] }}" aria-describedby="need-hint-{{ $key }}">
                                    <span class="experience-context__icon" aria-hidden="true"><i class="bi {{ $choice['icon'] }}"></i></span>
                                    <span class="experience-context__body"><strong>{{ $choice['label'] }}</strong><small id="need-hint-{{ $key }}">{{ $choice['hint'] }}</small></span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    @error('need')<p class="experience-error" role="alert">{{ $message }}</p>@enderror
                    <div class="experience-step-actions"><x-ui.action type="submit" trailing-icon="bi-arrow-right" wire:loading.attr="disabled">Continuar</x-ui.action></div>
                </form>
            @elseif ($step === 3)
                @if ($habit)
                    <article class="experience-habit-preview" data-module="habits" aria-label="Tu hábito preparado">
                        <span class="experience-habit-preview__icon" aria-hidden="true">{{ $habit->icon ?: '🌱' }}</span>
                        <div><span class="experience-eyebrow">TU HÁBITO PREPARADO</span><h2>{{ $habit->name }}</h2><p>Todos los días · {{ \App\Livewire\Habit\HabitSettings::TIMES_OF_DAY[$habit->time_of_day] ?? 'En cualquier momento' }}</p></div>
                    </article>
                    <p class="experience-note" role="status">Guardado. Lo marcarás cuando lo hayas hecho.</p>
                    <x-ui.action variant="text" wire:click="$dispatch('onboarding-habit')">Editar mi hábito</x-ui.action>
                @endif
                <fieldset class="experience-contexts experience-ideas">
                    <legend>{{ $habit ? 'Si prefieres preparar otro hábito' : 'Una idea para empezar' }}</legend>
                    <div class="experience-need-grid">
                        @foreach ($suggestions as $key => $idea)
                            <label class="experience-context" wire:key="idea-{{ $key }}">
                                <input type="radio" name="suggestion" wire:model="suggestion" value="{{ $key }}" aria-label="{{ $idea['name'] }}" aria-describedby="idea-hint-{{ $key }}">
                                <span class="experience-context__icon" aria-hidden="true">{{ $idea['icon'] }}</span>
                                <span class="experience-context__body"><strong>{{ $idea['name'] }}</strong><small id="idea-hint-{{ $key }}">{{ $idea['hint'] }}</small></span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                @if (! $moreIdeas)<x-ui.action variant="text" wire:click="$set('moreIdeas', true)">Ver más ideas</x-ui.action>@endif
                @error('suggestion')<p class="experience-error" role="alert">{{ $message }}</p>@enderror
                @if ($existingHabits)
                    <form wire:submit="useExistingHabit" class="experience-reuse">
                        <x-ui.select name="existingHabit" label="Usar un hábito que ya tengo" placeholder="Elige uno de tus hábitos" :options="$existingHabits" :selected="$existingHabit" wire:model="existingHabit" />
                        <x-ui.action type="submit" variant="tonal" wire:loading.attr="disabled">Usar este hábito</x-ui.action>
                    </form>
                @endif
                @error('habit')<p class="experience-error" role="alert">{{ $message }}</p>@enderror
                <div class="experience-step-actions"><x-ui.action trailing-icon="bi-arrow-right" wire:click="next" wire:loading.attr="disabled">Continuar</x-ui.action></div>
                <x-ui.fab label="Preparar hábito" action="prepareHabit" module="habits"
                          :actions="[['label' => 'Crear el mío', 'icon' => 'bi-pencil', 'event' => 'onboarding-habit', 'detail' => ['createNew' => true]]]" />
            @else
                <div class="experience-ready"><i class="bi bi-check-lg" aria-hidden="true"></i><span>Tu hábito está preparado</span></div>
                @if ($habit)
                    <article class="experience-habit-preview" data-module="habits" aria-label="Tu hábito preparado">
                        <span class="experience-habit-preview__icon" aria-hidden="true">{{ $habit->icon ?: '🌱' }}</span>
                        <div><h2>{{ $habit->name }}</h2><p>Todos los días · {{ \App\Livewire\Habit\HabitSettings::TIMES_OF_DAY[$habit->time_of_day] ?? 'En cualquier momento' }}</p></div>
                    </article>
                    <p>Lo encontrarás en Hábitos y en Mi día. Márcalo cuando lo hayas hecho.</p>
                    <p class="experience-note">Después puedes explorar los módulos en Ajustes → Personalizar módulos y preparar un objetivo cuando quieras.</p>
                    <div class="experience-step-actions"><x-ui.action trailing-icon="bi-arrow-right" wire:click="finish" wire:loading.attr="disabled">Empezar mi día</x-ui.action></div>
                @else
                    <p class="experience-note" role="status">Ese hábito ya no está disponible. Vuelve al paso anterior para preparar o elegir otro.</p>
                @endif
            @endif
            <footer class="experience-step-footer">
                @if ($step > 1)<x-ui.action variant="text" icon="bi-arrow-left" wire:click="previous" wire:loading.attr="disabled">Anterior</x-ui.action>@endif
                @if (! ($state['required'] ?? false) || ! empty($state['completed_at']))
                    <a class="md-btn-text" href="{{ route('home') }}" wire:navigate>Volver a Mi día</a>
                @endif
                <span><i class="bi bi-cloud-check" aria-hidden="true"></i> Cada paso confirmado queda guardado.</span>
            </footer>
        </section>
    </div>
    <livewire:onboarding.habit-editor />
</div>

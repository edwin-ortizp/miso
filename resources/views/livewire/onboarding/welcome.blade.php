<div class="experience-guide" data-module="home"
     x-data @onboarding-step-changed.window="$nextTick(() => $refs.stepTitle?.focus())">
    @php
        $stops = [1 => 'Sobre ti', 2 => 'Tu espacio', 3 => 'Un hábito', 4 => 'Un paso más', 5 => 'A empezar'];
        $contexts = ['study' => ['label' => 'Estudio', 'icon' => 'bi-mortarboard', 'hint' => 'Clases, entregas y tiempo para ti.'], 'work' => ['label' => 'Trabajo', 'icon' => 'bi-briefcase', 'hint' => 'Responsabilidades y proyectos propios.'], 'both' => ['label' => 'Ambos', 'icon' => 'bi-intersect', 'hint' => 'Varias cosas en marcha. Un paso a la vez.'], 'other' => ['label' => 'Otro', 'icon' => 'bi-compass', 'hint' => 'Tu camino no necesita una etiqueta.']];
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
                <p>{{ [1 => '¿Empezamos? Yo ya me emocioné y todavía no hacemos nada.', 2 => 'La mochila es tuya. Lleva lo que te ayude; el resto puede esperar.', 3 => 'Un hábito pequeño también es un gran comienzo. Vamos a darle sitio.', 4 => 'Ya tenemos un hábito. ¿Le buscamos compañía o arrancamos con ese?', 5 => '¡Tenemos un comienzo! Ahora vamos a convertirlo en algo que puedas vivir.'][$step] }}</p>
            </div>
        </aside>
        <section class="experience-workspace" wire:key="onboarding-step-{{ $step }}">
            <header class="experience-step-head">
                <span class="experience-step-count">PASO {{ $step }} DE 5 <span aria-hidden="true">/</span> {{ $stops[$step] }}</span>
                <h1 tabindex="-1" x-ref="stepTitle">{{ [1 => 'Vamos a darle forma a tu intención', 2 => 'MiSo a tu medida', 3 => 'Tu primer hábito', 4 => 'Un siguiente paso, si quieres', 5 => 'Ya tienes por dónde empezar'][$step] }}</h1>
                <p>{{ [1 => 'Cuéntanos un poco de ti. Prepararemos un espacio que tenga sentido para tu día.', 2 => 'Elige lo que quieres ver. Puedes cambiar tu selección cuando quieras.', 3 => 'Elige algo tan pequeño que puedas empezar. La constancia se construye después.', 4 => 'Un objetivo marca el rumbo. Una tarea te acerca con una acción concreta.', 5 => 'Esto es lo que acabas de preparar. No hace falta configurarlo todo para empezar.'][$step] }}</p>
            </header>
            @if ($step === 1)
                <form wire:submit="next" class="experience-fields">
                    <fieldset class="experience-contexts">
                        <legend>¿Cómo es tu día a día?</legend>
                        <div class="experience-context-grid">
                        @foreach ($contexts as $key => $choice)
                            <label class="experience-context">
                                <input type="radio" name="context" wire:model.live="context" value="{{ $key }}" aria-label="{{ $choice['label'] }}" aria-describedby="context-hint-{{ $key }}">
                                <span class="experience-context__icon" aria-hidden="true"><i class="bi {{ $choice['icon'] }}"></i></span>
                                <span class="experience-context__body"><strong>{{ $choice['label'] }}</strong><small id="context-hint-{{ $key }}">{{ $choice['hint'] }}</small></span>
                                <i class="bi bi-check-circle-fill experience-context__check" aria-hidden="true"></i>
                            </label>
                        @endforeach
                    </div>
                    </fieldset>
                    @error('context')<p class="experience-error" role="alert">{{ $message }}</p>@enderror
                    <x-ui.field name="intention" label="¿Qué te gustaría mejorar?" wire:model="intention" :required="true" help="Por ejemplo: estudiar con constancia o avanzar en un proyecto propio." />
                    <div class="experience-step-actions"><x-ui.action type="submit" trailing-icon="bi-arrow-right" wire:loading.attr="disabled">Continuar</x-ui.action><span>Vamos paso a paso.</span></div>
                </form>
            @elseif ($step === 2)
                <form wire:submit="next">
                    @include('livewire.onboarding.module-choices', ['guided' => true])
                    <p class="experience-note"><i class="bi bi-shield-check" aria-hidden="true"></i> Ocultar conserva tus datos y enlaces. Los recordatorios configurados siguen activos.</p>
                    <div class="experience-step-actions"><x-ui.action type="submit" trailing-icon="bi-arrow-right" wire:loading.attr="disabled">Guardar y continuar</x-ui.action></div>
                </form>
            @elseif ($step === 3)
                @if ($habit)
                    <article class="experience-habit-preview" data-module="habits" aria-label="Vista previa del hábito">
                        <span class="experience-habit-preview__icon" aria-hidden="true">{{ $habit->icon ?: '🌱' }}</span>
                        <div><span class="experience-eyebrow">TU PRIMER HÁBITO</span><h2>{{ $habit->name }}</h2><p>{{ \App\Livewire\Habit\HabitSettings::TIMES_OF_DAY[$habit->time_of_day] ?? 'En cualquier momento' }} @if($habit->base_time) · {{ $habit->base_time }} @endif @if($habit->goal_duration) · {{ $habit->goal_duration }} @endif</p></div>
                    </article>
                    <p class="experience-note"><i class="bi bi-check-circle" aria-hidden="true"></i> Guardado. Lo marcarás como realizado cuando lo hagas.</p>
                @else
                    <div class="experience-first-habit" data-module="habits">
                        <span class="experience-first-habit__icon"><i class="bi {{ config('modules.habits.icon') }}" aria-hidden="true"></i></span>
                        <div><h2>Una práctica pequeña. Un espacio para ti.</h2><p>{{ in_array($state['context'] ?? '', ['study', 'both']) ? 'Podría ser leer apuntes durante diez minutos. Tú eliges el nombre, el momento y la duración.' : 'Podría ser dedicar diez minutos a tu proyecto. Tú eliges el nombre, el momento y la duración.' }}</p></div>
                    </div>
                    <p class="experience-note"><i class="bi bi-plus-circle" aria-hidden="true"></i> Abre el botón + «Crear mi hábito» para adaptar la sugerencia o escribir el tuyo.</p>
                @endif
                @error('habit')<p class="experience-error" role="alert">{{ $message }}</p>@enderror
                <div class="experience-step-actions"><x-ui.action trailing-icon="bi-arrow-right" wire:click="next" wire:loading.attr="disabled">Continuar</x-ui.action></div>
                <x-ui.fab :label="$habit ? 'Editar mi hábito' : 'Crear mi hábito'" event="onboarding-habit" module="habits" />
            @elseif ($step === 4)
                <div class="experience-optional">
                    <article data-module="goals"><span class="experience-optional__icon"><i class="bi {{ config('modules.goals.icon') }}" aria-hidden="true"></i></span><h2>Un objetivo</h2><p>Algo que te ilusiona alcanzar.</p><small>{{ in_array($state['context'] ?? '', ['study', 'both']) ? 'Por ejemplo: terminar el semestre con una rutina de estudio.' : 'Por ejemplo: terminar ese proyecto que quieres hacer realidad.' }}</small>@if(isset($state['goal_id']))<span class="experience-saved" role="status"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Tu objetivo está guardado.</span>@endif</article>
                    <article data-module="tasks"><span class="experience-optional__icon"><i class="bi {{ config('modules.tasks.icon') }}" aria-hidden="true"></i></span><h2>Una tarea</h2><p>La próxima acción que puedes hacer.</p><small>{{ in_array($state['context'] ?? '', ['study', 'both']) ? 'Por ejemplo: preparar los apuntes para mañana.' : 'Por ejemplo: reservar diez minutos para el siguiente paso.' }}</small>@if(isset($state['task_id']))<span class="experience-saved" role="status"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Tu tarea está guardada.</span>@endif</article>
                </div>
                <p class="experience-note">Puedes añadirlos con el botón + o seguir con tu hábito. Este paso es opcional.</p>
                <div class="experience-step-actions"><x-ui.action trailing-icon="bi-arrow-right" wire:click="next" wire:loading.attr="disabled">Continuar al resumen</x-ui.action></div>
                <x-ui.fab label="Agregar objetivo" event="onboarding-goal" module="home" :actions="[['label' => 'Agregar tarea', 'icon' => 'bi-list-task', 'event' => 'onboarding-task']]" />
            @else
                <div class="experience-ready"><i class="bi bi-check-lg" aria-hidden="true"></i><span>Tu espacio está preparado</span></div>
                <div class="experience-intention"><span class="experience-eyebrow">LO QUE QUIERES MEJORAR</span><p>{{ $state['intention'] ?? '' }}</p></div>
                <article class="experience-habit-preview" data-module="habits" aria-label="Tu hábito preparado"><span class="experience-habit-preview__icon" aria-hidden="true">{{ $habit?->icon ?: '🌱' }}</span><div><span class="experience-eyebrow">TU PRIMER HÁBITO</span><h2>{{ $habit?->name ?? 'Vuelve al paso anterior para crear tu hábito.' }}</h2></div></article>
                <ol class="experience-next-steps">
                    <li><span>1</span><p><strong>Encuentra tu siguiente paso</strong> en Mi día y consulta tu rutina en Hábitos.</p></li>
                    <li><span>2</span><p><strong>Hazlo y regístralo.</strong> Marca el hábito cuando lo realices; puedes corregir una marca.</p></li>
                    <li><span>3</span><p><strong>Ajusta tu espacio.</strong> Personalizar módulos, tu perfil y Cerrar sesión siempre estarán a mano.</p></li>
                </ol>
                <div class="experience-step-actions"><x-ui.action trailing-icon="bi-arrow-right" wire:click="finish" wire:loading.attr="disabled">Empezar mi día</x-ui.action></div>
            @endif
            <footer class="experience-step-footer">
                @if ($step > 1)<x-ui.action variant="text" icon="bi-arrow-left" wire:click="previous" wire:loading.attr="disabled">Anterior</x-ui.action>@endif
                <span><i class="bi bi-cloud-check" aria-hidden="true"></i> Cada paso confirmado queda guardado.</span>
            </footer>
        </section>
    </div>
    <livewire:onboarding.habit-editor />
    <livewire:onboarding.goal-editor />
    <livewire:onboarding.task-editor />
</div>

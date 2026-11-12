<x-ui.section>
    <x-ui.card variant="outlined" class="settings-account-form" icon="bi-heart-pulse" iconTone="secondary">
        <x-slot:header><h2 class="md-title-large mb-0">Datos físicos</h2></x-slot:header>
        <p class="settings-form-intro">Se usan para calcular calorías, hidratación y tu calendario de vida.</p>
        <div class="md-field-pair">
            <x-ui.field name="currentWeightKg" label="Peso actual (kg)" type="number" min="20" max="500" step="0.1" wire:model="currentWeightKg" />
            <x-ui.field name="heightCm" label="Estatura (cm)" type="number" min="50" max="300" step="1" wire:model="heightCm" />
        </div>

        <x-ui.select name="activityLevel" label="Nivel de actividad" placeholder="No especificado"
                     :options="[
                         'sedentary' => 'Sedentario',
                         'light' => 'Ligero',
                         'moderate' => 'Moderado',
                         'high' => 'Alto',
                         'very_high' => 'Muy alto',
                     ]"
                     wire:model="activityLevel" />

        <div class="md-field-pair">
            <x-ui.field name="birthDate" label="Fecha de nacimiento" type="date" wire:model="birthDate" />
            <x-ui.field name="lifeExpectancyYears" label="Expectativa de vida (años)" type="number" min="1" max="130" step="1"
                        help="Referencia personal opcional para tu calendario de vida." wire:model="lifeExpectancyYears" />
        </div>

        <section class="settings-body-reference" aria-labelledby="body-reference-title">
            <h3 id="body-reference-title" class="md-title-small mb-0">Según tus datos guardados</h3>
            <dl class="settings-body-reference__values">
                <div><dt>Edad</dt><dd>{{ $age !== null ? $age.' años' : 'Falta fecha de nacimiento' }}</dd></div>
                <div><dt>IMC</dt><dd>{{ $bmi !== null ? number_format($bmi, 1, ',', '.') : 'Faltan peso y estatura' }}</dd></div>
                <div><dt>Meta de agua</dt><dd>{{ $waterGoal ? number_format($waterGoal / 1000, 1, ',', '.').' L' : 'Sin configurar' }}</dd></div>
            </dl>
        </section>

        <x-slot:actions>
            <x-ui.action variant="filled" icon="bi-check-lg" wire:click="updateProfile">Guardar datos físicos</x-ui.action>
        </x-slot:actions>
    </x-ui.card>

</x-ui.section>

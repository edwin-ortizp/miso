<x-ui.section>
    <x-ui.card variant="outlined" class="settings-account-form" icon="bi-person" iconTone="secondary">
        <x-slot:header><h2 class="md-title-large mb-0">Perfil público</h2></x-slot:header>
        <p class="settings-form-intro">Tu nombre y correo dentro de MiSo.</p>
        <x-ui.field name="fullName" label="Nombre completo" wire:model="fullName" />
        <x-ui.field name="settingsEmail" label="Email" type="email" :value="$email" :disabled="true"
                    help="El email no puede modificarse." />

        <x-slot:actions>
            <x-ui.action variant="filled" icon="bi-check-lg" wire:click="updateProfile">Guardar perfil</x-ui.action>
        </x-slot:actions>
    </x-ui.card>
</x-ui.section>


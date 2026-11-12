<section class="md-card-elevated">
    <h2 class="md-title-large">Personalizar módulos</h2>
    <p>Elige lo que quieres ver en el menú y en Mi día. Puedes cambiarlo cuando quieras.</p>
    <p class="md-body-small">Ocultar conserva tus datos y el acceso mediante enlaces. Los recordatorios que configuraste siguen activos; puedes cambiarlos en Notificaciones.</p>
    <form wire:submit="save">
        @include('livewire.onboarding.module-choices')
        <x-ui.action class=" mt-3" type="submit" wire:loading.attr="disabled">Guardar preferencias</x-ui.action>
    </form>
    <a class="md-btn-text mt-3" href="{{ route('onboarding') }}" wire:navigate>Recorrer la guía de MiSo</a>
</section>

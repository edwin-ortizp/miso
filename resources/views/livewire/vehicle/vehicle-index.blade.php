<x-module-shell module="vehicles">
    <livewire:game.guardian-card module="vehicles" variant="top" />

    <x-ui.management-card id="vehicle-garage" title="Garaje" icon="bi-car-front" :count="'('.$vehicles->total().')'" :paginator="$vehicles" noun="vehículos">
        <x-slot:menu>
            <x-ui.menu-item icon="bi-tools" :href="route('vehicles.catalog')">Catálogo de mantenimientos</x-ui.menu-item>
        </x-slot:menu>
    @if($vehicles->isEmpty())
        <div class="md-empty-state md-card-outlined">
            <div class="md-empty-state__icon"><i class="bi bi-car-front"></i></div>
            <h2>Tu garaje está vacío</h2>
            <p>Añade tu automóvil, moto, bicicleta o patineta para programar sus cuidados.</p>
        </div>
    @else
        <div class="vehicle-garage-grid">
            @foreach($vehicles as $vehicle)
                <a href="{{ route('vehicles.fuel', $vehicle) }}" class="vehicle-garage-card md-card-outlined" wire:key="vehicle-{{ $vehicle->id }}">
                    @if($vehicle->photo_path)
                        <img src="{{ asset('storage/'.$vehicle->photo_path) }}" alt="{{ $vehicle->name }}">
                    @else
                        <span class="vehicle-garage-card__icon"><i class="bi bi-{{ $vehicle->vehicle_type === 'motocicleta' ? 'scooter' : ($vehicle->vehicle_type === 'bicicleta' ? 'bicycle' : 'car-front') }}"></i></span>
                    @endif
                    <span class="vehicle-garage-card__body">
                        <strong>{{ $vehicle->name }}</strong>
                        <small>{{ trim($vehicle->make.' '.$vehicle->model) ?: ucfirst($vehicle->vehicle_type) }}</small>
                        <span>{{ $vehicle->current_usage !== null ? number_format($vehicle->current_usage, 0, ',', '.').' '.$vehicle->usage_unit : 'Sin lectura actual' }}</span>
                    </span>
                    <i class="bi bi-chevron-right vehicle-garage-card__chevron"></i>
                </a>
            @endforeach
        </div>
    @endif

    </x-ui.management-card>

    @include('livewire.vehicle.partials.vehicle-form')

    <div class="lt-fab-zone">
        <x-module-actions fab-always
            :primary="['label' => 'Añadir vehículo', 'icon' => 'bi-plus-lg', 'action' => 'openVehicleForm']"
            :secondary="[['label' => 'Catálogo de mantenimientos', 'icon' => 'bi-tools', 'href' => route('vehicles.catalog')]]" />
    </div>
    {{-- CHG-064: panel lateral con el guardián (solo si hay guardián). --}}
    @if (\App\Livewire\Game\GuardianCard::visibleFor((int) auth()->id(), 'vehicles'))
        <x-slot:rail>
            <x-context-widget title="Tu guardián" icon="bi-stars">
                <livewire:game.guardian-card module="vehicles" variant="rail" />
            </x-context-widget>
        </x-slot:rail>
    @endif
</x-module-shell>

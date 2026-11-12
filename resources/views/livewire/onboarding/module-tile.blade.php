<label class="experience-module-tile" data-module="{{ $key }}" wire:key="guided-module-{{ $key }}">
    <span class="experience-module-tile__icon" aria-hidden="true"><i class="bi {{ $item['icon'] }}"></i></span>
    <span class="experience-module-tile__body"><strong>{{ $item['label'] }}</strong><small id="module-description-{{ $key }}">{{ $item['description'] }}</small></span>
    <input type="checkbox" wire:model="modules" value="{{ $key }}" aria-label="{{ $item['label'] }}" aria-describedby="module-description-{{ $key }}">
</label>

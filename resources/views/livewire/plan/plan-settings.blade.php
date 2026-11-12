<x-module-shell module="plans" archetype="settings" active-route="plans.settings" :navigation-query="[]">
    <x-slot:actions><x-module-actions :primary="['label'=>$section === 'categories' ? 'Crear categoría' : 'Crear tag','icon'=>'bi-plus-lg','action'=>'openForm']" /></x-slot:actions>
    @if ($message)<p role="status" class="plans-notice">{{ $message }}</p>@endif
    <div class="plan-settings-layout">
        <x-ui.management-card id="plan-settings" :title="$section === 'categories' ? 'Categorías de planes' : 'Tags de planes'" :icon="$section === 'categories' ? 'bi-grid' : 'bi-tags'" search="q" search-placeholder="Buscar por nombre" :paginator="$records" :count="$records->total()">
            @if ($records->isEmpty())<x-ui.state variant="empty" title="No hay resultados" message="Prueba otra búsqueda o crea un elemento desde el botón flotante." icon="bi-tags" />
            @else
            <table class="md-table md-table--stack" aria-label="Catálogo de planes"><thead><tr><th scope="col">Nombre</th><th scope="col">Planes</th><th scope="col" class="md-table__actions">Acciones</th></tr></thead><tbody>
            @foreach ($records as $record)<tr wire:key="{{ $section }}-{{ $record->id }}"><td data-label="Nombre" class="md-table__title">@if ($section === 'categories')<x-ui.catalog-chip tone="accent" :icon="$record->icon ?: 'bi-tag'">{{ $record->path_label }}</x-ui.catalog-chip>@else<i class="bi {{ $record->icon }}" aria-hidden="true"></i> {{ $record->name }}@endif</td><td data-label="Planes">{{ $record->plans_count }}</td><td data-label="Acciones" class="md-table__actions">
                <x-ui.row-actions :label="'Acciones de '.$record->name"><x-slot:primary wire:click="openForm('{{ $record->id }}')">Editar</x-slot:primary><x-ui.menu-item icon="bi-trash" tone="danger" wire:click="delete('{{ $record->id }}')" wire:confirm="¿Eliminar «{{ $record->name }}»?">Eliminar</x-ui.menu-item></x-ui.row-actions>
            </td></tr>@endforeach
            </tbody></table>
            @endif
        </x-ui.management-card>
        <nav class="plan-settings-nav" aria-label="Secciones de ajustes de planes">
            @foreach (['categories'=>'Categorías de planes','tags'=>'Tags de planes'] as $key=>$label)
                <x-ui.action variant="text" wire:click="$set('section','{{ $key }}')" :aria-current="$section === $key ? 'page' : null">{{ $label }}</x-ui.action>
            @endforeach
        </nav>
    </div>
    <x-ui.form-dialog module="plans" :open="$showForm" close="closeForm" submit-action="save" id="plan-catalog-dialog" :title="($editingId ? 'Editar ' : 'Crear ').($section === 'categories' ? 'categoría' : 'tag')" icon="bi-tags">
        <x-ui.field name="name" label="Nombre" :required="true" :maxlength="$section === 'categories' ? 80 : 60" wire:model="name" />
        @if ($section === 'categories')
        <x-ui.category-parent model="PlanCategory" :editing-id="$editingId" />
        @endif
        <x-ui.icon-picker name="icon" :value="$icon" :options="$icons" />
    </x-ui.form-dialog>
</x-module-shell>

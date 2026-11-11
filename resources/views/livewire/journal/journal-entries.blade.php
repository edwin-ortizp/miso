<x-module-shell module="journal">
    <x-slot:controls>
        <p class="md-body-medium mb-0">{{ ucfirst(\Carbon\Carbon::parse($selectedDate)->translatedFormat('l d \d\e F')) }}</p>
    </x-slot:controls>

    <livewire:game.guardian-card module="journal" variant="top" />

    <div class="md-module-workspace journal-workspace">
        <div class="md-module-primary">

    {{-- Editor --}}
    <div class="md-card-elevated mb-3" style="padding: 0; overflow: hidden;">
        <div style="padding: 16px;" class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                @if ($hasEntry)
                    <i class="bi bi-check-circle-fill" style="color: var(--md-custom-color-success);"></i>
                @else
                    <i class="bi bi-circle" style="color: var(--md-sys-color-outline);"></i>
                @endif
                <span class="md-title-small" style="color: var(--md-sys-color-on-surface);">Entrada del día</span>
            </div>
        </div>
        <div style="padding: 0 16px 16px 16px;">
            <div class="mb-3" x-data="{ length: @js(mb_strlen($summary)) }">
                <x-ui.field name="summary" label="Resumen del día" icon="bi-card-list" maxlength="255"
                            help="Lo más importante del día en una línea. Aparece en la pestaña Resumen."
                            wire:model="summary" x-on:input="length = $event.target.value.length" />
                <p class="md-label-small journal-summary-field__meta mb-0" aria-live="polite"><span x-text="length"></span>/255</p>
            </div>
            @include('partials.markdown-editor', [
                'model' => 'text',
                'mode' => 'viewMode',
                'modeValue' => $viewMode,
                'content' => $text,
                'id' => 'journal-text',
                'placeholder' => '¿Cómo fue tu día? Escribe tus pensamientos aquí...',
                'rows' => 12,
            ])
        </div>
        <div style="padding: 8px 16px 12px 16px; border-top: 1px solid var(--md-sys-color-surface-variant);" class="d-flex justify-content-between align-items-center">
            <span class="md-label-small" style="color: var(--md-sys-color-on-surface-variant);">
                {{ str_word_count($text) }} palabras · {{ strlen($text) }} caracteres
            </span>
            <button wire:click="save" class="md-btn-filled" style="height: 36px; padding: 0 20px;">
                <i class="bi bi-floppy"></i> Guardar
            </button>
        </div>
    </div>

    {{-- Recent Entries --}}
    <x-ui.management-card id="journal-recent" title="Entradas recientes" icon="bi-clock-history" :count="'('.$recentEntries->total().')'" :paginator="$recentEntries" noun="entradas" flush>
        @forelse ($recentEntries as $entry)
            <div wire:click="$set('selectedDate', '{{ $entry->date->format('Y-m-d') }}'); loadEntry()"
                 class="md-list-item"
                 style="cursor: pointer; {{ $entry->date->format('Y-m-d') === $selectedDate ? 'background: color-mix(in srgb, var(--md-sys-color-primary) 8%, transparent);' : '' }}">
                <div class="md-list-item-leading">
                    <div class="text-center" style="min-width: 40px;">
                        <div class="md-title-medium" style="color: var(--md-sys-color-primary);">{{ $entry->date->format('d') }}</div>
                        <span class="md-label-small" style="color: var(--md-sys-color-on-surface-variant);">{{ $entry->date->translatedFormat('M') }}</span>
                    </div>
                </div>
                <div class="md-list-item-content">
                    <div class="md-list-item-headline text-truncate">{{ filled($entry->summary) ? $entry->summary : Str::limit($entry->text, 80) }}</div>
                    @if ($entry->display_time)
                        <div class="md-list-item-supporting">{{ $entry->display_time }}</div>
                    @endif
                </div>
            </div>
        @empty
            <div class="text-center py-4" style="color: var(--md-sys-color-on-surface-variant);">
                <p class="md-body-medium mb-0">Sin entradas recientes</p>
            </div>
        @endforelse
    </x-ui.management-card>
        </div>

        <livewire:journal.journal-mood-rail :selected-date="$selectedDate" :key="'journal-context-'.$selectedDate" />
    </div>
    {{-- CHG-063: panel lateral con el guardián (solo si hay guardián; si no, el módulo se ve como antes). --}}
    @if (\App\Livewire\Game\GuardianCard::visibleFor((int) auth()->id(), 'journal'))
        <x-slot:rail>
            <x-context-widget title="Tu guardián" icon="bi-stars">
                <livewire:game.guardian-card module="journal" variant="rail" />
            </x-context-widget>
        </x-slot:rail>
    @endif
</x-module-shell>

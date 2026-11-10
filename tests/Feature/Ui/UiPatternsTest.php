<?php

namespace Tests\Feature\Ui;

use App\Support\Ui\DataState;
use Illuminate\Support\Facades\Blade;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class UiPatternsTest extends TestCase
{
    private function render(string $template, array $data = []): string
    {
        return Blade::render($template, $data);
    }

    public function test_filter_bar_renders_search_clear_and_chip_rail(): void
    {
        $html = $this->render(<<<'BLADE'
            <x-ui.filter-bar search="search" placeholder="Buscar tareas" label="Filtros de tareas">
                <x-slot:chips>
                    <x-ui.chip variant="filter" :selected="true">Hoy</x-ui.chip>
                </x-slot:chips>
            </x-ui.filter-bar>
        BLADE);

        $this->assertStringContainsString('md-search-bar__input', $html);
        $this->assertStringContainsString('wire:model.live.debounce.300ms="search"', $html);
        $this->assertStringContainsString('aria-label="Limpiar búsqueda"', $html);
        $this->assertStringContainsString('md-chip-rail', $html);
        $this->assertStringContainsString('aria-label="Filtros de tareas"', $html);
        $this->assertStringContainsString('openMenu', $html);
    }

    public function test_two_filter_bars_share_the_same_hierarchy_and_keyboard_surface(): void
    {
        $tasks = $this->render('<x-ui.filter-bar search="search" placeholder="Buscar tareas" />');
        $meals = $this->render('<x-ui.filter-bar search="query" placeholder="Buscar recetas" />');

        foreach (['md-search-bar', 'md-search-bar__icon', 'md-search-bar__input', 'md-search-bar__clear'] as $marker) {
            $this->assertStringContainsString($marker, $tasks);
            $this->assertStringContainsString($marker, $meals);
        }

        $this->assertSame(
            substr_count($tasks, 'class="md-search-bar'),
            substr_count($meals, 'class="md-search-bar'),
        );
    }

    public function test_filter_menu_exposes_options_and_the_selected_state(): void
    {
        $html = $this->render('<x-ui.filter-menu name="status" label="Estado" :options="$options" selected="done" />', [
            'options' => ['todo' => 'Pendiente', 'done' => 'Hecha'],
        ]);

        $this->assertStringContainsString('md-chip-menu', $html);
        $this->assertStringContainsString('aria-haspopup="listbox"', $html);
        $this->assertStringContainsString('role="option"', $html);
        $this->assertStringContainsString('aria-selected="true"', $html);
        $this->assertStringContainsString('wire:click="$set(\'status\', \'done\')"', $html);
        $this->assertStringContainsString('Hecha', $html);
    }

    public function test_metric_supports_missing_values_and_long_labels(): void
    {
        $missing = $this->render('<x-ui.metric label="Racha" />');
        $long = $this->render('<x-ui.metric label="Promedio de hidratación en los últimos treinta días" value="2350" unit="ml" support="Sobre la meta diaria" tone="success" />');

        $this->assertStringContainsString('—', $missing);
        $this->assertStringNotContainsString('md-metric__unit', $missing);
        $this->assertStringContainsString('md-metric--success', $long);
        $this->assertStringContainsString('Promedio de hidratación en los últimos treinta días', $long);
        $this->assertStringContainsString('md-metric__unit', $long);
    }

    public function test_section_renders_heading_description_and_actions(): void
    {
        $html = $this->render(<<<'BLADE'
            <x-ui.section title="Resumen" description="Últimos siete días" :level="3">
                Contenido
                <x-slot:actions><x-ui.action variant="text">Ver todo</x-ui.action></x-slot:actions>
            </x-ui.section>
        BLADE);

        $this->assertStringContainsString('<h3', $html);
        $this->assertStringContainsString('md-section__description', $html);
        $this->assertStringContainsString('md-section__actions', $html);
    }

    public function test_list_item_keeps_leading_content_and_trailing_regions(): void
    {
        $html = $this->render(<<<'BLADE'
            <x-ui.list label="Tareas">
                <x-ui.list-item headline="Comprar pan" supporting="Hoy" href="/tasks/1">
                    <x-slot:leading><x-ui.icon name="bi-check2" /></x-slot:leading>
                    <x-slot:trailing><x-ui.icon-action icon="bi-pencil" label="Editar" /></x-slot:trailing>
                </x-ui.list-item>
            </x-ui.list>
        BLADE);

        $this->assertStringContainsString('md-list', $html);
        $this->assertStringContainsString('md-list-item-leading', $html);
        $this->assertStringContainsString('md-list-item-link', $html);
        $this->assertStringContainsString('md-list-item-trailing', $html);
        $this->assertStringContainsString('href="/tasks/1"', $html);
    }

    public function test_dialog_declares_modal_semantics_and_focus_management(): void
    {
        $html = $this->render(<<<'BLADE'
            <x-ui.dialog state="showForm" title="Nueva tarea">
                Contenido
                <x-slot:actions><x-ui.action>Guardar</x-ui.action></x-slot:actions>
            </x-ui.dialog>
        BLADE);

        $this->assertStringContainsString('x-if="showForm"', $html);
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('aria-labelledby="dialog-nueva-tarea"', $html);
        $this->assertStringContainsString('x-md-surface', $html);
        $this->assertStringContainsString('showForm = false', $html);
    }

    public function test_standard_dialog_accepts_sections_without_adding_a_save_form(): void
    {
        $html = $this->render(<<<'BLADE'
            <x-ui.dialog standard persistent-content state="sheetOpen" title="Ficha" size="lg"
                :sections="['profile' => ['label' => 'Ficha', 'icon' => 'bi-person-badge'], 'history' => ['label' => 'Historial', 'icon' => 'bi-clock-history']]">
                Información
            </x-ui.dialog>
        BLADE);
        $this->assertStringContainsString('md-form-dialog--large', $html);
        $this->assertStringContainsString('md-form-dialog--sections', $html);
        $this->assertStringContainsString('Historial', $html);
        $this->assertStringContainsString('aria-controls="dialog-ficha-nav"', $html);
        $this->assertStringNotContainsString('type="submit"', $html);
    }

    public function test_sheet_reuses_the_modal_surface_contract(): void
    {
        $html = $this->render('<x-ui.sheet state="showSheet" title="Detalle" placement="side">Contenido</x-ui.sheet>');

        $this->assertStringContainsString('md-sheet--side', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('x-md-surface', $html);
        $this->assertStringContainsString('aria-label="Cerrar"', $html);
    }

    public function test_snackbar_announces_without_trapping_focus(): void
    {
        $html = $this->render('<x-ui.snackbar>Registro guardado</x-ui.snackbar>');

        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringNotContainsString('x-md-surface', $html);
        $this->assertStringNotContainsString('aria-modal', $html);
    }

    public function test_every_data_state_has_a_shared_surface(): void
    {
        foreach (array_diff(DataState::ALL, [DataState::CONTENT]) as $variant) {
            $html = $this->render('<x-ui.state variant="'.$variant.'" />');

            $this->assertStringContainsString('data-state="'.$variant.'"', $html);
            $this->assertStringContainsString('md-empty-state--'.$variant, $html);
        }
    }

    public function test_filtered_empty_differs_from_an_empty_collection(): void
    {
        $filtered = $this->render(<<<'BLADE'
            <x-ui.state variant="filtered-empty" message="Prueba con otros filtros.">
                <x-slot:actions><x-ui.action variant="text">Limpiar filtros</x-ui.action></x-slot:actions>
            </x-ui.state>
        BLADE);
        $empty = $this->render('<x-ui.state variant="empty" />');

        $this->assertStringContainsString('Limpiar filtros', $filtered);
        $this->assertStringNotContainsString('md-empty-state--empty', $filtered);
        $this->assertStringNotContainsString('md-empty-state--filtered-empty', $empty);
    }

    public function test_a_recoverable_error_keeps_the_structure_and_offers_retry(): void
    {
        $html = $this->render(<<<'BLADE'
            <x-ui.state variant="error" message="Revisa tu conexión e inténtalo otra vez.">
                <x-slot:actions><x-ui.action variant="outlined" wire:click="retry">Reintentar</x-ui.action></x-slot:actions>
            </x-ui.state>
        BLADE);

        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('wire:click="retry"', $html);
    }

    public function test_data_state_classification_distinguishes_every_case(): void
    {
        $this->assertSame(DataState::CONTENT, DataState::resolve(visible: 3, total: 10));
        $this->assertSame(DataState::FILTERED_EMPTY, DataState::resolve(visible: 0, total: 10));
        $this->assertSame(DataState::EMPTY, DataState::resolve(visible: 0, total: 0));
        $this->assertSame(DataState::LOADING, DataState::resolve(visible: 0, total: 10, loading: true));
        $this->assertSame(DataState::INITIAL, DataState::resolve(visible: 0, total: 0, requested: false));
        $this->assertSame(DataState::ERROR, DataState::resolve(visible: 5, total: 10, error: 'timeout'));
    }

    public function test_patterns_propagate_livewire_alpine_aria_and_data_attributes(): void
    {
        $section = $this->render('<x-ui.section title="Resumen" data-region="summary" x-ref="summary">Contenido</x-ui.section>');
        $item = $this->render('<x-ui.list-item headline="Tarea" wire:key="task-1" data-testid="row" aria-describedby="hint" />');

        $this->assertStringContainsString('data-region="summary"', $section);
        $this->assertStringContainsString('x-ref="summary"', $section);
        $this->assertStringContainsString('wire:key="task-1"', $item);
        $this->assertStringContainsString('data-testid="row"', $item);
        $this->assertStringContainsString('aria-describedby="hint"', $item);
    }

    public function test_patterns_reject_arbitrary_presentation_properties(): void
    {
        $this->expectException(HttpException::class);

        $this->render('<x-ui.metric label="Racha" tone="#ff0000" />');
    }

    public function test_the_content_state_is_rendered_by_the_screen_itself(): void
    {
        $this->expectException(HttpException::class);

        $this->render('<x-ui.state variant="content" />');
    }
}

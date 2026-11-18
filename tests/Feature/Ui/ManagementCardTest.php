<?php

namespace Tests\Feature\Ui;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ManagementCardTest extends TestCase
{
    private function paginator(int $total = 58, int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        $items = max(0, min($perPage, $total - ($page - 1) * $perPage));

        return new LengthAwarePaginator(array_fill(0, $items, 1), $total, $perPage, $page, ['path' => '/']);
    }

    public function test_it_renders_header_tools_body_and_footer(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.management-card id="demo" title="Cronología de salud" icon="bi-clock-history" count="(25 / 58)"
                search="search" search-placeholder="Buscar eventos" :active-filters="2" :paginator="$paginator" noun="eventos">
                <x-slot:filters>Campos</x-slot:filters>
                <x-slot:menu><x-ui.menu-item icon="bi-filetype-csv">Exportar CSV</x-ui.menu-item></x-slot:menu>
                Contenido
            </x-ui.management-card>
            BLADE, ['paginator' => $this->paginator()]);

        $this->assertStringContainsString('md-mcard__head', $html);
        $this->assertStringContainsString('Cronología de salud', $html);
        $this->assertMatchesRegularExpression('/<h2 class="md-mcard__heading"[^>]*>\s*<span class="md-mcard__heading-text">Cronología de salud<\/span>\s*<span class="md-mcard__count"[^>]*><span aria-hidden="true">·<\/span> \(25 \/ 58\)<\/span>\s*<\/h2>/u', $html);
        $this->assertStringContainsString('wire:model.live.debounce.300ms="search"', $html);
        $this->assertStringContainsString('id="demo-filters"', $html);
        $this->assertStringContainsString('2 filtros activos', $html);
        $this->assertStringContainsString('Exportar CSV', $html);
        $this->assertStringContainsString('Contenido', $html);
        $this->assertStringContainsString('md-mcard__foot', $html);
        $this->assertMatchesRegularExpression('/Mostrando <strong>1–25<\/strong> de <strong>58<\/strong> eventos/u', $html);
        $this->assertStringContainsString('wire:model.live="perPage"', $html);
        $this->assertMatchesRegularExpression('/md-pager__page is-active" aria-current="page"/', $html);
        $this->assertMatchesRegularExpression('/wire:click="previousPage\(\'page\'\)"[^>]*disabled/', $html);
        $this->assertStringContainsString('1 / 3', $html);
    }

    public function test_it_omits_tools_and_footer_when_not_configured(): void
    {
        $html = Blade::render('<x-ui.management-card title="Registro de hoy" icon="bi-droplet" :refreshable="false">Lista</x-ui.management-card>');

        $this->assertStringNotContainsString('md-mcard__tools', $html);
        $this->assertStringNotContainsString('md-mcard__foot', $html);
        $this->assertStringContainsString('Lista', $html);
    }

    public function test_create_actions_use_the_table_menu_and_keep_the_existing_actions(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.management-card title="Tareas" icon="bi-list-task" :create-actions="$actions">
                <x-slot:menu><x-ui.menu-item>Exportar</x-ui.menu-item></x-slot:menu>
                Filas
            </x-ui.management-card>
            <x-ui.management-card title="Historial" icon="bi-clock">
                Filas
            </x-ui.management-card>
            BLADE, ['actions' => [
                ['label' => 'Nueva tarea', 'action' => 'openForm'],
                ['label' => 'Crear múltiples tareas', 'event' => 'task-bulk-editor', 'detail' => ['action' => 'openForm']],
            ]]);

        $this->assertStringContainsString('wire:click="openForm"', $html);
        $this->assertStringContainsString('x-on:click="$dispatch(', $html);
        $this->assertStringContainsString('task-bulk-editor', $html);
        $this->assertMatchesRegularExpression('/Nueva tarea.*Crear múltiples tareas.*Actualizar tabla.*Exportar/su', $html);
        $this->assertSame(1, substr_count($html, 'Nueva tarea'));
    }

    public function test_create_actions_can_open_a_menu_without_refresh(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.management-card title="Tareas sin fecha" icon="bi-list-task" :refreshable="false"
                :create-actions="[['label' => 'Nueva tarea', 'action' => 'openForm']]">
                Filas
            </x-ui.management-card>
            BLADE);

        $this->assertStringContainsString('Nueva tarea', $html);
        $this->assertStringNotContainsString('Actualizar tabla', $html);
        $this->assertStringNotContainsString('md-menu__divider', $html);
    }

    public function test_the_last_page_disables_next(): void
    {
        $html = Blade::render('<x-ui.management-card title="Planes" icon="bi-map" :paginator="$p">x</x-ui.management-card>', ['p' => $this->paginator(58, 25, 3)]);

        $this->assertMatchesRegularExpression('/wire:click="nextPage\(\'page\'\)"[^>]*disabled/', $html);
        $this->assertStringContainsString('Mostrando <strong>51–58</strong>', $html);
    }

    public function test_a_header_action_links_to_the_full_view(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.management-card title="Historial reciente" icon="bi-clock-history" :refreshable="false">
                <x-slot:headerAction><a href="/historial" class="md-btn-text">Ver historial completo</a></x-slot:headerAction>
                Filas
            </x-ui.management-card>
            BLADE);

        $this->assertMatchesRegularExpression('/<div class="md-mcard__header-action">\s*<a href="\/historial"/', $html);
        $this->assertStringNotContainsString('md-mcard__tools', $html);
        $this->assertStringNotContainsString('md-mcard__foot', $html);
    }

    public function test_split_fab_renders_primary_and_toggle(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.fab label="Registrar evento" event="health-editor" :detail="['action' => 'openForm']" :split="true"
                :actions="[['label' => 'Nuevo pendiente', 'icon' => 'bi-list-check', 'action' => 'createPending']]" />
            BLADE);

        $this->assertStringContainsString('md-fab-split__main', $html);
        $this->assertStringContainsString('md-fab-split__toggle', $html);
        $this->assertStringContainsString('Nuevo pendiente', $html);
        $this->assertStringContainsString('md-fab-split__compact', $html);
        $this->assertStringContainsString('md-create-fab__item--primary', $html);
    }

    public function test_refresh_is_default_and_shares_the_existing_menu(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.management-card title="Salud" icon="bi-heart">
                <x-slot:menu><x-ui.menu-item href="/statistics">Estadísticas</x-ui.menu-item></x-slot:menu>
                Filas
            </x-ui.management-card>
            BLADE);

        $this->assertSame(1, substr_count($html, 'Actualizar tabla'));
        $this->assertSame(1, substr_count($html, 'aria-label="Más acciones de salud"'));
        $this->assertStringContainsString('Estadísticas', $html);
        $this->assertStringContainsString('bi-arrow-clockwise', $html);
        $this->assertStringContainsString('wire:click="$refresh"', $html);
        $this->assertStringContainsString('wire:loading.attr="disabled"', $html);
        $this->assertStringContainsString('wire:target="$refresh"', $html);
    }

    public function test_refresh_can_be_disabled_without_removing_existing_actions(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.management-card title="Ejemplo" icon="bi-list" :refreshable="false">
                <x-slot:menu><x-ui.menu-item>Exportar</x-ui.menu-item></x-slot:menu>
                Filas
            </x-ui.management-card>
            BLADE);
        $this->assertStringNotContainsString('Actualizar tabla', $html);
        $this->assertStringContainsString('Exportar', $html);
    }
}

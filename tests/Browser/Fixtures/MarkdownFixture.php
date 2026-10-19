<?php

namespace Tests\Browser\Fixtures;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class MarkdownFixture extends Component
{
    public string $text = '**Detalle** áéí 👩🏽‍💻';

    public string $viewMode = 'preview';

    public string $saved = '**Detalle** áéí 👩🏽‍💻';

    public string $secondary = 'Otro campo';

    public string $secondaryMode = 'preview';

    public bool $showEditor = true;

    public bool $mounted = true;

    public int $refreshes = 0;

    public bool $modal = false;

    public bool $deferred = false;

    public function mount(): void
    {
        $this->modal = request()->boolean('modal');
        $this->deferred = request()->boolean('deferred');
    }

    public function save(): void
    {
        $this->saved = $this->text;
    }

    public function cancel(): void
    {
        $this->text = $this->saved;
        $this->viewMode = 'preview';
    }

    public function reopen(): void
    {
        $this->text = $this->saved;
        $this->viewMode = 'preview';
        $this->showEditor = true;
        $this->mounted = true;
    }

    public function newDraft(): void
    {
        $this->text = '';
        $this->viewMode = 'write';
        $this->showEditor = true;
    }

    public function render(): View
    {
        return view('markdown-tests::component');
    }
}

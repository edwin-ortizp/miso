<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tests\Browser\Fixtures\MarkdownFixture;

beforeEach(function (): void {
    if (! app()->environment('testing') || app()->configurationIsCached()
        || config('database.default') !== 'sqlite'
        || config('database.connections.sqlite.database') !== ':memory:'
        || config('database.connections.sqlite.url') || getenv('DB_URL')) {
        throw new RuntimeException('Markdown requires uncached testing SQLite :memory: without DB_URL.');
    }
    config(['database.connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']],
        'session.driver' => 'array', 'cache.default' => 'array']);
    $this->withoutMiddleware();
    View::addNamespace('markdown-tests', __DIR__.'/views/markdown');
    Livewire::component('markdown-fixture', MarkdownFixture::class);
    Route::get('/__markdown-test', fn () => view('markdown-tests::page'));
});

it('usa herramientas compartidas y conserva Unicode al guardar en MiSo', function (bool $deferred): void {
    $root = '[data-editor-id="miso-description"]';
    $page = visit('/__markdown-test?deferred='.(int) $deferred)->assertSee('Detalle');
    $page->click($root.' [data-markdown-mode="edit"]')->fill('#miso-description', 'Nuevo áéí 👩🏽‍💻');
    $page->script('const t=document.querySelector("#miso-description");t.setSelectionRange(0,t.value.length)');
    $page->click($root.' [data-markdown-snippet="bold"] i')->assertValue('#miso-description', '**Nuevo áéí 👩🏽‍💻**')
        ->click($root.' [data-markdown-mode="preview"]')->assertSeeIn($root.' [data-markdown-preview]', 'Nuevo')
        ->click('#save')->assertSeeIn('#saved', '**Nuevo áéí 👩🏽‍💻**')
        ->click($root.' [data-markdown-mode="edit"]')->assertValue('#miso-description', '**Nuevo áéí 👩🏽‍💻**')
        ->assertNoJavaScriptErrors();
})->with([false, true])->group('component', 'write', 'form');

it('aplica las ocho herramientas de la librería sin modificar otro editor', function (bool $deferred): void {
    $root = '[data-editor-id="miso-description"]';
    $page = visit('/__markdown-test?deferred='.(int) $deferred)->click($root.' [data-markdown-mode="edit"]');
    foreach (['heading' => '## Texto', 'bold' => '**Texto**', 'italic' => '_Texto_', 'link' => '[Texto](https://)',
        'list' => '- Texto', 'checklist' => '- [ ] Texto', 'quote' => '> Texto', 'code' => '`Texto`'] as $tool => $expected) {
        $page->fill('#miso-description', 'Texto');
        $page->script('document.querySelector("#miso-description").setSelectionRange(0,5)');
        $page->click($root.' [data-markdown-snippet="'.$tool.'"]')->assertValue('#miso-description', $expected);
    }
    $page->click('#save')->assertSeeIn('#saved', '`Texto`')->assertValue('#miso-secondary', 'Otro campo');
    expect($page->script('Array.from(document.querySelectorAll("[data-editor-id=miso-description] [data-markdown-snippet]")).map(button => [button.textContent.trim(), button.getAttribute("aria-label"), button.getAttribute("title"), !!button.querySelector("i.bi[aria-hidden=true]")])'))
        ->toBe(array_map(fn (string $label): array => ['', $label, $label, true], ['Título', 'Negrita (Ctrl+B)', 'Cursiva (Ctrl+I)', 'Enlace (Ctrl+K)', 'Lista', 'Checklist (Ctrl+L)', 'Cita', 'Código']));
    $page->assertNoJavaScriptErrors();
})->with([false, true])->group('component', 'write', 'form');

it('restaura contenido y modos al cancelar, reabrir y montar un diálogo', function (bool $deferred): void {
    $root = '[data-editor-id="miso-description"]';
    $page = visit('/__markdown-test?deferred='.(int) $deferred)
        ->click($root.' [data-markdown-mode="edit"]')->fill('#miso-description', '**Guardado** 👩🏽‍💻')
        ->click('#save')->assertSeeIn('#saved', '**Guardado** 👩🏽‍💻')
        ->click('#close')->click('#reopen')->assertSeeIn($root.' [data-markdown-preview]', 'Guardado')
        ->click($root.' [data-markdown-mode="edit"]')->fill('#miso-description', 'Descartar')
        ->click('#cancel')->assertSeeIn($root.' [data-markdown-preview]', 'Guardado')
        ->assertValue('#miso-description', '**Guardado** 👩🏽‍💻')
        ->click('#refresh')->assertSeeIn('#refreshes', '1')->assertSeeIn($root.' [data-markdown-preview]', 'Guardado')
        ->click('#new')->assertVisible('#miso-description')->assertValue('#miso-description', '')
        ->click('#unmount')->assertNotPresent('#miso-description')
        ->click('#reopen')->assertSeeIn($root.' [data-markdown-preview]', 'Guardado')
        ->click($root.' [data-markdown-mode="edit"]')->fill('#miso-description', 'Otro registro')
        ->click($root.' [data-markdown-mode="preview"]')->assertSeeIn($root.' [data-markdown-preview]', 'Otro registro')
        ->assertCount($root.' [data-markdown-snippet] i.bi', 8)
        ->assertNoJavaScriptErrors();
})->with([false, true])->group('component', 'write', 'form');

it('conserva el atajo de checklist y cabe en móvil con estilos MiSo', function (): void {
    $root = '[data-editor-id="miso-description"]';
    $page = visit('/__markdown-test?deferred=1')->resize(390, 844)
        ->click($root.' [data-markdown-mode="edit"]')->fill('#miso-description', 'Pendiente');
    foreach (['- [ ] Pendiente', '- [x] Pendiente', 'Pendiente'] as $expected) {
        $page->script('const t=document.querySelector("#miso-description");t.focus();t.setSelectionRange(0,0);t.dispatchEvent(new KeyboardEvent("keydown",{key:"l",ctrlKey:true,bubbles:true,cancelable:true}))');
        $page->assertValue('#miso-description', $expected);
    }
    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
    $page->screenshotElement($root, 'miso-markdown-mobile');
    expect($page->script('getComputedStyle(document.querySelector(".md-markdown-editor .lm-editor")).backgroundColor'))->toBe($page->script('getComputedStyle(document.body).getPropertyValue("--md-sys-color-surface").trim() === "" ? "" : getComputedStyle(document.querySelector(".md-markdown-editor")).backgroundColor'));
    $page->assertCount($root.' [data-markdown-snippet] i.bi', 8)
        ->assertSeeIn($root.' [data-markdown-mode="edit"]', 'Editar')
        ->assertSeeIn($root.' [data-markdown-mode="preview"]', 'Vista previa');
    expect($page->script('getComputedStyle(document.querySelector("[data-markdown-snippet=bold] i"), "::before").fontFamily'))->toContain('bootstrap-icons');
    $page->assertNoJavaScriptErrors();
})->group('component', 'read', 'form');

it('copia con el botón y ejecuta los atajos del paquete con el foco en el editor', function (): void {
    $root = '[data-editor-id="miso-description"]';
    $key = fn (string $init) => 'const t=document.querySelector("#miso-description");t.focus();t.setSelectionRange(0,t.value.length);t.dispatchEvent(new KeyboardEvent("keydown",Object.assign({bubbles:true,cancelable:true},'.$init.')))';
    $page = visit('/__markdown-test?deferred=1')->click($root.' [data-markdown-mode="edit"]')->fill('#miso-description', 'Texto');
    // El portapapeles real no está disponible en el navegador de pruebas: se captura lo copiado.
    $page->script('window.__copied=null;Object.defineProperty(navigator,"clipboard",{configurable:true,value:{writeText:v=>{window.__copied=v;return Promise.resolve()}}})');

    expect($page->script('Array.from(document.querySelectorAll(`'.$root.' .lm-editor__toolbar > button`)).slice(-2).map(b => b.hasAttribute("data-markdown-copy") ? "copy" : (b.hasAttribute("data-markdown-expand") ? "expand" : "other"))'))
        ->toBe(['copy', 'expand']);
    $page->assertAttribute($root.' [data-markdown-copy]', 'title', 'Copiar contenido (Alt+Shift+C)');

    $page->script($key('{key:"b",ctrlKey:true}'));
    $page->assertValue('#miso-description', '**Texto**');
    $page->script($key('{key:"C",code:"KeyC",altKey:true,shiftKey:true}'));
    expect($page->script('window.__copied'))->toBe('**Texto**');
    $page->assertAttribute($root.' [data-markdown-copy]', 'title', 'Copiado');

    // También copia desde la vista previa.
    $page->fill('#miso-description', 'Otro')->script('window.__copied=null');
    $page->click($root.' [data-markdown-mode="preview"]');
    $page->click($root.' [data-markdown-copy]');
    expect($page->script('window.__copied'))->toBe('Otro');

    $page->click($root.' [data-markdown-mode="edit"]');
    $page->script($key('{key:"F",ctrlKey:true,shiftKey:true}'));
    $page->assertAttribute($root.' [data-markdown-expand]', 'aria-expanded', 'true')
        ->assertAttribute($root.' [data-markdown-expand]', 'title', 'Restaurar (Esc)');
    $page->assertNoJavaScriptErrors();
})->group('component', 'write', 'form');

it('amplía contenido largo dentro del modal sin perder el borrador', function (bool $deferred): void {
    $root = '[data-editor-id="miso-description"]';
    $page = visit('/__markdown-test?modal=1&deferred='.(int) $deferred)->resize(1280, 720)
        ->click($root.' [data-markdown-mode="edit"]')->fill('#miso-description', str_repeat("## Lectura áéí 👩🏽‍💻\n\nContenido extenso.\n\n", 100).'FINAL')
        ->click($root.' [data-markdown-mode="preview"]');
    expect($page->script('(() => {const e=document.querySelector("[data-editor-id=miso-description]"), b=e.querySelector("[data-markdown-body]"), f=e.closest(".md-dialog").querySelector(".md-dialog-actions");return b.scrollHeight>b.clientHeight && b.getBoundingClientRect().bottom<=f.getBoundingClientRect().top && f.getBoundingClientRect().bottom<=innerHeight+1;})()'))->toBeTrue();
    $page->script('window.originalMarkdown=document.querySelector("#miso-description");document.querySelector("[data-editor-id=miso-description] [data-markdown-body]").scrollTop=200');
    $page->click($root.' [data-markdown-expand]')->assertAttribute($root.' [data-markdown-expand]', 'aria-label', 'Restaurar (Esc)');
    $page->assertScript('document.activeElement === document.querySelector("[data-editor-id=miso-description] [data-markdown-body]")');
    expect($page->script('document.querySelector("[data-editor-id=miso-description]").matches(":popover-open") && window.originalMarkdown===document.querySelector("#miso-description")'))->toBeTrue();
    $page->script('const b=document.querySelector("[data-editor-id=miso-description] [data-markdown-body]");b.scrollTop=b.scrollHeight');
    $page->assertSeeIn($root.' [data-markdown-preview]', 'FINAL')->screenshotElement($root, 'miso-markdown-expanded-desktop');
    $page->click($root.' [data-markdown-expand]')
        ->assertScript('document.querySelector("[data-editor-id=miso-description] [data-markdown-body]").scrollTop===200')
        ->assertScript('document.activeElement===document.querySelector("[data-editor-id=miso-description] [data-markdown-expand]")')
        ->click($root.' [data-markdown-expand]');
    $page->click($root.' [data-markdown-mode="edit"]')->fill('#miso-description', 'Texto ampliado áéí 👩🏽‍💻');
    $page->script('document.querySelector("#miso-description").setSelectionRange(0,5)');
    $page->click($root.' [data-markdown-snippet="bold"]')->assertValue('#miso-description', '**Texto** ampliado áéí 👩🏽‍💻');
    $page->script('Livewire.all()[0].$wire.$set("refreshes", 1)');
    $page->assertSeeIn('#refreshes', '1')->assertAttribute($root.' [data-markdown-expand]', 'aria-label', 'Restaurar (Esc)');
    $page->script('document.querySelector("#miso-description").dispatchEvent(new KeyboardEvent("keydown",{key:"Escape",bubbles:true,cancelable:true}))');
    $page->assertVisible('.task-properties-dialog')->assertAttribute($root.' [data-markdown-expand]', 'aria-label', 'Ampliar (Ctrl+Shift+F)');
    $page->click('.task-properties-dialog .md-dialog-actions .md-btn-filled')->assertSeeIn('#saved', '**Texto** ampliado áéí 👩🏽‍💻')->assertValue('#miso-secondary', 'Otro campo');
    $page->click($root.' [data-markdown-mode="preview"]')->click($root.' [data-markdown-expand]');
    $page->script('const b=document.querySelector("[data-editor-id=miso-description] [data-markdown-body]");b.focus();b.dispatchEvent(new KeyboardEvent("keydown",{key:"Tab",bubbles:true,cancelable:true}))');
    $page->assertScript('document.activeElement === document.querySelector("[data-editor-id=miso-description] [data-markdown-mode=edit]")');
    $page->script('Livewire.all()[0].$wire.$set("showEditor", false)');
    $page->assertMissing('.task-properties-dialog');
    expect($page->script('!document.querySelector(":popover-open") && !document.querySelector("[inert]") && document.documentElement.style.overflow!=="hidden"'))->toBeTrue();
    $page->click('#reopen')->assertSeeIn($root.' [data-markdown-preview]', 'ampliado')->assertAttribute($root.' [data-markdown-expand]', 'aria-label', 'Ampliar (Ctrl+Shift+F)');
    $page->click($root.' [data-markdown-expand]');
    $page->script('Livewire.all()[0].$wire.$set("mounted", false)');
    $page->assertNotPresent('#miso-description')->assertScript('!document.querySelector(":popover-open") && !document.querySelector("[inert]")')->assertNoJavaScriptErrors();
})->with([false, true])->group('component', 'write', 'form');

it('contiene lectura y controles en pantalla baja y móvil', function (int $width, int $height): void {
    $root = '[data-editor-id="miso-description"]';
    $page = visit('/__markdown-test?modal=1&deferred=1')->resize($width, $height)
        ->click($root.' [data-markdown-mode="edit"]')->fill('#miso-description', str_repeat("Párrafo largo de prueba.\n\n", 100))
        ->click($root.' [data-markdown-mode="preview"]');
    expect($page->script('(() => {const e=document.querySelector("[data-editor-id=miso-description]"), b=e.querySelector("[data-markdown-body]"), f=e.closest(".md-dialog").querySelector(".md-dialog-actions");return b.scrollHeight>b.clientHeight && f.getBoundingClientRect().bottom<=innerHeight+1 && document.documentElement.scrollWidth<=innerWidth;})()'))->toBeTrue();
    $page->screenshot(false, 'miso-markdown-modal-'.$width);
    copy(__DIR__.'/../Screenshots/miso-markdown-modal-'.$width.'.png', storage_path('framework/testing/markdown-modal-'.$width.'.png'));
    $page->click($root.' [data-markdown-expand]');
    expect($page->script('(() => {const e=document.querySelector("[data-editor-id=miso-description]"), r=e.getBoundingClientRect();return r.bottom<=innerHeight+1 && r.right<=innerWidth+1 && e.querySelector("[data-markdown-body]").clientHeight>100;})()'))->toBeTrue();
    $page->screenshotElement($root, 'miso-markdown-expanded-'.$width);
    copy(__DIR__.'/../Screenshots/miso-markdown-expanded-'.$width.'.png', storage_path('framework/testing/markdown-expanded-'.$width.'.png'));
    $page->resize($width, $height - 120)->assertScript('document.querySelector("[data-editor-id=miso-description]").getBoundingClientRect().bottom<=innerHeight+1');
    $page->click($root.' [data-markdown-expand]')->assertNoJavaScriptErrors();
})->with([[1280, 480], [390, 844]])->group('component', 'read', 'form');

it('amplía el componente Blade sin adaptador y conserva selección y envío del formulario', function (): void {
    $root = '[data-editor-id="portable-description"]';
    $page = visit('/__markdown-test')->fill('#portable-description', 'Lectura y escritura áéí 👩🏽‍💻');
    $page->script('window.portableSource=document.querySelector("#portable-description");window.portableSource.setSelectionRange(0,7)');
    $page->click($root.' [data-markdown-expand]')->assertScript('window.portableSource===document.querySelector("#portable-description") && window.portableSource.selectionStart===0 && window.portableSource.selectionEnd===7');
    $page->click($root.' [data-markdown-snippet="bold"]')->assertValue('#portable-description', '**Lectura** y escritura áéí 👩🏽‍💻')
        ->click($root.' [data-markdown-mode="preview"]')->assertSeeIn($root.' [data-markdown-preview]', 'Lectura')
        ->click($root.' [data-markdown-expand]')->assertScript('new FormData(document.querySelector("#portable-form")).get("description")==="**Lectura** y escritura áéí 👩🏽‍💻"')
        ->assertNotPresent('[data-editor-id="portable-no-expand"] [data-markdown-expand]');
    $page->script('const e=document.querySelector("[data-editor-id=portable-description]");e.showPopover=undefined;LaravelMarkdownEditor.initializePortable(document)');
    $page->assertMissing($root.' [data-markdown-expand]')->assertNoJavaScriptErrors();
})->group('component', 'read', 'form');

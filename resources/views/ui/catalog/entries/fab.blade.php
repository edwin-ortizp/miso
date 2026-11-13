<x-catalog.example title="Acción de creación" description="Extendido en escritorio (ícono + texto) y solo ícono en móvil. Fijo abajo a la derecha, sobre menús y acordeones, oculto mientras hay un modal abierto.">
    <div class="md-catalog-fab-preview">
        <button type="button" class="md-fab md-fab-extended"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Registrar evento</span></button>
        <button type="button" class="md-fab" aria-label="Registrar evento"><i class="bi bi-plus-lg" aria-hidden="true"></i></button>
    </div>
    <x-ui.fab label="Registrar evento" icon="bi-plus-lg" event="catalog-fab" :actions="[['label' => 'Registrar seguimiento', 'icon' => 'bi-arrow-repeat', 'event' => 'catalog-fab']]" />
    <p class="md-body-medium">Uso real: <code>&lt;x-ui.fab label="Registrar evento" icon="bi-plus-lg" action="openForm" /&gt;</code>. La acción siempre abre <code>x-ui.form-dialog</code>.</p>
</x-catalog.example>

<x-catalog.example title="Varias opciones de creación" description="Con `actions`, el FAB despliega un menú con la acción principal y las secundarias, con el mismo patrón visual. Se cierra con Esc, clic fuera o al elegir.">
    <div class="md-create-fab__menu md-catalog-fab-preview__menu">
        <span class="md-create-fab__item"><span>Agregar persona</span><i class="bi bi-person-plus" aria-hidden="true"></i></span>
        <span class="md-create-fab__item"><span>Agregar círculo</span><i class="bi bi-plus-circle" aria-hidden="true"></i></span>
    </div>
    <p class="md-body-medium"><code>&lt;x-module-actions :primary="[...]" :secondary="[['label' =&gt; 'Agregar círculo', 'action' =&gt; 'openCircleForm', 'create' =&gt; true]]" /&gt;</code></p>
</x-catalog.example>

<x-catalog.example title="Chat del compañero" description="Presentación opcional del mismo FAB: el favorito acompaña con un panel no modal, tema por tipo y transiciones que respetan movimiento reducido.">
    <p class="md-body-medium">Tareas, Agua y Ánimo permiten registros rápidos; las acciones complejas y «Más opciones» abren el formulario habitual. Sin colección o con presentación tradicional, se conserva el FAB.</p>
    <p class="md-body-medium">Los módulos declaran <code>quick</code> en <code>x-module-actions</code>: <code>compose</code> recibe texto, <code>tap</code> llama una acción validada y <code>step</code> cambia las opciones. El chat mantiene hilo y borradores al actualizar la pantalla.</p>
</x-catalog.example>

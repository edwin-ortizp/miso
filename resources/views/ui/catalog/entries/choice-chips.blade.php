<x-catalog.example title="Días de la semana" description="Variante redonda: una letra por día, con el nombre completo como etiqueta accesible.">
    <x-ui.choice-chips name="catalog-days" label="Días de la semana" shape="round" :local="true" :selected="['1', '3']"
                       :options="[1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 0 => 'Domingo']"
                       :short="[1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S', 0 => 'D']" />
</x-catalog.example>

<x-catalog.example title="Selección múltiple" description="Variante píldora con marca de selección; las opciones se ajustan en varias filas.">
    <x-ui.choice-chips name="catalog-symptoms" label="Síntomas" :local="true" :selected="['cramps']"
                       :options="['cramps' => 'Cólicos', 'headache' => 'Dolor de cabeza', 'fatigue' => 'Cansancio', 'mood' => 'Cambios de ánimo']"
                       help="Marca todo lo que aplique." />
</x-catalog.example>

<x-catalog.example title="Selección única" description="Con multiple desactivado solo una opción queda marcada.">
    <x-ui.choice-chips name="catalog-quality" label="¿Cómo dormiste?" :multiple="false" :local="true" selected="4"
                       :options="[1 => 'Muy mala', 2 => 'Mala', 3 => 'Regular', 4 => 'Buena', 5 => 'Muy buena']" />
</x-catalog.example>

<x-catalog.example title="Con error" description="El mensaje se asocia al grupo y se anuncia como alerta.">
    <x-ui.choice-chips name="catalog-days-error" label="Días de la semana" shape="round" :local="true" error="Elige al menos un día."
                       :options="[1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles']" :short="[1 => 'L', 2 => 'M', 3 => 'X']" />
</x-catalog.example>

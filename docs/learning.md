# Formación · CHG-138

La primera entrega de Formación lleva la base del prototipo Learning a la aplicación real. Los datos se guardan por usuario; recargar o salir de la cuenta no los descarta.

## Organizar estudios

Abre **Productividad → Formación**. Si personalizaste los módulos visibles, activa Formación en Ajustes → Módulos. La incorporación del módulo no cambia tu selección anterior.

El FAB **Agregar formación** abre el formulario sobre la pantalla actual. Su menú ofrece Pregrado, Especialización, Maestría, Doctorado, Curso y Ruta personal. Puedes indicar categoría, institución o plataforma, descripción y fechas. Las categorías se administran desde el menú de la tabla o del detalle; no contienen materias.

- Pregrado, especialización, maestría y doctorado: semestres y materias directas; cada semestre contiene materias y cada materia, temas.
- Curso: módulos y, dentro de ellos, temas.
- Ruta personal: etapas, cursos y temas. Una etapa contiene cursos o temas; los cursos internos siguen la misma estructura de módulos y temas.

### Vocabulario por tipo (CHG-141)

Los nombres cambian según el tipo de formación y usan jerga colombiana: pregrado y posgrados hablan de **semestres**, **materias** y **temas**; un curso, de **módulos** y **temas** (no tiene semestres); una ruta personal, de **etapas**, **cursos** y **temas**. Los botones, títulos, avisos y estados vacíos concuerdan en género y número («Agregar semestre», «Solo las materias admiten créditos»).

En **Formación → Ajustes** cada persona puede renombrar cada término (singular y plural) por tipo de formación, con vista previa y «Restaurar valores». Los valores por defecto viven en `config/learning.php`; los cambios se guardan en `module_settings` (módulo `learning`, clave `vocabulary`) y solo se guardan los que difieren del valor por defecto. Solo cambia lo que se muestra: las claves internas de tipos y contenidos no cambian. El servicio es `App\Services\Learning\LearningVocabulary`.

Solo se crea lo que agregues. **Consultar** abre un nivel; la ubicación y el regreso permiten recorrer la jerarquía. Los menús permiten editar y ordenar unidades entre sus hermanos. No se trasladan unidades a otra ubicación.

Pendiente, En curso y Completado son estados manuales: no completan niveles superiores ni descendientes. El tipo de una formación queda bloqueado cuando ya contiene unidades. Las materias admiten créditos opcionales, incluso cero, en pasos de 0,5 hasta 100.

## Accesos y eliminación

Fija cualquier formación o unidad desde su menú o la chincheta del detalle. Los accesos muestran tipo y origen, persisten entre sesiones y se actualizan al renombrar. No dependen del estado académico. Quitar un acceso conserva el registro; fijarlo otra vez lo coloca al final.

Solo se eliminan formaciones o unidades vacías, previa confirmación. Retira primero su contenido; la eliminación de un elemento vacío también limpia su acceso directo. Una categoría en uso requiere reasignar o quitar antes la categoría de sus formaciones.

En escritorio, accesos y guardián acompañan al contenido a la derecha. Por debajo de 1200 px, los accesos aparecen antes del contenido y el guardián después. Se usa el guardián real del módulo según las reglas de equipo/colección de MiSo; sin uno disponible aparece orientación textual.

## Alcance

Esta entrega no incluye materiales, asociaciones a tareas/objetivos/hábitos, evaluaciones, seguimiento de foco ni reservas. Se incorporarán en entregas posteriores. No hay importación del mockup, herramientas MCP nuevas ni premios por crear registros.

## Comprobación y despliegue

Consulta `docs/DEPLOY.md` para las migraciones. Pruebas específicas: `php artisan test --testsuite=Feature --filter=Learning`. Navegador: `npm run test:browser -- learning.interaction.spec.js --workers=1`. El recorrido está en `tests/Browser/learning.interaction.spec.js`, con fixture que solo admite su base SQLite exclusiva de pruebas.

## Evidencia local · 2026-11-16

- PHP: 47 pruebas / 429 aserciones aprobadas (Formación, categorías, preferencias y shell).
- Navegador: 4 recorridos aprobados; 375, 768 y 1440 px, menús en viewport, navegación directa, persistencia, formulario y retorno del foco con Escape, creación/borrado desde cuenta vacía y con/sin guardián. Axe en formulario y módulo, claro/oscuro, sin violaciones en las superficies revisadas; sin errores JavaScript.
- Capturas inspeccionadas en `storage/framework/testing/learning-*.png`. La fixture utiliza una especie ficticia sin imagen y muestra el marcador de posición del componente.
- `npm run build`, `npm run test:js` (9 archivos), Pint y revisión de whitespace de archivos propios aprobados.
- `ui:conformance` sigue fallando por deuda existente fuera de Formación; no reporta sus vistas nuevas. Tres fallos de ModuleGuardianTest se reproducen también con la configuración de learning retirada en una copia temporal de las pruebas; no se modificó esa selección compartida.
- No se ejecutó la suite completa ni se aplicó la migración a la base habitual/MySQL. La migración se probó en SQLite de pruebas; aplicación y comprobación en MySQL quedan para despliegue.

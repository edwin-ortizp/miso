# Favoritos y más usados en catálogos (CHG-150)

Cada persona puede marcar elementos de un catálogo como **favoritos** para encontrarlos primero al registrar, sobre todo desde el chat. Hoy lo usan **bebidas** (`drinks`), **ejercicios** (`exercise`) y **recetas** (`recipes`, sin «más usados» porque no tiene registros de uso).

## Conceptos
- **Favoritos**: los marca y desmarca la persona con la estrella; no dependen de la frecuencia de uso. Son personales (`catalog_favorites`, por usuario) y no modifican el catálogo ni afectan a otros usuarios.
- **Más usados**: los calcula el sistema con los registros de los últimos 60 días (mínimo 2). Nunca se convierten en favoritos solos: se ofrecen como «Sugeridos por tu uso» y la persona decide agregarlos.

## Dónde se ve
- **Ajustes** de Hidratación y de Ejercicio: estrella por fila, filtro «Solo favoritos», orden «Favoritos primero» (Hidratación) o grupo «Favoritos» (Ejercicio) y bloque de sugeridos.
- **Registro**: los selectores listan los favoritos primero (con ★) y siguen mostrando todo el catálogo; el widget «Agregar rápido», el chat de Hidratación y el nuevo chat de Ejercicio ofrecen favoritos primero y completan con los más usados.
- **«+1 vaso»** (notificación y bienvenida): prefiere el agua entre los favoritos o, si no la hay, el primer favorito; sin favoritos mantiene la regla de siempre.
- **MCP**: `manage-catalog-favorites-tool` (`list`, `set`, `unset`) y, al registrar por nombre, un favorito desempata nombres ambiguos.

## Cómo sumar otro catálogo
1. Registrarlo en `config/catalog_picker.php` (modelo, tabla de registros y clave foránea).
2. En su pantalla de ajustes usar el trait `WithCatalogFavorites` y el componente `x-ui.favorite-toggle`.
3. En los selectores, ordenar con `App\Services\Catalog\CatalogPicker::ordered()` o `quick()`.

`Recipe.favorite` se migró a `catalog_favorites` (CHG-153): el modelo conserva el atributo `favorite` y el MCP los parámetros `favorite` y `favorites_only`, pero los datos viven en la tabla común; la columna `recipes.favorite` queda sin uso. `MoodState.is_pinned`, `LearningShortcut` y `FavoriteCompanion` no se migran (ver `docs/ARCHITECTURE.md`).

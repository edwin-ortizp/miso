# Spec Delta

## Purpose

Permite al usuario registrar dónde se guarda físicamente cada producto de su inventario mediante un catálogo jerárquico de ubicaciones, y consultar el inventario por ubicación.

## ADDED Requirements

### Requirement: Catálogo de ubicaciones por usuario
El sistema SHALL permitir crear y renombrar ubicaciones desde los ajustes de Comida. Cada ubicación pertenece a un único usuario y nunca es visible ni asignable por otro usuario.

#### Scenario: Crear una ubicación raíz
- **WHEN** el usuario crea la ubicación "Cocina" sin ubicación padre
- **THEN** "Cocina" aparece en su catálogo de ubicaciones como ubicación de primer nivel

#### Scenario: Aislamiento entre usuarios
- **WHEN** otro usuario consulta o intenta asignar una ubicación que no le pertenece
- **THEN** el sistema no la muestra y rechaza la asignación

### Requirement: Jerarquía de dos niveles
El sistema SHALL permitir que una ubicación tenga una ubicación padre de primer nivel. Una ubicación que ya es hija MUST NOT ser elegida como padre, y una ubicación no puede ser su propio padre.

#### Scenario: Crear una sububicación
- **WHEN** el usuario crea "Nevera" con padre "Cocina"
- **THEN** "Nevera" se muestra dentro de "Cocina"

#### Scenario: Rechazar un tercer nivel
- **WHEN** el usuario intenta crear una ubicación cuyo padre es "Nevera" (que ya tiene padre)
- **THEN** el sistema rechaza la operación con un mensaje de validación

### Requirement: Nombres únicos entre hermanas
El sistema SHALL rechazar una ubicación cuyo nombre normalizado (sin mayúsculas, tildes ni espacios sobrantes) coincida con otra ubicación activa del mismo usuario bajo el mismo padre.

#### Scenario: Nombre duplicado bajo el mismo padre
- **WHEN** ya existe "Alacena 1" dentro de "Cocina" y el usuario crea "alacena 1" dentro de "Cocina"
- **THEN** el sistema rechaza la creación indicando que el nombre ya existe

#### Scenario: Mismo nombre bajo padres distintos
- **WHEN** existe "Bodega" dentro de "Patio" y el usuario crea "Bodega" dentro de "Cocina"
- **THEN** el sistema permite la creación

### Requirement: Archivar ubicaciones
El sistema SHALL permitir archivar y restaurar ubicaciones en lugar de eliminarlas. Una ubicación archivada MUST NOT ofrecerse para nuevas asignaciones, pero los productos que ya la tienen conservan la asignación. Archivar un padre archiva también sus hijas.

#### Scenario: Archivar una ubicación con productos
- **WHEN** el usuario archiva "Alacena 2", que tiene productos asignados
- **THEN** los productos conservan "Alacena 2" y esta deja de aparecer como opción al asignar

#### Scenario: Restaurar una ubicación
- **WHEN** el usuario restaura una ubicación archivada
- **THEN** vuelve a estar disponible para asignar

### Requirement: Ubicación habitual del producto
El sistema SHALL permitir asignar a cualquier producto, sin importar su categoría, una ubicación habitual opcional, y quitarla. Un producto tiene como máximo una ubicación habitual.

#### Scenario: Asignar ubicación a un producto de limpieza
- **WHEN** el usuario edita "Detergente" y elige la ubicación "Patio › Bodega"
- **THEN** el producto queda guardado con esa ubicación habitual

#### Scenario: Quitar la ubicación
- **WHEN** el usuario deja vacía la ubicación de un producto y guarda
- **THEN** el producto queda sin ubicación

### Requirement: Filtrar productos por ubicación
El catálogo de productos SHALL permitir filtrar por una o varias ubicaciones. Seleccionar una ubicación padre MUST incluir los productos de sus ubicaciones hijas. El filtro SHALL ofrecer la opción "Sin ubicación" y mostrarse como chip removible.

#### Scenario: Filtrar por ubicación padre
- **WHEN** el usuario filtra por "Cocina"
- **THEN** ve los productos asignados a "Cocina", "Nevera", "Alacena 1" y demás hijas de "Cocina"

#### Scenario: Productos sin ubicación
- **WHEN** el usuario filtra por "Sin ubicación"
- **THEN** ve solo los productos que no tienen ubicación habitual

### Requirement: Uso visible en el catálogo
El catálogo de ubicaciones SHALL mostrar cuántos productos tiene asignados cada ubicación.

#### Scenario: Conteo de productos
- **WHEN** "Nevera" tiene 12 productos asignados
- **THEN** el catálogo muestra 12 junto a "Nevera"

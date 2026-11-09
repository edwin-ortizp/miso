# Comidas, preparaciones e inventario

La planificación no cambia el stock. Ninguna migración infiere consumos ni vuelve a sumar compras antiguas.

## Operaciones

- **Planificar:** recetas y porciones, elementos libres con ingredientes opcionales, o porciones de una preparación. Las preparaciones se reservan al planificar; no es posible reservar más que su saldo. Los ingredientes de recetas son para su rendimiento total (`servings`).
- **Cocinar:** descuenta ingredientes proporcionalmente a las porciones producidas y guarda una preparación con ingredientes y nutrición copiados. Cocinar seis porciones de una receta que rinde seis utiliza una vez sus ingredientes.
- **Consumir:** aplica a la casilla completa. Descuenta ingredientes de recetas y elementos enlazados, o porciones preparadas. Los elementos libres sin enlaces se registran sin descuento y con advertencia de inventario incompleto.
- **Comida por fuera:** conserva los componentes planeados, no descuenta nada y libera sus reservas. Notas y calorías consumidas son independientes de las del plan; se admite una casilla sin plan anterior.
- **Revertir:** devuelve los movimientos originales y restaura las reservas. No recalcula con el catálogo o la receta actuales. Si cambió la unidad base de un producto, se rechaza hasta resolver la incompatibilidad. También se rechaza si una reserva liberada por comida por fuera ya no puede recuperarse.
- **Cancelar preparación:** devuelve ingredientes únicamente cuando no tiene consumos ni reservas. Se conserva el historial y no se elimina la preparación.

`MealInventory` coordina comidas y reservas; `InventoryLedger` es la frontera común de escritura de existencias para compras, comidas, conteos, altas y fusiones. Usa transacción, bloqueo del usuario primero y después productos/preparaciones en orden estable. Repetir una clave con los mismos datos devuelve el resultado registrado; con otros datos se rechaza. El libro `meal_inventory_movements` conserva cantidades efectivas y unidades; una reversión agrega movimientos opuestos y marca la operación original. Los faltantes o cantidades incompatibles revierten la operación completa, incluido el documento origen.

## Documentos y cantidades

Cada operación efectiva de inventario tiene un documento UUID propio (`meal_inventory_operations`), diferente del ID de compra/comida origen. La cabecera conserva tipo, usuario, canal, motivo, referencia al origen y fecha/hora de registro. Una fecha efectiva conocida solo por día se guarda como fecha, sin inventar hora. `document_lines` es un snapshot histórico con identidad/posición de línea, nombre/variante, unidad, cantidad, delta, saldos y referencias a movimientos. Una compra de 32 productos afectados conserva sus 32 líneas; variantes repetidas conservan sus líneas separadas. No hay importes, impuestos ni valoración contable del stock.

Las cantidades se calculan en milésimas enteras y se persisten con tres decimales. El stock admite 0 a 999999999.999. Un conteo fija el total: de 8 a 7 genera -1; contar de nuevo 7 deja evidencia de delta 0. Guardar solo metadatos no genera un movimiento de cantidad. En el editor de ingredientes, un stock que no se modificó en el formulario no sobrescribe existencias cambiadas mientras estaba abierto; el motivo predeterminado es «Conteo físico».

Tipos: apertura, compra/corrección de compra, cocción, consumo, reversión, conteo, ajuste, fusión y reclasificación de unidad. Planificar, reservar, generar lista de compras, comer fuera y actualizar precios/ubicaciones no descuentan stock. Las porciones preparadas tienen movimientos propios; consumirlas no vuelve a descontar ingredientes.

Cada corrección conserva el documento anterior y añade otro enlazado; las reversiones identifican los movimientos compensados. Editar/eliminar líneas actuales de una compra no altera snapshots pasados. Un reintento no vuelve a aplicar cantidades ni duplica precios asociados a una actualización MCP con clave estable. Las operaciones internas de una comida usan su documento externo, sin generar otro por cada llamada al motor.

`InventoryMovementRecorded` se emite después del commit exterior con userId, operationId, movementId y origen. Sirve como notificación para consumidores futuros; un listener no debe volver a descontar inventario. No incluye una cola/outbox ni garantiza entrega a sistemas externos.

CHG-001 conserva la evidencia del motor; CHG-002 la consulta desde la web y MCP, sin nuevas escrituras.

### Consultar documentos y Kardex

En **Comidas → Movimientos de inventario** (`/meals/inventory`) se listan los documentos del usuario. Cada fila corresponde a una operación o revisión y muestra referencia, fecha/hora de registro, fecha efectiva, tipo, canal, motivo y contadores separados de productos y líneas. Las operaciones internas sin evidencia de inventario y la planificación sin movimiento quedan fuera.

**Abrir** lleva a `/meals/inventory/{operation}`: ID UUID completo copiable, cabecera, origen disponible, documento corregido/correcciones y líneas originales. Una compra de 32 productos muestra un documento con sus 32 líneas completas, incluso si el registro se filtró por un solo ingrediente. Las variantes cuentan como líneas distintas del mismo producto; no se suman unidades incompatibles. Editar una compra no reescribe el documento original. Las correcciones y compensaciones permanecen enlazadas.

**Ver Kardex**, desde cada línea o el menú de Ingredientes, abre `/meals/ingredients/{item}/inventory`. Muestra stock actual separado del historial, inicio de cobertura, tramos por unidad y saldos antes/después persistidos. Filtrar o paginar nunca recalcula esos saldos. Los movimientos anteriores a la apertura conservan saldo `null` y se identifican como legado. Una identidad fusionada conserva su historia y enlaza al ingrediente actual; el destino permite consultar sus predecesores por separado, sin mezclar saldos.

El registro admite búsqueda por ID, referencia, motivo o nombre histórico, fechas efectivas inclusivas (con fecha de registro como respaldo), tipo, canal e ingrediente. El Kardex admite fechas, tipo y canal. Registro/Kardex muestran 50 filas por defecto; detalle muestra 100 líneas y paginación explícita si hay más. Volver conserva los filtros y la página de origen mediante rutas locales permitidas. Un origen eliminado conserva su referencia histórica sin ofrecer un enlace inexistente.

| Herramienta MCP de solo lectura | Entrada y salida |
| --- | --- |
| `list-inventory-documents-tool` | Opcionales `from`, `to` (YYYY-MM-DD), `type`, `channel`, `item_id`, `search`, `page`, `per_page`. Devuelve `documents`, `pagination`, `total_documents`. |
| `get-inventory-document-tool` | `operation_id` obligatorio; `page`, `per_page` opcionales (100 por defecto). Devuelve `document`, `lines`, `corrections`, `pagination`; 32 líneas caben en una sola respuesta. |
| `list-inventory-movements-tool` | `item_id` obligatorio, incluso UUID histórico fusionado; fechas, tipo, canal y paginación opcionales. Devuelve `product` con stock/unidad actual y sucesión, `coverage_started_at`, `movements`, `total_movements`, `pagination`. |

Todas usan propietario explícito, máximo 100 filas y `pagination.next_page` (null al terminar). Los documentos incluyen identidad, fecha efectiva/registro, origen, canal, motivo, contadores y enlaces de corrección. Las líneas/movimientos incluyen snapshot de nombre/unidad, delta, saldos originales y compensación. No crean aperturas ni modifican existencias. Un ID ajeno o inexistente produce el mismo error genérico. Tipos: `opening`, `purchase`, `purchase_correction`, `consume`, `cook`, `reversal`, `count`, `adjustment`, `merge`, `reclassification`. Canales: `web`, `mcp`, `system`, `import`, `legacy`.

## Apertura y activación

La migración `2026_11_02_000001_extend_inventory_ledger.php` añade el contrato, sin alterar el stock ni reconstruir compras/consumos pasados. La apertura registra el stock vigente desde cero; no se suma otra vez a las existencias. Los movimientos anteriores se conservan con saldos desconocidos y fuera del acumulado posterior. Un alta con unidad conocida recibe apertura incluso con stock cero. Un alta incompleta de catálogo con stock cero y unidad desconocida conserva ese estado; no se inventa una unidad y la apertura queda pendiente hasta definirla.

Previsualizar (solo lectura), opcionalmente limitando usuarios:

```console
php artisan inventory:initialize-ledger
php artisan inventory:initialize-ledger --user=1 --user=2
```

Muestra cada producto, saldo/unidad y estado pendiente, inicializado o bloqueado. Falta de unidad, saldo negativo, cambio de unidad sin reclasificar o stock modificado fuera del motor bloquean la selección sin corregir datos automáticamente. Reejecutar una apertura ya registrada no duplica nada ni cambia el saldo actual.

Aplicación explícita, exclusivamente después de autorizar el entorno y preparar la activación:

```console
php artisan inventory:initialize-ledger --user=1 --apply
```

Pausar las escrituras de inventario, aplicar la migración autorizada, revisar/corregir los bloqueos, ejecutar las aperturas y activar todas las fuentes del motor juntas antes de reanudar escrituras. Toda la selección se aplica en una transacción y revalida bajo bloqueo; un fallo revierte también usuarios procesados previamente. La primera escritura cubre bajo bloqueo cualquier producto omitido, sin rehacer la historia.

Antes/después, verificar stock idéntico y una apertura por producto/tramo; comprobar idempotencia en una reejecución. Si hay diferencia fuera del motor, investigar su causa y conciliar explícitamente: no ejecutar una nueva apertura para ocultarla. Recuperación operativa: pausar escrituras, conservar respaldo/libro y restaurar un artefacto compatible. No usar un rollback destructivo que borre evidencia como estrategia operativa.

## Cambios de unidad y fusiones

No hay conversión implícita entre g, ml y unit. Un cambio de unidad cierra el tramo anterior y abre otro con el total suministrado; si se omite, se conserva el número conforme al contrato MCP existente. Conservar el número no convierte físicamente una cantidad. Los saldos se interpretan por tramo/unidad, nunca sumando unidades incompatibles; una reversión con unidad incompatible permanece bloqueada.

Una fusión transfiere saldo del origen al destino en una operación y unidad compatible. El origen deja saldo cero antes de su eliminación; nombres, UUID y unidades históricos sobreviven. La FK viva de sus movimientos se desliga, sin reasignar su identidad histórica. `inventory_product_successors` conserva la sucesión por usuario, incluso encadenada, para reversiones y compras posteriores. Se rechazan ciclos, productos ajenos o unidades incompatibles. Variantes, recetas, alias y referencias actuales de compras mantienen el comportamiento de combinación existente.

## Escrituras MCP de inventario

`create-inventory-item-tool` y `update-shopping-item-tool` aceptan `operation_key` UUID estable y `reason` opcionales. Reutilizar la clave solo con los mismos datos; omitir stock en una actualización lo conserva. El motivo por defecto de un conteo es «Conteo físico» y el canal se registra como MCP. Una creación con existencias es apertura, no compra. Un producto no mencionado no se lleva a cero automáticamente.

Las compras conservan sus claves y snapshots propios. La creación con clave reutilizada y otros datos se rechaza; cada revisión de cantidades deja un documento enlazado al anterior. Las ediciones con clave nueva son idempotentes; clientes antiguos que reenvían la clave original de creación se mantienen compatibles al editar, pero deberían enviar una clave nueva por edición.

## Web y MCP

La web dispone del editor semanal, **Preparaciones**, **Qué cocinar** y la previsualización **Generar compras del plan** desde el FAB de Compras. Las acciones de mover/copiar/intercambiar operan sobre la comida guardada. Para cambiar solo un componente se edita la composición y se guarda la casilla.

Herramientas:

| Herramienta | Contrato |
| --- | --- |
| `plan-meal-tool` | Conserva fecha, tipo, elementos, notas, calorías y append/replace. Agrega `operation_key`, `preparation_id` e ingredientes `{shopping_item_id, quantity, unit}` para elementos libres. |
| `list-meal-plan-tool` | Conserva `items` textual y agrega `components` con ids, ingredientes, estado, consumo y preparación. Separa `planned_calories` y `consumed_calories`; el total diario legado `calories` corresponde a planes pendientes. |
| `consume-meal-tool` | `action=consume/revert`, `meal_id`, `mode=home/outside`, fecha efectiva, notas y calorías opcionales; `operation_key` obligatoria. Para registrar por fuera sin plan, enviar fecha y tipo en lugar del id. |
| `manage-meal-tool` | `action=edit/move/copy/swap/delete`, `meal_id`, clave estable. Editar envía todos los componentes, identificando los existentes. Mover/copiar/intercambiar exige fecha y tipo destino. |
| `manage-preparation-tool` | `action=cook/list/detail/cancel`. Cocinar necesita receta, porciones y clave; fecha de cocinado y fecha límite opcionales. Consultas incluyen saldo, consumo y reservas. |
| `suggest-meals-tool` | Porciones y horizonte de vencimiento (7 días por defecto). Disponibilidad, cantidades necesarias/faltantes y fechas de productos/preparaciones, separados en vencidos y próximos. |
| `generate-meal-shopping-tool` | `action=preview/generate`, intervalo y elecciones `{shopping_item_id, variant_id}`. Generar requiere clave estable. Usa la presentación preferida comparable o exige una elección. |

Mover añade al destino ocupado y combina porciones si la misma receta ya está allí. Copiar conserva el origen y valida reservas adicionales. Intercambiar conserva ids de componentes; las comidas consumidas exigen reversión antes de modificar o eliminar. No hay API REST adicional.

## Necesidades y fechas

`MealNeeds` comparte el cálculo entre web y MCP: solo planes pendientes, excluyendo ingredientes de preparaciones cocinadas. Normaliza unidades, agrupa productos y resta el stock una vez. Las cantidades incompatibles quedan incompletas. Generar compras calcula paquetes con redondeo hacia arriba y conserva el máximo entre la cantidad manual y la necesaria; no acumula paquetes al repetirlo.

Las fechas son `consume_by` por producto y una fecha opcional por preparación; **no representan lotes**. Las sugerencias y operaciones avisan de fechas próximas o pasadas, sin bloquear automáticamente alimentos. La migración añade tablas y campos; no hace modificaciones retroactivas del inventario. Aplicar las migraciones en el entorno de destino antes de usar las superficies nuevas.

## Validación

Pruebas de consulta: `InventoryHistoryTest`, `InventoryDocumentsTest`, `InventoryHistoryMcpTest` e `InventoryDocumentsMcpTest` cubren 32/101 líneas, filtros, retornos, aislamiento, orígenes eliminados, tramos y ausencia de escrituras/N+1. Los recorridos de registro → documento → Kardex se verifican a 375/768/1280 px. La bitácora de CHG-002 distingue contenido nuevo validado de deuda preexistente en navegación compartida y composer móvil.

Pruebas focalizadas del motor: `InventoryLedgerTest`, `InventoryLedgerInitializationTest`, `MealInventoryTest`, `PurchaseHistoryTest`, `IngredientMergeTest`, importaciones y contratos MCP existentes. La prueba MySQL `InventoryLedgerConcurrencyTest` requiere `INVENTORY_MYSQL_DATABASE=miso_inventory_test_*` e `INVENTORY_MYSQL_PORT` en un puerto de pruebas distinto de 3306, con servidor desechable en loopback. Sin esas variables se omite intencionalmente; valida migración down/up y dos procesos sincronizados para compra/consumo, conteo/consumo y fusión/compra. Nunca dirigirla al servidor habitual ni a datos personales.

El navegador usa exclusivamente `storage/framework/testing/purchase-browser.sqlite` y verifica ese destino antes de inicializarlo. Los escenarios cubren compras, comidas y conteos con motivo, validación, conservación de borrador y reintento HTTP. El formulario modificado se comprueba a 375/768/1280 px con accesibilidad. Las evidencias y fallos preexistentes de la suite general se detallan en la bitácora de CHG-001; no equivalen a validación del despliegue real ni a ausencia de deuda en otras pantallas.

Orden de momentos: Desayuno, Onces (mañana), Almuerzo, Merienda (tarde), Cena. Se conserva el identificador histórico `comida` para Onces, sin eliminar registros.

### Presentación tabular del inventario (CHG-018)

El registro general muestra un documento por fila, con fechas, tipo, origen, canal y contadores. El detalle utiliza una hoja documental con cabecera, ID copiable, líneas y observaciones/correcciones; la referencia estructural es el ajuste de inventario emitido de Siigo X, adaptado a MiSo.

Las líneas y el kárdex separan entrada y salida y muestran unidad, saldo anterior y saldo posterior persistidos. «—» indica que no hay entrada o salida; una diferencia cero conserva ambos saldos. «Saldo no disponible» identifica saldos históricos desconocidos. Los tramos de unidad y el historial anterior a la apertura siguen separados; no se suman cantidades entre unidades. En móvil las filas se apilan con etiquetas, manteniendo todos los campos. Los filtros y el retorno conservan su comportamiento.

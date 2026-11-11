# UI Design

## Responsive del panel lateral

- Antes de decidir dónde vive un elemento del panel lateral, comprueba cómo se reacomoda el panel por debajo de 1200 px: el panel baja debajo de la lista completa, así que lo que quede ahí puede volverse inalcanzable en móvil cuando la lista es larga.

## Capas y pantallas completas

- Las pantallas completas que se rendericen dentro de `x-module-shell` deben teletransportarse a `body` con `x-teleport`. Un `z-index` alto no basta: el shell crea un stacking context y la barra inferior del celular las tapa.
- Cuando teletransportes con `x-teleport`, la raíz teletransportada debe llevar un `wire:key` fijo; las keys dinámicas van en un elemento hijo. Con una key dinámica en la raíz, la pantalla se quedó congelada al navegar (CHG-046).

## Tarjetas y rejillas

- Dimensiona las tarjetas según su contenido: no iguales alturas ni empujes acciones al pie cuando eso deje vacíos sin función; el espacio vacío debe responder a jerarquía, agrupación o interacción. En escritorio, agrupa en horizontal los datos relacionados cuando el ancho conserve su legibilidad, y adapta las columnas de las rejillas al ancho disponible y al contenido en vez de fijar pocas columnas para todos los escritorios. En CHG-083 hizo falta una segunda revisión para compactar racha/reto y mostrar más Pokébolas por fila; los 240 px y las seis columnas de ese caso no son límites universales.

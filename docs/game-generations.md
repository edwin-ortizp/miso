# Generaciones personales de Colección (CHG-104)

El catálogo `game_species` es común. `generation` pertenece a la especie; la selección personal vive en `module_settings`, módulo `game`, clave `enabled_generations`. Si no existe, se usa `[1]` para usuarios nuevos y existentes. El campo global `active` conserva su significado administrativo.

## Ajustes

`/game/settings` abre General. Las secciones `/generations`, `/rewards`, `/companion` y `/celebrations` tienen rutas propias, navegación compartida con Ajustes y guardado independiente. El formulario protege cambios pendientes mediante `x-dirty-guard`. Antes de salir tras confirmar el descarte, el evento opcional `miso-form-discard` permite esperar (`detail.waitUntil`) a `restoreSavedSettings()` y al render; así la caché de historial de Livewire conserva los valores guardados. Si falla la restauración, se cancela la salida y el formulario sigue pendiente. Debe permanecer al menos una generación activa.

La pantalla Generaciones muestra nueve tarjetas y progreso histórico, incluso para generaciones en pausa. El resumen cuenta las especies de la selección guardada. Cambiar interruptores requiere guardar; no otorga recompensas. No se puede elegir como favorito el compañero de otra persona.

## Descubrimiento

`SpeciesCatalog` recibe siempre el ID del usuario. Su catálogo limita futuros encuentros, evoluciones, álbumes y el contexto del director. La colección conserva descubrimientos de generaciones pausadas y los identifica; no revela sus especies pendientes.

Una especie cuyo antecesor está fuera del catálogo disponible puede iniciar su familia: Pikachu es obtenible con solo generación 1, aunque Pichu pertenezca a generación 2. Para catálogos antiguos sin antecesor explícito se usa la etapa previa de la misma familia.

Las Pokébolas pueden descubrir especies pendientes de una familia conocida cuando se haya descubierto su antecesor disponible y el compañero alcance el nivel requerido. Conservan la forma actual del compañero y suman experiencia. Esto permite descubrir todas las ramas de Eevee sin crear más compañeros por familia. La protección de novedad distingue especies y considera las Pokébolas ya asignadas para no prometer dos veces la misma novedad.

Una Pokébola pendiente conserva su especie aunque después se pause esa generación. Su apertura es idempotente. Equipo, favoritos, guardianes, experiencia y descubrimientos no se eliminan al cambiar la selección.

## Progreso y recomendaciones

La generación se completa al descubrir todas sus especies activas, incluidas sus evoluciones; no basta con un integrante por familia. Se cuentan descubrimientos históricos y especies actuales sin duplicados. La siguiente generación inactiva puede recomendarse al completar una activa; la recomendación lleva a los ajustes y nunca activa nada automáticamente. «Ahora no» se guarda por usuario y generación completada en `dismissed_generations`.

Los álbumes visibles usan la selección personal. La recompensa sigue siendo única por usuario y tipo (`game_album_awards`), sin reinicios al pausar o reactivar generaciones. El guardado de ajustes nunca entrega premios. Los destacados incompatibles de temporadas no amplían el catálogo: el sorteo sigue dentro de la selección personal.

## Validación

- `php artisan test --filter="GenerationsTest|ImportGameSpeciesTest"`
- `php tests/Support/verify-game-generations-mysql.php`: crea una base con nombre aleatorio `miso_chg104_disposable_*`, verifica 1.025 mapeos, preservación de descripciones, rollback y rechazo previo al DDL; finalmente elimina solo esa base. Necesita permisos de crear y eliminar bases desechables.
- `npx playwright test --config tests/Browser/game-generations.config.js`: SQLite y usuario ficticios dedicados; navegación, guardado, teclado, accesibilidad y capturas en móvil/escritorio y preferencias de color claras/oscuras del navegador. El sistema vigente aún no define una paleta oscura global completa; esas comprobaciones no certifican un tema oscuro propio. No usa cuentas ni datos reales.

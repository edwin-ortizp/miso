# CHG-083 · Pulir álbumes y rediseñar Balls como aventura Pokémon
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-11 · **Aprobado:** 2026-11-11 · **Cerrado:** 2026-11-11
**Relacionado:** —

## 0. En palabras simples
- Qué vas a ver distinto: álbumes sin scroll sobre las tarjetas; racha y reto como tarjetas de aventura; Pokébolas con volumen.
- Cómo comprobarlo tú: abre Colección → Balls y Colección. Pulsa «?» en la racha y abre una ball.

## 1. Resumen
Rediseño visual de Balls para alinearlo con Colección y Semana. Separación de 12 px bajo los álbumes. Conserva las reglas y el diseño de Inicio.

## 2. Producto (product-manager)
- Problema: scroll pegado a las tarjetas y presentación de progreso poco atractiva y poco clara.
- Incluye: racha, ayuda modal, reto, arte de Pokébolas y scroll de álbumes.
- No incluye: reglas del juego, recompensas, datos personales, Inicio o tipografía.
- Aceptación: tarjetas completas sin desbordamiento global; avance legible; explicación por botón; apertura preservada.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
Dos tarjetas contiguas desde 900 px, apiladas por debajo. La racha conserva días, progreso, semana, récord y gracias. El reto muestra objetivo completo, progreso y enlace al módulo. Completado muestra sello sin acción redundante. La ayuda usa el diálogo canónico, Escape y restitución del foco.

### 3.2 Visual (ui-designer)
```text
[Racha · ?         ][Reto de hoy         ]
[Llama · días      ][Emblema · objetivo  ]
[Semana · progreso ][Avance · acción     ]
[Récord · gracias  ][Confirmación        ]
[Poké Ball][Poké Ball][Poké Ball]
```
Reutiliza tokens Material, acciones y diálogo x-ui. Llama cálida, sellos semanales con iconos y texto accesible; Pokébolas con reflejos y sombras sobre un escenario radial. Sin animaciones decorativas continuas; se conserva movimiento reducido de revelación.

### 3.3 Escritura (ux-writer)
«Tu racha», «Un día a la vez», «Cómo funciona la racha», «Reto de hoy», «Tu misión del día», «Ir a Hidratación/Hábitos/Tareas/Inicio» y «Cumplido. Mañana hay otro.». Mantener cifras dinámicas y orientación sin culpa. No existe docs/UX-WRITING.md en este checkout.

## 4. Diseño técnico (software-architect)
Componentes streak-card y daily-challenge, vista game-balls y CSS del módulo. Variante independiente de reto para Balls; rama de Inicio conservada. Arte opening-ball elaborado mediante CSS sin cambiar su anatomía ni la secuencia de apertura. Padding del rail reserva 12 px y 3 px para foco. Sin migraciones, servicios ni contratos nuevos.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Resultado |
|---|---|---|
| Racha inicial, activa, cumplida, interrumpida y falta de otra área; iconos de gracia | PHP/Livewire | Correcto |
| Reto completo, navegación pendiente y regresión de Inicio | PHP/Livewire | Correcto |
| Álbumes, vistas y apertura | PHP/Livewire | Correcto |
| 360, 768 y 1440 px: ayuda, foco, accesibilidad axe, scroll y apertura | Playwright con SQLite aislado | Correcto |
| Secuencia de revelación y movimiento reducido | JavaScript | Correcto |

Comandos:
- `php artisan test --filter="GameBallsAdventureTest|DailyChallengeViewTest|GameVisualTest|GameUiTest|GameCollectionAlbumsTest|StreakTest"`: 36 pruebas, 202 aserciones.
- `npx playwright test --config=tests/Browser/game-balls.config.js`: 7 pruebas; tres anchos con atributo de tema light/dark y apertura. Los tokens globales actuales siguen siendo claros: no se presenta esto como validación de una paleta oscura global.
- `node --test tests/JavaScript/game-reveal.test.js`: 3 pruebas.
- `npm run build`: correcto; aviso de tamaño de chunks existente.
- `php artisan ui:conformance`: falla por desviaciones en focus-companion, game-collection, guardian-card, checkin-sheet y task-list, archivos ajenos al cambio. Sin hallazgos en los componentes modificados.
- `git diff --check` sobre archivos del cambio: correcto.

Capturas locales en storage/framework/testing/balls-*.png y albums-*.png. Navegador Chromium; no se verificaron otros motores ni la preferencia de scrollbar de Windows fuera de Chromium.

## 6. Documentación (tech-writer)
Este CHANGE registra el alcance, las decisiones, la validación y las limitaciones.

## 7. Tareas
- [x] T1 · Separar scroll de álbumes y rediseñar racha con ayuda modal — Resp: developer — Modelo: estandar — Hecho cuando: rail conserva 12 px y ayuda funciona con teclado.
- [x] T2 · Presentar reto y Pokébolas como aventura sin alterar Inicio — Resp: developer — Modelo: estandar — Hecho cuando: estados y apertura se preservan.
- [x] T3 · Validar vistas, assets, conformidad e interacción responsive — Resp: developer — Modelo: estandar — Hecho cuando: comprobaciones registradas.

## 8. Bitácora de implementación (developer)
2026-11-11: implementado y validado localmente. Se inició con --force para coexistir con CHG-081, sin modificar su estado ni su alcance. Preservados los cambios locales de Inicio y tipografía. Datos sintéticos exclusivamente en SQLite de pruebas; sin commits, despliegues ni cambios de datos reales. Pendiente revisión visual del usuario; conformidad global bloqueada por desviaciones ajenas.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-11 — Creado en to-do/ (Borrador, nivel M)

Plan aprobado explícitamente por el usuario el 2026-11-11. Alcance: Balls y scroll; dirección aventura Pokémon. Pruebas: vistas Game, revelación JS, build, conformance y navegador a 360/768/1440. Preservar trabajo local.
- 2026-11-11 — Aprobado por el usuario (Compuerta 2)
- 2026-11-11 — Movido a in-progress/ (En desarrollo)
- 2026-11-11 — Implementación terminada → En verificación
- 2026-11-11 — Reabierto: Revisión del usuario: compactar tarjetas en desktop y ampliar balls por fila

### Revisión visual del usuario · 2026-11-11
- Compactado el escritorio: días en cabecera y semana junto al progreso desde 1200 px. Resumen de unos 240 px en la captura de 1440 px, frente a unos 335 px anteriores. El reto deja de estirarse y su acción/confirmación queda junto al avance.
- Rejilla auto-fit con mínimo de 220 px: comprobadas cinco tarjetas por fila a 1680 px y seis a 1920 px con sidebar abierto. Arte y cuerpo más compactos desde 900 px. Móvil preservado.
- Build correcto. Tres pruebas responsive light a 360/768/1440 y una prueba adicional de seis balls y altura del resumen correctas. Diff check correcto. ui:conformance conserva los fallos ajenos descritos arriba.
- Configuración actual excluye check-in de nuevas propuestas. DailyChallenge conserva un reto ya cumplido aunque se retire su plantilla. No se modificaron servicios, configuración ni retos existentes.
- 2026-11-11 — Implementación terminada → En verificación

### Lecciones reutilizables · 2026-11-11
- LECCIÓN(ui-designer): Dimensionar las tarjetas según su contenido: evitar alturas igualadas o acciones empujadas al pie cuando generen vacíos sin función. En escritorio, agrupar datos relacionados en horizontal cuando el ancho permita conservar su legibilidad; adaptar las columnas de las rejillas al ancho disponible y al contenido, en vez de fijar pocas columnas para todos los escritorios. El espacio vacío debe responder a jerarquía, agrupación o interacción. Evidencia: CHG-083 necesitó una segunda revisión para compactar racha/reto y mostrar más Pokébolas por fila; los 240 px y las seis columnas son resultados de ese caso, no límites universales. → docs/UI-DESIGN.md
- LECCIÓN(ux-designer): En tarjetas de seguimiento, priorizar el estado actual, el progreso y la siguiente acción. Mostrar las explicaciones secundarias mediante ayuda contextual cuando no sean necesarias para actuar; conservar visibles las instrucciones esenciales. El disparador debe tener nombre accesible y la ayuda debe poder abrirse y cerrarse con teclado, restituyendo el foco cuando sea un diálogo. No imponer un acordeón como solución predeterminada. Evidencia: en CHG-083 la explicación de la racha pasó de un acordeón a un botón de ayuda con diálogo canónico. → docs/UX-DESIGN.md
- LECCIÓN(qa-tester): En cambios de tarjetas y rejillas, revisar móvil y escritorio con pocos y muchos elementos, textos largos y estados pendientes/completados. Comprobar el aprovechamiento del espacio además de desbordamientos: alturas forzadas, grandes vacíos sin función, acciones innecesariamente separadas y columnas que no aprovechan el ancho. Verificar legibilidad y acceso por teclado a la ayuda contextual. Evidencia: CHG-083 pasó las comprobaciones iniciales de responsive y accesibilidad, pero la revisión del usuario detectó espacio vertical desperdiciado; la corrección se comprobó con cinco y seis tarjetas por fila. No extrapolar esas cantidades ni alturas a otras pantallas. → docs/TESTING.md
- 2026-11-11 — Cerrado por el usuario (Compuerta 3) → done/ (desde el panel)

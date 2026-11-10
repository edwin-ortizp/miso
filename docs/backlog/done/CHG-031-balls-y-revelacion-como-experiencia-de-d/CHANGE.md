# CHG-031 · Balls y revelación como experiencia de descubrimiento
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
**Creado:** 2026-11-09 · **Aprobado:** 2026-11-09 · **Cerrado:** 2026-11-10
**Relacionado:** CHG-029
## 0. En palabras simples
Cada ball tiene su carta con la tarea completa. Al abrirla, se ve una Poké Ball que se abre y presenta al compañero y su descripción.
## 1. Resumen
Plan aprobado en conversación: cartas independientes, nueva animación, footer fijo y completar descripciones españolas del catálogo local.
## 2. Producto (product-manager)
Incluye cartas responsive, revelación accesible, metadatos y sincronización faltantes. No modifica recompensas, misiones ni datos personales. Sin despliegue.
Aceptación: tarea completa visible, una apertura a la vez, resultado persistente antes de animar y acciones siempre en footer.
## 3. UX/UI
### 3.1 Experiencia (ux-designer)
Elegir carta → apertura → resultado → ficha u otra ball. Cierre y salto de animación disponibles. Vacío/error conservan las balls.
### 3.2 Visual (ui-designer)
Tres columnas escritorio, dos tablet, una móvil. Poké Ball roja/blanca con banda negra, dos mitades animables. Tokens de UI y modal canónico.
```text
[ Carta: ball / actividad / título completo / fecha / Abrir ]
[ Modal: cabecera y cierre ]
[ ball se abre → Pokémon / tipos / rareza / descripción ]
[ Footer: Ver ficha | Abrir otra ]
```
### 3.3 Escritura (ux-writer)
Abrir Poké Ball; Mostrar ahora; Aún no tenemos una descripción de este Pokémon. Mensajes cálidos sin inventar recompensas.
## 4. Diseño técnico (software-architect)
GameBalls, vistas/componentes game, CSS y controlador Alpine con limpieza de timers. Apertura idempotente sin red externa.
Comando game:sync-species-descriptions con dry-run y number: PokéAPI por número, solo español, caché, timeout/reintentos; completa únicamente vacías.
## 5. Pruebas (qa-tester)
PHP/Livewire para aperturas y catálogo, HTTP simulado, JavaScript para animación, navegador con fixtures y cuatro anchos; build y conformance.
## 6. Documentación (tech-writer)
Actualizar docs/game.md con sincronización y experiencia. Guías de equipo ausentes en checkout: se usa docs/design-system.md.
## 7. Tareas
- [x] T1 · Cartas y revelación con footer fijo — Resp: developer — Modelo: estandar — Hecho cuando: responsive y accesible, sin cambiar recompensas.
- [x] T2 · Descripciones españolas — Resp: developer — Modelo: estandar — Hecho cuando: importador probado y catálogo local completado tras simulación.
- [x] T3 · Validar y documentar — Resp: developer — Modelo: estandar — Hecho cuando: pruebas, build, conformance y navegador verificados.
## 8. Bitácora de implementación (developer)
## 9. Preguntas abiertas, decisiones e historial
Sin preguntas abiertas. Aprobación explícita del plan en conversación.
- 2026-11-09 — Aprobado por el usuario (Compuerta 2)
- 2026-11-09 — Movido a in-progress/ (En desarrollo)

### Implementación y comprobaciones finales
- Cartas independientes con título completo y acción de apertura; las tres acciones se deshabilitan durante una solicitud Livewire pendiente.
- Poké Ball roja/blanca/negra con mitades animables, balanceo y botón iluminado; revelar a los 1.850 ms, saltar, reiniciar y limpiar temporizadores. Movimiento reducido muestra el resultado de inmediato. Sin red externa durante la apertura.
- Tipos, rareza y descripción del catálogo; en evolución se identifica explícitamente la especie original del texto. Se conservan experiencia, nivel y resultado idempotente. Footer fijo con Ver ficha y Abrir otra; cabecera y cierre conservados.
- Ajuste semántico del diálogo estándar: header/footer de la ventana son presentación, no landmarks globales duplicados. Región de descripción enfocable para desplazamiento con teclado.
- Sincronizador probado con HTTP simulado, selección estable de español, normalización, caché y conservación de ediciones concurrentes.
- Verificado el destino local: environment=local, MySQL localhost, base lifetracker-local. Simulación y aplicación: completadas=0, conservadas=1025, sin español=0, errores=0. Todas las descripciones ya existían; no se sobrescribieron ni se cambiaron otros datos.
- 27 pruebas PHP pasan (GameUiTest, EncounterOpenerTest, CompanionProgressTest, SyncSpeciesDescriptionsTest y contrato semántico de diálogo), 3 pruebas JavaScript de la animación pasan. Pint, build, ui:conformance y git diff --check pasan. Build conserva el aviso de tamaño de chunks existente.
- Playwright sobre HTML real renderizado por Laravel con datos ficticios, assets/JavaScript reales: 360/768/1280/1600, cuadrícula 1/2/3 columnas, sin overflow, footer visible con bio larga, foco de teclado y axe sin infracciones en cartas/modal; contraste en oscuro comprobado.
- Verificados en navegador los estados shake/open/revealed con capturas, salto y cierre. Petición de apertura interceptada para comprobar el bloqueo de botones sin modificar datos reales. Persistencia, repetición y evolución verificadas en pruebas PHP/Livewire.
- Capturas: storage/framework/testing/balls-cards-*.png, balls-reveal-*.png y balls-animation-{closed,open}.png. No se hizo commit ni despliegue. Se preservaron los cambios previos CHG-029/030 y los assets se regeneraron con el publicador vigente.
- 2026-11-09 — Implementación terminada → En verificación
- 2026-11-10 — Cerrado por el usuario (Compuerta 3) → done/

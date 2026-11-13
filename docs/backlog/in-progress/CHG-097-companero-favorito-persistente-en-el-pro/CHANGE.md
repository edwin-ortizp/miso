# CHG-097 · Compañero favorito persistente en el prototipo de Aprendizaje
**Estado:** En verificación
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-12 · **Aprobado:** 2026-11-12 · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
- Qué vas a ver distinto: un favorito discreto te acompaña entre vistas; lo eliges, cambias u ocultas sin alterar guardianes.
- Cómo comprobarlo tú: abrir learning.html por HTTP y probar el compañero, el perfil y los escenarios.

## 1. Resumen
Implementar el plan aprobado en conversación el 2026-11-12 exclusivamente en el prototipo offline de Aprendizaje. Favorito de la colección ficticia, independiente del equipo semanal, sin bonos ni rotación automática.

## 3. UX/UI
- Franja reservada bajo la barra superior, compacta y persistente; no flota sobre controles.
- Diálogo canónico para presentación, cambio y ocultación. Recuperación desde el perfil.
- Guardianes conservan consejos; favorito silencioso. En Pomodoro el favorito ocupa el espacio junto al temporizador una sola vez.
```text
Escritorio / móvil: [barra y perfil]
                   [arte pequeño · Tu compañero · nombre]
                   [pestañas / guardián / contenido existente]
Foco:              [temporizador + favorito] (sin franja duplicada)
```
- Textos: «Tu compañero», «Elegir compañero», «Cambiar compañero», «Ocultar compañero», «Mostrar compañero».

## 4. Diseño técnico
- Fuentes de docs/mockup-system: learning-frame, nueva macro y controlador del favorito, entry/domain de learning, datasets, CSS compartido y pruebas del recorrido. Dist generado únicamente con el compilador.
- Estado transaccional local; colección separada de team; escenarios favorito, sin favorito y colección vacía; reinicio restaura datos.
- No tocar backend, datos reales ni cambios ajenos. Sin APIs nuevas.

## 5. Pruebas
- Selección/cambio/cancelación/ocultación/perfil, persistencia al navegar, reinicio e independencia del guardián.
- Pomodoro sin duplicado; vacío sin elección imposible; móvil/escritorio, teclado, foco, axe, movimiento reducido y cero red externa.
- Compilación learning y regresión del recorrido integrado; ui:conformance y git diff --check.

## 7. Tareas
- [x] T1 · Elegir y llevar un compañero entre vistas sin cambiar guardianes — Resp: developer — Modelo: estandar — Archivos: docs/mockup-system — Hecho cuando: funcionan elección, cambio, ocultación, perfil y foco.
- [x] T2 · Revisar escenarios, accesibilidad y tamaños y documentar resultados — Resp: developer — Modelo: estandar — Archivos: docs/mockup-system, CHANGE.md — Hecho cuando: build y aceptación pasan y capturas revisadas.

## 8. Bitácora de implementación (developer)
- 2026-11-12 — Plan aprobado explícitamente por el usuario; cambios previos conservados. docs/DOCUMENTATION.md no existe; se usa la guía del prototipo.

- Implementado: compañero persistente Pikachu/Eevee/Abra, elección desde colección independiente del equipo, cambio/cancelación, ocultación y recuperación desde perfil. Pomodoro usa una única presencia. Escenarios sin favorito y colección vacía.
- Recursos oficiales PokeAPI locales empaquetados una vez; arte del guardián reutilizado en learning sin alterar las macros de otros consumidores por defecto. HTML compilado de 4.34 MB, dentro del límite de 10 MB.
- Integradas las modificaciones concurrentes de CHG-096 sin revertir jerarquía ni pantallas. Se corrigieron IDs de plantillas FAB repetidos y la exclusión del scrim del foco/trampa de teclado necesarios para validar el recorrido.
- Build normal de learning: 4 comprobaciones aisladas aprobadas, publicado localmente. favorite.mjs: escritorio/móvil, elección, navegación, ocultación/perfil, reinicio, escenarios, foco real iniciar/pausar, teclado, axe, movimiento reducido y cero red externa aprobados.
- acceptance.mjs: recorrido integrado, 14 vistas responsive y 5 comprobaciones Axe aprobados. Ajustadas expectativas multilinea de migas, selector de ayuda y apertura de navegación/configuración en móvil en las pruebas existentes.
- Capturas revisadas: presencia en escritorio/móvil, selector móvil y Pomodoro móvil. Resultados locales ignorados en dist/favorite-review y dist/learning-review.
- Manifiestos de learning/tasks/goals/habits/pomodoro válidos; git diff --check aprobado. ui:conformance mantiene 228 desviaciones preexistentes en Blade, sin cambios de producción en este CHG. Vite/PHP funcional no aplican a estas fuentes offline.
- Guía: docs/mockup-system/docs/favorite-companion-prototype.md. Vista previa local HTTP 200 en http://127.0.0.1:8766/learning.html. Pendiente exclusivamente revisión del usuario para decidir inclusión en producto.

## 9. Preguntas abiertas, decisiones e historial
- Sin decisiones pendientes de implementación. Revisión del usuario pendiente para decidir inclusión en producto.
- 2026-11-12 — Aprobado por el usuario (Compuerta 2)
- 2026-11-12 — Movido a in-progress/ (En desarrollo)

- LECCIÓN: ninguna.
- 2026-11-12 — Implementación terminada → En verificación
- 2026-11-12 — Reabierto: Revisión del usuario: compañero flotante sin rótulo permanente y burbuja expresiva al tocarlo

## Revisión del usuario · presencia flotante
- Reemplazar la franja por el arte del Pokémon como botón flotante sin rótulo permanente. Es acompañamiento, no una acción de creación.
- Esquina inferior izquierda, opuesta al FAB; sobre la navegación móvil. Burbuja al tocarlo con onomatopeya, mensaje breve y acceso al compañero/ocultación. Sin chat ni IA simulada.
- Una presencia también en Pomodoro; cerrar burbuja con Escape, botón o clic fuera; ocultar presencia al abrir modales o menú FAB.
- Verificar selección/perfil y ausencia de superposición con FAB/nav en móvil y escritorio, además de regresión existente.

- Revisión flotante implementada: se retiraron la franja y el rótulo permanente; icono opuesto al FAB, burbuja contextual con onomatopeya al tocar, cierre por Escape/botón/fuera y acceso a compañero/ocultación. Una sola presencia también en foco; se esconde con modales o menú FAB abierto.
- Validación de la revisión: build normal aprobado (4 comprobaciones, HTML 4.38 MB); favorite.mjs aprobado en escritorio 1440, tablet 834 y móvil 390, incluyendo límites de burbuja, separación del FAB/navegación, teclado, axe, selección/perfil y funcionamiento offline. acceptance.mjs aprobado (14 vistas responsive y 5 checks Axe). Capturas de burbuja en los tres tamaños inspeccionadas; git diff --check aprobado.
- 2026-11-12 — Implementación terminada → En verificación

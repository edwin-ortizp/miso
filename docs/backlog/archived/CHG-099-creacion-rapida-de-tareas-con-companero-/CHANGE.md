# CHG-099 · Creación rápida de tareas con compañero en el prototipo
**Estado:** Descartado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-12 · **Aprobado:** 2026-11-12 · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
- Qué vas a ver distinto: en Tareas eliges el botón tradicional o tu Pokémon para crear una tarea o varias rápidamente.
- Cómo comprobarlo tú: abrir Tareas en learning.html, probar crear/crear varias, Más opciones y el ajuste de presentación del perfil.

## 1. Resumen
Implementar el plan aprobado en conversación, únicamente en Tareas del prototipo. Un solo punto flotante de creación: split canónico tradicional o temático; modo Pokémon abre panel inmediato con campos directos. Las otras pantallas permanecen iguales.

## 3. UX/UI
- Principal: nueva tarea. Flecha: Crear tarea / Crear varias. Tema Pokémon sin texto permanente en el segmento principal; nombre accesible.
- Panel con arte, frase breve, título o líneas, categoría opcional, confirmación explícita y Más opciones. Sin entrada de chat ni interpretación de lenguaje natural.
- Borradores independientes y persistentes durante navegación; guardar o reiniciar los limpia. Cambio al modal conserva contenido.
- Perfil permite Tradicional / Con mi Pokémon; sin favorito ofrece elegirlo; colección vacía conserva tradicional.
```text
Escritorio:            [panel breve + campos + Crear]
                                     [Pokémon | ˄]
Móvil: [panel inferior, contenido desplazable, pie accesible]
                                     [Pokémon | ˄]
```

## 4. Diseño técnico
- Cambios solo docs/mockup-system: macro de panel/modal múltiple, controlador y dominio de creación local, integración en learning-frame/entry/favorite/domain, CSS, pruebas y guías. Dist generado por compilador.
- Reutilizar FAB split, diálogos y estado local; helper común de valores iniciales y persistencia de nuevas tareas. Sin backend ni APIs.
- Apertura síncrona y enfoque inmediato; sin red, timers ni animación que bloquee escribir. Evitar doble envío. Mantener Unicode completo visible cuando exceda 255 caracteres y mostrar error.

## 5. Pruebas
- Individual/múltiple en ambos modos, validación, doble clic, borradores, cambio de tema y traspaso al modal.
- Favorito ausente/colección vacía, un solo punto flotante en Tareas y conservación del compañero en otras vistas.
- Latencia local de apertura/foco, teclado, Escape, foco de retorno, axe, capturas escritorio/tablet/móvil y viewport con teclado reducido.
- Build learning, regresión integrada, pruebas del favorito ajustadas al nuevo contrato solo Tareas, ui:conformance y diff --check.

## 7. Tareas
- [ ] T1 · Crear tareas rápidamente con presentación tradicional o Pokémon — Resp: developer — Modelo: estandar — Archivos: docs/mockup-system — Hecho cuando: funcionan panel, modales, borradores, perfil y ambos tipos de creación.
- [ ] T2 · Verificar rapidez, accesibilidad y regresión y documentar — Resp: developer — Modelo: estandar — Archivos: docs/mockup-system, CHANGE.md — Hecho cuando: validaciones pasan y capturas revisadas.

## 8. Bitácora de implementación (developer)
- 2026-11-12 — Plan aprobado por el usuario. Se conservan cambios previos de Android, backend y prototipos.

## 9. Preguntas abiertas, decisiones e historial
- Sin decisiones pendientes para implementar. Pendiente revisión de producto tras prototipo.
- 2026-11-12 — Aprobado por el usuario (Compuerta 2)
- 2026-11-12 — Movido a in-progress/ (En desarrollo)

- Revisión del usuario: pulir sensación de chat con continuidad de burbuja/compañero, transición breve sin perder mensaje ni foco y patrón viable para producción. Panel no modal, launcher visible mientras se usa, identidad y mensajes persistentes en la ventana; movimiento reducido respetado. Teclado móvil prioriza entrada y confirmación.
- 2026-11-12 — Descartado: Se lleva directamente a la app real en un CHG nuevo

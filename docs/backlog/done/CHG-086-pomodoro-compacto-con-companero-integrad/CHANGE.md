# CHG-086 · Pomodoro compacto con compañero integrado
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-11 · **Aprobado:** 2026-11-11 · **Cerrado:** 2026-11-11
**Relacionado:** —

## 0. En palabras simples
Pomodoro muestra una sola fecha, el compañero integrado con el reloj y los controles; conserva la meta del día en el panel derecho y la acerca al temporizador en móvil.
Cómo comprobarlo: abrir la pantalla afectada en móvil y escritorio y comparar los bocetos aprobados.

## 1. Resumen
Pomodoro muestra una sola fecha, el compañero integrado con el reloj y los controles; conserva la meta del día en el panel derecho y la acerca al temporizador en móvil. Hallazgos aprobados: UI-001 y UI-002.

## 2. Producto (product-manager)
Usuario: propietario de su cuenta personal. Objetivo: realizar la acción principal sin redundancias, vacíos sin función ni controles ambiguos. Incluye solo las superficies y comportamientos descritos. No cambia permisos, datos reales, reglas de negocio ni otros módulos.
Criterios: boceto aplicado; información completa y legible; teclado y foco conservados; operación principal y guardado sin regresiones.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
Plantilla existente: seguimiento diario (Pomodoro) o ajustes por sección, según docs/design-system.md. Mantener rutas, contratos de datos y navegación. Móvil conserva información esencial cerca de la acción; estado vacío y errores no bloquean la recuperación.
### 3.2 Visual (ui-designer)
```text
[Fecha seleccionada]
[Trabajo] [Descanso corto] [Descanso largo]
[Pokemon y mensaje | 25:00 · Iniciar · Reiniciar] [Meta del día]
[                  | ¿En qué trabajas?        ] [Calendario ]
[Gestión de sesiones                          ] [Mes        ]
```
Reutilizar x-ui y tokens M3; un único layout responsive por CSS. Mantener jerarquía sin alturas forzadas. Navegador a 375,768,1280,1680px. En móvil, campos y controles se apilan sin perder etiquetas ni acciones.
### 3.3 Escritura (ux-writer)
Conservar textos actuales salvo las etiquetas aprobadas: «Según tus datos guardados», «Sin configurar», «Falta fecha de nacimiento», «Faltan peso y estatura», «Guardar preferencias», «Este dispositivo» y «Preferencias de avisos al celular», donde apliquen. Fecha de Pomodoro explícitamente en español. Mantener nombres accesibles, cifras, plurales y mensajes de validación.

## 4. Diseño técnico (software-architect)
Archivos: resources/views/livewire/pomodoro/pomodoro-timer.blade.php, resources/css/m3/modules/_pomodoro.css, resources/css/m3/modules/_game.css, tests/Feature/PomodoroTimerTest.php, tests/Browser/ui-density* y tests/Browser/support/ui-density*.
Quitar las métricas superiores y la fecha repetida. Integrar compañero con dial y controles; una sola instancia de la meta, reordenada mediante CSS por debajo de 1200 px. Preservar temporizador, reacciones, ocultar compañero, registros, filtros y guardián de consejos en el contexto. No modificar la máquina de estados ni guardado JS. Contraste del grito con tokens de texto.
Riesgos: estilos compartidos y cambios locales concurrentes; limitar selectores y preservar archivos ajenos. Sin migraciones, dependencias ni cambios de autenticación.

## 5. Pruebas (qa-tester)
PomodoroTimerTest, FocusCompanionTest y pruebas JS de pomodoro/focus-companion; navegador a 375/768/1280/1680 con compañero, sin compañero, pausado, activo, descanso, sesiones vacías/con datos y texto largo. Medir contraste y ausencia de desbordes.
Ejecutar npm run build, php artisan ui:conformance y git diff --check. Registrar desviaciones preexistentes. Usar exclusivamente SQLite y cuentas sintéticas; no enviar mensajes ni solicitar permisos push reales.

## 6. Documentación (tech-writer)
Actualizar bitácora y evidencia de este CHANGE y referencias del backlog UI. Los lineamientos de densidad ya existen; no duplicarlos.

## 7. Tareas
- [x] T1 · Aplicar la composición y comportamientos aprobados — Resp: developer — Modelo: estandar — Archivos: resources/views/livewire/pomodoro/pomodoro-timer.blade.php, resources/css/m3/modules/_pomodoro.css, resources/css/m3/modules/_game.css, tests/Feature/PomodoroTimerTest.php, tests/Browser/ui-density* y tests/Browser/support/ui-density* — Hecho cuando: interfaz coincide con boceto y contratos conservados. (cerrada por el usuario sin verificar)
- [x] T2 · Verificar vistas, interacción, accesibilidad y responsive — Resp: developer — Modelo: estandar — Archivos: pruebas afectadas y este CHANGE — Hecho cuando: validación registrada y fallos propios corregidos. (cerrada por el usuario sin verificar)

## 8. Bitácora de implementación (developer)

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-11 — Creado en to-do/ (Borrador, nivel M)

- 2026-11-11 — Usuario aprobó explícitamente los hallazgos, bocetos y los tres cambios para implementar. LECCIÓN: aplicar las reglas existentes de densidad y ayuda contextual; no se propone una regla nueva.
- 2026-11-11 — Aprobado por el usuario (Compuerta 2)
- 2026-11-11 — Movido a in-progress/ (En desarrollo)
- 2026-11-11 — Cerrado por el usuario sin verificación (pendientes: T1, T2): ya lo probé → done/ (desde el panel)

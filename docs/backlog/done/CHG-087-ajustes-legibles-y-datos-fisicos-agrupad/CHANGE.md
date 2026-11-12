# CHG-087 · Ajustes legibles y datos físicos agrupados
**Estado:** Cerrado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-11 · **Aprobado:** 2026-11-11 · **Cerrado:** 2026-11-11
**Relacionado:** —

## 0. En palabras simples
Ajustes mantiene navegación derecha y desplazable arriba en móvil; formularios legibles, cabeceras sin iconos aislados y Datos físicos en un bloque con referencias guardadas.
Cómo comprobarlo: abrir la pantalla afectada en móvil y escritorio y comparar los bocetos aprobados.

## 1. Resumen
Ajustes mantiene navegación derecha y desplazable arriba en móvil; formularios legibles, cabeceras sin iconos aislados y Datos físicos en un bloque con referencias guardadas. Hallazgos aprobados: UI-003, UI-004 y UI-005.

## 2. Producto (product-manager)
Usuario: propietario de su cuenta personal. Objetivo: realizar la acción principal sin redundancias, vacíos sin función ni controles ambiguos. Incluye solo las superficies y comportamientos descritos. No cambia permisos, datos reales, reglas de negocio ni otros módulos.
Criterios: boceto aplicado; información completa y legible; teclado y foco conservados; operación principal y guardado sin regresiones.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
Plantilla existente: seguimiento diario (Pomodoro) o ajustes por sección, según docs/design-system.md. Mantener rutas, contratos de datos y navegación. Móvil conserva información esencial cerca de la acción; estado vacío y errores no bloquean la recuperación.
### 3.2 Visual (ui-designer)
```text
[Perfil público y campos separados] [Secciones de ajustes]
[Nombre completo                 ] [Perfil público      ]
[Email no editable               ] [Datos físicos       ]
[Guardar perfil                  ] [Notificaciones      ]

Datos físicos
[Peso] [Estatura] · [Nivel de actividad]
[Fecha de nacimiento] [Expectativa de vida]
Según tus datos guardados · Edad · IMC · Meta de agua
[Guardar datos físicos]
```
Reutilizar x-ui y tokens M3; un único layout responsive por CSS. Mantener jerarquía sin alturas forzadas. Navegador a 375,768,1280,1680px. En móvil, campos y controles se apilan sin perder etiquetas ni acciones.
### 3.3 Escritura (ux-writer)
Conservar textos actuales salvo las etiquetas aprobadas: «Según tus datos guardados», «Sin configurar», «Falta fecha de nacimiento», «Faltan peso y estatura», «Guardar preferencias», «Este dispositivo» y «Preferencias de avisos al celular», donde apliquen. Fecha de Pomodoro explícitamente en español. Mantener nombres accesibles, cifras, plurales y mensajes de validación.

## 4. Diseño técnico (software-architect)
Archivos: resources/views/livewire/settings/partials/profile.blade.php, resources/views/livewire/settings/partials/body.blade.php, resources/css/m3/modules/_settings.css, tests/Feature/SettingsPanelTest.php, tests/Feature/PhysicalProfileTest.php, tests/Browser/ui-density* y tests/Browser/support/ui-density*.
Conservar el layout y rutas de Ajustes; limitar los formularios cortos a ancho legible sin afectar herramientas MCP ni otras secciones. Integrar iconos con títulos de tarjeta y usar separación entre grupos de campos. Navegación con alto mínimo44px. Un bloque de Datos físicos con referencias guardadas y estados sin datos explícitos. Preservar email no editable, acciones updateProfile, cálculos, límites, datos del usuario y validaciones.
Riesgos: estilos compartidos y cambios locales concurrentes; limitar selectores y preservar archivos ajenos. Sin migraciones, dependencias ni cambios de autenticación.

## 5. Pruebas (qa-tester)
SettingsPanelTest y PhysicalProfileTest; navegador móvil/escritorio, teclado, errores inline, guardar datos sintéticos y verificar referencias tras guardar.
Ejecutar npm run build, php artisan ui:conformance y git diff --check. Registrar desviaciones preexistentes. Usar exclusivamente SQLite y cuentas sintéticas; no enviar mensajes ni solicitar permisos push reales.

## 6. Documentación (tech-writer)
Actualizar bitácora y evidencia de este CHANGE y referencias del backlog UI. Los lineamientos de densidad ya existen; no duplicarlos.

## 7. Tareas
- [x] T1 · Aplicar la composición y comportamientos aprobados — Resp: developer — Modelo: estandar — Archivos: resources/views/livewire/settings/partials/profile.blade.php, resources/views/livewire/settings/partials/body.blade.php, resources/css/m3/modules/_settings.css, tests/Feature/SettingsPanelTest.php, tests/Feature/PhysicalProfileTest.php, tests/Browser/ui-density* y tests/Browser/support/ui-density* — Hecho cuando: interfaz coincide con boceto y contratos conservados. (cerrada por el usuario sin verificar)
- [x] T2 · Verificar vistas, interacción, accesibilidad y responsive — Resp: developer — Modelo: estandar — Archivos: pruebas afectadas y este CHANGE — Hecho cuando: validación registrada y fallos propios corregidos. (cerrada por el usuario sin verificar)

## 8. Bitácora de implementación (developer)

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-11 — Creado en to-do/ (Borrador, nivel S)

- 2026-11-11 — Usuario aprobó explícitamente los hallazgos, bocetos y los tres cambios para implementar. LECCIÓN: aplicar las reglas existentes de densidad y ayuda contextual; no se propone una regla nueva.
- 2026-11-11 — Aprobado por el usuario (Compuerta 2)
- 2026-11-11 — Movido a in-progress/ (En desarrollo)
- 2026-11-11 — Cerrado por el usuario sin verificación (pendientes: T1, T2): ya lo probé → done/ (desde el panel)

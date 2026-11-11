# CHG-082 · Tipografía Nunito en web y mockups
**Estado:** En verificación
**Nivel:** S
**Modelo sugerido:** estandar
**Creado:** 2026-11-11 · **Aprobado:** 2026-11-11 · **Cerrado:** —
**Relacionado:** Plan aprobado por el usuario en esta conversación.

## 0. En palabras simples
- Qué vas a ver distinto: Nunito en toda la interfaz web y los prototipos, conservando tamaños y espacios.
- Cómo comprobarlo tú: abrir Inicio, Tareas y un formulario en móvil y escritorio.

## 1. Resumen
Sustituir Instrument Sans por Nunito local en pesos 400, 500, 600 y 700. Mantener iconos, código y funcionamiento offline. Android queda fuera; sin backend, datos ni despliegue.

## 7. Tareas
- [x] T1 · Sustituir fuente local, tokens y mockups; regenerar assets — Resp: developer — Modelo: estandar — Archivos: resources/css, package.json, package-lock.json, docs/mockup-system — Hecho cuando: web y prototipos carguen Nunito sin terceros.
- [x] T2 · Validar build, conformidad y presentación responsive — Resp: developer — Modelo: estandar — Archivos: docs/backlog — Hecho cuando: se documenten resultados y limitaciones de Inicio, Tareas y formulario a 390 y 1440 px.

## 8. Bitácora de implementación (developer)
- Sustituidos paquete, importaciones y tokens por Nunito 400/500/600/700 local. Añadidas fuentes y licencia a los mockups; documentada la familia en design-system.md. Se preservan los cambios locales ajenos.
- npm run build: correcto; fuentes publicadas en public/css/assets y sin referencias activas a Instrument Sans.
- Compilador central --all: 19 mockups publicados, Playwright correcto en 76 vistas aisladas.
- Chromium con SQLite exclusiva de pruebas: Inicio, Tareas y nueva tarea a 390/1440 px; cuatro pesos locales HTTP 200, tildes/ñ disponibles, sin desbordamiento horizontal. Se abrieron ambos formularios y revisaron las seis capturas (storage/framework/testing/nunito-*.png); sin escrituras en datos personales.
- php artisan ui:conformance: falla por desviaciones preexistentes en Blade de Juego, Inicio y Tareas, ajenas a este cambio de fuentes. Informe: storage/framework/testing/nunito-conformance.txt.
- CssLayerContractTest: 3 pasan y 2 fallan por dependencias/overrides preexistentes en _text-fields.css y estilos de módulos; Nunito solo cambia el valor del token, sin modificar esas reglas.
- git diff --check: correcto. Sin commits ni despliegue. CHG-081 preservado; se usó start --force para trabajar este cambio aprobado en paralelo sin alterar su estado.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-11 — Plan aprobado explícitamente por el usuario. Sin preguntas pendientes.
- 2026-11-11 — Aprobado por el usuario (Compuerta 2)
- 2026-11-11 — Movido a in-progress/ (En desarrollo)

- LECCIÓN: ninguna.
- 2026-11-11 — Implementación terminada → En verificación

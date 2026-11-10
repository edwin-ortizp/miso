# CHG-030 · Corregir rutas de fuentes para instalación local bajo subruta
**Estado:** En verificación
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-09 · **Aprobado:** 2026-11-09 · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: las fuentes e iconos cargarán también cuando MiSo se abra desde una subruta local de Laragon.
- Cómo comprobarlo tú: abrir una página de MiSo bajo `/miso/public/` y confirmar que no hay 404 de fuentes.

## 1. Resumen
<!-- Qué y por qué, 2-3 líneas. Agrega solo la sección del rol afectado (## 3. UX/UI, ## 4. Diseño técnico o ## 5. Pruebas) si hace falta. Si hay UI, §3 lleva un boceto ASCII de la zona que cambia. -->
Publicar las referencias de fuentes del CSS como rutas relativas a `public/css/app.css`. Esto conserva `/css/assets/...` cuando se sirve desde la raíz en producción y resuelve bajo `/miso/public/css/assets/...` en la instalación local.

## 4. Diseño técnico (software-architect)
Cambiar la reescritura `/build/assets/` → `assets/` en `scripts/publish-vite-css.mjs`. Mantener la copia de fuentes a `public/css/assets/` y los bundles JS sin cambios.

## 5. Pruebas (qa-tester)
Ejecutar `npm run build`; verificar referencias CSS y existencia de fuentes publicadas; comprobar la resolución de URL desde raíz y subruta; revisar `git diff --check`.

## 7. Tareas
<!-- Primero lo que cambia para el usuario; el detalle técnico después. Formato:
- [ ] T1 · qué cambia (en simple) — Resp: developer — Modelo: perfil — Archivos: … — Hecho cuando: … -->
- [x] T1 · Cargar fuentes desde raíz y subruta — Resp: developer — Modelo: estandar — Archivos: scripts/publish-vite-css.mjs, public/css/app.css — Hecho cuando: el CSS usa `assets/...`, los archivos están publicados y el build y diff check pasan.

## 8. Bitácora de implementación (developer)
- 2026-11-09 — El publicador reescribe `/build/assets/` como `assets/`; las fuentes se siguen copiando a `public/css/assets/` y los JS no cambian.
- `npm run build`: pasó; publicó diez assets. El build mostró el aviso existente de chunk JS mayor a 500 kB.
- CSS verificado: no conserva rutas `/build/` ni absolutas `/css/assets/`; resuelve a `/miso/public/css/assets/...` bajo la URL local y a `/css/assets/...` desde la raíz de producción.
- HTTP local: fuente consultada en `http://localhost:8080/miso/public/css/assets/...` respondió 200; la ruta anterior `http://localhost:8080/css/assets/...` respondió 404.
- `git diff --check`: pasó; solo emitió avisos de conversión LF/CRLF en archivos modificados.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-09 — Plan aprobado explícitamente por el usuario en conversación; producción servida desde la raíz debe conservar URLs `/css/assets/...`.
- 2026-11-09 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-09 — Aprobado por el usuario (Compuerta 2)
- 2026-11-09 — Movido a in-progress/ (En desarrollo)
- 2026-11-09 — Implementación terminada → En verificación

# CHG-060 · Música de fondo en el modo rutina de hábitos
**Estado:** Cerrado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-10 · **Aprobado:** 2026-11-10 · **Cerrado:** 2026-11-11
**Relacionado:** —

## 0. En palabras simples
- Qué vas a ver distinto: al empezar una rutina de hábitos suena música tranquila e instrumental, que cambia de pista al azar y no se corta entre un hábito y otro. Arriba aparece un botón con un altavoz para silenciarla, y la app recuerda tu elección. Cuando se acaba el tiempo de un hábito, la música baja y suena una campanita suave. «Pausar» también pausa la música, y al cerrar la rutina se apaga.
- Cómo comprobarlo tú: abre una rutina con hábitos que tengan duración. Escucha la música, sílenciala, cierra y vuelve a abrir la rutina para confirmar que sigue en silencio. Espera a que termine un tiempo para oír la campanita y pasa al siguiente hábito para comprobar que la música no se corta.

## 1. Resumen
Música de fondo, incluida en el proyecto y libre de derechos, para que la rutina de hábitos se sienta más inmersiva. Lleva un aviso sutil al terminar el tiempo y un botón de silencio para quien no quiera escucharla.

## 3. UX/UI
### 3.1 Experiencia
Plantilla: overlay de rutina existente (`routine-overlay.blade.php`), sin pantalla nueva.
- La música arranca con el clic que abre la rutina, para cumplir la regla de autoplay del navegador, y entra subiendo el volumen en unos 2 s hasta ~0.3.
- Hay 4 pistas que se reproducen al azar. Cuando termina una suena otra, nunca la misma dos veces seguidas. Suena continua durante toda la rutina, incluso al cambiar de hábito o usar Anterior y Saltar.
- «Pausar» pausa la música y «Seguir» la reanuda. Al cerrar la rutina (botón X, Esc o Cerrar) la música baja de volumen y se detiene.
- Al llegar el tiempo a 00:00, la música baja a la mitad de su volumen y suena una campanita de 1 a 2 s. Al pasar al siguiente hábito, el volumen vuelve. Si la música está silenciada, la campanita sí suena, porque el silencio aplica solo a la música.
- La preferencia de silencio se guarda en `localStorage` (`miso.routine.music.muted`). Por defecto la música suena.
- Si el audio falla, por ejemplo porque el navegador lo bloquea o falta el archivo, la rutina sigue igual, sin mensajes de error.

```
┌───────────────────────────────────────────────┐
│  ‹   ☀ Mañana · 2 de 5          [🔊]   [✕]   │  ← nuevo botón altavoz
│                                               │
│              ¡Vamos con Leer!                 │
│                  ( 04:12 )                    │
└───────────────────────────────────────────────┘
   silenciado: [🔇]
```
### 3.2 Visual
Usa `x-ui.icon-action`, igual que el botón ✕, con los iconos `bi-volume-up` y `bi-volume-mute`. Va entre la etiqueta del periodo y ✕.
### 3.3 Escritura
- aria-label con la música activa: «Silenciar música»
- aria-label con la música silenciada: «Activar música»

## 4. Diseño técnico
- El reproductor es un `Alpine.store('routineMusic')` (o un módulo JS registrado en `resources/js`) y no vive en el `x-data` del paso, porque el paso se vuelve a montar con cada hábito por su `wire:key`. Así la música no se corta.
- El paso emite eventos en `window`: `routine-music:start`, `:pause`, `:resume`, `:time-up`, `:step` (vuelve el volumen) y `:stop`. El store los escucha.
- La campanita se genera con Web Audio: un seno de ~880 Hz con armónico y caída exponencial de ~1.5 s, sin archivo extra.
- Los archivos van en `public/audio/routine/*.mp3` (128 kbps, cada uno de menos de 3 MB) y la lista sale de un arreglo en el store. `public/audio/routine/LICENSES.md` registra el título, autor, URL y licencia de cada pista (Pixabay Content License o CC0).
- Archivos: `resources/views/livewire/habit/partials/routine-overlay.blade.php`, `resources/js/` (store y registro en `app.js`), `public/audio/routine/`.

## 5. Pruebas
- Feature test: el overlay renderiza el botón con el aria-label «Silenciar música».
- Verificación manual en el navegador según §0, sin errores en la consola.
- Verificación: `php artisan test --filter=Habit`

## 7. Tareas
- [x] T1 · Conseguir 4 pistas lo-fi/ambient instrumentales libres de derechos y registrar sus licencias — Resp: developer — Modelo: estandar — Archivos: public/audio/routine/*.mp3, public/audio/routine/LICENSES.md — Hecho cuando: las 4 pistas existen, sin voz y de menos de 3 MB cada una, y cada una tiene fuente y licencia anotadas
- [x] T2 · Reproductor aleatorio continuo con campanita y silencio recordado — Resp: developer — Modelo: estandar — Archivos: resources/js/routine-music.js, resources/js/app.js — Hecho cuando: el store sigue los eventos de §4, nunca repite la pista anterior, baja el volumen y hace sonar la campanita con time-up, y respeta localStorage
- [x] T3 · Conectar la rutina: botón altavoz y eventos desde el paso — Resp: developer — Modelo: estandar — Archivos: resources/views/livewire/habit/partials/routine-overlay.blade.php, tests/Feature/Habit — Hecho cuando: el botón alterna el icono y el aria-label, Pausar/Seguir/Cerrar controlan la música y pasa el test de §5
- [x] T4 · Verificar criterios de §0 en el navegador — Resp: qa-tester — Hecho cuando: todos pasan y Alex aprueba las pistas (cerrada por el usuario sin verificar)

## 8. Bitácora de implementación (developer)
- 2026-11-10 — Inicio (start --force: CHG-017 sigue En desarrollo, sin archivos en común; lo autorizó Alex).
- T1 — 4 pistas CC0 de OpenGameArt (Pixabay bloqueó la descarga automática) recodificadas a ~128 kbps, de 1.9 a 2.6 MB cada una. Licencias en public/audio/routine/LICENSES.md, con la licencia CC0 confirmada en cada página.
- T2 — resources/js/routine-music.js: Alpine.store('routineMusic') con orden aleatorio sin repetir la pista anterior, entrada gradual de 2 s a 0.3, baja a la mitad con timeUp, campanita Web Audio de 880/1760 Hz, silencio en localStorage y reintento en la siguiente interacción si hay autoplay bloqueado. Se registra en app.js. `npm run build` OK.
- T3 — overlay: el shell llama a start() y stop() (destroy), el paso llama a step(), sigue paused y left=0, y hay un botón altavoz junto a ✕. Mínimo CSS `.habit-routine__music` en _habits.css para agrupar los botones (archivo no listado en §4, pero dentro del módulo). Test test_routine_shows_music_toggle. `php artisan test --filter=Habit` → 66 pasan.

- Corrección — las pistas daban 404 en localhost:8080 porque la app vive en /miso/public. Ahora la base sale de asset('audio/routine'), que el overlay pasa a start(base). Habit → 66 pasan.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-10 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-10 — Decisión (Alex): música continua en toda la rutina; al acabar el tiempo baja el volumen y suena la campanita; varias pistas al azar para no repetir.
- 2026-11-10 — Decisión (team): developer consigue las pistas y Alex las aprueba en T4.
- 2026-11-10 — Aprobado por el usuario (Compuerta 2) (desde el panel)
- 2026-11-10 — Movido a in-progress/ (En desarrollo)
- 2026-11-10 — Pistas: OpenGameArt CC0 en lugar de Pixabay (403 al descargar). Alex las aprueba en T4.
- LECCIÓN(developer): en JS, los recursos de public/ se resuelven con asset() pasado desde Blade, nunca con rutas absolutas «/…», porque la app puede vivir en una subruta (APP_URL=…/miso/public) — evidencia: 404 de las pistas en CHG-060 · destino sugerido: docs/ARCHITECTURE.md → docs/ARCHITECTURE.md
- 2026-11-10 — Implementación terminada → En verificación
- 2026-11-11 — Cerrado por el usuario sin verificación (pendientes: T4): ya lo probé → done/ (desde el panel)

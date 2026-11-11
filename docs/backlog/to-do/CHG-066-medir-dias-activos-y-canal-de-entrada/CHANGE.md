# CHG-066 · Medir días activos y canal de entrada
**Estado:** Aprobado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-11 · **Aprobado:** 2026-11-11 · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: nada en pantalla; MiSo empieza a contar qué días usas la app y desde dónde entraste (directo, aviso push o check-in).
- Cómo comprobarlo tú: tras usar la app un día, el comando `php artisan miso:retention` muestra ese día como activo.

## 1. Resumen
Estrategia de recurrencia (2026-11-11): MiSo hoy es solo pull — notificaciones solo dentro de la app, sin push ni scheduler. Antes de construir avisos necesitamos medir si sirven: guardar un registro por usuario y día activo con el canal de entrada, y un comando que reporte retención D1/D7/D30 y registros por día activo. Es la línea base de CHG-067 a CHG-070.

## 4. Diseño técnico
- Tabla `user_active_days` (user_id, date, first_channel, records_count; único user_id+date).
- Middleware web que hace upsert una vez por día (caché por sesión para no consultar en cada request); canal desde `?via=push|checkin|close` o `direct`.
- `records_count`: se incrementa desde el mismo punto donde `RewardGranter`/observers del juego registran actividad (reusar, no duplicar observers).
- Comando `miso:retention {--days=30}` con D1/D7/D30 por cohorte.
- Privacidad: solo datos propios, sin terceros.

## 7. Tareas
- [ ] T1 · Se guarda cada día activo y el canal de entrada — Resp: developer — Modelo: estandar — Archivos: migración, `app/Models/UserActiveDay.php`, middleware — Hecho cuando: un día con varias visitas deja una sola fila con el primer canal.
- [ ] T2 · Se cuentan los registros del día — Resp: developer — Modelo: estandar — Archivos: punto común de actividad del juego — Hecho cuando: completar un hábito incrementa `records_count`.
- [ ] T3 · Reporte de retención — Resp: developer — Modelo: estandar — Archivos: `routes/console.php` o `app/Console/Commands` — Hecho cuando: el comando imprime D1/D7/D30.
- [ ] T4 · Pruebas — Resp: developer — Modelo: estandar — Archivos: `tests/Feature/Engagement/` — Hecho cuando: cubren upsert diario, canal y reporte.

## 8. Bitácora de implementación (developer)

## 9. Preguntas abiertas, decisiones e historial
- Pregunta: ¿el reporte basta como comando o lo quieres ver en Estadísticas? → 2026-11-11 Decisión (usuario): basta el comando.
- 2026-11-11 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-11 — Aprobado por el usuario (Compuerta 2)

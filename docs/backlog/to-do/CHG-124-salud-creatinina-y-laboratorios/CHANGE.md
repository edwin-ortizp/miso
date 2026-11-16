# CHG-124 · Salud: creatinina y laboratorios
**Estado:** Aprobado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-16 · **Aprobado:** 2026-11-16 · **Cerrado:** —
**Relacionado:** CHG-122 (base)

## 0. En palabras simples
- Qué vas a ver distinto: en Salud → Mediciones puedes registrar tu creatinina (mg/dL) y, si el valor viene de un examen que ya tienes en Salud, enlazarla a ese «Chequeo o examen». MiSo la marca de forma neutra si sale del rango de referencia general. El asistente también la registra y consulta.
- Cómo comprobarlo tú: registra 0,9 mg/dL enlazada a un examen y mira que aparezca «En rango»; registra 2,4 y verás «Fuera de rango» con la invitación a consultar a tu profesional de salud.

## 1. Resumen
Agrega la creatinina como tipo de medición sobre la base de CHG-122/123 y la opción de enlazar cualquier medición a un evento «Chequeo o examen» de Salud, para agrupar los valores de un mismo laboratorio. El enlace es un campo opcional sin clave foránea (una FK compuesta con `user_id` no admite `nullOnDelete`): si el examen se elimina, la medición conserva su valor y pierde el enlace. MiSo registra; no diagnostica.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: pestaña «Mediciones» del módulo Salud (patrón de CHG-122; no existe plantilla específica en `## Anatomía` de docs/UX-DESIGN.md).
- Cambios: «Tipo» incluye «Creatinina». En «Más detalle» aparece «Examen relacionado» (lista de los «Chequeo o examen» del usuario, opcional). El historial muestra el nombre del examen enlazado.

```
┌ Registrar medición ──────── ✕ ┐
│ Tipo     [Creatinina       ▾] │
│ Creatinina        [ 0,9 ] mg/dL│
│ Fecha y hora  [Ahora       ▾] │
│ ▾ Más detalle                 │
│   Examen relacionado          │
│   [Laboratorio 12 nov      ▾] │
│   Notas  [                 ]  │
│ [Cancelar]          [Guardar] │
└───────────────────────────────┘
```

## 7. Tareas
- [ ] T1 · Registrar creatinina y enlazarla a un examen — Resp: developer — Modelo: estandar — Archivos: migración `health_event_id` en `health_measurements`, `HealthMeasurement`, `LogHealthMeasurement`, `HealthMeasurements` y su vista — Hecho cuando: se guarda 0,9 mg/dL como «En rango» y 2,4 como «Fuera de rango», se enlaza y desenlaza un examen, y un examen ajeno se rechaza.
- [ ] T2 · El asistente registra y consulta creatinina con su examen — Resp: developer — Modelo: estandar — Archivos: `LogHealthMeasurementTool`, `ListHealthMeasurementsTool`, `docs/UX-WRITING.md` — Hecho cuando: el MCP acepta `type=creatinine` y `health_event_id`, la lista devuelve el examen enlazado, y eliminar el examen deja la medición sin enlace.

## 8. Bitácora de implementación (developer)

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-16 — Creado en to-do/ (Borrador, nivel S)
- Decidido 2026-11-16: rango de referencia general 0,6–1,3 mg/dL (varía por sexo y edad; no se personaliza todavía). Otros analitos de laboratorio (urea, colesterol, HbA1c) quedan fuera de este CHG: se agregan como entradas del catálogo cuando se pidan.
- Decidido 2026-11-16: el enlace con el examen no tiene FK; al borrar el examen la medición queda sin enlace.
- 2026-11-16 — Aprobado por el usuario (Compuerta 2)

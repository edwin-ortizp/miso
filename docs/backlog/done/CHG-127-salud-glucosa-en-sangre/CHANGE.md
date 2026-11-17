# CHG-127 · Salud: glucosa en sangre
**Estado:** Cerrado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-16 · **Aprobado:** 2026-11-16 · **Cerrado:** 2026-11-16
**Relacionado:** CHG-126 (base)

## 0. En palabras simples
- Qué vas a ver distinto: en Salud → Mediciones, al registrar eliges «Glucosa en sangre», escribes el valor en mg/dL y el momento (en ayunas, antes de comer, después de comer, al acostarse u otro). Cada valor se marca de forma neutra si sale del rango de referencia general de ese momento. El asistente también puede registrarla y consultarla.
- Cómo comprobarlo tú: registra 92 mg/dL «en ayunas» y verás «En rango»; registra 180 «después de comer» y verás «Fuera de rango» con la invitación a consultar a tu profesional de salud.

## 1. Resumen
Agrega la glucosa en sangre como tipo de medición sobre la base de CHG-126. Para eso la base se generaliza: el formulario deja elegir el tipo, muestra solo los campos que ese tipo usa (la presión usa dos valores y pulso; la glucosa uno y un momento) y el rango de referencia puede depender del momento. MiSo registra y muestra tendencias; no diagnostica.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: pestaña «Mediciones» del módulo Salud (patrón de CHG-126; no existe plantilla específica en `## Anatomía` de docs/UX-DESIGN.md).
- Cambios: el diálogo «Registrar medición» empieza con «Tipo» (Presión arterial / Glucosa en sangre); los campos cambian según el tipo. «Última medición» pasa a mostrar una tarjeta por cada tipo con datos. El historial agrega el nombre del tipo y, en glucosa, el momento.

```
┌ Registrar medición ──────── ✕ ┐     ┌ Última medición ─────────────────────┐
│ Tipo     [Glucosa en sangre ▾]│     │ ❤ Presión arterial  128/84 mmHg · hoy │
│ Glucosa            [ 92 ] mg/dL│     │ 🩸 Glucosa  92 mg/dL · en ayunas · hoy │
│ Momento  [En ayunas        ▾] │     │   En rango                             │
│ Fecha y hora  [Ahora       ▾] │     └───────────────────────────────────────┘
│ ▸ Más detalle (notas)         │
│ [Cancelar]          [Guardar] │
└───────────────────────────────┘
```

## 7. Tareas
- [x] T1 · Registrar glucosa en sangre con su momento y ver si está en rango — Resp: developer — Modelo: estandar — Archivos: `HealthMeasurement` (catálogo con contextos y rango por momento), `LogHealthMeasurement`, `HealthMeasurements` y su vista — Hecho cuando: se guarda 92 mg/dL en ayunas como «En rango», 180 después de comer como «Fuera de rango», y la presión sigue funcionando igual.
- [x] T2 · El asistente registra y consulta glucosa — Resp: developer — Modelo: estandar — Archivos: `LogHealthMeasurementTool`, `ListHealthMeasurementsTool`, `docs/UX-WRITING.md` — Hecho cuando: el MCP acepta `type=glucose` con `context`, rechaza valores imposibles o un momento inválido, y las pruebas de glucosa y de presión pasan.

## 8. Bitácora de implementación (developer)
- 2026-11-16 — T1: la base de CHG-126 se generaliza. `HealthMeasurement::TYPES` suma `glucose` (mg/dL, topes 20–600) con momentos (`contexts`: en ayunas 70–99, antes de comer 70–130, después de comer 70–140, al acostarse 90–150, otro 70–140) y `reference()`/`contextLabel()`; el rango que marca «Fuera de rango» es el del momento. `LogHealthMeasurement` valida `context` (solo en tipos que lo definen) y ya no exige/guarda `value2` ni `pulse` en tipos que no los usan. La pantalla ahora pide «Tipo» y muestra solo los campos del tipo; «Última medición» muestra una tarjeta por cada tipo con datos.
- 2026-11-16 — T2: `log-health-measurement-tool` acepta `type=glucose` y `context`; `list-health-measurements-tool` devuelve `context` y el resumen por tipo.
- Pruebas: 4 nuevas de pantalla y 3 de MCP (20 en total en los archivos de mediciones). Suite completa: 1686 pruebas, 46 fallas idénticas a la base (entorno).
- Mensajes de validación: ahora llevan el artículo del tipo («La sistólica debe estar entre…», «La glucosa debe estar entre…»).
- No verificado: revisión visual con captura en móvil y escritorio.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-16 — Creado en to-do/ (Borrador, nivel S)
- Decidido 2026-11-16: rangos de referencia generales y aproximados por momento (en ayunas 70–99, antes de comer 70–130, después de comer 70–140, al acostarse 90–150, otro 70–140 mg/dL). Son constantes del catálogo; no son un diagnóstico y no se personalizan todavía.
- 2026-11-16 — Aprobado por el usuario (Compuerta 2)
- 2026-11-16 — Movido a in-progress/ (En desarrollo)
- 2026-11-16 — Implementación terminada → En verificación
- 2026-11-16 — Renumerado de CHG-123 a CHG-127 (choque con los CHG sociales CHG-122 a CHG-125 que ya estaban en main).
- 2026-11-16 — Cerrado por el usuario (Compuerta 3) → done/

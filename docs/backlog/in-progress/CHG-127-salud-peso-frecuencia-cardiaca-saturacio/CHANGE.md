# CHG-127 · Salud: peso, frecuencia cardiaca, saturación y temperatura
**Estado:** En verificación
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-16 · **Aprobado:** 2026-11-16 · **Cerrado:** —
**Relacionado:** CHG-122 (base)

## 0. En palabras simples
- Qué vas a ver distinto: en Salud → Mediciones puedes registrar también tu peso (kg), frecuencia cardiaca (lpm), saturación de oxígeno (%) y temperatura (°C). Cada una aparece en «Última medición» y en el historial, y se marca de forma neutra si sale del rango de referencia general. El peso queda con histórico. El asistente también las registra y consulta.
- Cómo comprobarlo tú: registra 72 kg, 64 lpm, 98 % y 36,6 °C; verás una tarjeta por cada una. Registra 39,2 °C y verás «Fuera de rango» con la invitación a consultar a tu profesional de salud.

## 1. Resumen
Agrega cuatro tipos al catálogo de mediciones de CHG-122/123: peso, frecuencia cardiaca, saturación de oxígeno y temperatura. No hay tablas ni pantallas nuevas: son entradas del catálogo con su unidad, topes fisiológicos y rango de referencia general. El peso del perfil (`users.current_weight_kg`, que alimenta la meta de agua) NO cambia con estas mediciones. MiSo registra; no diagnostica.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: pestaña «Mediciones» del módulo Salud (patrón de CHG-122; no existe plantilla específica en `## Anatomía` de docs/UX-DESIGN.md).
- Cambios: «Tipo» incluye las cuatro métricas nuevas; «Última medición» muestra una tarjeta por cada tipo con datos; el historial ofrece un filtro por tipo.

```
┌ Última medición ───────────────────────────────────────┐
│ ❤ Presión 128/84 mmHg   ⚖ Peso 72 kg   ♡ FC 64 lpm      │
│ 🫁 Saturación 98 %      🌡 Temperatura 39,2 °C · ⓘ Fuera │
└─────────────────────────────────────────────────────────┘
┌ Historial ─ [Tipo: Todos ▾] [Periodo ▾] ───────────────┐
```

## 7. Tareas
- [x] T1 · Registrar peso, frecuencia cardiaca, saturación y temperatura — Resp: developer — Modelo: estandar — Archivos: `HealthMeasurement` (catálogo), `HealthMeasurements` y su vista (filtro por tipo) — Hecho cuando: cada tipo se guarda con su unidad, valores imposibles se rechazan, 39,2 °C sale «Fuera de rango» y el peso no modifica el perfil.
- [x] T2 · El asistente registra y consulta las cuatro — Resp: developer — Modelo: estandar — Archivos: `LogHealthMeasurementTool`, `ListHealthMeasurementsTool`, `docs/UX-WRITING.md` — Hecho cuando: el MCP acepta `weight`, `heart_rate`, `oxygen_saturation` y `temperature` y su resumen incluye promedio y rango de referencia por tipo.

## 8. Bitácora de implementación (developer)
- 2026-11-16 — T1: cuatro tipos nuevos en `HealthMeasurement::TYPES`: `weight` (kg, 20–400, sin rango: no se juzga), `heart_rate` (lpm, 25–250, referencia 60–100), `oxygen_saturation` (%, 50–100, referencia 95–100) y `temperature` (°C, 30–45, referencia 36–37,5). Sin tablas ni pantallas nuevas. El historial suma el filtro «Tipo» (con su chip en «Filtros aplicados» y validación de la clave). El peso medido no cambia `users.current_weight_kg` (hay una prueba que lo fija).
- 2026-11-16 — T2: `log-health-measurement-tool` y `list-health-measurements-tool` aceptan y describen los cuatro tipos; el resumen por tipo trae promedio y rango de referencia.
- Pruebas: 5 de pantalla y 3 de MCP nuevas (34 en total en mediciones). Suite completa: 1700 pruebas, 46 fallas idénticas a la base (entorno).
- No verificado: revisión visual con captura en móvil y escritorio (en particular las tarjetas de «Última medición» con seis tipos).

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-16 — Creado en to-do/ (Borrador, nivel S)
- Decidido 2026-11-16: rangos de referencia generales: frecuencia cardiaca 60–100 lpm, saturación 95–100 %, temperatura 36,0–37,5 °C; el peso no tiene rango (no se juzga). No se personalizan todavía.
- Decidido 2026-11-16: el peso medido NO actualiza `users.current_weight_kg` (afectaría la meta de agua sin que la persona lo pida). Se puede ofrecer como acción explícita más adelante.
- 2026-11-16 — Aprobado por el usuario (Compuerta 2)
- 2026-11-16 — Movido a in-progress/ (En desarrollo)
- 2026-11-16 — Implementación terminada → En verificación

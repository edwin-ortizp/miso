# CHG-132 · Salud: tendencias y contexto de mediciones
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-16 · **Aprobado:** 2026-11-16 · **Cerrado:** 2026-11-16
**Relacionado:** CHG-126 (base)

## 0. En palabras simples
- Qué vas a ver distinto: en Salud aparece «Tendencias»: una gráfica por cada medición que registras (presión, glucosa, peso…) y una del sueño, con el rango de referencia sombreado y un resumen en texto («promedio», «mínimo», «máximo», «cuántas salieron del rango»). Si hay suficientes datos, MiSo te muestra con cautela si tu energía tiende a ser distinta los días que cumples tu meta de sueño. El asistente también ve este resumen cuando le hablas de tu salud.
- Cómo comprobarlo tú: registra varias mediciones de presión en una semana y abre Salud → Tendencias: verás la línea, el promedio y la banda del rango. Cambia entre «30 días» y «90 días». Pregúntale al asistente «¿cómo van mis mediciones y mi sueño?».

## 1. Resumen
Agrega la vista de tendencias sobre lo que ya se registra (CHG-126/123/124/125/127): gráficas simples por tipo de medición y por sueño, hechas en el servidor con SVG (sin librería de gráficas), con el rango de referencia, un resumen en texto y una tabla de datos para lectores de pantalla. Agrega una correlación cauta entre sueño y energía («tiende a») y suma las mediciones, el sueño y los medicamentos de hoy al contexto de salud que lee el asistente (`get-health-context-tool` y `get-recent-activity-tool`). No diagnostica ni establece causas. El ciclo menstrual queda fuera de las tendencias y del contexto amplio por privacidad. El cierre del día no cambia (ver §9).

## 2. Producto (product-manager)
- Problema / usuario: tener los datos no basta; la persona quiere ver si un valor sube, baja o se mantiene y si cumple su sueño, sin interpretar tablas.
- Incluye:
  - Pestaña «Tendencias» con periodo de 30 o 90 días.
  - Una gráfica por cada tipo de medición con datos (presión con dos líneas: sistólica y diastólica) y una de horas de sueño con su meta.
  - Resumen en texto por gráfica: número de registros, promedio, mínimo, máximo, cuántos salieron del rango de referencia y dirección cautelosa («tiende a subir/bajar/mantenerse»).
  - Tabla de datos plegable por gráfica.
  - Correlación sueño ↔ energía con lenguaje cauto y solo con datos suficientes.
  - Contexto para el asistente: mediciones de los últimos 30 días, sueño de los últimos 7 días, medicamentos de hoy y la correlación; módulos `measurements`, `sleep` y `medications` en la actividad reciente.
- No incluye: gráficas del ciclo menstrual, interpretación clínica, causalidad, metas de mediciones, exportar o compartir, el cierre del día, librerías de gráficas, avisos.
- Criterios de aceptación (Dado/Cuando/Entonces):
  - CA1. Dado un tipo con registros en el periodo, cuando abro «Tendencias», entonces veo su gráfica y su resumen en texto; un tipo sin registros no aparece.
  - CA2. Dado un solo registro, cuando veo la gráfica, entonces se muestra un punto y el resumen sin dirección.
  - CA3. Dado seis o más días con registros y un cambio de al menos 5 % entre la primera y la segunda mitad, cuando veo el resumen, entonces dice «tiende a subir» o «tiende a bajar»; si no, «se mantiene estable».
  - CA4. Dado un rango de referencia, cuando se dibuja, entonces aparece como banda con su etiqueta y los valores fuera de rango se distinguen por forma y texto, no solo por color.
  - CA5. Dado siete o más días con sueño y energía y al menos dos días en cada grupo, cuando la energía promedio difiere en 0,5 o más entre días con sueño de meta cumplida y días sin cumplir, entonces se muestra una frase cautelosa que aclara que no implica causa; si no, no se muestra nada.
  - CA6. Dado el asistente, cuando consulta el contexto de salud, entonces recibe un resumen de mediciones, sueño y medicamentos de hoy; lo marcado como sensible no se incluye salvo `include_sensitive=true`, y el ciclo nunca se incluye.
  - CA7. Dado otro usuario, cuando consulta, entonces nunca ve estos datos.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: pestaña nueva «Tendencias» del módulo Salud (patrón de módulo con pestañas de `config/modules.php`; no existe plantilla específica en `## Anatomía` de docs/UX-DESIGN.md).
- Flujo y cantidad de pasos: Salud → Tendencias (0 pasos de captura). Cambiar el periodo: 1 toque en «30 días» / «90 días». Ver los datos exactos: abrir «Ver datos» de una gráfica.
- Orden: periodo → (si aplica) hallazgo de sueño y energía → una tarjeta por tipo, en el orden del catálogo, y el sueño al final.
- Estados: sin ningún dato («Aún no hay datos para mostrar tendencias. Registra una medición o tu sueño»), tipo sin datos en el periodo (no aparece), un solo registro (punto sin dirección).
- Accesibilidad: cada gráfica es una imagen con descripción en texto y tiene el resumen visible y una tabla de datos; los puntos fuera de rango usan otra forma además de otra tonalidad; el valor exacto aparece al pasar el cursor (`<title>`).

```
┌ Salud ▸ … | Ciclo | Tendencias ────────────────────────────────────────────┐
│ MiSo muestra la evolución de lo que registras. No diagnostica ni reemplaza │
│ a tu profesional de salud.        [ 30 días ] [ 90 días ]                   │
│ ┌ Sueño y energía ─────────────────────────────────────────────────┐       │
│ │ Los días que dormiste al menos tu meta, tu energía tiende a ser   │       │
│ │ más alta (4,1 frente a 3,2 de 5). Esto no implica una causa.      │       │
│ └───────────────────────────────────────────────────────────────────┘       │
│ ┌ Presión arterial ─ 12 registros ───────────────────────────────┐          │
│ │ 140┤        ◆                                                    │          │
│ │ 120┤░░░░░░░░░░░░░░░░░░░░░ rango de referencia ░░░░░░░░░░░░░░░░ │          │
│ │  80┤──●──●────●──●── Sistólica   ╌╌■╌╌■╌╌ Diastólica            │          │
│ │ Promedio 122/79 mmHg · 3 fuera de rango · tiende a mantenerse    │          │
│ │ ▸ Ver datos                                                      │          │
│ └──────────────────────────────────────────────────────────────────┘          │
│ ┌ Sueño ─ 14 registros ── meta 8 h ───────────────────────────────┐          │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 3.2 Visual (ui-designer)
- Distribución y componentes (ruta): `x-module-shell module="health"`, `x-ui.section`, `x-ui.chip` para el periodo y un componente nuevo `resources/views/components/health/trend-chart.blade.php` (SVG en línea con `currentColor`, sin estilos en línea ni colores fijos, para heredar claro y oscuro). Una sola tonalidad (la del módulo); la presión distingue sus dos líneas por trazo (continuo y discontinuo) y por marcador (círculo y cuadrado), con leyenda y etiqueta directa al final de cada línea. Texto de valores y ejes en tokens de texto. Líneas finas, banda de referencia tenue y cuadrícula recesiva. Contraste AA.
- Verificación de color: no se define una paleta categórica propia (una sola tonalidad del sistema y diferenciación por forma), así que no aplica el validador de paletas.

### 3.3 Escritura (ux-writer)
- Textos: aviso «MiSo muestra la evolución de lo que registras. No diagnostica ni reemplaza a tu profesional de salud.»; resumen «{n} registros · promedio {x} {unidad} · mínimo {min} · máximo {max}»; «{n} fuera del rango de referencia general ({rango})»; dirección «Tiende a subir», «Tiende a bajar», «Se mantiene estable»; sin datos «Aún no hay datos para mostrar tendencias. Registra una medición o tu sueño en sus pestañas.»; hallazgo «Los días que dormiste al menos tu meta, tu energía tiende a ser {más alta|más baja} ({a} frente a {b} de 5). Esto no implica una causa.».
- Reglas de docs/UX-WRITING.md: tuteo neutro; tono respetuoso y sin humor en salud; lenguaje cauto («tiende a», como CHG-071); sin juicios sobre el cuerpo.
- Criterios de aceptación de los textos: ningún texto diagnostica, atribuye causa ni da consejos clínicos.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar: `app/Support/HealthTrends.php` (series diarias por tipo, resumen, dirección, serie de sueño, correlación sueño-energía; lógica pura y testeable); `app/Livewire/Health/Trends.php` + `resources/views/livewire/health/trends.blade.php`; `resources/views/components/health/trend-chart.blade.php`; ruta `health.trends` y pestaña en `config/modules.php`; `app/Mcp/Tools/Context/GetHealthContextTool.php` (mediciones, sueño, medicamentos de hoy, hallazgo) y `GetRecentActivityTool.php` (módulos `measurements`, `sleep`, `medications`).
- Enfoque y patrones a seguir: `SleepProgress`, `MedicationSchedule` y `LifeTrackerContextMcpTest`; `McpOutput::compact`; el criterio `is_sensitive`/`include_sensitive` de las herramientas existentes.
- Riesgos y mitigación: gráficas con pocos o muchos puntos (máximo un punto por día con el promedio del día; 90 días como tope); lectura clínica de una tendencia (texto cauto y aviso fijo); fugas de privacidad en el contexto (el ciclo nunca se incluye y lo sensible se filtra); consultas pesadas (una consulta por tipo y periodo).
- Qué NO tocar: el cierre del día, el guardián y las notificaciones.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Series, promedio, mínimo, máximo y fuera de rango (CA1, CA2) | Unitaria | Support | `HealthTrendsTest` |
| Dirección con seis o más días (CA3) | Unitaria | Support | `HealthTrendsTest` |
| Correlación cautelosa y umbrales (CA5) | Unitaria | Support | `HealthTrendsTest` |
| Pantalla: gráficas, banda, tabla y estados (CA1, CA2, CA4) | Feature | Livewire | `HealthTrendsPageTest` |
| Contexto del asistente y sensibles (CA6) | Feature | MCP | `LifeTrackerHealthTrendsContextMcpTest` |
| Aislamiento (CA7) | Feature | Pantalla y MCP | ambos |
- Comando para verificar: `php artisan test --filter='HealthTrends|Trends'`, `php artisan ui:conformance`.
- Regresión a vigilar: `LifeTrackerContextMcpTest` y las pruebas de actividad reciente.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/UX-WRITING.md` (textos de tendencias) y `docs/PRODUCT.md` si cambia el alcance de salud.

## 7. Tareas
- [x] T1 · Ver la evolución de mis mediciones y mi sueño en gráficas — Resp: developer — Modelo: estandar — Archivos: `HealthTrends`, `Health\Trends`, vista, componente `trend-chart`, ruta, `config/modules.php` — Hecho cuando: CA1 a CA4 y CA7 pasan en pruebas.
- [x] T2 · Ver si mi energía tiende a cambiar según mi sueño — Resp: developer — Modelo: estandar — Archivos: `HealthTrends`, vista — Hecho cuando: CA5 pasa en pruebas con datos suficientes e insuficientes.
- [x] T3 · El asistente conoce mis mediciones, sueño y medicamentos al hablar de mi salud — Resp: developer — Modelo: estandar — Archivos: `GetHealthContextTool`, `GetRecentActivityTool` — Hecho cuando: CA6 pasa en pruebas.
- [x] T4 · Pruebas, documentación y verificación — Resp: developer — Modelo: estandar — Archivos: `tests/Feature/*`, `docs/UX-WRITING.md` — Hecho cuando: los comandos de §5 pasan y no hay fallas nuevas en la suite.

## 8. Bitácora de implementación (developer)
- 2026-11-16 — T1: `HealthTrends` (lógica pura: serie diaria con el promedio de cada día por tipo, resumen con promedio, mínimo, máximo y fuera de rango, serie de sueño con la meta, y dirección cautelosa), página `Health\Trends` con periodo de 30 o 90 días (`#[Url] days`, valores inválidos vuelven a 30), un componente `x-health.trend-chart` en SVG del servidor (sin librería ni JavaScript, sin estilos en línea ni colores fijos: todo hereda `currentColor`), ruta `health.trends` y pestaña «Tendencias». La presión dibuja dos líneas diferenciadas por trazo (continuo/discontinuo) y por marcador (círculo/cuadrado), con leyenda en texto y etiqueta directa; los puntos fuera de rango son rombos (forma, no solo tono); cada punto trae su valor en `<title>`; cada gráfica tiene resumen visible y una tabla «Ver datos». Sin registros de un tipo, no aparece su tarjeta.
- 2026-11-16 — T2: `HealthTrends::sleepEnergy` compara la energía promedio de los días con sueño de meta cumplida y sin cumplir (mínimo 7 días con ambos datos, 2 por grupo y diferencia de 0,5 o más) y solo entonces muestra la frase cautelosa «…tiende a ser más alta/baja… Esto no implica una causa.».
- 2026-11-16 — T3: `get-health-context-tool` suma `measurements_last_30_days`, `sleep_last_7_days`, `sleep_and_energy` y `medications_today` (lo sensible se omite salvo `include_sensitive=true` y se suma a `hidden_sensitive`); `get-recent-activity-tool` suma los módulos `measurements`, `sleep` y `medications`. El ciclo menstrual no aparece en ninguno (hay una prueba).
- 2026-11-16 — T4: `HealthTrendsTest` (13) y `LifeTrackerHealthTrendsContextMcpTest` (6) pasan. Suite completa: 1758 pruebas, 46 fallas idénticas a la base (entorno).
- Decisión: el cierre del día NO se modificó (ver §9): sus metas las acompaña el guardián y se marcan «Pendiente»; para sueño o medicamentos sería un juicio. Los colores de la gráfica heredan `currentColor` del sistema, así que no se definió ni validó una paleta propia.
- No verificado: no se vieron las gráficas renderizadas (no hay navegador ni assets en este entorno): la geometría (ejes, banda, etiquetas, solapamientos) y el aspecto en claro/oscuro y móvil están sin revisar visualmente. Es el punto de mayor riesgo de esta entrega.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-16 — Creado en to-do/ (Borrador, nivel M)
- Decidido 2026-11-16: el cierre del día NO cambia. Cuenta metas que acompaña el guardián y las marca «Pendiente»; para sueño o medicamentos eso sería un juicio sobre el cuerpo y mezclaría la gamificación con datos de salud. Se puede decidir aparte.
- Decidido 2026-11-16: gráficas en SVG del servidor, sin librería ni JavaScript; una sola tonalidad del sistema y diferenciación por forma y trazo.
- Decidido 2026-11-16: el ciclo menstrual no entra a tendencias ni al contexto amplio del asistente (solo `list-cycle-tool` con `include_sensitive=true`).
- 2026-11-16 — Aprobado por el usuario (Compuerta 2)
- 2026-11-16 — Movido a in-progress/ (En desarrollo)
- 2026-11-16 — Implementación terminada → En verificación
- 2026-11-16 — Renumerado de CHG-128 a CHG-132 (choque con los CHG sociales CHG-122 a CHG-125 que ya estaban en main).
- 2026-11-16 — Cerrado por el usuario (Compuerta 3) → done/

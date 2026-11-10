# CHG-018 · Inventario tabular y documento tipo Siigo X
**Estado:** Cerrado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-04 · **Aprobado:** 2026-11-04 · **Cerrado:** 2026-11-10
**Relacionado:** CHG-002

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: registro, documento y kárdex con columnas claras; documento con cabecera y observaciones.
- Cómo comprobarlo tú: abrir Movimientos, consultar un documento y entrar al kárdex de un ingrediente, también en móvil.

## 1. Resumen
<!-- Qué y por qué, 2-3 líneas. Agrega solo la sección del rol afectado (## 3. UX/UI, ## 4. Diseño técnico o ## 5. Pruebas) si hace falta. Si hay UI, §3 lleva un boceto ASCII de la zona que cambia. -->

Tablas de solo lectura y hoja documental inspirada en el ajuste emitido de Siigo X, adaptadas a MiSo. Plan aprobado por el usuario el 2026-11-04; implementación completa autorizada.

## 3. UX/UI
Registro → Documento → Kárdex → retorno conservando filtros.
```text
[Título / ID / fechas / origen]
[Tabla de líneas]
[Observaciones / correcciones]
```
Tablas canónicas md-table--stack, etiquetas en móvil, números a la derecha, sin totales entre unidades.

## 4. Diseño técnico
Archivos: resources/views/livewire/meal/meal-inventory-{documents,document-show,history}.blade.php; resources/css/m3/modules/_meals.css; tests/Browser/purchases.interaction.spec.js; docs/integrations/meal-inventory.md. Assets generados mediante npm run build.
Conservar consultas, snapshots, rutas, contratos MCP, aislamiento y saldos persistidos. Sin migraciones ni escrituras de inventario. Reutilizar management-card y componentes de documento existentes.

## 5. Pruebas
php artisan test --filter='InventoryDocumentsTest|InventoryHistoryTest'; npm run build; php artisan ui:conformance; navegador aislado con documento de 32 líneas, filtros, retorno, teclado, Axe y geometría a 390/768/1440; paginación de 101 líneas cubierta por Feature; git diff --check.

## 7. Tareas
<!-- Primero lo que cambia para el usuario; el detalle técnico después. Formato:
- [x] T1 · qué cambia (en simple) — Resp: dev-implementer — Modelo: perfil — Archivos: … — Hecho cuando: … -->

- [x] T1 · Consultar registro y kárdex por columnas — Resp: dev-implementer — Archivos: vistas documents/history y _meals.css — Hecho cuando: datos, segmentos, acciones y filtros se conservan, filas se apilan con etiquetas en móvil.
- [x] T2 · Consultar documento con cabecera y líneas tabulares — Resp: dev-implementer — Archivos: vista document-show y _meals.css — Hecho cuando: ID copiable, metadatos, 32 líneas, saldos, observaciones y correcciones son accesibles.
- [x] T3 · Verificar y documentar las tres vistas — Resp: dev-implementer — Archivos: tests/Browser/purchases.interaction.spec.js, docs/integrations/meal-inventory.md — Hecho cuando: comprobaciones focalizadas, build, conformance y responsive pasan o límites preexistentes quedan explícitos.

## 8. Bitácora de implementación (dev-implementer)

2026-11-05 — T1/T2 implementadas: registro, líneas y kárdex con tablas semánticas, entradas/salidas separadas, saldos persistidos, tramos y apilado móvil etiquetado. Documento con hoja, ID copiable, metadatos, observaciones y correcciones. CSS acotado a inventario; enlaces ajustados a su celda tras inspección visual. Sin cambios a motor, contratos, rutas ni datos habituales.

Validación: 14 pruebas focalizadas, 99 aserciones correctas; build correcto con aviso de tamaño de chunks; ui:conformance sin deuda nueva (345 inventariadas, 256 desviaciones). Revisión visual de capturas de documento/registro/kárdex. Pruebas de navegador en SQLite aislada; se ajustan aserciones al formato tabular y se comprueba geometría de enlaces. Presión de memoria externa provocó fallos de arranque de Node/PHP y timeouts iniciales; se limita memoria de Node, se espera navegación explícitamente y se amplía el timeout de este flujo. Verificación final correcta: 3/3 recorridos completos a 390/768/1440 px (55.4 s), con 32 líneas, copia de ID, filtros/retorno, teclado, Axe sin infracciones serias/críticas del contenido nuevo, ausencia de desbordamiento y enlaces dentro de sus celdas. Capturas kardex-{registry,document,history}-{390,768,1440}.png en storage/framework/testing/purchase-previews. Corrección del harness durante verificación: el chequeo geométrico se acotó a enlaces dentro de td; no debe incluir acciones del encabezado. El servidor de la suite queda explícitamente aislado en 8027. Sintaxis JS y git diff --check correctos. Sin migraciones, cambios reales de inventario, commits ni despliegue. Se conserva la deuda compartida de contraste de navegación ya documentada en CHG-002; no se amplió alcance a ella.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-04 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-04 — Aprobado por el usuario (Compuerta 2)
- 2026-11-04 — Movido a in-progress/ (En desarrollo)

2026-11-05 — Usuario aprobó el plan completo y autorizó su implementación en lote. Se conserva el resto del checkout.
- 2026-11-05 — Implementación terminada → En verificación
- 2026-11-10 — Cerrado por el usuario (Compuerta 3) → done/

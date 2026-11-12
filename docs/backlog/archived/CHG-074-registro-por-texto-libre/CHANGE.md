# CHG-074 · Registro por texto libre
**Estado:** Descartado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-11 · **Aprobado:** — · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: escribes o dictas "tomé 2 vasos de agua y fui al gym 40 minutos" y MiSo registra el agua y el ejercicio, mostrándote qué entendió antes de guardar.
- Cómo comprobarlo tú: escribe una frase con varios registros y confirma la vista previa.

## 1. Resumen
Separado de CHG-069 por decisión del usuario. Interpretar texto libre requiere un modelo de lenguaje: la frase se convierte en llamadas a las mismas acciones que ya expone el MCP de MiSo, con vista previa y confirmación. Necesita definir proveedor, costo y dónde vive la clave.

## 2. Producto (product-manager)
- Problema / usuario:
- Incluye:
- No incluye:
- Criterios de aceptación (Dado/Cuando/Entonces):

## 3. UX/UI
<!-- Sin pantallas: escribe "No aplica — motivo" en cada parte. Con UI, approve exige un boceto ASCII en un bloque de código. Si hay flujo, agrega un diagrama en un bloque mermaid. -->
### 3.1 Experiencia (ux-designer)
- Flujo y cantidad de pasos:
- Qué contiene cada pantalla y qué es lo más importante:
- Estados (vacío / carga / error / éxito / validaciones):
- Información necesaria y momento de presentación:
- Accesibilidad de uso (teclado, foco, errores):

### 3.2 Visual (ui-designer)
- Distribución:
- Componentes existentes a reutilizar (ruta):
- Jerarquía, color, tipografía y espaciado (tokens):
- Responsive y contraste:

### 3.3 Escritura (ux-writer)
- Ubicación y estado:
- Textos finales (variables y variantes):
- Reglas de docs/UX-WRITING.md aplicadas:
- Criterios de aceptación de los textos:

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar:
- Enfoque y patrones a seguir:
- Riesgos y mitigación:
- Qué NO tocar:

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
- Comando para verificar:
- Regresión a vigilar:

## 6. Documentación (tech-writer)
- Docs a actualizar:

## 7. Tareas
<!-- Primero lo que cambia para el usuario; el detalle técnico después. Formato:
- [ ] T1 · qué cambia (en simple) — Resp: developer — Modelo: perfil — Archivos: … — Hecho cuando: … -->

## 8. Bitácora de implementación (developer)

## 9. Preguntas abiertas, decisiones e historial
- Pregunta: ¿qué proveedor de IA y qué tope de costo mensual aceptas?
- Pregunta: ¿solo texto o también voz (dictado del sistema basta en el celular)?
- 2026-11-11 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-11 — Descartado: No relevante aún (desde el panel)

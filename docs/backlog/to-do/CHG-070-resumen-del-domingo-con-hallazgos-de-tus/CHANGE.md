# CHG-070 · Resumen del domingo con hallazgos de tus datos
**Estado:** Borrador
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-11 · **Aprobado:** — · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: cada domingo, un resumen con 2–3 hallazgos sobre tu propia vida, p. ej. "los días que haces ejercicio tu energía sube 1,2" o "los martes avanzas más tareas".
- Cómo comprobarlo tú: con al menos 3 semanas de datos, abre el resumen del domingo.

## 1. Resumen
Los datos registrados no le devuelven nada al usuario, así que registrar se siente administrativo. Calculamos correlaciones simples entre fuentes (ejercicio, sueño/energía, ánimo, agua, tareas, hábitos) y mostramos solo las que superan un mínimo de datos y diferencia. Sin IA en la primera versión; lenguaje cauto ("tiende a", no causalidad).

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
- Pregunta: ¿mostrarlo en Inicio, en Estadísticas o como aviso push del domingo?
- Necesita: datos de CHG-066 para medir si aumenta la recurrencia.
- 2026-11-11 — Creado en to-do/ (Borrador, nivel M)

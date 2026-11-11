# CHG-068 · Registro en un toque y check-in de 20 segundos
**Estado:** Borrador
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-11 · **Aprobado:** — · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: desde el aviso puedes marcar el hábito o sumar un vaso sin abrir la app, y en Inicio hay un "check-in de 20 s" con ánimo, energía, agua y hábitos pendientes en una sola pantalla.
- Cómo comprobarlo tú: toca "Hecho" en un aviso y verifica el hábito; abre el check-in y registra todo en menos de 20 s.

## 1. Resumen
Registrar cuesta: cada dato vive en su módulo. Reducimos la acción a un toque: acciones en la notificación push (CHG-067) y una hoja de check-in que reúne los registros más frecuentes. Reutiliza los servicios existentes de hábitos, agua, ánimo y energía; entra con `?via=checkin` para CHG-066.

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
- Depende de: CHG-067.
- Pregunta: ¿incluir captura por texto libre ("tomé 2 vasos y fui al gym") usando las herramientas MCP, o dejarlo para otro CHG?
- 2026-11-11 — Creado en to-do/ (Borrador, nivel M)

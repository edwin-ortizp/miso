---
name: "miso-listo-para-ia"
description: "Audita y completa tareas de MiSo (antes Life Tracker) para garantizar que una IA pueda ejecutarlas sola, y solo entonces las deja en el estado AI Agent. Úsala siempre que diga 'revisemos si esta tarea está lista para la IA', 'pasa X a AI Agent', 'audita/refinemos las tareas de AI Agent', 'esta tarea es para la IA', o pase un ID/nombre de tarea pidiendo dejarla para un agente, aunque no diga 'auditar'. Va después de miso-refinar-tareas: toma tareas en Ready (o ya en AI Agent) y las vuelve accionables por IA. No ejecuta las tareas."
---

# Dejar tareas de MiSo listas para que las ejecute una IA

Las tareas en el estado **AI Agent** las toma una tarea programada que corre **en el computador de Alexander** y las ejecuta sin que él esté presente. Esa IA planea con él, pero no puede adivinar el proyecto, el repo, qué está fuera de alcance ni cuándo terminó. Tu trabajo es ser el portero: **ninguna tarea entra (ni se queda) en AI Agent si una IA tendría que adivinar algo esencial.** No ejecutes la tarea; solo la auditas, la completas con Alexander y la mueves.

Herramientas: `mcp__MiSo__*` (cárgalas con ToolSearch si faltan). Llámala MiSo.

Principio clave: **Alexander define el producto, no siempre lo técnico.** Pregúntale el qué, el para qué, el alcance y cuándo está hecho. Lo técnico que no sepa no bloquea: lo investigas tú en el repo (paso 2b) o queda en "Investigar antes de implementar" para el agente. Lo que sí bloquea es lo funcional ambiguo.

## Reglas fijas del entorno

- **Repos:** todos viven en `C:\laragon\www\<repo>\` (p. ej. `C:\laragon\www\certmind`, `C:\laragon\www\certmind-web`, `C:\laragon\www\gesthor`, `C:\laragon\www\quizlab`, `C:\laragon\www\siigo-x`, `C:\laragon\www\unicauca`). En la descripción anota el repo con su ruta completa.
- **Ejecución:** siempre local, en el computador de Alexander. No preguntes dónde corre.
- **Sin commits, nunca.** La IA ejecutora deja los cambios en el working tree para que Alexander los revise; no hace commit, push, PR ni cambia de rama. Esto va siempre en "Fuera de alcance" y como condición en "Terminar significa". Si la tarea pide un commit/PR, señálalo como contradicción.

## 1. Encontrar las tareas

- Consulta los estados reales con `manage-task-status-tool` (`action: list`). Identifica el estado de agente (nombre con "agent", tipo `ready`) y Ready.
- Por ID → `get-task-tool`. Por nombre → `list-tasks-tool` con `search` (si hay varias, pregunta).
- "Audita las de AI Agent" → `list-tasks-tool` con `status_name` de ese estado. "Las que están en Ready" → igual con Ready.
- Con varias tareas: muestra la lista y trabaja **una por una**.

## 2. Reunir contexto antes de preguntar

**a) Fuentes de conversación:** lee la tarea completa. Busca en memoria (archivos del proyecto/app), chats pasados (`conversation_search` con palabras clave de la tarea) y tareas relacionadas en MiSo (hermanas, dependencias como "CER-00052"). Ahí suele estar el repo, el stack y decisiones previas.

**b) Revisión del repo (cuando la tarea toca código o el contexto no alcanza):** si el computador de Alexander está conectado, revisa el repo en `C:\laragon\www\<repo>\` **en solo lectura** (herramientas `mcp__remote-devices__*`; si no están, llama primero la `enable__mcp__remote-devices__*` disponible o pide acceso a la carpeta). Busca lo justo para preguntar mejor y anotar insumos concretos:
- Que el repo exista y su stack (README, `composer.json`/`package.json`, marcador `mockup-system.project.json`).
- Las pantallas, modelos, rutas o módulos que la tarea menciona (p. ej. "insignias", "vouchers", "partner"): dónde están y cómo funcionan hoy.
- Funcionalidad parecida que sirva de patrón.
Nunca modifiques archivos. Si no hay acceso al computador, sigue sin esto y deja la exploración en "Investigar antes de implementar".

Di en 2–3 líneas qué encontraste y de dónde. Si encuentras algo, **confírmalo** en vez de preguntarlo ("Entiendo que es `C:\laragon\www\certmind`, ¿correcto?").

## 3. Evaluar contra el checklist "listo para IA"

Muestra el resultado como tabla ✅ / ⚠️ / ❌ con una nota corta:

| Criterio | Qué se necesita |
|---|---|
| Proyecto | Siempre. A qué proyecto/app pertenece. |
| Repositorio | Si la tarea toca código o archivos: la ruta `C:\laragon\www\<repo>\` (verificada en el paso 2b si se pudo). Sin rama base. Si no aplica repo, decirlo explícitamente. |
| Objetivo | Qué pasa hoy y qué debe pasar / qué debe producirse, sin ambigüedad. |
| Alcance | Qué sí entra y qué **no** (fuera de alcance; siempre incluye "commits"). |
| Criterios de aceptación | Condiciones que **una IA puede verificar por sí misma** (test que pasa, comando que corre, archivo que existe con X, pantalla que muestra Y). "Queda bien", "listo para revisar" o "queda definido" no sirven. |
| Insumos | Archivos, módulos, documentos, links o datos de partida (los hallados en 2b van aquí con su ruta). |
| Validación | Cómo se comprueba: tests, build, revisión visual, comparar con un documento. |
| Entregable | Qué deja la IA al terminar: cambios sin commit en el repo, archivo en una ruta concreta, comentario o actualización en la tarea de MiSo. |
| Dudas abiertas | Nada funcional sin responder que impida empezar. |

Pregúntate además, críticamente: si yo fuera la IA, sola, sin poder preguntar, **¿dónde me equivocaría o haría de más?** Lo que aparezca también es un ❌.

Si la tarea es demasiado grande o mezcla cosas, propón partirla.

**Contradicciones:** si una respuesta de Alexander o lo hallado en el repo choca con lo que dice la tarea (p. ej. la tarea pide "pantalla de compra" y él dice que no se compra en la plataforma), no lo resuelvas en silencio: dilo explícitamente y deja el resultado visible en el alcance.

**Límites del agente:** revisa que cada criterio lo pueda cumplir un agente sin intervención humana (no puede instalar cosas en cuentas de Alexander, usar apps a las que no tiene acceso ni hacer commits). Lo que requiera a Alexander se separa: fuera de alcance, paso posterior suyo o tarea aparte.

## 4. Preguntar bien e iterar

Si hay ❌ o ⚠️, pregunta con **AskUserQuestion** (1–4 preguntas por ronda; si no está disponible, en texto con opciones numeradas). Cómo armar buenas preguntas:

- **Opciones salidas del contexto, no genéricas.** Usa lo que viste en el repo y en la tarea: "En el repo los vouchers se asignan desde *Admin › Partners › Vouchers*. ¿El cupo de insignias se asigna igual?" es mejor que "¿Cómo se asigna el cupo?".
- **Marca una recomendada** cuando tengas base para ello (primera opción, con "(Recomendada)") y di por qué en su descripción.
- **Pregunta el comportamiento, no la implementación.** "¿Qué ve el partner cuando se pasa del cupo?", no "¿qué validación agrego?".
- **Incluye "Que el agente proponga"** solo cuando la decisión sea realmente de detalle y revisable después; no para lo que define el alcance.
- **Prioriza:** primero lo que bloquea (alcance, entregable, contradicciones), después los detalles.
- **Header corto** (máx. 12 caracteres) y una pregunta por tema; no juntes dos decisiones en una.

Después de cada ronda vuelve a evaluar la tabla completa. **No hay tope de rondas.** Una respuesta puede abrir dudas nuevas o pedir otra revisión del repo; hazla. Si Alexander no puede responder algo ahora, la tarea no pasa (paso 6).

## 5. Redactar la descripción

Con las palabras de Alexander (organiza, no formalices, no inventes):

```
**Proyecto:** …
**Repositorio:** C:\laragon\www\<repo>\ (o "No aplica")

**Contexto**
…

**Objetivo**
…

**Alcance**
- Incluye: …
- Fuera de alcance: … · commits, push, PR o cambio de rama

**Insumos**
- … (rutas encontradas en el repo)

**Investigar antes de implementar** (solo si quedan temas técnicos abiertos)
…

**Cómo validar**
…

**Entregable**
…

**Terminar significa:**
- [ ] condición verificable por una IA
- [ ] Los cambios quedan sin commit en el repo para revisión de Alexander
```

Muéstrala junto con la tabla final (todo ✅) y pide confirmación.

## 6. Guardar y mover

Solo con OK explícito:

- `update-task-tool` una vez con `description` y `status` = estado AI Agent.
- Si la descripción original tenía información que no entró, pregunta si conservarla al final bajo `---\nNotas originales:`.
- No toques otros campos. Nunca la pases a WIP ni a done.

Si **no** queda lista:
- Si venía de AI Agent, propón sacarla a Ready (o a Borrador si está muy verde) y explica qué falta.
- Si venía de Ready, déjala ahí. Con permiso, guarda el avance con una sección `Pendiente para IA:` al final.

## 7. Cierre

Una línea por tarea: cómo quedó y en qué estado. En una auditoría, termina con un resumen: cuántas quedaron en AI Agent, cuántas se devolvieron y por qué. Si Alexander corrigió el checklist o las preguntas, proponle actualizar esta skill.

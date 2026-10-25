---
name: miso-validar-tareas
description: "Valida que lo pedido en una o varias tareas de MiSo (antes Life Tracker) realmente se desarrolló en el repositorio, y sugiere cerrarlas solo si todo se cumple. Recibe uno o varios IDs (TEC-00014, SGX-00231…), lee cada tarea, revisa el repo en C:\\laragon\\www y reporta qué se cumplió y qué falta. Úsala siempre que diga 'valida/verifica/revisa si la tarea X quedó completa', 'antes de cerrar esta tarea', 'la IA ya terminó SGX-…', 'revisa lo que hizo el agente', 'qué le falta a esta tarea', 'puedo cerrar estas tareas', o pase IDs de MiSo pidiendo comprobarlas contra el código, aunque no diga 'validar'. No ejecuta ni corrige el desarrollo: solo comprueba, reporta y, con confirmación, cierra."
---

# Validar tareas de MiSo contra el repositorio

Alexander delega muchas tareas a agentes de IA (y otras las hace él) y luego no sabe con certeza si quedó todo antes de cerrarlas. Tu trabajo es ser el **revisor final**: comparar lo que la tarea de MiSo pide contra lo que de verdad existe en el repo, elemento por elemento, y decir con evidencia si está completa o qué falta. Cerrar una tarea que no está completa es peor que dejarla abierta, así que ante la duda no la declares cumplida.

Herramientas: `mcp__MiSo__*` y `mcp__remote-devices__*` (cárgalas con ToolSearch si faltan). Llámala MiSo.

## Reglas fijas

- **Solo lectura sobre el repo.** No edites archivos, no corrijas lo que falte, no hagas commit, push, PR ni cambies de rama. Revisas; no arreglas. Si Alexander quiere que lo faltante se implemente, es otra tarea.
- **Repos:** viven en `C:\laragon\www\<repo>\` (certmind, certmind-web, gesthor, quizlab, siigo-x, unicauca…).
- **Los agentes no hacen commit**, así que el trabajo normalmente está **sin commitear en el working tree**. Revísalo con `git status` / `git diff` además de los archivos tal como están; no concluyas "no existe" mirando solo el historial de commits.
- **MiSo solo se modifica con OK explícito**, tarea por tarea o en lote confirmado. Cerrar = `complete-task-tool` con `action: complete`. `update-task-tool` no puede dejar una tarea en done (el estado done es fijo).

## Flujo

```
IDs → leer tarea → extraer checklist → identificar repo → verificar cada elemento
   → veredicto por tarea → reporte → ¿Completa? ── sí → proponer cerrar → OK → complete-task-tool
                                              └── no → solo reportar faltantes
```

## 1. Leer las tareas

- Cada ID (código legible o UUID) → `get-task-tool`. Si pasa varios, trabaja **una por una** y deja el resumen para el final.
- Si da un nombre en vez de ID → `list-tasks-tool` con `search`; si hay varias coincidencias, pregunta.
- Si la tarea ya está en done, valídala igual (puede servir de auditoría) y dilo.

## 2. Extraer el checklist verificable

La descripción es la fuente de verdad. Convierte lo que pide en una lista numerada de elementos que se puedan comprobar:

- Si es una tarea refinada, usa sobre todo **Terminar significa**, **Alcance › Incluye**, **Objetivo** y **Cómo validar**. **Fuera de alcance** no es checklist, pero sí se revisa al revés: si el agente hizo algo que estaba prohibido, repórtalo.
- Incorpora las subtareas (`- [ ]`) y cualquier archivo, ruta, pantalla o comportamiento que se nombre.
- Si la descripción es vaga (sin elementos concretos), no inventes criterios: dilo ("esta tarea no tiene nada verificable") y propón refinarla antes de validar.

Muestra el checklist en una línea por elemento antes de revisar, para que Alexander vea contra qué estás midiendo.

## 3. Identificar el repositorio

En este orden: línea `Repositorio:` de la descripción → rutas que se mencionen → categoría de la tarea como **pista, no como respuesta**. La categoría engaña: una tarea de categoría Unicauca puede ser documentación del SGSI (repo `unicauca`) o el módulo de auditorías (repo `gesthor`, que tiene una instancia para Unicauca); certmind puede ser `certmind` o `certmind-web`. Si la descripción no nombra el repo, **confirma con Alexander** en una frase ("¿Es `C:\laragon\www\gesthor`?"), con la opción que más pese como primera. Si la tarea no toca código (documento, trámite, revisar algo en una plataforma), dilo y valida por el medio que corresponda, o márcala como no verificable.

Antes de dar un elemento por ❌ Falta, comprueba que estás mirando el repo correcto: si el repo ni siquiera contiene el módulo o tema de la tarea (solo datos o documentos), probablemente es otro repo. Pregunta antes de concluir que "no existe".

Para acceder: si las herramientas `mcp__remote-devices__*` no están, llama primero la `enable__mcp__remote-devices__*` disponible o pide acceso a la carpeta. Si no hay forma de llegar al computador, dilo y explica cómo conectarlo (abrir el chat en la app de escritorio, "Add folder"); no valides "de memoria".

## 4. Verificar cada elemento

Para cada elemento busca **evidencia concreta** en el repo: el archivo, la ruta, el método, la vista o la migración que lo implementa, y que haga lo que la tarea pide (no basta con que exista el archivo). Apóyate en `git status`, `git diff` y búsquedas de texto, y lee los archivos relevantes.

Si el agente dejó un reporte o un resumen ("todo listo", `reporte-*.md`), úsalo como pista de dónde mirar, pero **comprueba tú mismo**: un reporte describe lo que el agente cree que hizo. Cuando el elemento admita una comprobación programática (contar archivos, comparar filas, buscar una ruta), hazla en vez de leer solo el reporte.

Cada elemento recibe un veredicto:

| Veredicto | Cuándo |
|---|---|
| ✅ Cumplido | Hay evidencia directa de que existe y hace lo pedido. Cita archivo (y línea si ayuda). |
| ⚠️ Parcial | Existe pero incompleto, con casos sin cubrir, o difiere de lo pedido. Di qué falta. |
| ❌ Falta | No se encontró implementación. Di dónde se buscó. |
| ❓ No verificable | Depende de algo que el código no muestra (revisión visual, despliegue, acción humana, dato en producción). No cuenta como cumplido. |

Si "Cómo validar" pide correr tests o un build, puedes hacerlo cuando sea seguro (no altera archivos del proyecto); si dudas de que pueda tener efectos, pregunta antes. Reporta el resultado real, no lo supongas.

## 5. Veredicto y reporte por tarea

Un veredicto global por tarea:

- **Completa**: todos los elementos ✅ (los ❓ se mencionan y dependen de Alexander).
- **Incompleta**: hay ⚠️ o ❌.
- **No concluyente**: no se pudo acceder al repo o la tarea no es verificable.

Formato (breve, sin relleno):

```
### TEC-00014 · <título> — ✅ Completa / ⚠️ Incompleta / ❓ No concluyente
Repo: C:\laragon\www\<repo>\

| # | Elemento | Veredicto | Evidencia |
|---|----------|-----------|-----------|
| 1 | … | ✅ | app/Http/…:42 |

Faltan: … (solo si hay)
Fuera de alcance violado: … (solo si hay)
```

Con varias tareas, cierra con una tabla resumen (tarea · veredicto · qué falta en una línea).

## 6. Sugerir el cierre

- **Completa** → propón cerrarla con `complete-task-tool` y espera un sí explícito. Con varias, agrupa las completas en una sola pregunta ("¿Cierro SGX-1, SGX-2 y SGX-3?"). Si hay ❓ pendientes, menciónalos antes de pedir el OK: la decisión de cerrar es de Alexander.
- **Incompleta o no concluyente** → no propongas cerrar. Solo reporta qué falta y deja que él decida el siguiente paso (no cambies estado, no escribas notas en la tarea a menos que lo pida).
- Tras cerrar, confirma en una línea cuáles quedaron cerradas.

## Cierre

Termina con el resumen y lo que sigue por decidir. Si Alexander corrige tu criterio de validación (por ejemplo, un tipo de evidencia que sí aceptaba o no), propón actualizar esta skill.

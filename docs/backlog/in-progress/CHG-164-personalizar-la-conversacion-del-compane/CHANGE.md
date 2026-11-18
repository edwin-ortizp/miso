# CHG-164 · Personalizar la conversación del compañero por pantalla
**Estado:** En verificación
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-17 · **Aprobado:** 2026-11-17 · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
Abre el compañero en cualquier módulo. Te saluda por tu nombre y hace una pregunta sobre lo que puedes hacer allí. Al reabrirlo retoma el hilo.

## 1. Resumen
Personalizar la apertura por pantalla y actualizar la voz de los recorridos existentes. Plan aprobado con «Implement the plan».

## 2. Producto (product-manager)
- Incluye todas las pantallas web con compañero, los mensajes compartidos y los recorridos de Tareas, Hidratación, Ejercicio y Ánimo.
- Dado un usuario con favorito, al abrir el chat ve su nombre y una pregunta pertinente. Sin nombre se usa «Hola». Al reabrir no se repite el saludo.
- Fuera de alcance: habilitar nuevas superficies, cambiar acciones o integrar IA.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
Plantilla: lista + detalle, con el panel del compañero sobre la pantalla existente. Se mantiene el flujo de apertura, conversación y cierre.

```text
Tareas
Compañero  Hola, Edwin. ¿Qué tarea tienes en mente?
Edwin      Enviar el informe
Compañero  Listo, ya quedó en tu lista.
```

### 3.2 Visual (ui-designer)
No aplica — se reutilizan las burbujas y el panel existentes.

### 3.3 Escritura (ux-writer)
«Hola, {nombre}.» o «Hola.» seguido de una pregunta específica. Para acciones dinámicas, citar el nombre del botón. Frases cortas, tuteo, confirmaciones precisas, tono respetuoso en temas sensibles. El catálogo de textos y sus variables vive en config/companion_chat.php.

## 4. Diseño técnico (software-architect)
Resolver el mensaje en el Blade compartido con el nombre autenticado y una tabla de rutas. Las pantallas con preguntas de acciones rápidas las conservan como fuente. Para una acción dinámica o una ruta sin texto editorial usar su etiqueta real. Reutilizar el contrato JS de apertura y de conservación del hilo.

## 5. Pruebas (qa-tester)
CompanionChatTest, pruebas JavaScript y Playwright del compañero, build, UI conformance y diff. Comprobar nombre, pantallas, hilo, FAB y las acciones rápidas.

## 6. Documentación (tech-writer)
Actualizar docs/companion-chat.md con la selección de textos.

## 7. Tareas
- [x] T1 · Personalizar el saludo y actualizar la conversación — Resp: developer — Archivos: config/companion_chat.php, resources/views/components/ui/companion-chat.blade.php, app/Services/Game/FavoriteCompanion.php, pruebas del compañero, docs/companion-chat.md — Hecho cuando: el saludo corresponde a la pantalla y los recorridos existentes pasan sus pruebas.

## 8. Bitácora de implementación (developer)
- Apertura con nombre y pregunta contextual en el componente compartido. El catálogo enumera las rutas con texto propio. Las demás usan la pregunta rápida o la etiqueta de su acción real.
- CompanionChatTest: 23 pruebas, 132 aserciones. JavaScript: 21 pruebas. Playwright: 9 pruebas, incluyendo reapertura, navegación entre pestañas, registros rápidos, FAB y formularios. Build en copia temporal para preservar el trabajo concurrente en assets publicados. Diff limpio.
- UI conformance informó 196 desviaciones fuera de los archivos de interfaz editados. El CLI local no incluye los comandos context y activity. Se registró la evidencia en esta bitácora.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-17 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-17 — Aprobado por el usuario (Compuerta 2)
- 2026-11-17 — Movido a in-progress/ (En desarrollo)
- LECCIÓN: ninguna.
- 2026-11-17 — Implementación terminada → En verificación

# CHG-159 · Mostrar institución y cambiar semestre de materias
**Estado:** En verificación
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-17 · **Aprobado:** 2026-11-17 · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
- En Formaciones, busca el nombre de la institución debajo del nombre de la formación.
- En una materia, abre ⋯ → Cambiar semestre, elige un semestre o Sin semestre y guarda.

## 1. Resumen
Mostrar la institución guardada y permitir cambiar el semestre de una materia conservando su contenido y sus accesos directos.

## 3. UX/UI
Plantilla: lista + detalle. Reutilizar el editor y abrir Organización desde el menú.
```text
Ingeniería de sistemas
  Nombre de la institución

Matemáticas I  [⋯ → Cambiar semestre]
Organización · Semestre [Sin semestre ▾] · [Guardar]
```

## 4. Diseño técnico
Validar el destino dentro de la transacción por usuario. Ubicar la materia al final del destino y conservar su identidad.

## 5. Pruebas
Verificar subtítulo, edición y menú, destinos válidos e inválidos, regreso al nivel principal y conservación de temas y accesos directos.

## 7. Tareas
- [x] T1 · Mostrar institución y permitir cambiar el semestre — Resp: developer — Archivos: resources/views/livewire/learning, app/Livewire/Learning/LearningPage.php, app/Services/Learning/LearningRecords.php, tests/Feature — Hecho cuando: el listado muestra la institución y una materia puede cambiar de semestre sin perder contenido.

## 8. Bitácora de implementación (developer)
- 2026-11-17 — Implementados el subtítulo y el cambio de semestre. Pasaron 26 pruebas de Formación y `git diff --check`. Conformidad reportó desviaciones fuera de Formación. Pendiente revisión visual en navegador. El CLI local carece de registro automático de contexto.

- 2026-11-17 — Corregidos el espaciado del título y el recorte del menú. El menú flotante se activa en Formación. Pasaron 26 pruebas PHP y 4 pruebas en Chromium, con comprobaciones a 375, 768 y 1440 px. Compilación publicada y conformidad sin hallazgos en las vistas modificadas.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-17 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-17 — Aprobado por el usuario (Compuerta 2)
- 2026-11-17 — Movido a in-progress/ (En desarrollo)
- 2026-11-17 — Implementación terminada → En verificación
- 2026-11-17 — Reabierto: Corregir alineación y recorte de menú detectados en verificación
- 2026-11-17 — Implementación terminada → En verificación

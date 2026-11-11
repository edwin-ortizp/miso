# CHG-062 · Ficha del guardián en un modal sin salir del módulo
**Estado:** Cerrado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-10 · **Aprobado:** 2026-11-10 · **Cerrado:** 2026-11-11
**Relacionado:** CHG-055, CHG-057, CHG-058 (guardianes de módulo)

## 0. En palabras simples
- Qué vas a ver distinto: al tocar al guardián en cualquier módulo se abre su ficha en una ventana encima del módulo (nivel, experiencia, evoluciones, historial, poder). Ya no te saca a la Colección; si quieres ir, hay un botón "Ver colección".
- Cómo comprobarlo tú: en Hidratación toca al guardián → se abre la ficha y la dirección de la página no cambia. Cierra → sigues en Hidratación. Toca "Ver colección" → vas a la Colección con su ficha abierta.

## 1. Resumen
La ficha de especie de la Colección se extrae a un servicio reutilizable y se muestra en solo lectura dentro de `GuardianCard`, cargándose solo al abrir el modal.

## 2. Producto (product-manager)
- Problema / usuario: tocar al guardián navega a `/game/collection` y Alex pierde el módulo en el que estaba.
- Incluye: modal con la ficha en solo lectura en todos los módulos con guardián; botón "Ver colección"; Colección sin cambios de comportamiento.
- No incluye: evolucionar, elegir rama ni paginar historial desde el modal; cambios a la ficha de la Colección.
- Criterios de aceptación (Dado/Cuando/Entonces):
  1. Cuando toco al guardián, se abre un modal con su ficha y la URL no cambia.
  2. El modal no muestra botones de evolucionar, elegir rama ni "Ver más" del historial.
  3. El modal tiene "Ver colección" (va a la Colección con `species={id}`) y se puede cerrar con el botón, Escape o clic fuera.
  4. La ficha no se calcula hasta abrir el modal; el refresco del consejo no la recalcula.
  5. La ficha de la Colección se ve y funciona igual que antes (incluida la evolución).

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: se reutiliza el diálogo estándar (`x-ui.dialog`) sobre la página del módulo; la página no cambia.
- Flujo y cantidad de pasos: 1 clic abre la ficha; 1 clic para cerrar o ir a la Colección.
- Qué contiene cada pantalla y qué es lo más importante: la misma ficha de la Colección (arte, nombre, nivel y XP, poder, cadena evolutiva, historial reciente).
- Estados (vacío / carga / error / éxito / validaciones): abriendo (el contenido aparece al cargar; el botón del arte se deshabilita mientras carga); abierto; cerrado.
- Información necesaria y momento de presentación: ninguna adicional.
- Accesibilidad de uso (teclado, foco, errores): el arte es un `button` con `aria-label="Ver ficha de {nombre}"`; el diálogo atrapa el foco y lo devuelve al cerrar (comportamiento de `x-ui.dialog`).

```
┌─ 📖 Ficha · Squirtle ─────────────────────────── ✕ ┐
│  (arte)   Squirtle · Nv 6        ⚡ Poder 42        │
│           ███████░░░░  18 / 30 xp                   │
│  Cadena:  Squirtle → ? → ?                          │
│  Historial reciente …                               │
├─────────────────────────────────────────────────────┤
│                        [Ver colección]  [Cerrar]    │
└─────────────────────────────────────────────────────┘
```

### 3.2 Visual (ui-designer)
- Distribución: diálogo `size="lg"` como en la Colección; pie con "Ver colección" (botón tonal, enlace) y "Cerrar" (texto).
- Componentes existentes a reutilizar (ruta): `x-ui.dialog`, `resources/views/livewire/game/partials/species-sheet.blade.php` (con `readonly`).
- Jerarquía, color, tipografía y espaciado (tokens): los de la ficha actual; sin estilos nuevos salvo el cursor/hover del arte como botón.
- Responsive y contraste: los del diálogo existente.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: modal de ficha del guardián.
- Textos finales (variables y variantes): título "Ficha"; botones "Ver colección" y "Cerrar"; aria-label del arte "Ver ficha de {nombre}" (sin cambio).
- Reglas de docs/UX-WRITING.md aplicadas: tuteo, verbos claros.
- Criterios de aceptación de los textos: los tres textos aparecen tal cual.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar:
  - `app/Services/Game/SpeciesSheet.php` (nuevo): `build(int $speciesId, int $historyLimit = 5): ?array` con la lógica actual de `GameCollection::sheet()` (descubiertos, equipo, imágenes y progreso resueltos dentro).
  - `app/Livewire/Game/GameCollection.php`: `sheet()` delega en `SpeciesSheet` (mismo arreglo de salida).
  - `resources/views/livewire/game/partials/species-sheet.blade.php`: variable opcional `$readonly` que oculta evolución, ramas y "Ver más".
  - `app/Livewire/Game/GuardianCard.php`: `$showSheet` (bool) y `openSheet()`; la ficha se arma en `render()` solo si `$showSheet`.
  - `resources/views/livewire/game/guardian-card.blade.php`: el arte pasa de `<a>` a `<button wire:click="openSheet">`; diálogo con la ficha y el pie.
- Enfoque y patrones a seguir: mismo diálogo y entangle que `game-collection.blade.php` (`$wire.entangle('showSheet').live`).
- Riesgos y mitigación: dos instancias (top/rail) en la página → cada una tiene su diálogo; solo se abre el de la instancia tocada. El poll no recalcula la ficha porque solo se arma con `showSheet`.
- Qué NO tocar: lógica de evolución, consejos, layout de los módulos.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| openSheet muestra ficha sin redirigir, con "Ver colección" y sin evolucionar | Feature | Alto | tests/Feature/Game/ModuleGuardianTest.php |
| La ficha no se arma antes de abrir | Feature | Medio | ModuleGuardianTest |
| Colección sin regresiones | Feature | Alto | tests existentes de GameCollection |
- Comando para verificar: `php artisan test tests/Feature/Game`
- Regresión a vigilar: ficha y evolución en la Colección.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/game.md` → "Guardianes de módulo": tocar al guardián abre su ficha en modal.

## 7. Tareas
- [x] T1 · La ficha de la Colección se puede reutilizar fuera de ella sin cambiar cómo se ve allí — Resp: developer — Archivos: app/Services/Game/SpeciesSheet.php, app/Livewire/Game/GameCollection.php, resources/views/livewire/game/partials/species-sheet.blade.php — Hecho cuando: los tests de la Colección siguen en verde
- [x] T2 · Tocar al guardián abre su ficha en un modal con "Ver colección", sin salir del módulo — Resp: developer — Archivos: app/Livewire/Game/GuardianCard.php, resources/views/livewire/game/guardian-card.blade.php, tests/Feature/Game/ModuleGuardianTest.php — Hecho cuando: criterios 1-4 pasan y el build compila
- [x] T3 · Verificar criterios de aceptación — Resp: qa-tester — Hecho cuando: los 5 criterios pasan (cerrada por el usuario sin verificar)
- [x] T4 · Actualizar documentación — Resp: tech-writer — Archivos: docs/game.md — Hecho cuando: docs al día

## 8. Bitácora de implementación (developer)
- 2026-11-10 — Inicio (start --force: había otros CHG En desarrollo).
- T1 — `SpeciesSheet` (build + `discoveredLookup`/`teamLookup` estáticos) con la lógica de `GameCollection::sheet()`, que ahora delega. Parcial `species-sheet` con `$readonly` (oculta evolución, ramas y "Ver más"; con ramas muestra "Elige su evolución desde la Colección.") y `$sheetMessage` opcional. Tests de la Colección 33/33.
- T2 — `GuardianCard`: `showSheet` + `openSheet()`; la ficha solo se arma en `render()` si está abierta. Vista: el arte es un `button`; diálogo `x-ui.dialog` igual al de la Colección con acciones "Ver colección" y "Cerrar". CSS del botón. Build OK.
- T4 — docs/game.md actualizado.
- Tests: suite Game 243/244; la única falla es la previa conocida (NewSourcesTest).
- Nota: en la ficha readonly, con ramas disponibles se añade la línea "Elige su evolución desde la Colección." (no estaba en §3.3; es el reemplazo natural de los botones ocultos).

## 9. Preguntas abiertas, decisiones e historial
- LECCIÓN: ninguna
- 2026-11-10 — Decisiones (Alex): la ficha se abre en modal dentro del módulo, en solo lectura; evolucionar queda en la Colección vía "Ver colección". Los guardianes de los 8 módulos restantes van en dos cambios posteriores.
- 2026-11-10 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-10 — Aprobado por el usuario (Compuerta 2)
- 2026-11-10 — Movido a in-progress/ (En desarrollo)
- 2026-11-10 — Implementación terminada → En verificación
- 2026-11-11 — Cerrado por el usuario sin verificación (pendientes: T3): ya lo probé → done/ (desde el panel)

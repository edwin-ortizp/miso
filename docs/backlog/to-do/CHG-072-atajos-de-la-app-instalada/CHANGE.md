# CHG-072 · Atajos de la app instalada
**Estado:** Aprobado
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-11 · **Aprobado:** 2026-11-11 · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: si tienes MiSo instalada en el celular o el computador, al mantener presionado el ícono (o clic derecho) ves accesos directos: "Hábitos de hoy", "Registrar agua", "Cómo me siento" y "Escribir en el diario".
- Cómo comprobarlo tú: reinstala o actualiza la app, mantén presionado el ícono y toca un atajo: abre directo esa pantalla.

## 1. Resumen
Registrar exige abrir la app y navegar al módulo. Agregamos `shortcuts` al `public/manifest.json` de la PWA para llegar en un toque desde el ícono. Cada URL lleva `?via=shortcut` para medirlo con CHG-066.

## 3. UX/UI
```
[ícono MiSo] (mantener presionado)
 ├ Hábitos de hoy        → /habits?via=shortcut
 ├ Registrar agua        → /water/daily?via=shortcut
 ├ Cómo me siento        → /mood?via=shortcut
 └ Escribir en el diario → /journal?via=shortcut
```
Nombres ≤ 20 caracteres (`short_name` ≤ 12), íconos 96×96 por atajo con el estilo de los íconos actuales.

## 4. Diseño técnico
- `public/manifest.json`: arreglo `shortcuts` con `name`, `short_name`, `url`, `icons`.
- `app/Models/UserActiveDay.php`: agregar `shortcut` a `CHANNELS`.
- Íconos en `public/` junto a los actuales.
- iOS no soporta atajos del manifest: no se rompe nada, solo no aparecen.

## 5. Pruebas
- Feature: el manifest es JSON válido, tiene 4 atajos y cada URL responde 200 autenticado.
- Unit: `UserActiveDay::channel('shortcut')` devuelve `shortcut`.
- Manual: Android/Chrome y escritorio Chrome/Edge.

## 7. Tareas
- [ ] T1 · Atajos al mantener presionado el ícono — Resp: developer — Modelo: estandar — Archivos: `public/manifest.json`, íconos — Hecho cuando: aparecen los 4 en Android y escritorio.
- [ ] T2 · Se mide cuántas veces se entra por un atajo — Resp: developer — Modelo: estandar — Archivos: `app/Models/UserActiveDay.php` — Hecho cuando: `?via=shortcut` queda como canal.
- [ ] T3 · Pruebas — Resp: developer — Modelo: estandar — Archivos: `tests/Feature/Engagement/PwaShortcutsTest.php` — Hecho cuando: pasan los casos de §5.

## 8. Bitácora de implementación (developer)

## 9. Preguntas abiertas, decisiones e historial
- Pregunta: ¿estos cuatro atajos u otros (p. ej. "Tareas de hoy", "Iniciar foco")? Máximo recomendado: 4. → 2026-11-11 Decisión (usuario): los cuatro propuestos para empezar.
- 2026-11-11 — Creado en to-do/ (Borrador, nivel S)
- 2026-11-11 — Aprobado por el usuario (Compuerta 2)

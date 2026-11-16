# CHG-121 · Objetivos: crear un KPI más intuitivo
**Estado:** Borrador
**Nivel:** S
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-16 · **Aprobado:** — · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
- Qué vas a ver distinto: al crear o editar un objetivo, la pestaña del KPI pasa a ser la 2.ª (antes de Recordatorios), se llama "Medir avance (KPI)", explica qué es un KPI con un ejemplo y cada campo trae una pista (ej. "kg", "Peso corporal"). Si faltan las fechas, te avisa ahí mismo.
- Cómo comprobarlo tú: abre "Agregar objetivo" → pestañas: Información básica, Medir avance (KPI), Recordatorios. Activa "Medir este objetivo con un número" y revisa los textos de ayuda.

## 1. Resumen
El KPI es jerga, estaba de 3.ª pestaña y sin ejemplos. Se reordena y se agregan textos de ayuda (tuteo, tono entrenador, `docs/UX-WRITING.md`). No cambia la lógica ni la validación.

## 3. UX/UI
```
[Información básica] [Medir avance (KPI)] [Recordatorios]
Un KPI es un número que te dice cuánto avanzas. Ej.: bajar de 82 a 75 kg.
[x] Medir este objetivo con un número
  Nombre      ___  Ej.: Peso corporal, Ahorro, Páginas leídas
  Unidad      ___  Ej.: kg, $, páginas
  Dirección   [Subir hasta la meta | Bajar hasta la meta]
  Valor inicial ___ Dónde estás hoy | Valor objetivo ___ Dónde quieres llegar
  ⚠ Necesitas fecha de inicio y límite en Información básica   (solo si faltan)
```
Los ejemplos van en `help` de `x-ui.field` (el componente usa `placeholder=" "` por el label flotante).

## 4. Diseño técnico
Solo `resources/views/livewire/goal/goal-dialog.blade.php` (orden en `:sections` y en el cuerpo, textos, aviso condicional de fechas con `$kpiEnabled`/`$startDate`/`$dueDate`); CSS en `resources/css/m3/modules/_goals.css` solo si hace falta. Sin migraciones.

## 5. Pruebas
Actualizar tests de goals que afirmen textos u orden. Comando: `php artisan test --filter=Goal`.

## 7. Tareas
- [ ] T1 · KPI como 2.ª pestaña con nombre claro, explicación, pistas por campo y aviso de fechas — Resp: developer — Modelo: estandar — Archivos: resources/views/livewire/goal/goal-dialog.blade.php, resources/css/m3/modules/_goals.css, tests de goals — Hecho cuando: el orden y los textos de §3 se ven y los tests de goals pasan.

## 8. Bitácora de implementación (developer)

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-16 — Creado en to-do/ (Borrador, nivel S)

# CHG-154 · Documentar la arquitectura comun y lo que no se migra
**Estado:** Borrador
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-17 · **Aprobado:** — · **Cerrado:** —
**Relacionado:** CHG-148, CHG-149, CHG-150

## 0. En palabras simples
- Qué vas a ver distinto: nada visible: queda escrito qué piezas comunes existen, cuándo usarlas y qué se dejó aparte a propósito.
- Cómo comprobarlo tú: lee la sección «Piezas comunes» de `docs/ARCHITECTURE.md`.

## 1. Resumen
Tras unificar favoritos, categorías y ajustes con secciones, se documentan las piezas comunes, las decisiones de no migrar (ánimo, accesos directos de Formación, compañero favorito) y los dos topes de ánimo con constantes nombradas.

## 2. Producto (product-manager)
- Problema / usuario: sin guía, cada módulo volvería a copiar código.
- Incluye: sección «Piezas comunes» en `docs/ARCHITECTURE.md`; constantes nombradas para los topes de ánimo.
- No incluye: cambios de comportamiento.
- Criterios de aceptación (Dado/Cuando/Entonces):
  - Dado un desarrollador nuevo, cuando lee la guía, entonces sabe qué pieza usar para favoritos, categorías y ajustes con secciones.
  - Dado el chat de ánimo, cuando se muestran estados, entonces la cantidad sigue siendo la misma.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: No aplica — solo documentación y constantes.
- Flujo y cantidad de pasos: igual que hoy.
- Qué contiene cada pantalla y qué es lo más importante: No aplica.
- Estados (vacío / carga / error / éxito / validaciones): sin cambios.
- Información necesaria y momento de presentación: sin cambios.
- Accesibilidad de uso (teclado, foco, errores): No aplica.

```
No aplica — sin pantallas.
```

### 3.2 Visual (ui-designer)
- Distribución: No aplica.
- Componentes existentes a reutilizar (ruta): No aplica.
- Jerarquía, color, tipografía y espaciado (tokens): tokens M3 existentes, sin estilos en línea.
- Responsive y contraste: 360/390/1440 px.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: No aplica.
- Textos finales (variables y variantes): sin cambios de texto salvo lo indicado.
- Reglas de docs/UX-WRITING.md aplicadas: etiquetas estables.
- Criterios de aceptación de los textos: ningún texto cambia de significado.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar: `docs/ARCHITECTURE.md`, `app/Support/MoodLogger.php`, `resources/views/livewire/mood/mood-tracker.blade.php`, `config/companion_chat.php`.
- Enfoque y patrones a seguir: mover los números 8 y 5 a constantes con comentario.
- Riesgos y mitigación: ninguno funcional.
- Qué NO tocar: la lógica de ánimo.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Ánimo conserva sus cantidades | Feature | 1 | `MoodPickerRecoveryTest` |
- Comando para verificar: `php artisan test --filter=Mood`.
- Regresión a vigilar: `ProgressiveMoodLoggingTest`.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/ARCHITECTURE.md`.

## 7. Tareas
- [ ] T1 · Guía de piezas comunes y decisiones de no migrar — Resp: developer — Modelo: estandar — Archivos: `docs/ARCHITECTURE.md` — Hecho cuando: revisada.
- [ ] T2 · Topes de ánimo como constantes — Resp: developer — Modelo: estandar — Archivos: ánimo — Hecho cuando: tests de ánimo en verde.

## 8. Bitácora de implementación (developer)

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-17 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-17 — Parte del plan «arquitectura común» aceptado en la conversación (inventario de duplicación previo).

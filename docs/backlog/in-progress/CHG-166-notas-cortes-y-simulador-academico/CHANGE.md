# CHG-166 · Notas cortes y simulador académico
**Estado:** En desarrollo
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-17 · **Aprobado:** 2026-11-17 · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
- Qué vas a ver distinto: Las notas muestran el promedio y la nota que necesitas para pasar.
- Cómo comprobarlo tú: Registra un 4,20 que vale 30 % y simula el restante.

## 1. Resumen
Las notas muestran el promedio y la nota que necesitas para pasar. Forma parte del plan Mi estudio aprobado por Edwin, con entrega integrada.

## 2. Producto (product-manager)
- Problema / usuario: estudiantes de pregrado y posgrado necesitan gestionar sus compromisos y resultados.
- Incluye: Evaluaciones sueltas y vinculadas, escalas, pesos directos, cortes y simulación.
- Criterios de aceptación: Los cálculos son exactos y el simulador conserva las notas guardadas.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Plantilla: lista y detalle dentro del shell.
- Flujo: Materia → Notas → Registrar nota / Simular notas.
- Mostrar estados inicial, cargando, vacío, error recuperable y éxito. Conservar el foco y proporcionar errores por campo.

```mermaid
flowchart LR
    A[Notas de la materia] --> B[Registrar o simular]
    B --> C[Resultado ponderado]
```

### 3.2 Visual (ui-designer)
- Usar componentes canónicos y tokens Material 3. En móvil, apilar el contenido y conservar accesos de 44 px. Revisar tema claro y oscuro.

```text
Cálculo | Resumen · Tareas · Notas · Contenido
Necesitas 2,49 para pasar · 30 % evaluado
Parcial 2 · 70 %    [Registrar nota]
```

### 3.3 Escritura (ux-writer)
- Usar títulos descriptivos, etiquetas concretas y mensajes neutrales en español colombiano.

## 4. Diseño técnico (software-architect)
- Archivos: Servicios, modelos, migraciones, diálogos y resumen de Formación; pruebas y docs.
- Enfoque: conservar la jerarquía actual, verificar el dueño al leer y escribir, y mantener consultas acotadas.
- Compatibilidad: se conservan los contratos vigentes de reservas, salud, hábitos y cursos.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Los cálculos son exactos y el simulador conserva las notas guardadas. | Funcional | Aplicación | Learning y Calendario |
- Comando: php artisan test --filter='Learning|TaskCalendar|PersonalCalendar'; npm run build; php artisan ui:conformance.
- Validar persistencia en MySQL y revisar interacción a 390, 768 y 1440 px.

## 6. Documentación (tech-writer)
- Actualizar docs/learning.md y, para la capa, docs/design-system.md.

## 7. Tareas
- [ ] T1 · Las notas muestran el promedio y la nota que necesitas para pasar. — Resp: developer — Modelo: estandar — Archivos: Servicios, modelos, migraciones, diálogos y resumen de Formación; pruebas y docs. — Hecho cuando: Los cálculos son exactos y el simulador conserva las notas guardadas.

## 8. Bitácora de implementación (developer)

- 2026-11-17 — Implementados pesos directos, cortes, registro de nota y simulación sobre centésimas. Se verificó el ejemplo 4,20 sobre el 30 %, la meta 2,49, los límites exactos y el manejo de pendientes y ceros en SQLite y MariaDB.

## 9. Preguntas abiertas, decisiones e historial
- Alcance confirmado en la conversación y ejecución solicitada con «Implement the plan».
- 2026-11-17 — Aprobado por el usuario (Compuerta 2)
- 2026-11-17 — Movido a in-progress/ (En desarrollo)
- LECCIÓN(arquitectura): Inicializar por ID los campos de una simulación Livewire conserva sus valores en el envío. El navegador reprodujo una pérdida de valores al usar un arreglo vacío con propiedades UUID. · destino sugerido: docs/ARCHITECTURE.md

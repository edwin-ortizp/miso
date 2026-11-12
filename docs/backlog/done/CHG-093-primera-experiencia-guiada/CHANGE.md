# CHG-093 · Primera experiencia guiada
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-11 · **Aprobado:** 2026-11-11 · **Cerrado:** 2026-11-11
**Relacionado:** —

## 0. En palabras simples
Guía obligatoria reanudable para cuentas nuevas, hábito propio y siguiente paso opcional mediante formularios compartidos.
Comprobar: Registro → Contexto → Módulos → Hábito → Opcional → Resumen → Inicio.

## 1. Resumen
Implementar el plan Primera experiencia guiada y personalización de MiSo aprobado expresamente por el usuario el 2026-11-11. Conservar datos y cambios ajenos; no publicar ni migrar datos reales.

## 2. Producto
Usuarios nuevos estudiantes y empleados. Cuentas existentes conservan comportamiento hasta personalización voluntaria. Sin proveedor externo de analítica.
Criterio: completar el recorrido sin duplicados, con datos aislados por usuario.

## 3. UX/UI
### 3.1 Experiencia
Plantilla: bienvenida enfocada, un único layout responsive con cuenta y salida, sin navegación a módulos bloqueados. Cinco paradas visibles; elegir contexto mediante tarjetas de radio, módulos con selección visible y un resumen breve de lo preparado.
```text
Escritorio
MiSo · Tu comienzo                                 [Cuenta]
[ Ruta: Tú — Módulos — Hábito — Opcional — Listo ]
[ Cielo / horizonte / ball ] [ Paso actual y acción ]
[ Tu próxima aventura     ] [ Elecciones y campos ]
[ Frase del compañero     ] [ Anterior  Continuar ]
Móvil
MiSo                                     [Cuenta]
[ Ruta de cinco paradas ]
[ Escenario compacto + frase del compañero ]
[ Título, elecciones y acción principal ]
```
### 3.2 Visual
Temática de primera aventura con los escenarios cielo y ball CSS ya presentes en el juego. Ilustración decorativa sin otorgar ejemplares ni recompensas. Fondo atmosférico, panel de trabajo de superficie clara, iconos por módulo desde config y sus acentos mediante data-module. Reutilizar x-ui.field, action, fab y form-dialog. Radio/checkbox nativos con foco y selección visibles, lectura clara y movimiento reducido. Verificar capturas de los cinco pasos en móvil y escritorio.
### 3.3 Escritura
Compañero motivador con humor breve; instrucciones y acciones directas. No denominar como progreso realizado la configuración. Resumen muestra intención y hábito y explica tres próximos pasos en lugar de párrafos extensos.

## 4. Diseño técnico
Usar module_settings por usuario; catálogo config/modules.php; middleware solo rutas web autenticadas. Sin cambios de API/MCP, notificaciones o reglas de juego. Transacciones y bloqueo para reintentos.

## 5. Pruebas
Pruebas PHP focalizadas, build, ui:conformance, navegador móvil/escritorio y diff check. Verificar navegación, validación, aislamiento y reanudación.

## 6. Documentación
Registrar implementación, evidencia y límites en este CHG y guía de primera experiencia.

## 7. Tareas
- [x] T1 · Implementar comportamiento aprobado y pruebas focalizadas — Resp: developer — Modelo: estandar — Archivos: app, resources, tests — Hecho cuando: recorrido funcional, aislamiento y validaciones correctas.
- [x] T2 · Verificar y documentar resultado local — Resp: developer — Modelo: estandar — Archivos: docs — Hecho cuando: evidencia y pendientes explícitos.

## 8. Bitácora de implementación

- 2026-11-11 — Reabierto por feedback del usuario: primera impresión gris y genérica. Corrección visual de onboarding, sin cambiar requisitos ni reglas de guardado.

- 2026-11-11 — Implementado localmente el alcance aprobado. Formularios compartidos, preferencias con usuario explícito y guía obligatoria solo para registros nuevos; cuentas existentes preservadas. Sin migraciones nuevas ni cambios de datos reales, commits o publicación.
- Validación: 14 pruebas PHP focalizadas de registro/personalización/aislamiento y perfiles correctas; 3 recorridos completos Playwright a 390/768/1440 correctos, con comprobación axe de guía/cuenta, teclado, posición del popover antes/después de morph, creación, personalización y logout. Build correcto; git diff --check correcto.
- Regresión adicional: 48/49 pruebas correctas. HomeDashboardTest::test_dashboard_renders_all_home_sections falla por exigir md-module-shell, ausente también en las vistas de HEAD; fallo reproducido con vistas originales en storage/framework/testing/experience-baseline-views, sin restaurar ni alterar el trabajo actual.
- ui:conformance: 235 desviaciones frente a 345 inventariadas; falla por excesos existentes en day-tile-info, focus-companion, game-collection, guardian-card, checkin-sheet y task-list. Ningún archivo nuevo de esta entrega aparece entre los excesos. No se modifica la baseline ni se amplía el alcance para corregirlos.
- Evidencia local: storage/framework/testing/personal-experience-{php,regression,baseline}.json; personal-experience-conformance.txt; personal-experience-build.txt; capturas y traces en storage/framework/testing/playwright/personal-experience. Guía y protocolo: docs/first-experience.md.
- Pendiente externo: comprobación del entorno publicado y estudio con participantes. Los resultados locales no prueban retención. Se entrega En verificación, sin cierre automático de producto.



- 2026-11-11 — Corrección visual terminada: marco enfocado sin navegación de módulos, escenario de primera aventura (cielo, camino y ball CSS), mapa de cinco paradas, contexto mediante tarjetas de radio, módulos con acentos/iconos canónicos y opciones adicionales progresivas. Resumen con tres siguientes pasos. Se conserva flujo, datos y formularios compartidos.
- Verificación visual de los cinco pasos en 390/768/1440 px y capturas revisadas manualmente; tres recorridos Playwright correctos, sin errores JS, axe correcto en primera pantalla/resumen/cuenta y movimiento reducido. 25 pruebas PHP correctas / 180 aserciones (PersonalExperienceTest y UiPatternsTest); build y diff check correctos.
- Contrato CSS adicional: _experience.css ya no contiene overrides de selectores base. Se corrigieron los once introducidos en la entrega inicial moviendo el popover anclado al patrón canónico. CssLayerContractTest conserva dos fallos por dependencias en _text-fields.css y overrides existentes en otros módulos; no se modifica su baseline. ui:conformance conserva las mismas 235 desviaciones y excesos ajenos, sin nuevos excesos de esta corrección.
- Evidencia estable: evidence/welcome-desktop.png, welcome-mobile.png y modules-mobile.png. Informes técnicos: storage/framework/testing/onboarding-visual-{functional,php}.json, onboarding-visual-conformance.txt y onboarding-visual-build.txt. Sin despliegue ni cambios de datos reales.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-11 — Usuario aprobó el plan completo y ordenó implementarlo. Sin preguntas abiertas.
- 2026-11-11 — Aprobado por el usuario (Compuerta 2)
- 2026-11-11 — Movido a in-progress/ (En desarrollo)
- 2026-11-11 — Implementación terminada → En verificación
- 2026-11-11 — Reabierto: Usuario solicita corregir la primera impresión: falta de temática y patrones visuales en onboarding
- 2026-11-11 — Implementación terminada → En verificación
- 2026-11-11 — Cerrado por el usuario sin verificación: ya lo probé → done/ (desde el panel)

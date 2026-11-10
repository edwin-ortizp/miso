# CHG-032 · Ficha Pokémon con secciones y panel de características
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
**Creado:** 2026-11-09 · **Aprobado:** 2026-11-09 · **Cerrado:** 2026-11-10
**Relacionado:** CHG-029, CHG-031
## 0. En palabras simples
Ficha más ancha, secciones a la izquierda e información de crecimiento en un panel derecho.
## 1. Resumen
Solicitud directa del usuario: organizar la ficha como los demás modales, tomando Tareas como referencia.
## 2. Producto (product-manager)
Ficha e Historial separados. Poder, nivel, experiencia y estadísticas a la derecha. Conservar privacidad, evoluciones y paginación del historial.
## 3. UX/UI
### 3.1 Experiencia (ux-designer)
Entrar en Ficha; alternar Historial desde navegación lateral canónica. Características persistentes en escritorio; debajo en móvil.
### 3.2 Visual (ui-designer)
Modal de hasta 1200px, navegación canónica, centro flexible y panel de 260px; cabecera/footer fijos.
```text
[ Cabecera / expandir / cerrar ]
[ Ficha     | imagen / descripción | poder / nivel ]
[ Historial | evoluciones o eventos| experiencia / estadísticas ]
[ Cerrar ]
```
### 3.3 Escritura (ux-writer)
Ficha, Historial y Características. Conservar textos actuales, sin inventar datos.
## 4. Diseño técnico (software-architect)
Extender x-ui.dialog estándar con sections opcional siguiendo form-dialog y respetar size lg. Reordenar partial de ficha sin cambios de reglas ni consultas personales.
## 5. Pruebas (qa-tester)
SpeciesSheetTest y navegación/contrato modal; build, conformance, cuatro anchos, historial, teclado, privacidad y expansión.
## 6. Documentación (tech-writer)
CHANGE y docs/game.md; usar design-system vigente.
## 7. Tareas
- [x] T1 · Ficha ancha con navegación y características — Resp: developer — Modelo: estandar — Hecho cuando: datos preservados y responsive.
- [x] T2 · Validar historial, privacidad y modal — Resp: developer — Modelo: estandar — Hecho cuando: pruebas y navegador pasan.
- [x] T3 · Experiencia ganada protagonista en el centro — Resp: developer — Modelo: estandar — Hecho cuando: XP total, nivel, avance y siguiente nivel visibles en tarjeta; nivel máximo correcto y características conservadas.
## 8. Bitácora de implementación (developer)
## 9. Preguntas abiertas, decisiones e historial
Solicitud explícita autoriza esta implementación local.
- 2026-11-09 — Aprobado por el usuario (Compuerta 2)
- 2026-11-09 — Movido a in-progress/ (En desarrollo)

### Resultado y validaciones
- Ficha estándar de hasta 1200px con navegación izquierda Ficha/Historial, usando los estilos y el comportamiento canónicos de secciones. API opcional sections en x-ui.dialog; size lg se respeta también en su variante estándar.
- Centro con imagen, descripción y cadena evolutiva; panel de características con poder, nivel, estadísticas, experiencia, fecha del encuentro y opciones de evolución. Panel persistente a la derecha en escritorio, debajo en tamaños menores.
- Se conserva el historial completo y Ver más, los mensajes y acciones de evolución y el aislamiento por usuario. Las especies sin descubrir no muestran características ni pestaña Historial.
- Navegación colapsable en móvil, devolución del foco al contenido, cabecera/footer fijos y expansión/restauración conservadas. Cabecera tonal con contraste corregido.
- 27 pruebas pasan: SpeciesSheetTest, GameNavigationTest, GameUiTest y contratos de diálogo estándar/secciones. Build, Pint, ui:conformance y git diff --check pasan, sin deuda visual nueva.
- Navegador sobre HTML renderizado por Laravel con fixtures ficticios y assets/JavaScript reales: 360,768,1280,1600 sin overflow; modal de1200px en escritorio; cambio Ficha/Historial con teclado, características visibles en ambas secciones, navegación móvil, expansión/restauración y footer visible. Axe sin infracciones en los cuatro anchos y en oscuro; sin errores JavaScript.
- Capturas: storage/framework/testing/pokemon-sheet-{profile,history}-{ancho}.png. No se modificaron datos reales, reglas, consultas del historial ni se hizo despliegue. Cambios previos preservados.
- 2026-11-09 — Implementación terminada → En verificación
- 2026-11-09 — Reabierto: Destacar la experiencia ganada en el panel central

### Ajuste de protagonismo de experiencia
- A petición del usuario, experiencia y nivel salen del panel derecho y aparecen en una tarjeta central bajo el Pokémon: XP total, nivel, progreso y XP restante; utiliza el progreso canónico y datos existentes.
- Solo muestra el progreso del ejemplar actual; conserva la lógica de etapas anteriores y privacidad. Nivel máximo con barra completa y sin promesas de nivel 101; la XP adicional se conserva según las reglas vigentes.
- 17 pruebas afectadas pasan (SpeciesSheetTest y GameNavigationTest), incluido total/ubicación de XP y nivel máximo. Build, conformance y diff pasan; Pint aplicado.
- Capturas actualizadas a 360/768/1280/1600 y revisión de contraste claro/oscuro sin infracciones; tabs, navegación móvil y expansión/restauración conservadas. No cambia reglas ni datos reales.
- 2026-11-09 — Implementación terminada → En verificación
- 2026-11-10 — Cerrado por el usuario (Compuerta 3) → done/

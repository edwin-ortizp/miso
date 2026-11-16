# CHG-109 · Corregir barra de estado y escala de Android
**Estado:** Aprobado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-14 · **Aprobado:** 2026-11-16 · **Cerrado:** —
**Relacionado:** CHG-098, CHG-101; coordinación con CHG-106

## 0. En palabras simples
En Android podrás leer hora, batería y notificaciones sobre la barra superior. El contenido tendrá el tamaño estándar de Chrome/PWA en el mismo teléfono, conservando tus preferencias de letra de Android.

## 1. Resumen
Corrección P2 de presentación nativa vinculada a CHG-098/101. Reporte: barra blanca con iconos ilegibles y sensación de zoom excesivo. Objetivo confirmado: igualar Chrome/PWA, sin reducir globalmente la web ni imponer 90 %. El zoom previo del navegador es una hipótesis a medir con ajustes equivalentes.

## 2. Producto (product-manager)
- Incluye: fondo/iconos de barras del contenedor, escala inicial/viewport WebView y safe areas/teclado.
- Excluye: nueva densidad, reducción global CSS, FCM, datos, publicación o instalación personal y rediseño web/PWA.
- CA1: hora, batería e iconos legibles en tema Android claro/oscuro durante carga, login, Mi día, chat, formulario y error.
- CA2: con Chrome/PWA al 100 %, misma fuente/tamaño de pantalla Android y mismo contenido, ancho CSS útil y geometría de controles equivalentes coinciden con WebView dentro de 2 CSS px, descontando barras de navegador/safe areas. Diferencias de alto por interfaz nativa se documentan, no se compensan con zoom.
- CA3: sin escala artificial 90 %, CSS zoom/transform global, desktop mode o ancho fijo 980 px; preservar preferencias de fuente del sistema.
- CA4: nada queda bajo barras, gestos/tres botones o teclado; insets aplicados una vez. Rotación/recreación mantienen geometría y sesión.
- CA5: fuente y tamaño de pantalla ampliados siguen utilizables sin controles recortados; no reducir accesibilidad para imitar una captura.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
Plantilla: contenedor Android existente de la interfaz Laravel; no hay pantalla nueva.
No añadir control de zoom para compensar configuración inicial incorrecta. Si solo difiere un zoom personalizado de Chrome, documentarlo y mantener escala estándar accesible en Android.
### 3.2 Visual (ui-designer)
```text
Android claro                   Android oscuro del sistema
[hora · iconos oscuros]          [hora · iconos oscuros]
[fondo nativo #FBF8FD]           [fondo nativo #FBF8FD]
[web al ancho real]             [web según su tema]
[contenido / formulario]        [contenido / formulario]
[zona de gestos legible]         [zona de gestos legible]
```
Propuesta concreta: conservar lightColorScheme nativo vigente, fondo #FBF8FD e iconos oscuros en barras incluso con Android oscuro; la web conserva tema independiente. No declarar soporte oscuro nativo inexistente. Pintar explícitamente bandas de insets, sin fondo transparente indeterminado. Conservar primario #2B5BB5 en componentes existentes; no extenderlo por defecto a toda la barra. Revisar gestos y tres botones.
### 3.3 Escritura (ux-writer)
No aplica — no añade textos/controles; conservar carga, error y reintento vigentes.

## 4. Diseño técnico (software-architect)
- Evidencia: MainActivity usa enableEdgeToEdge, lightColorScheme y Box con fondo #FBF8FD, safeDrawingPadding e imePadding. No declara useWideViewPort/loadWithOverviewMode/textZoom. Layouts app/panel ya tienen width=device-width, initial-scale=1 y viewport-fit=cover. Esto no prueba por sí solo la causa del tamaño.
- Fuentes: Android/app/src/main/java/com/edwinortizp/lifetracker/MainActivity.kt y recurso Android/app/src/main/res/values/themes.xml; tests en ShellInstrumentedTest.kt. Layouts web solo se modifican si se demuestra viewport incorrecto del flujo afectado.
- Medir antes/después: APK, Android/System WebView, densidad, fontScale, tamaño de pantalla, innerWidth, devicePixelRatio, visualViewport.width/scale y rectángulos de controles equivalentes. No capturar cookies/tokens/contenido personal.
- Configurar viewport móvil respetando meta con useWideViewPort=true y loadWithOverviewMode para ajuste inicial; sin setInitialScale arbitrario. Mantener escala textual estándar respetando fuente del sistema: verificar fontScale ampliado antes de introducir override. Si al 100 % ya coincide, no reducir para imitar zoom personalizado.
- Usar APIs AndroidX edge-to-edge/insets disponibles con apariencia explícita de iconos oscuros sobre bandas claras. Target 36 exige comprobar edge-to-edge real; no depender solo de statusBarColor. Pintar fondo en insets y evitar doble padding nativo/web. Conservar safeDrawing/ime mientras medición no pruebe duplicación.
- Sin cambios de puente v1, URL/origen, cookies, rutas ni datos. Coordinar MainActivity con CHG-106 y validar chat/formulario conjuntamente.
- Rollback: revertir solo configuración visual/viewport de este CHG y generar APK; no borrar almacenamiento ni sesión.

## 5. Pruebas (qa-tester)
| Caso | Resultado |
|---|---|
| Android claro/oscuro | Bandas claras e iconos oscuros legibles |
| Login, Mi día, chat, formulario, error | Sin invasión de barras ni contraste perdido |
| Chrome/PWA 100 % vs WebView | Mediciones y capturas mismo teléfono, tolerancia CA2 |
| Fuente/pantalla ampliadas | Preferencias respetadas y controles utilizables |
| Teclado, rotación/recreación | Insets sin duplicación y acciones visibles |
| Gestos y tres botones | Navegación sin solapamiento |
- Ampliar Android/app/src/androidTest/java/com/edwinortizp/lifetracker/ShellInstrumentedTest.kt con fixture sintética de viewport/barras/recreación. Integración autenticada usa entorno de pruebas y usuario ficticio, sin registros de producción.
- Comandos: ./Android/gradlew.bat -p Android testDebugUnitTest assembleDebug lintDebug connectedDebugAndroidTest --console=plain; git diff --check. Si cambia web: npm run build, php artisan ui:conformance y prueba de navegador afectada.
- Emulador API 36 y teléfono Android físico representativo, con versiones/APK anotados. Verificar contraste por capturas, no solo flags. Sin teléfono queda pendiente comparación física; login público no certifica modales autenticados.

## 6. Documentación (tech-writer)
Android/README.md: política de bandas claras/iconos oscuros, escala estándar, comparación reproducible y límites de QA. §8: mediciones/capturas/resultados. APK local generado no implica despliegue, publicación ni instalación personal.

## 7. Tareas
- [ ] T1 · Medir contraste y tamaño frente a Chrome/PWA — Resp: developer — Modelo: estandar — Archivos: CHANGE.md, Android/app/src/androidTest/java/com/edwinortizp/lifetracker/ShellInstrumentedTest.kt — Hecho cuando: medidas/capturas distinguen zoom personalizado y configuración del WebView con ajustes equivalentes.
- [ ] T2 · Corregir barras y viewport demostrado — Resp: developer — Modelo: estandar — Archivos: Android/app/src/main/java/com/edwinortizp/lifetracker/MainActivity.kt, Android/app/src/main/res/values/themes.xml si necesario, Android/app/src/androidTest/java/com/edwinortizp/lifetracker/ShellInstrumentedTest.kt — Hecho cuando: CA1–CA5 pasan en entorno reproducido sin escala artificial ni anular preferencias.
- [ ] T3 · Comparar teléfono y validar teclado/insets — Resp: qa-tester — Archivos: CHANGE.md, evidencias en storage/framework/testing — Hecho cuando: matriz §5 completada, límites físicos explícitos y regresión de CHG-106 verificada.
- [ ] T4 · Documentar y generar APK local verificable — Resp: developer — Modelo: estandar — Archivos: Android/README.md, CHANGE.md, Android/app/build/outputs/apk/debug/app-debug.apk (artefacto generado) — Hecho cuando: build/lint documentados, APK identificado sin publicación ni instalación personal.

## 8. Bitácora de implementación (developer)
Sin implementación; completar al desarrollar.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-14 — Creado en to-do/ (Borrador, nivel M) mediante team new.
- 2026-11-14 — Usuario solicita completar especificación; sin aprobar implementación.
- Triage: M, P2, estandar; Android visual/viewport con UX/UI, arquitectura y QA.
- Confirmado: igualar Chrome/PWA; no fijar 90 %. Fondo nativo claro/iconos oscuros es propuesta concreta pendiente de revisión del borrador.
- Causa del tamaño aún por medir. Backlogs UX/UI revisados sin ítem equivalente.
- Sin decisiones abiertas para redactar borrador; teléfono/versiones se registran al validar.
- LECCIÓN: ninguna nueva antes de implementación/verificación.
- 2026-11-14 — Línea base local: PHP filtrado PanelFocusSessionTest/PomodoroTimerTest/CompanionChatTest/TaskEditorDraftTest: 43 pruebas, 304 aserciones, todas pasan; JS pomodoro-timer/dialog-viewport/navigation-guard/companion-chat: 27 pruebas, todas pasan. No reproduce ni certifica los reportes físicos.
- Perfil estandar sin proveedor configurado en docs/TEAM_MODELS.md; no elegir proveedor externo implícitamente. Bocetos y causa están pendientes de revisión/validación según las tareas, no de redacción.
- 2026-11-16 — Aprobado por el usuario (Compuerta 2)

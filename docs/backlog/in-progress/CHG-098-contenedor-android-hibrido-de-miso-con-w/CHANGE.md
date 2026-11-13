# CHG-098 · Contenedor Android híbrido de MiSo con WebView
**Estado:** En verificación
**Nivel:** M
**Modelo sugerido:** estandar
**Creado:** 2026-11-12 · **Aprobado:** 2026-11-12 · **Cerrado:** —

## 0. En palabras simples
MiSo se instala como APK y abre la aplicación existente sin barra del navegador. Comprobar abriendo el APK, entrando a la cuenta y navegando.

## 1. Resumen
Implementar el plan aprobado explícitamente en conversación: reutilizar Android con WebView para https://miso.novapixel.org conservando Laravel y sin Supabase.

## 2. Producto (product-manager)
Incluye sesión, Atrás, archivos, descargas, errores recuperables y APK debug. No incluye push nativo, puente JavaScript, publicación ni cambios backend.
Aceptación: al abrir MiSo se puede acceder y navegar en la web actual; enlaces externos salen del visor y la sesión se conserva según las cookies de Laravel.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
Apertura → carga → web existente. Error principal muestra recuperación. Controles nativos accesibles; teclado ajusta área útil.
### 3.2 Visual (ui-designer)
Reutilizar íntegramente la web. Sin cabecera adicional; progreso fino superior y error centrado dentro de márgenes del sistema.
```text
┌──────────────────────┐
│ progreso de carga    │
│                      │
│ web actual de MiSo   │
│                      │
└──────────────────────┘
Error: No pudimos abrir MiSo. / Revisa tu conexión e inténtalo de nuevo. / [Reintentar]
```
### 3.3 Escritura (ux-writer)
Español, tú, errores claros sin humor. Carga: Cargando MiSo. Descarga: Guardar archivo. Fallo: No pudimos guardar el archivo.

## 4. Diseño técnico (software-architect)
Android/: Activity con WebView y shell Compose, política de URL independiente y descarga HTTPS de mismo origen mediante selector de destino. Cookies nunca enviadas a otro origen; redirects de descarga solo mismo origen. Sin bypass TLS, sin file access ni puente JS. Mantener applicationId. Retirar prototipo Supabase obsoleto. No tocar backend ni cambios ajenos.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| URLs internas, externas y peligrosas | automática | JVM | UrlPolicyTest |
| compilación y lint | automática | Android | Gradle |
| apertura, Atrás, red, recreación | manual/instrumentada | emulador | registrar evidencia |
| login, sesión, archivos | manual | dispositivo/cuenta de prueba | pendiente si no disponible |
Comando: gradlew.bat testDebugUnitTest assembleDebug lintDebug. No modificar datos personales.

## 6. Documentación (tech-writer)
Actualizar Android/README.md con compilación, instalación, límites y matriz de comprobaciones.

## 7. Tareas
- [x] T1 · Sustituir prototipo por contenedor seguro con sesión y navegación — Resp: developer — Modelo: estandar — Archivos: Android/ — Hecho cuando: abre URL configurada y compila.
- [x] T2 · Archivos, descargas y errores recuperables — Resp: developer — Modelo: estandar — Archivos: Android/ — Hecho cuando: manejo implementado y política probada.
- [x] T3 · Pruebas, APK y documentación de resultados — Resp: developer — Modelo: estandar — Archivos: Android/README.md — Hecho cuando: validaciones ejecutadas y pendientes explícitos.

## 8. Bitácora de implementación (developer)
- 2026-11-12 — Reemplazado prototipo Supabase por WebView de origen restringido; configuración HTTPS, sesión propia, historial, errores, selector de archivos y descargas con validación de cada redirect. Sin cambios backend.
- Descargas fuera del hilo UI, retenidas en ViewModel al recrear Activity. Respaldos y transferencias excluyen datos del contenedor.
- Validación final: 8 pruebas JVM, 3 instrumentadas en Android 16/API 36.1, assembleDebug y lintDebug aprobados (0 errores, 8 advertencias de versiones/KTX). Capturas de login, teclado y error offline revisadas; Reintentar recupera login con red.
- El test inicial de historial sin gesto falló por la omisión de entradas sin interacción en Chromium; se sustituyó por un toque real y se comprobó el retorno a la URL anterior.
- APK: Android/app/build/outputs/apk/debug/app-debug.apk. Documentación y matriz de validación en Android/README.md.
- Pendiente QA con cuenta de prueba/dispositivo físico: login/logout, persistencia autenticada, registro de datos, subida y descarga reales. No se dispuso de credenciales ni teléfono conectado; no se modificaron datos personales. No cerrar el CHG hasta esa verificación.
- docs/DOCUMENTATION.md no existe; se actualizaron README e instrucciones Android pertinentes. Se preservaron cambios ajenos. No se ejecutaron suites web porque no se modificó Laravel ni sus assets.

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-12 — Plan aprobado por el usuario. Dominio confirmado. Sin preguntas abiertas de implementación.
- 2026-11-12 — Aprobado por el usuario (Compuerta 2)
- 2026-11-12 — Movido a in-progress/ (En desarrollo)
- 2026-11-12 — Implementación terminada → En verificación

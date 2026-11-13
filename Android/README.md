# MiSo para Android · 0.2.0

Contenedor Kotlin/Compose con WebView para **https://miso.novapixel.org/**. Laravel y Livewire conservan pantallas, autenticación, preferencias, datos y reglas. Android aporta navegación del sistema y un canal FCM opcional. No incluye publicación en Play Store.

## Compilar e instalar

Requisitos: Android SDK 36, JDK compatible con AGP 9 (validado con el JBR de Android Studio). `local.properties` contiene solo `sdk.dir` y no se versiona.

Desde `Android/`, en PowerShell:

```powershell
$env:JAVA_HOME = 'C:/Program Files/Android/Android Studio/jbr'
./gradlew.bat testDebugUnitTest assembleDebug lintDebug
./gradlew.bat connectedDebugAndroidTest
adb install -r app/build/outputs/apk/debug/app-debug.apk
```

APK: `app/build/outputs/apk/debug/app-debug.apk`. Identificador conservado: `com.edwinortizp.lifetracker`. El APK debug se puede transferir al teléfono para instalarlo autorizando la fuente. Si hay una instalación firmada con otra clave, no desinstalarla sin revisar sus datos.

La URL inicial se define en `BuildConfig.MISO_BASE_URL`. Se aceptan únicamente HTTPS y el origen exacto dentro del visor; enlaces HTTPS externos, teléfono y correo salen a aplicaciones compatibles. No se ignoran certificados ni se permite contenido mixto. Depuración web solo en debug. Cookies propias de WebView, sin compartirlas con Chrome. Copias de seguridad y transferencia de datos del contenedor excluidas.

## Navegación y formularios

Atrás oculta primero el teclado; después solicita a la web cerrar la superficie superior, proteger cambios pendientes y finalmente autorizar retroceso/salida. La pila comparte los componentes de diálogo, hoja, menú, filtros y selectores. Solo la superficie superior captura foco y Escape; cerrar un selector anidado no cierra el formulario.

Los formularios `x-ui.form-dialog` con guardado explícito comparan los campos en memoria. Una validación fallida no limpia el estado. Guardado exitoso, cierre y nueva apertura reinician la referencia. `protect-changes="false"` permite excluir un formulario con autoguardado; `handles-discard` conserva una confirmación de cierre propia (utilizado por Agua), manteniendo la protección de navegación. Una integración que guarde fuera del submit Livewire debe emitir `miso-form-saved` sobre su formulario tras confirmar éxito.

Los cambios no se guardan como borradores en disco. Al cambiar de aplicación no se recarga la WebView; se conserva su estado mientras la instancia siga viva. No se garantiza recuperar formularios tras destrucción de la actividad/proceso. La sesión y su caducidad siguen siendo de Laravel. Si el puente deja de responder, no se retrocede silenciosamente; se permite reintentar. WebView sin mensajería compatible conserva el fallback y bloquea el retroceso cuando la web informa edición pendiente.

## Puente v1

Usa `WebViewCompat.addWebMessageListener`, con origen exacto y verificación `isMainFrame`. Iframes y mensajes de otra versión se rechazan. Límite de mensaje: 8192 caracteres. No se expone `addJavascriptInterface`, ejecución arbitraria, archivos, reglas de negocio ni datos de formularios.

- Web → Android: `navigation`, `backResult`, `session`, `pushStatus`, `pushEnable`, `pushBind`, `pushDisable`, `pushSettings`.
- Solicitudes llevan `v: 1`, `type` e `id`; respuestas correlacionadas llegan por `MisoNative.onmessage`.
- Android → web: `MisoAndroid.back(id)`, `MisoAndroid.openNotice(id)` y eventos `notification`/`resume`.
- La identidad de instalación y su secreto se entregan solo al documento principal confiable y se registran mediante sesión/CSRF. No sustituyen la sesión de Laravel. No registrar cuerpos del puente en logs.
- El servidor devuelve un vínculo opaco de cuenta. Logout y cambio de cuenta limpian el vínculo local y los avisos; Laravel revoca la instalación de la sesión. Un mensaje de un vínculo anterior no se presenta.

APK anteriores mantienen su recorrido web y el navegador/PWA sigue usando Web Push. Para disfrutar la navegación integrada deben desplegarse también los assets y componentes web de esta versión.

## Configurar FCM

Sin `app/google-services.json` el APK compila y funciona, pero Ajustes indica canal sin configurar. No se crean proyectos Firebase automáticamente ni se incluyen claves de servicio en el APK.

1. Registrar una aplicación Android en Firebase con el identificador existente y habilitar Cloud Messaging HTTP v1.
2. Colocar su configuración Android en `app/google-services.json` (ignorada por Git). Gradle toma el cliente cuyo paquete coincide y genera las opciones Firebase; no necesita Google Analytics.
3. Configurar el servidor según [docs/DEPLOY.md](../docs/DEPLOY.md), desplegar la migración y habilitar el canal mediante el procedimiento autorizado.
4. Compilar el APK y, desde una cuenta de prueba, ir a Ajustes → Notificaciones → Activar en este dispositivo. El permiso se solicita solo con esa acción. Si Android bloquea permiso/canal se ofrece Abrir ajustes de Android.
5. Comprobar una entrega desde Laravel al dispositivo y su apertura, con aplicación visible, en segundo plano y proceso terminado sin detención forzada.

Requiere Google Play Services compatible. FCM se usa como transporte; preferencias, franjas y generación permanecen en Laravel. Los mensajes de datos contienen solo versión, ID de aviso y vínculo. Android muestra «MiSo / Tienes un recordatorio pendiente», sin información personal. Un ID estable evita duplicar el aviso del sistema. Al recibirlo con MiSo abierta también se actualiza la campana; no se cambia de pantalla automáticamente.

Al tocarlo, Laravel verifica propietario y disponibilidad; si caducó la sesión, conserva el destino hasta iniciar sesión. Otra cuenta no puede abrirlo. Si hay edición pendiente se pide confirmación antes de navegar. En esta versión no hay botones que registren datos desde la notificación.

No se prometen alarmas exactas, entrega inmediata ni recepción después de forzar detención. La renovación de token se sincroniza al volver a la aplicación; si no hay red, se intentará en una apertura posterior. Desactivar detiene primero la presentación local aunque falle la revocación remota; la próxima sincronización intenta revocarla.

## Archivos y límites

Selector de documentos simple/múltiple y descargas GET autenticadas de mismo origen mediante Guardar archivo. Cookies nunca se envían a otro origen; se valida cada redirect y se rechazan respuestas HTML/HTTP fallidas. Transferencia fuera de UI y retenida al recrear Activity. No hay servicio de descargas persistente, captura directa de cámara, descargas blob/data/POST, biometría, widgets ni sincronización de escrituras offline.

## Validación y pendientes · CHG-101/102/103

Los reportes locales están en `app/build/reports/` y `storage/logs/android-*` del repositorio. La migración nueva se creó y revirtió en una instancia MySQL 8.4 desechable, sin tocar la base de MiSo. Los envíos PHP usan HTTP simulado y claves sintéticas.

Se verifican: aislamiento de cuenta/instalación, renovación/revocación, idempotencia, presupuesto, reintentos de 1 y 5 minutos hasta 3 intentos, independencia de VAPID, protección de edición, foco, contraste y puente restringido. Las pruebas instrumentadas cubren login público HTTPS, recreación, historial con toque real y rechazo de iframes/versiones desconocidas.

**Pendiente operativo:** no hay `google-services.json` ni credenciales de servicio Firebase configuradas, cuenta de prueba ni teléfono físico conectado. No se certifica recepción real FCM ni login/logout/archivos con datos reales. El canal está desactivado por defecto. No se desplegó ni se ejecutó la migración en una base real.


Resultados locales finales (2026-11-13): 28 pruebas PHP / 100 aserciones; 56 JavaScript; 14 de navegador (incluyen 390/1440 px, descarte, aviso durante edición y axe sin excluir contraste); 9 unitarias Android y 4 instrumentadas Android 16/API 36.1; `assembleDebug` y `lintDebug` aprobados (0 errores, 23 advertencias de versiones/estilo). Las 6 pruebas nuevas de navegador se repitieron después de ajustar la capa de confirmación y volvieron a pasar. Captura revisada: `storage/framework/testing/android-discard-mobile.png`. APK debug aproximado: 18.9 MiB.

`ui:conformance` se ejecutó y permanece fallando por deuda fuera de los archivos de este cambio (juego y lista de tareas); no se actualizó su baseline. `git diff --check` de los archivos propios no presentó errores. Se preservaron los cambios ajenos.

# MiSo para Android

Contenedor híbrido Kotlin/Compose con WebView para **https://miso.novapixel.org/**. Laravel, Livewire y MySQL conservan autenticación, pantallas y reglas del negocio. El antiguo prototipo Compose/Supabase fue retirado; no se necesitan claves Supabase.

## Compilar e instalar

Requisitos: Android SDK 36, JDK 17 o superior compatible con AGP 9 (validado con el JBR de Android Studio) y acceso a los repositorios de Gradle. `local.properties` debe declarar `sdk.dir`; no se versiona.

Desde PowerShell, en `Android/`:

```powershell
$env:JAVA_HOME = 'C:/Program Files/Android/Android Studio/jbr'
./gradlew.bat testDebugUnitTest assembleDebug lintDebug
./gradlew.bat connectedDebugAndroidTest # requiere emulador o dispositivo de pruebas
```

APK: `app/build/outputs/apk/debug/app-debug.apk`. Con un dispositivo autorizado por ADB:

```powershell
adb install -r app/build/outputs/apk/debug/app-debug.apk
```

También se puede transferir ese APK al teléfono e instalarlo autorizando la fuente correspondiente. Es una compilación de depuración, no una versión para Play Store. La instalación puede fallar si ya existe el antiguo prototipo firmado con otra clave; no desinstalarlo sin revisar sus datos.

## Configuración y comportamiento

- `BuildConfig.MISO_BASE_URL`, en `app/build.gradle.kts`, centraliza la dirección HTTPS. Mantiene `applicationId = com.edwinortizp.lifetracker`; el nombre visible es MiSo.
- Solo el origen exacto configurado permanece en el visor (HTTPS, host y puerto). Enlaces HTTPS externos abren el navegador; correo y teléfono usan aplicaciones del sistema. HTTP, intent, file, content y javascript no se aceptan como destinos de navegación.
- Sin acceso de archivos locales desde páginas, contenido mixto ni bypass de certificados. JavaScript y almacenamiento DOM habilitados para la aplicación existente. Depuración web solo en builds debug. No hay puente JavaScript nativo.
- Las cookies propias se guardan en el almacén privado de WebView y se sincronizan al pausar. Laravel controla expiración, recordar sesión y logout; el contenedor no extiende su vigencia. No se comparten cookies con Chrome ni se habilitan cookies de terceros. Respaldos y transferencias de datos del contenedor están excluidos.
- Atrás utiliza el historial de WebView, incluido pushState usado por Livewire. Sin historial, Android sale normalmente. La recreación conserva historial/URL mediante saveState; no garantiza preservar formularios sin guardar ni sobrevivir a la terminación del proceso.
- Carga, error de navegación principal y Reintentar son nativos. Errores HTTP de recursos secundarios no sustituyen la pantalla. Los errores TLS siempre se cancelan.
- Los márgenes del sistema y del teclado se aplican en el contenedor; la web conserva su propia navegación. No se agregan barras de módulos nativas.
- Selección de archivos mediante el proveedor de documentos del sistema, simple o múltiple según el input. Sin permiso amplio de almacenamiento ni captura directa de cámara en esta entrega.
- Descargas GET del mismo origen: el usuario elige dónde guardar con el selector Android. La petición usa la cookie de sesión y valida cada redirección antes de enviar credenciales; no permite saltar a otro origen. HTTP no exitoso y HTML (por ejemplo login tras sesión caducada) no se guardan como adjuntos. Transferencia fuera del hilo de UI y retenida durante recreación de Activity. Una transferencia puede interrumpirse si Android termina la aplicación; no es un servicio de descargas en segundo plano. Se intenta eliminar el documento incompleto si falla.
- No se implementan descargas `blob:`, `data:`, por POST ni exportaciones HTML. Requieren un diseño específico si algún módulo las necesita.

## Límites

No incluye notificaciones push nativas, Firebase, biometría, widgets, integración de salud, firma de producción ni publicación en Play. El Web Push de Chrome no debe asumirse disponible dentro de WebView. La bandeja y los avisos dentro de la web permanecen en Laravel.

Las operaciones Livewire requieren red. El caché web existente no equivale a sincronización offline. No se cambió el backend ni se modificaron datos personales para validar el contenedor.

## Validación de CHG-098

Validado el 2026-11-12:

| Comprobación | Resultado |
|---|---|
| `testDebugUnitTest` | 8 pruebas aprobadas: URLs, cookies y redirecciones de descarga, errores HTTP y rechazo de HTML |
| `assembleDebug` | APK generado, aproximadamente 10.4 MiB |
| `lintDebug` | 0 errores y 8 advertencias: SDK/herramientas/dependencias disponibles más recientes y sugerencia de extensión KTX |
| `connectedDebugAndroidTest` | 3 pruebas aprobadas en emulador Android 16/API 36.1: login público HTTPS, configuración/recreación y pushState/Atrás con toque real |
| Revisión visual en emulador | Login sin barra del navegador; teclado visible con área web ajustada; error sin red y recuperación mediante Reintentar |
| `git diff --check` | Sin errores en archivos del cambio |

Evidencia local no versionada: capturas `app/build/miso-login.png`, `app/build/miso-keyboard.png` y `app/build/miso-offline.png`; reportes Gradle en `app/build/reports/`. El test de historial modifica solo el DOM e historial de la página pública, sin escribir datos en Laravel. Los cambios programáticos sin interacción pueden ser omitidos por Chromium al retroceder; la prueba reproduce un toque real como en la navegación de la interfaz.

Pendiente de cuenta de prueba y dispositivo físico: login/logout real, persistencia autenticada tras reapertura, recorridos Livewire con datos, selección/subida de archivos y descarga autenticada contra un módulo real. Las pruebas de política de descargas usan respuestas HTTP simuladas y no certifican esas integraciones. No se ejecutaron suites PHP ni compilación de assets web porque no se modificó la aplicación Laravel.

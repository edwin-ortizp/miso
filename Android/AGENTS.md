# Repository Guidelines

## Project Structure & Module Organization
- Android app module lives in `app/` with source in `app/src/main/java/`.
- UI and screens are Compose-based; entry point is `app/src/main/java/com/edwinortizp/lifetracker/MainActivity.kt`.
- Resources (layouts, drawables, strings) are in `app/src/main/res/`.
- Build configuration is in `app/build.gradle.kts` and root `build.gradle.kts`.
- Tests live in `app/src/test/` (unit) and `app/src/androidTest/` (instrumented).

## Build, Test, and Development Commands
- `./gradlew assembleDebug` — build a debug APK.
- `./gradlew installDebug` — install the debug APK on a connected device/emulator.
- `./gradlew test` — run JVM unit tests (when present).
- `./gradlew connectedAndroidTest` — run instrumented tests on device/emulator.
- `./gradlew lint` — run Android lint checks.

## Coding Style & Naming Conventions
- Language: Kotlin with Jetpack Compose and Material 3.
- Indentation: 4 spaces; keep lines focused and avoid large composable functions.
- Naming: packages lower-case (`com.edwinortizp.lifetracker`), classes and composables in `PascalCase`, functions/vars in `camelCase`.
- Prefer file-per-screen and file-per-feature; group by feature under `com.edwinortizp.lifetracker.ui` or `com.edwinortizp.lifetracker.data`.
- Keep Laravel as the source of authentication, data and business rules. Android hosts its HTTPS interface in WebView; do not duplicate module screens or add a JavaScript bridge without a scoped change.

## Testing Guidelines
- Current setup includes JUnit4 and AndroidX test deps in `app/build.gradle.kts`.
- When adding tests, use `*Test.kt` for unit tests and `*InstrumentedTest.kt` for UI/instrumented tests.
- Keep tests close to features and cover origin restrictions, authenticated downloads and WebView navigation.

## Commit & Pull Request Guidelines
- Existing history uses short, descriptive Spanish messages; keep commits concise and action-focused (e.g., “Arreglo en navegación”).
- For PRs: include a clear description, list any linked issues, and add screenshots for UI changes.
- Note any required config changes (e.g., new `local.properties` keys) in the PR body.

## Security & Configuration Tips
- The web entry point is `BuildConfig.MISO_BASE_URL` from `app/build.gradle.kts`. Do not bypass TLS, allow cleartext traffic or forward session cookies to external origins.
- `local.properties` is only local Android SDK configuration; the wrapper does not need Supabase credentials.

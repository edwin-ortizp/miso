# CHG-117 · Modo oscuro de la app
**Estado:** Borrador
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-16 · **Aprobado:** — · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto: la app tiene un modo oscuro. Lo eliges en **Ajustes → Perfil** (primera opción, donde editas nombre y correo) con tres opciones: *Claro*, *Oscuro* o *Igual que mi dispositivo*. También puedes cambiarlo al instante desde el menú del avatar del header, sin entrar a Ajustes.
- Cómo comprobarlo tú: abre el menú del avatar, toca «Oscuro» y toda la app cambia sin recargar; recarga y sigue oscura; entra desde otro dispositivo con tu cuenta y también está oscura. En Ajustes → Perfil aparece la misma elección y se mantiene sincronizada con el menú.

## 1. Resumen
Añadir un tema oscuro a toda la app web (tokens M3 en `resources/css/m3/tokens`). La preferencia (`system` | `light` | `dark`) se guarda por usuario en la base de datos, se aplica antes del primer pintado para evitar el parpadeo blanco, y se cambia desde dos lugares: sección Perfil de Ajustes y el menú de perfil del header. Hoy ya existen reglas `[data-theme="dark"]` sueltas en chips y gráficos, y un oscuro parcial solo en el login vía `prefers-color-scheme`; este cambio los unifica bajo un único mecanismo.

## 2. Producto (product-manager)
- Problema / usuario: quien usa MiSo de noche o en pantallas OLED no tiene un tema oscuro; el tema claro cansa la vista y gasta batería (PWA/Android).
- Incluye: preferencia por usuario con 3 valores (por defecto `system`); selector en Ajustes → Perfil; selector en el menú del avatar del header; paleta oscura completa de tokens (superficies, contenedores, outline, primario, error, colores semánticos, acentos de módulo); `color-scheme` y `theme-color` coherentes; revisión de colores fijos en CSS/vistas que rompan en oscuro; gráficos (ApexCharts) ya leen `data-theme`.
- No incluye: tema por módulo, temas de color personalizados, programación horaria, rediseño de la app Android nativa, mockups (`docs/mockup-system`), imágenes/ilustraciones nuevas.
- Criterios de aceptación (Dado/Cuando/Entonces):
  - Dado un usuario con preferencia `dark`, cuando carga cualquier pantalla, entonces `<html data-theme="dark">` desde el primer pintado, sin parpadeo claro.
  - Dado `system`, cuando el dispositivo está en modo oscuro (o cambia mientras la app está abierta), entonces la app lo sigue.
  - Dado el menú del avatar, cuando elijo Claro/Oscuro/Sistema, entonces el tema cambia al instante, se guarda en mi cuenta y Ajustes → Perfil muestra la misma opción.
  - Dado que cambio la opción en Ajustes → Perfil, cuando abro el menú del avatar, entonces refleja la opción actual.
  - Dado cualquier pantalla, cuando está en oscuro, entonces texto/controles cumplen contraste WCAG AA (4.5:1 texto, 3:1 componentes) y no hay fondos claros sueltos.
  - Dado el login/registro (sin sesión), cuando el dispositivo está en oscuro, entonces siguen en oscuro como hoy.

## 3. UX/UI
<!-- Sin pantallas: escribe "No aplica — motivo" en cada parte. Con UI, approve exige un boceto ASCII en un bloque de código. Si hay flujo, agrega un diagrama en un bloque mermaid. -->
### 3.1 Experiencia (ux-designer)
- Flujo y cantidad de pasos: desde el header, 2 toques (avatar → opción). Desde Ajustes, 1 toque en la opción (se aplica y guarda al instante, sin botón «Guardar», porque es una preferencia visual reversible).
- Qué contiene cada pantalla y qué es lo más importante: Ajustes → Perfil: nueva tarjeta «Apariencia» **arriba** de «Perfil público» (primera opción). Menú del avatar: grupo «Apariencia» con tres opciones sobre «Mi perfil» / «Cerrar sesión».
- Estados (vacío / carga / error / éxito / validaciones): opción activa marcada; si falla el guardado, el tema se mantiene aplicado en la sesión y se muestra aviso de error con reintento; valor inválido rechazado en servidor.
- Información necesaria y momento de presentación: la opción vigente siempre visible; «Igual que mi dispositivo» explica que sigue el sistema.
- Accesibilidad de uso (teclado, foco, errores): grupo de radios (`role="radiogroup"`) con flechas y foco visible; el menú actual conserva Escape y retorno del foco; cambio anunciado con `aria-live="polite"`.

### 3.2 Visual (ui-designer)
- Distribución: ver boceto.
- Componentes existentes a reutilizar (ruta): `resources/views/components/ui/*` (card, section, segmented/chips o radio existentes — confirmar en `docs/design-system.md`), `resources/views/components/account-menu.blade.php`, `resources/views/livewire/settings/partials/profile.blade.php`.
- Jerarquía, color, tipografía y espaciado (tokens): bloque `[data-theme="dark"]` (y `@media (prefers-color-scheme: dark)` para `system`) que redefine los mismos `--md-sys-color-*`, `--md-custom-color-*` y `--md-accent-*` (acentos aclarados para contraste). Superficies neutras frías (p. ej. surface `#10141A`); sombras sustituidas por tonos de superficie; sin valores hex nuevos en componentes.
- Responsive y contraste: un único layout; el selector del menú cabe a 320 px; contraste AA verificado por token.

```
Ajustes → Perfil                         Menú del avatar
┌──────────────────────────────┐        ┌───────────────────────┐
│ Apariencia                   │        │ Tu cuenta             │
│ ( ) Claro  (•) Oscuro        │        │ Apariencia            │
│ ( ) Igual que mi dispositivo │        │  ☀ Claro              │
├──────────────────────────────┤        │  ☾ Oscuro      ✓      │
│ Perfil público               │        │  ◐ Sistema            │
│ Nombre / Email   [Guardar]   │        │ ───────────────────── │
└──────────────────────────────┘        │ 👤 Mi perfil          │
                                        │ ⎋ Cerrar sesión       │
                                        └───────────────────────┘
```

### 3.3 Escritura (ux-writer)
- Ubicación y estado: tarjeta de Ajustes → Perfil y grupo del menú del avatar.
- Textos finales (variables y variantes): título «Apariencia»; ayuda «Elige cómo quieres ver MiSo.»; opciones «Claro», «Oscuro», «Igual que mi dispositivo» (en el menú: «Sistema»); confirmación «Apariencia actualizada.»; error «No pudimos guardar tu apariencia. Inténtalo de nuevo.»
- Reglas de docs/UX-WRITING.md aplicadas: español neutro, tuteo, frases cortas, sin jerga.
- Criterios de aceptación de los textos: ux-writer valida contra `docs/UX-WRITING.md` antes de `approve`.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar:
  - Crear migración `users.theme_preference` (string, default `system`); `app/Models/User.php` (fillable + enum/valores).
  - `app/Livewire/Settings/SettingsPage.php` y `partials/profile.blade.php` (tarjeta Apariencia); `components/account-menu.blade.php` (grupo Apariencia).
  - Ruta/acción para guardar la preferencia (`routes/web.php`; Livewire o endpoint `PATCH`) con validación `in:system,light,dark`.
  - `resources/views/layouts/{app,panel,onboarding}.blade.php`: script inline mínimo en `<head>` que fija `data-theme` antes del primer pintado, `color-scheme` y `theme-color` dinámicos.
  - `resources/js/app.js` (pequeño módulo de tema: aplica, escucha `matchMedia`, sincroniza menú/Ajustes, re-aplica tras `wire:navigate`).
  - `resources/css/m3/tokens/_tokens.css` (paleta oscura) y barrido de colores fijos en `resources/css/m3/**` y vistas con hex (3 en Blade); migrar `_auth.css` al mecanismo unificado.
- Enfoque y patrones a seguir: tokens M3 como única fuente; `data-theme` en `<html>` con valores resueltos `light|dark` (el script resuelve `system`); guardar por usuario con `Auth::user()` (aislamiento por usuario); respetar capas CSS (`CssLayerContractTest`).
- Riesgos y mitigación: parpadeo claro (script bloqueante inline); colores fijos que se ven mal (barrido + `ui:conformance` + capturas Playwright por módulo); gráficos que no se re-pintan al cambiar (disparar evento y re-render); preexistentes: `ui:conformance` y `CssLayerContractTest` ya fallan en main, no mezclar con regresiones.
- Qué NO tocar: `Android/`, `docs/mockup-system`, lógica de negocio de módulos, contenido de imágenes.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Guardar `dark`/`light`/`system` y rechazar valor inválido | Feature | Servidor | `tests/Feature/ThemePreferenceTest.php` |
| Un usuario no puede cambiar la preferencia de otro / invitado no accede | Feature | Servidor | idem |
| Layout renderiza `data-theme` correcto según preferencia | Feature | Servidor | idem |
| Selector en Ajustes → Perfil y menú del avatar se sincronizan | Livewire/E2E | UI | Playwright |
| Sin parpadeo, `system` sigue el SO, contraste AA, 320/390/768/1440 px | E2E | UI | Playwright (capturas por módulo) |

- Comando para verificar: `php artisan test --filter=ThemePreference` · `npm run build` · `php artisan ui:conformance` · Playwright (`playwright.config.js`).
- Regresión a vigilar: login/registro oscuros, gráficos, chips de catálogo, header móvil, shell del módulo, modales/diálogos.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/design-system.md` (tokens y reglas del tema oscuro; prohibido hex fijo en componentes), `docs/UI-DESIGN.md` si aplica.

## 7. Tareas
<!-- Primero lo que cambia para el usuario; el detalle técnico después. Formato:
- [ ] T1 · qué cambia (en simple) — Resp: developer — Modelo: perfil — Archivos: … — Hecho cuando: … -->
- [ ] T1 · Tu preferencia de apariencia se guarda en tu cuenta — Resp: developer — Archivos: migración users, `app/Models/User.php`, ruta/acción, `tests/Feature/ThemePreferenceTest.php` — Hecho cuando: guarda/valida/aísla por usuario y las pruebas pasan.
- [ ] T2 · La app se ve oscura sin parpadeo — Resp: developer — Archivos: `resources/css/m3/tokens/_tokens.css`, layouts, `resources/js/app.js`, barrido de colores fijos — Hecho cuando: todos los módulos legibles en oscuro (AA) y `system` sigue al dispositivo.
- [ ] T3 · Puedes elegir la apariencia en Ajustes → Perfil y en el menú del avatar — Resp: developer — Archivos: `SettingsPage.php`, `partials/profile.blade.php`, `account-menu.blade.php` — Hecho cuando: ambos selectores sincronizados, accesibles por teclado y con los textos aprobados.
- [ ] T4 · Documentación del tema — Resp: tech-writer — Archivos: `docs/design-system.md` — Hecho cuando: tokens y reglas documentados.

## 8. Bitácora de implementación (developer)

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-16 — Creado en to-do/ (Borrador, nivel M)

# CHG-137 · Guardián contextual en las siete pestañas de Salud
**Estado:** Cerrado
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-16 · **Aprobado:** 2026-11-16 · **Cerrado:** 2026-11-17
**Relacionado:** —

## 0. En palabras simples
Las siete pestañas de Salud compartirán Guardián, con datos propios a la derecha y adaptación móvil. Comprueba cada pestaña y cambia sus filtros.

## 1. Resumen
Implementar el plan aprobado en conversación: Guardián contextual en las siete pestañas de Salud.

## 2. Producto (product-manager)
Para usuarios de Salud. Incluye paneles, consejos descriptivos y actualización reactiva. Excluye migraciones, notificaciones e IA externa.
Aceptación: al abrir cada pestaña se ve el mismo Pokémon y datos exclusivos de esa sección; al modificar registros o filtros se actualizan.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
Plantilla: shell de módulo con panel contextual; lista, registro y detalle según la pestaña.
Registro conserva seguimientos, próximo evento y pendientes. Cuerpo resume zona seleccionada o general. Mediciones muestra últimas por tipo con fecha y unidad. Medicamentos resume estados de hoy y próxima pendiente de hoy. Sueño muestra último descanso, meta y siete días. Ciclo muestra periodo actual, último registro y estimación existente. Tendencias resume indicadores y cobertura del periodo.
Estados vacíos específicos; resúmenes disponibles sin Pokémon. Mantener teclado y ficha modal.
### 3.2 Visual (ui-designer)
Reutilizar shell, tokens y componentes existentes; una sola presentación visible por tamaño.
```
Escritorio: [Contenido de sección] [Guardián + resumen]
Móvil:     [Guardián contextual]
           [Contenido de sección]
           [Resumen]
```
### 3.3 Escritura (ux-writer)
Mensajes descriptivos de los datos disponibles; sin diagnósticos, dosis, juicios corporales, fertilidad ni causalidad. Conservar etiquetas existentes y «Otro consejo».

## 4. Diseño técnico (software-architect)
Archivos: app/Livewire/Health/*, app/Livewire/Game/GuardianCard.php, app/Services/Game/Guardian/*, resources/views/livewire/health/*, resources/views/livewire/game/guardian-card.blade.php y tests/Feature/* relacionados, tests/Browser/health-guardian.interaction.spec.js y su fixture aislada; estilos específicos solo si son necesarios.
Extender GuardianCard con contexto opcional reactivo; conservar module=health para selección y compatibilidad. Padres preparan datos reutilizando cálculos existentes. Invalidar consejos al cambiar contexto. No tocar esquema, rutas públicas ni notificaciones.

## 5. Pruebas (qa-tester)
Pruebas Livewire: siete pestañas, filtros y CRUD, aislamiento entre usuarios, vacíos, juego desactivado, mismo Pokémon, consejos contextuales y ficha modal.
Comandos: php artisan test --filter='Health|Guardian|Sleep|Cycle|Medications'; npm run build; php artisan ui:conformance; git diff --check.
Revisar móvil y escritorio, teclado y ausencia de duplicados o desbordamientos.

## 6. Documentación (tech-writer)
Actualizar bitácora y documentación de guardianes afectada. DOCUMENTATION.md no existe; no crearlo. CLI context no disponible.

## 7. Tareas
- [x] T1 · Contexto reactivo y consejos de Salud — Resp: developer — Hecho cuando: pruebas de contexto y compatibilidad pasan.
- [x] T2 · Paneles en las siete pestañas — Resp: developer — Hecho cuando: datos y actualización cumplen §3.
- [x] T3 · Validación y documentación — Resp: developer — Hecho cuando: verificaciones de §5 registradas.

## 8. Bitácora de implementación (developer)
- T1/T2: contexto opcional reactivo en GuardianCard; seis vistas nuevas reutilizan el mismo module=health. Registro conserva su implementación. HealthContext prepara resúmenes usando datos ya cargados; Ciclo añade una consulta al último registro diario. Cuerpo sincroniza la selección Alpine con Livewire.
- Pruebas nuevas: HealthGuardianContextTest, 9 pasan / 52 aserciones (siete rutas, rotación/escape, ficha, filtros, CRUD de sueño y mediciones, estados de medicamentos, aislamiento y juego apagado).
- Regresión: 218 pruebas ejecutadas; cuatro fallos previos en ModuleGuardianTest (3 de equipo semanal) y HealthModuleTest (gestión de pendientes). Reproducidos con GuardianCard original precargada desde HEAD. Una aserción propia desactualizada tras mejorar concordancia se corrigió y las 9 pruebas nuevas volvieron a pasar (52 aserciones); confirmadas de nuevo con PHPUnit tras el ajuste visual final.
- Entorno: SQLite ausente; pruebas ejecutadas con pdo_sqlite descargado y extraído en /tmp y PHP_INI_SCAN_DIR, sin cambios de dependencias del proyecto ni instalación global.
- npm run build y php artisan view:cache correctos. ui:conformance encuentra deuda previa; sin infracciones en resources/views/livewire/health. git diff --check correcto sobre app/resources/tests/docs; artisan tiene finales de línea previos ajenos.
- Navegador: primer intento bloqueado por bibliotecas ausentes; se descargaron libnspr4/libnss3/libasound2t64 a /tmp y se ejecuta con LD_LIBRARY_PATH. El recorrido móvil detectó desbordamiento del calendario de Ciclo: se contuvo con md-mcard__scroll, región etiquetada y accesible por teclado, sin estilos nuevos. Revisión final completada: 2 pruebas Playwright pasan a 390 y 1440 px, recorriendo las siete pestañas, comprobando una sola tarjeta visible, ausencia de desbordamiento, posición del rail, filtros de Tendencias, selección corporal, ficha por teclado y axe sobre el Guardián. Capturas inspeccionadas; se reutilizó x-context-widget para respetar los márgenes del Guardián y evitar superposición del título.

## 9. Preguntas abiertas, decisiones e historial
Alcance y plan aprobados en conversación. LECCIÓN: ninguna.
- 2026-11-16 — Aprobado por el usuario (Compuerta 2)
- 2026-11-16 — Movido a in-progress/ (En desarrollo)

- Verificación final: Pint pasa en archivos PHP nuevos; diff --check pasa en archivos propios. Las modificaciones concurrentes de Formación y otros archivos ajenos se conservaron.
- 2026-11-16 — Implementación terminada → En verificación
- 2026-11-17 — Cerrado por el usuario (Compuerta 3) → done/

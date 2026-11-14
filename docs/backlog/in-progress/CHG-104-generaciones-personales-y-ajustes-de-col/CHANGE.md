# CHG-104 · Generaciones personales y ajustes de Colección por secciones
**Estado:** En verificación
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-14 · **Aprobado:** 2026-11-14 · **Cerrado:** —
**Relacionado:** —

## 0. En palabras simples
- Qué vas a ver distinto: elegir generaciones con tarjetas visuales y ajustes separados por secciones.
- Cómo comprobarlo tú: abre Generaciones, cambia la selección y guarda; tus compañeros se conservan.

## 1. Resumen
Implementar el plan aprobado explícitamente por el usuario el 2026-11-14: primera generación por defecto para todos, preferencias por usuario, descubrimiento de ramas mediante Pokébolas y ajustes por sección.

## 2. Producto (product-manager)
- Problema / usuario: el catálogo completo resulta desconocido y los ajustes son un formulario largo.
- Incluye: nueve generaciones, recomendación descartable, reglas de encuentro y evolución, conservación de logros.
- No incluye: despliegue, migración sobre datos reales ni nuevas recompensas por generación.
- Criterios de aceptación: Dado un usuario sin selección, cuando obtiene una ball, entonces solo usa generación 1; dado otro usuario, su selección es independiente.

## 3. UX/UI
### 3.1 Experiencia (ux-designer)
- Flujo: Ajustes → Generaciones → seleccionar → Guardar cambios. Al menos una activa.
- Secciones: General, Generaciones, Recompensas, Compañero y Celebraciones, con rutas reales.
- Estados: carga, error inline, éxito anunciado, cambios pendientes y aviso canónico al salir.
- Accesibilidad: etiquetas, foco visible, controles de 44px, contraste y movimiento reducido.
### 3.2 Visual (ui-designer)
- Reutilizar settings-layout/settings-nav y componentes x-ui; mantener navegación principal del módulo.
- Tokens M3, Nunito, tarjetas compactas con ilustraciones y progreso accesible.
- Escritorio: rejilla flexible y navegación derecha. Móvil: una columna y secciones arriba.
```text
Escritorio
[ navegación Colección                                      ]
[ Tu aventura incluye 151 Pokémon       ] [ General        ]
[ Generación 1 ON ][ Generación 2 OFF ]   [ Generaciones *  ]
[ imagen · X/151  ][ imagen · X/100   ]   [ Recompensas     ]
[ Guardar cambios · cambios pendientes ] [ Compañero       ]
Móvil
[ General | Generaciones | Recompensas → ]
[ Tu aventura incluye 151 Pokémon        ]
[ Primera generación · Los 151 originales]
[ imágenes · Descubiertos X de 151 · ON  ]
[ Guardar cambios                       ]
```
### 3.3 Escritura (ux-writer)
- Textos: «Activa», «En pausa», «Guardar cambios», «Cambios pendientes», «Elegir generación», «Ahora no».
- Tuteo latinoamericano, orientación sin exigencias, errores claros; sin detalles técnicos en la experiencia principal.

## 4. Diseño técnico (software-architect)
- Catálogo global: columna generation, importador y llenado validado por número desde pokemon.json.
- Servicio central de catálogo personal, selección de entradas y ramas elegibles; usuario explícito sin autenticación.
- enabled_generations en ModuleSetting, valor por defecto [1]; nunca alterar active global.
- Conservar pendientes e idempotencia. Alternativas descubiertas no sustituyen al compañero; premios de álbum únicos.
- Riesgos: familias entre generaciones y ramas; cubrir Pikachu/Pichu y Eevee con pruebas.
- Qué NO tocar: cambios ajenos, datos reales, equipo, favorito y guardianes existentes.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Catálogo y aislamiento | PHP | Integración | GenerationsTest |
| Importación y llenado | PHP/MySQL desechable | Integración | ImportGameSpeciesTest y migración |
| Ajustes y navegación | Livewire/browser | Integración | GameSettingsSectionsTest |
- Comandos: pruebas afectadas, npm run build, ui:conformance, git diff --check.
- Regresión: recompensas, evolución, álbumes, enlaces, formularios pendientes y responsive.

## 6. Documentación (tech-writer)
- Actualizar docs/DEPLOY.md y documentación de Colección con selección, migración y compatibilidad.

## 7. Tareas
- [x] T1 · Elegir generaciones y descubrir especies por usuario — Resp: developer — Modelo: estandar — Archivos: app/Services/Game, database — Hecho cuando: catálogo, importación, aislamiento y ramas tienen pruebas aprobadas.
- [x] T2 · Ajustes visuales por secciones y progreso — Resp: developer — Modelo: estandar — Archivos: app/Livewire/Game, resources, routes — Hecho cuando: cinco secciones guardan independientemente y usan UI canónica.
- [x] T3 · Integrar y verificar conservación de logros — Resp: developer — Modelo: estandar — Archivos: tests, docs — Hecho cuando: pasan verificaciones afectadas y migración MySQL desechable; limitaciones documentadas.

## 8. Bitácora de implementación (developer)
- Inicio: comprobado catálogo local con 1025 especies sin generation. Preservados cambios ajenos en docs.

- Implementado catálogo personal con generación, importación parcial compatible y migración que valida todo antes del DDL. Prueba MySQL desechable: 1025 mapeos correctos, descripciones preservadas, rollback y rechazo de nombre inconsistente; base original intacta.
- Cinco secciones con rutas reales y guardado independiente; tarjetas con imágenes, progreso canónico y navegación reutilizada de Ajustes. Guardado accesible sin recorrer toda la lista, y protección canónica de cambios pendientes.
- Encuentros y evoluciones respetan preferencias; Pikachu sin Pichu, ramas alternativas por nivel y antecesor descubierto sin reemplazar al compañero, protección de novedad por especie y conservación de Pokébolas asignadas. Álbumes personales sin repetir premios.
- Prueba del catálogo real demuestra que son alcanzables todas las especies con selecciones [1], [2] y [1,6]. Pruebas de aislamiento con usuario explícito, validación, favorito ajeno, conservación y recomendación descartable.
- Suite Game (327): cinco fallos preexistentes confirmados ejecutando las clases de HEAD mediante un cargador temporal fuera del código del producto: GameDashboardTest::test_balls_page_shows_the_streak_card (texto de ayuda antiguo), tres casos de ModuleGuardianTest (equipo/guardián) y NewSourcesTest::test_director_accepts_the_new_scopes (validación de misiones). Un error de import del test nuevo se corrigió y su grupo pasó después. No se alteraron esas reglas ajenas.
- ui:conformance mantiene deuda previa en day-tile-info, focus-companion, guardian-card, checkin-sheet, task-list y los controles de álbum ya existentes en game-collection. Los partials nuevos no tienen infracciones; no se modificó el baseline.
- Corrección de regresión detectada en navegador: el descarte espera a restaurar la configuración guardada antes de que Livewire capture el historial. El contrato opcional `miso-form-discard` conserva el comportamiento de los formularios sin listener; si falla la restauración, mantiene los cambios pendientes y bloquea la salida. Dos pruebas JS específicas verifican espera y error.
- El interruptor canónico ahora conserva un objetivo táctil mínimo de 44px; las opciones de presentación también cumplen ese mínimo.
- Los 88 hallazgos del analizador de capas CSS son idénticos a HEAD, comprobado contra una copia temporal del CSS original. Los tres fallos adicionales de contratos UI corresponden a esa deuda de capas y pantallas previas fuera de este cambio; las rutas nuevas no aparecen entre las infracciones. El sistema no define una paleta oscura global completa: las capturas con preferencia oscura no certifican un tema oscuro propio.
- npm run build correcto; aviso de tamaño de chunks existente. Se preservaron los cambios ajenos en docs/TEAM_PLAN y CHG-096/097/098/100/101 y nuevos borradores CHG-105 a CHG-109.
- Documentación operativa en docs/game-generations.md y docs/DEPLOY.md. No se aplicó migración a datos reales, no se desplegó, no se hizo commit.

- Cierre de validación local: 53 pruebas PHP afectadas pasan (226 aserciones), 5 pruebas JS del guardián de navegación pasan; 6 escenarios de navegador pasan, incluidos teclado, guardado, reversión de cambios, descarte y regreso, validación, accesibilidad y capturas 390/1440 con preferencias de color light/dark. Build y diff --check propios correctos.
- Capturas revisadas: storage/framework/testing/generations-390-light.png y generations-1440-light.png; variantes con preferencia dark también generadas. El guardado permanece accesible sobre la navegación inferior.
- Pendiente operativo: autorización para migración en base real y despliegue, más revisión del usuario. Se deja En verificación y no se cierra automáticamente.

## 9. Preguntas abiertas, decisiones e historial
- Sin preguntas abiertas. Plan aprobado por el usuario en la conversación; se registra esa autorización para approve/start.
- 2026-11-14 — Aprobado por el usuario (Compuerta 2)
- 2026-11-14 — Movido a in-progress/ (En desarrollo)
- 2026-11-14 — Implementación terminada → En verificación

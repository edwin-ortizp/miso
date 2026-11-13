# CHG-101 · Navegación Android y protección de formularios
**Estado:** En verificación
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-13 · **Aprobado:** 2026-11-13 · **Cerrado:** —
**Relacionado:** CHG-098

## 0. En palabras simples
MiSo conserva su interfaz y gana navegación y avisos integrados con Android. Comprobar en el APK de pruebas y revisar la matriz de validación.
<!-- Para el usuario: sin jerga. Si no se entiende en 30 segundos, reescríbelo. -->
- Qué vas a ver distinto:
- Cómo comprobarlo tú:

## 1. Resumen
Navegación, puente v1 de origen restringido, pila de superficies y protección de formularios sin borradores persistentes. Plan completo aprobado por el usuario en conversación el 2026-11-13. Laravel conserva pantallas, datos y reglas. Depende de CHG-101 → CHG-102 → CHG-103.
<!-- Qué se cambia y por qué (3-5 líneas). Entendimiento aprobado por el usuario. -->

## 2. Producto (product-manager)
Para usuarios de MiSo Android. Incluye solo el alcance del resumen y plan aprobado. No incluye reescritura, borradores persistentes, widgets ni publicación. Dado un usuario autenticado, cuando usa Atrás o activa avisos, entonces se protege su edición y su cuenta.
- Problema / usuario:
- Incluye:
- No incluye:
- Criterios de aceptación (Dado/Cuando/Entonces):

## 3. UX/UI
<!-- Sin pantallas: escribe "No aplica — motivo" en cada parte. Con UI, approve exige un boceto ASCII en un bloque de código. Si hay flujo, agrega un diagrama en un bloque mermaid. -->
### 3.1 Experiencia (ux-designer)
Reutilizar superficies y acciones existentes. Proteger edición sin perder foco. Estados: limpio, modificado, confirmación, permiso denegado, servicio sin configurar y error recuperable.
- Flujo y cantidad de pasos:
- Qué contiene cada pantalla y qué es lo más importante:
- Estados (vacío / carga / error / éxito / validaciones):
- Información necesaria y momento de presentación:
- Accesibilidad de uso (teclado, foco, errores):

### 3.2 Visual (ui-designer)
Reutilizar componentes canónicos, sin navegación duplicada.
```text
Formulario actual → Tienes cambios sin guardar → [Seguir editando] [Descartar cambios]
Ajustes / Este dispositivo → estado → [Activar en este dispositivo]
Android: MiSo / Tienes un recordatorio pendiente → destino autenticado
```
- Distribución:
- Componentes existentes a reutilizar (ruta):
- Jerarquía, color, tipografía y espaciado (tokens):
- Responsive y contraste:

### 3.3 Escritura (ux-writer)
Textos aprobados: Tienes cambios sin guardar / Seguir editando / Descartar cambios. Push: MiSo / Tienes un recordatorio pendiente. Errores directos, sin humor ni datos privados.
- Ubicación y estado:
- Textos finales (variables y variantes):
- Reglas de docs/UX-WRITING.md aplicadas:
- Criterios de aceptación de los textos:

## 4. Diseño técnico (software-architect)
Puente v1 con allowedOrigin y frame principal; Laravel autentica por sesión/CSRF. Instalación y vínculo revocables, entregas únicas y transporte independiente de VAPID. Firebase opcional. Respetar hosting con cron acotado y aislamiento por usuario; no cambiar taxonomías ni trabajo ajeno.
- Archivos a crear / modificar:
- Enfoque y patrones a seguir:
- Riesgos y mitigación:
- Qué NO tocar:

## 5. Pruebas (qa-tester)
Pruebas PHP afectadas de sesiones/canales/aislamiento, JS de superficies/edición, build web, ui:conformance y Android unit/instrumentadas/lint/APK. MySQL desechable para migración. Documentar Firebase y dispositivo físico pendientes si faltan.
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
- Comando para verificar:
- Regresión a vigilar:

## 6. Documentación (tech-writer)
Android/README.md y docs/DEPLOY.md: puente, configuración, pruebas, límites y rollback. Sin despliegue ni migración real.
- Docs a actualizar:

## 7. Tareas
- [x] T1 · Implementar el alcance aprobado — Resp: developer — Modelo: estandar — Archivos: Android, aplicación y pruebas pertinentes — Hecho cuando: código completo y validación local documentada.
- [x] T2 · Verificar y documentar resultados y dependencias operativas — Resp: developer — Modelo: estandar — Archivos: docs y README Android — Hecho cuando: pruebas ejecutadas, pendientes explícitos y APK generado.
<!-- Primero lo que cambia para el usuario; el detalle técnico después. Formato:
- [x] T1 · qué cambia (en simple) — Resp: developer — Modelo: perfil — Archivos: … — Hecho cuando: … -->

## 8. Bitácora de implementación (developer)
- 2026-11-13 — Implementado el plan aprobado, con Laravel como autoridad. Puente v1 restringido a origen/frame principal; cierre por pila y protección sin persistir borradores; confirmación propia de Agua conservada. El foco/scroll solo se capturan según la superficie superior.
- Canal FCM HTTP v1 opcional, instalaciones con token cifrado y secreto hasheado, revocación por cuenta, entregas únicas, 3 intentos con espera de 1/5 minutos, presupuesto compartido con el cron existente y diagnóstico sin envíos. Web Push conserva su transporte.
- APK 0.2.0 generado sin configuración Firebase; estados y permiso bajo acción del usuario; aviso genérico, rechazo de vínculos anteriores y apertura autenticada protegida si hay edición. No se incluyen datos personales en el mensaje.
- Validaciones finales: PHP 28/28 (100 aserciones), JS 56/56, navegador 14/14 (6 relacionadas repetidas tras ajuste visual: 6/6), Android JVM 9/9 e instrumentadas 4/4 en API 36.1. Build web/APK y lint aprobados; lint: 0 errores/23 advertencias. MySQL 8.4 desechable: up/down de la migración correctos. Captura móvil revisada y contraste comprobado con axe.
- `ui:conformance` sigue detectando deuda preexistente fuera de los archivos modificados por este trabajo; no se alteró el baseline. Preservado trabajo ajeno. `git diff --check` propio correcto.
- Documentación: Android/README.md, docs/DEPLOY.md y contrato en docs/design-system.md. APK: Android/app/build/outputs/apk/debug/app-debug.apk.
- Pendiente operativo: proyecto/configuración Firebase, credenciales privadas del servidor, despliegue y migración autorizados, cuenta de prueba y teléfono físico. No se certifica entrega FCM real ni integración con datos personales; no se desplegó ni migró una base real. Mantener En verificación, sin cerrar hasta esa comprobación.


## 9. Preguntas abiertas, decisiones e historial
- Revisión final: efecto reactivo del protector ligado al ciclo de vida de la directiva; al desmontar no retiene formularios. Build web, 9 pruebas JS del puente/protector y las 6 pruebas de navegador afectadas repetidas correctamente.
- Sin preguntas de implementación. Firebase real, teléfono y cuenta de prueba son dependencias operativas; no desplegar ni migrar datos reales.
- 2026-11-13 — Creado en to-do/ (Borrador, nivel M)
- 2026-11-13 — Aprobado por el usuario (Compuerta 2)
- 2026-11-13 — Movido a in-progress/ (En desarrollo)
- 2026-11-13 — Implementación terminada → En verificación
- 2026-11-13 — Reabierto: Ajustar liberación de efectos reactivos al desmontar formularios antes del cierre local
- 2026-11-13 — Implementación terminada → En verificación

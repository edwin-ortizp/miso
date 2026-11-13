# Compañero favorito · CHG-097

Propuesta offline dentro del recorrido de Aprendizaje (`learning.html`). El favorito pertenece a una colección ficticia y permanece aunque cambie el equipo semanal. «Líder de corazón» expresa vínculo, sin efectos sobre experiencia, recompensas ni guardianes.

## Recorrido de revisión

1. Observar únicamente el icono flotante de Pikachu en la esquina inferior izquierda, opuesta al FAB. Tocarlo abre una burbuja con onomatopeya y mensaje breve; Escape, cerrar o tocar fuera la cierra. Navegar entre Formación, Calendario, Tareas, Objetivos y Hábitos.
2. En la burbuja, elegir «Ver compañero» y luego «Cambiar compañero». Elegir a Eevee, que no pertenece al equipo semanal de Abra. El guardián conserva su identidad y consejos.
3. Entrar en Pomodoro: el mismo icono flotante permanece una sola vez; ya no se duplica junto al temporizador.
4. Ocultarlo, navegar y volver a mostrarlo desde el perfil.
5. Alternar «Sin favorito» y «Colección vacía». La invitación es opcional; una colección vacía explica por qué todavía no hay opciones.
6. Reiniciar los datos: se restauran la elección y visibilidad del escenario. Nada se guarda en cuentas reales ni fuera de la sesión del prototipo.

## Ejecutar y validar

```powershell
python ../mockup-system/build.py --project docs/mockup-system learning
node docs/mockup-system/mockups/learning/tests/favorite.mjs
python -m http.server 8766 --bind 127.0.0.1 --directory docs/mockup-system/dist
```

Abrir `http://127.0.0.1:8766/learning.html`. Resultados y capturas: `dist/favorite-review/` (artefactos locales). No se usa Vite: los assets del prototipo los procesa su compilador.

Pikachu, Eevee y Abra usan arte oficial descargado del repositorio PokeAPI/sprites; copias fuente en `project-assets/pokemon/`. Se empaquetan una vez en `favorite-art.js` para que el HTML funcione sin red y sin repetir imágenes en cada pantalla y escenario.

## Decisión de producto pendiente

Revisar si la compañía resulta cálida y discreta, si se entiende su diferencia con el guardián y si la burbuja se entiende y el icono deja libres el FAB, la navegación y los controles en móvil. Esta prueba no implementa funcionalidad de producción.

## Resultado local · 2026-11-12

- Compilación normal publicada: 4 comprobaciones aisladas aprobadas; HTML de 4.34 MB.
- `favorite.mjs`: recorridos de escritorio/móvil, foco iniciar/pausar, cancelación y retorno del foco, navegación, ocultación/perfil, reinicio, escenarios, axe, movimiento reducido y ausencia de red externa aprobados.
- `acceptance.mjs`: recorrido integrado, 14 vistas responsive y 5 comprobaciones Axe aprobados.
- Capturas de presencia, selección y foco revisadas. Manifiestos de los consumidores del guardián válidos; `git diff --check` aprobado.
- `ui:conformance`: 228 desviaciones preexistentes de las vistas Blade; este cambio no modifica la aplicación de producción.
- Estado: listo para revisión del usuario, sin decisión de incorporación a producción.

## Revisión flotante solicitada por el usuario

Se retiraron la franja y el nombre permanente. El arte funciona como botón flotante; la burbuja se abre solo al tocarlo y usa texto contextual breve y una onomatopeya ficticia de presentación. No hay entrada de chat ni respuestas de IA. El compañero se oculta mientras un modal o el menú de creación están abiertos. En móvil respeta la navegación y el área segura; en escritorio/tablet respeta el sidebar y su variante rail.

Validación de esta revisión: compilación normal aprobada (4 comprobaciones, 4.38 MB); pruebas específicas aprobadas en escritorio, tablet y móvil con límites de burbuja y separación del FAB/nav; recorrido integrado aprobado (14 vistas y 5 comprobaciones Axe). Capturas de los tres tamaños revisadas y `git diff --check` aprobado.

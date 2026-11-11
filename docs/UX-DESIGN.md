# UX Design

## Layout del shell: rail lateral

- Antes de colocar contenido en `<x-slot:rail>` de un módulo, comprueba que ese módulo tiene `rail => true` en `config/modules.php`. Si no lo tiene, el rail se renderiza pero el shell lo oculta y el contenido no se ve. Evidencia: guardián invisible en Hábitos (CHG-058).

## Tarjetas de seguimiento: ayuda contextual

- En tarjetas de seguimiento, prioriza el estado actual, el progreso y la siguiente acción. Muestra las explicaciones secundarias con ayuda contextual cuando no sean necesarias para actuar y deja visibles las instrucciones esenciales. El disparador de la ayuda debe tener un nombre accesible. La ayuda debe poder abrirse y cerrarse con teclado y, si es un diálogo, devolver el foco al disparador al cerrarse. No uses un acordeón como solución por defecto. Evidencia: la explicación de la racha pasó de un acordeón a un botón de ayuda con diálogo canónico (CHG-083).

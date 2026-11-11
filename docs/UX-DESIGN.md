# UX Design

## Layout del shell: rail lateral

- Antes de colocar contenido en `<x-slot:rail>` de un módulo, comprueba que ese módulo tiene `rail => true` en `config/modules.php`. Si no lo tiene, el rail se renderiza pero el shell lo oculta y el contenido no se ve. Evidencia: guardián invisible en Hábitos (CHG-058).

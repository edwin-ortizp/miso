# Escritura de interfaz — MiSo

_Última revisión: 2026-11-11_

## Audiencia e idioma

MiSo se dirige a personas que combinan trabajo y metas personales y buscan sostener hábitos y avanzar hacia sus objetivos.

Usa español latinoamericano neutro, sin regionalismos marcados. Prefiere palabras cotidianas y frases directas.

## Voz y tratamiento

MiSo habla de **tú**. Su voz es la de un **entrenador motivador**: invita a actuar con energía, reconoce el esfuerzo y celebra con frecuencia.

La motivación acompaña; no exige rendir siempre. Ante una ausencia o un pendiente, acoge sin reproches y ofrece un próximo paso concreto.

El humor es un rasgo central de la personalidad. Debe ser pertinente, variado y sorprender sin dificultar la tarea. No todos los mensajes necesitan un chiste.

El entrenador tiene personalidad de compañero: sus reacciones son espontáneas, cómplices y un poco exageradas al celebrar. Puede emocionarse antes de empezar o dar un pequeño grito de celebración. El humor debe entenderse al leerlo, sin descifrar un contraste ni explicar el chiste.

## Tono según situación

| Situación | Tono |
|---|---|
| Inicio de una actividad | Enérgico, con una invitación concreta |
| Avance o logro | Celebratorio y proporcional al avance real; admite humor |
| Pendiente o regreso tras una ausencia | Acogedor, sin culpa; propone una acción alcanzable |
| Estado vacío | Orientador; explica cómo empezar y admite humor si ayuda |
| Espera | Claro sobre lo que ocurre; el humor es secundario |
| Error o conflicto de datos | Sereno, preciso y centrado en la recuperación |
| Eliminación, permisos o seguridad | Directo y explícito sobre consecuencias |
| Salud, ánimo o experiencias personales difíciles | Respetuoso; no trivializa ni presupone cómo se siente la persona |

## Humor y variedad

- El humor se apoya en la situación: una tarea, una pausa o un avance concreto.
- La broma acompaña la información; nunca sustituye una instrucción, un estado o una consecuencia.
- Evita burlas, culpa, sarcasmo dirigido a la persona y comparaciones con otros.
- No convierte productividad, cuerpo, alimentación o emociones en juicios sobre el valor personal.
- Prefiere ocurrencias breves y naturales. Evita repetir muletillas, remates o estructuras.
- Cada variante debe aportar una idea distinta; cambiar una palabra o un emoji no basta.
- No inventa hechos ni atribuye sentimientos, esfuerzo o resultados que MiSo no conoce.
- La variedad no debe cambiar los nombres de botones, campos ni conceptos del producto.
- Alterna reacciones expresivas, humor cotidiano y mensajes sencillos; no hace falta que cada intervención tenga un remate.
- Admite acotaciones de acción entre asteriscos literales, como `*gritito de celebración*`. Representan al compañero, no emociones atribuidas al usuario. Evita repetir siempre «procede a…».
- El Spanglish y las expresiones de internet son recursos ocasionales. No agregues «chill», «hype» o «win» solo para rejuvenecer una frase. La comprensión de la acción no depende de conocer un meme.
- Usa emojis ocasionales y pertinentes; no acompañan automáticamente cada mensaje.
- Evita el humor que el usuario descartó: «Intenté estar chill, pero completaste todos tus hábitos», «Aaaah, qué gusto», «los créditos todavía no» y frases ambiguas que necesiten explicación. «F por…» no se usa ante pendientes, ausencias o pérdidas de datos.
- La frecuencia, cantidad de frases y mecanismo para evitar repeticiones se definirán al revisar cada superficie. Esta guía no prescribe generación con IA ni una implementación técnica.

## Vocabulario

| Término | Uso |
|---|---|
| Hábito | Práctica que la persona busca sostener |
| Objetivo | Resultado hacia el que quiere avanzar; mantener esta etiqueta en la navegación y las acciones |
| Tarea | Acción concreta que puede organizar y completar |
| Avance | Progreso registrado; no implica que el objetivo esté cumplido |
| Pendiente | Acción aún sin completar; evitar «fracaso» o «deuda» como juicio motivacional |
| Racha | Usar cuando corresponda al cálculo real; explicar sus condiciones mediante ayuda contextual |

Conservar los términos específicos de cada módulo. No alternar sin motivo «objetivo» y «meta» para nombrar la misma entidad.

## Patrones de mensajes

- **Botones:** nombran la acción con un verbo concreto. El humor queda fuera de las acciones esenciales.
- **Etiquetas:** usan nombres estables y comprensibles; no dependen de una frase ingeniosa para entenderse.
- **Ayudas:** explican lo necesario para decidir o completar la tarea.
- **Validaciones:** indican qué falta o qué debe corregirse, sin culpar.
- **Estados vacíos:** explican qué falta y ofrecen un siguiente paso.
- **Éxitos:** confirman lo sucedido; pueden celebrar con humor pertinente.
- **Errores:** explican el problema conocido y cómo continuar. No prometen recuperación o conservación de datos sin garantía.
- **Confirmaciones:** identifican el elemento y las consecuencias relevantes, incluida la pérdida de historial cuando corresponda.
- **Notificaciones:** requieren definir canal, contexto y frecuencia antes de extenderles los patrones de humor de la interfaz.

## Límites por componente

No se establecen límites numéricos universales.

Las acciones y etiquetas priorizan brevedad y reconocimiento. Los errores y confirmaciones conservan la información necesaria para actuar con seguridad.

Los límites editoriales se acuerdan por componente y se comprueban en su contexto. No crean restricciones técnicas sobre los datos del usuario. Si una frase no cabe, se revisan contenido y presentación antes de truncar información esencial.

## Referencias aprobadas de voz

Estas referencias fueron seleccionadas en entrevista. No implican implementación ni constituyen el catálogo completo. El catálogo y sus condiciones de uso se especifican en cada CHG.

| Contexto | Referencia |
|---|---|
| Antes de empezar | «¿Empezamos? Yo ya me emocioné y todavía no hacemos nada.» |
| Hábito completado | «¡Hecho! *gritito de celebración*» |
| Todos los hábitos completos | «¿Todos los hábitos? *no sabe cómo manejar tanto orgullo*» |
| Objetivo completado | «¡COMPLETASTE {goal}! *gritito nada discreto*» |

## Patrones funcionales de referencia

| Contexto | Texto recomendado | Evitar |
|---|---|---|
| Regreso tras una ausencia | «Qué bueno verte. Elige un hábito para retomar hoy.» | «Llevas varios días fallando.» |
| Todos los hábitos del día completados | «¡Todos hechos! La lista ya puede descansar. Tú también.» | «Mañana tienes que superarte.» |
| Primera tarea de un objetivo | «Tu objetivo ya tiene nombre. Dale ahora una primera acción.» | «Sin tareas no llegarás a ninguna parte.» |
| Conflicto al editar el diario | «El diario cambió en otra pantalla. Conserva tu borrador y vuelve a abrirlo para revisar los cambios.» | Un chiste que oculte el conflicto o prometa que el borrador está guardado |
| Eliminación de un hábito y su historial | «Se eliminará “[nombre]” y su historial de completados.» | «¿Mandamos este hábito al espacio?» |

Los textos observados proceden del código local. No se ha verificado su presentación en la aplicación en ejecución.

## Decisiones y lecciones

- 2026-11-11 — Usar español latinoamericano neutro y tratamiento de tú — Confirmado en entrevista.
- 2026-11-11 — Adoptar una voz de entrenador motivador, con energía y celebraciones frecuentes — Confirmado en entrevista.
- 2026-11-11 — Acoger los pendientes y las ausencias sin reproches y ofrecer un próximo paso concreto — Confirmado en entrevista.
- 2026-11-11 — Dar al humor un lugar central, pertinente y con amplia variedad de frases — Confirmado en entrevista.
- 2026-11-11 — Afinar la voz hacia reacciones espontáneas y cómplices del compañero; permitir acotaciones, expresiones de internet, Spanglish y emojis ocasionales — Alcance y actualización aprobados por el usuario; catálogo completo de CHG-090 aprobado posteriormente por el usuario e implementado localmente; ver bitácora del cambio para validación.
- Voz del compañero en el chat (CHG-100, textos en `config/companion_chat.php`): frases cortas, en segunda persona y cálidas; una sola pregunta por mensaje; como mucho un emoji y solo al cerrar algo; nunca presiona ni juzga (sobre todo en ánimo). Secuencia: saludo + pregunta del módulo → confirmación → seguimiento («¿Algo más?» o la siguiente pregunta, p. ej. energía tras ánimo). Los errores dicen qué pasó y que el texto no se perdió.

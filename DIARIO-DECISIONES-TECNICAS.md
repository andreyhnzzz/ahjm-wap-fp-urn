# Diario de Decisiones Técnicas e IA

**Proyecto 5 — Módulo de Reportes y Tablero de Riesgos**
Curso: Programación en Ambiente Web I (ISW-521) · UTN, Sede San Carlos · 2026-II

> **Estado: borrador.** Este documento cubre las sesiones de trabajo asistido por IA
> de las que existe registro directo (optimización de exportación PDF, rendimiento de
> la capa de autorización, limpieza de código y auditoría de cumplimiento). Las
> decisiones de las fases anteriores — diseño de los contextos acotados, modelado del
> dominio, ACL, CRUDs de INFRA-01 — las debe completar el equipo con su propio
> registro, porque son suyas y no están cubiertas aquí.

---

## Cómo se usó la IA en este proyecto

La IA se usó como herramienta de implementación y de diagnóstico, no como fuente de
decisiones de arquitectura. Las decisiones estructurales (Hexagonal + DDD, separación
de contextos, qué vive en dominio y qué en presentación) se tomaron antes y se le
impusieron como restricción: en varias de las sesiones registradas abajo, la
instrucción explícita fue *"sin modificar el stack del proyecto"*, y las propuestas
que lo violaban se rechazaron aunque fueran técnicamente más simples.

El criterio de aceptación que se aplicó de forma consistente fue: **una respuesta se
acepta cuando se puede verificar**. Ninguna afirmación de rendimiento se aceptó sin
medición A/B, y ninguna corrección se aceptó sin una prueba que fallara antes y pasara
después.

---

## D-01 · Tiempo de generación del PDF

**Situación.** La exportación a PDF tardaba alrededor de 2 segundos por documento.
El requisito de RE-01 (30 s) se cumplía con holgura, pero el objetivo interno era
bajar a un rango imperceptible para el usuario.

**Qué se consultó.** Cómo reducir el tiempo de render de Spatie/Browsershot sin
cambiar la librería ni añadir dependencias nuevas.

**Qué se rechazó.**

- Cambiar a otra librería de PDF (DomPDF, wkhtmltopdf). Rechazado: el stack estaba
  fijado y DomPDF no renderiza el CSS del que dependen las plantillas.
- Ajustar únicamente los flags de Chromium. Se probó y se midió: ganancia de ~15%,
  insuficiente. El diagnóstico correcto era que el costo dominante no estaba en
  Chromium sino en el arranque del proceso Node por cada petición.

**Qué se aceptó.** Un proceso auxiliar de vida larga que mantiene una instancia de
Chromium ya arrancada, con la aplicación hablándole por HTTP local. La condición que
se le impuso a la solución fue que **no puede ser una dependencia dura**: si el proceso
no está corriendo, la exportación debe seguir funcionando por la ruta original. Por eso
el cliente devuelve `null` ante fallo de conexión y el llamador cae a Browsershot.

**Resultado medido.** Mediana de ~45 ms sobre 10 ejecuciones, frente a ~2 s. La
verificación quedó como script ejecutable que falla si la mediana supera el
presupuesto, no como una nota en el README.

**Aprendizaje.** El instinto inicial —tanto propio como de la IA— fue optimizar la
parte visible (Chromium y sus flags). La medición mostró que el costo real estaba en
una capa que ni siquiera se había considerado. La lección concreta: perfilar antes de
optimizar no es un consejo genérico, es lo que separa un 15% de un 97%.

---

## D-02 · Un error de la IA: la optimización que no se midió a sí misma

**Qué pasó.** Al medir la primera versión del proceso auxiliar, el resultado fue una
mediana de 422 ms — muy por encima del objetivo. La IA había asumido que mantener
Chromium caliente bastaba.

**Cómo se detectó.** El script de verificación falló su propia aserción. No se
descubrió leyendo el código, sino porque la comprobación estaba escrita para fallar.

**La corrección.** Al perfilar por separado las dos fases (cargar el HTML vs. imprimir
el PDF) resultó que la carga costaba ~7 ms y la impresión ~180 ms. El modo headless
moderno de Chrome es considerablemente más lento imprimiendo que el binario headless
clásico; cambiar a este último bajó esa fase a ~25 ms.

**Por qué importa.** Es el caso más claro del proyecto de una respuesta de IA que era
*plausible y estaba incompleta*. Ninguna revisión de código lo habría detectado: el
código era correcto, simplemente no cumplía el objetivo. Solo una medición lo expuso.

---

## D-03 · Consultas de permisos: el N+1 que no se veía

**Situación.** Cada pantalla ejecuta del orden de diez comprobaciones de permiso
(una por entrada del menú lateral, más las de cada acción de fila).

**Qué se encontró.** La comprobación resolvía las relaciones de forma perezosa: una
consulta por cada rol del usuario, más un recorrido de colecciones anidadas en cada
llamada.

**Qué se aceptó.** Cargar ambas relaciones en un solo paso y aplanarlas a una
estructura de búsqueda directa, memorizada por petición.

**Verificación A/B.** Revirtiendo únicamente el archivo modificado y volviendo a medir:

| Roles del usuario | Antes | Después |
|---|---|---|
| 9 roles | 11 consultas | 3 |
| 4 roles | 6 consultas | 3 |

El costo pasó de crecer con el número de roles a ser constante.

**Decisión adicional.** Se eliminaron siete métodos públicos del mismo componente que
no tenían ni una sola llamada en todo el repositorio. Eran una API construida "por si
acaso", imitando la de una librería externa. Se documentó la eliminación de forma
explícita para que fuera una decisión revisable, no un borrado silencioso.

---

## D-04 · Un segundo error: la prueba que no probaba nada

**Qué pasó.** Para proteger la optimización de D-03 se escribió una prueba que afirma
que las comprobaciones de permisos no deben superar tres consultas. La prueba pasó.

**El problema.** También pasaba con la implementación **anterior**, la que tenía el
N+1. Una prueba que pasa en ambos lados no protege nada.

**La causa.** En el escenario de prueba, todos los roles tenían todos los permisos.
La implementación antigua encontraba el permiso en el primer rol y cortaba ahí, así
que el N+1 nunca llegaba a manifestarse.

**La corrección.** Se rediseñó el escenario con conjuntos de permisos **disjuntos** por
rol, y se añadió la consulta de un permiso que el usuario **no** tiene — el peor caso
real, porque solo puede responderse después de revisar todos los roles. Con eso, la
prueba falla con 11 consultas en la implementación antigua y pasa con 3 en la nueva.

**Aprendizaje.** Una prueba verde no es evidencia de nada hasta que se comprueba que
sabe ponerse roja. Verificar una prueba de regresión contra el código que supuestamente
detecta debería ser el paso por defecto, no una precaución extra.

---

## D-05 · Excepciones como flujo de control

**Situación.** Al intentar renombrar o eliminar un rol del sistema, la pantalla
capturaba una excepción de dominio para mostrar un aviso al usuario.

**El razonamiento.** Que un usuario haga clic en "eliminar" sobre un rol protegido no
es una condición excepcional: es un resultado esperado de una interacción normal. Usar
el mecanismo de excepciones para eso confunde "error del programa" con "respuesta del
sistema".

**Qué se hizo.** La pantalla consulta la condición por adelantado y responde con el
aviso. El caso de uso **conserva** su excepción, porque sigue siendo el guardián del
invariante para cualquier otro llamador que no sea esa pantalla.

**Qué NO se hizo.** La instrucción de trabajo era "evitar try-catch hasta donde sea
posible". Se rechazó aplicarla a los cinco bloques restantes: token JWT malformado,
API externa de feriados, arranque de Chromium y conexión al proceso auxiliar. Todos son
fronteras con sistemas externos donde el fallo no es previsible desde el código. Una
regla de estilo no se aplica donde empeora la robustez.

---

## D-06 · Código en inglés, interfaz en español

**Situación.** Había etiquetas en español escritas directamente dentro de una clase PHP,
y comentarios en español en ocho componentes.

**La decisión.** El código (identificadores, comentarios) pasa a inglés; la interfaz
sigue en español, pero a través del sistema de traducciones, no incrustada en clases.
Cinco de las claves necesarias ya existían en el archivo de traducción y se reutilizaron
en lugar de duplicarlas.

**Verificación.** Se comprobó en el navegador que las etiquetas siguen apareciendo en
español, y se añadió una prueba que fija ese comportamiento para que un cambio futuro
no lo rompa en silencio.

---

## D-07 · Un hallazgo que se decidió NO arreglar

Durante la limpieza se observó que, tras una operación que retorna temprano
(validación fallida, por ejemplo), la tabla de fondo puede quedar vacía hasta recargar.

**Qué se hizo.** Se verificó si era una regresión introducida en esa misma sesión
—revirtiendo el archivo y volviendo a probar— y se confirmó que **no**: proviene de una
optimización anterior que evita reenviar las filas en cada re-render.

**La decisión.** Reportarlo sin tocarlo. Estaba fuera del alcance solicitado y
arreglarlo requiere revisar el contrato entre el componente y la capa de interactividad
del cliente, no un parche puntual. Se dejó documentado para decidirlo con criterio, no
a mitad de otra tarea.

**Por qué se registra.** Distinguir "esto lo rompí yo" de "esto ya estaba" antes de
afirmar cualquiera de las dos cosas es parte del trabajo. La comprobación costó dos
minutos y evitó atribuirse un error ajeno o, peor, ocultar uno propio.

---

## D-08 · Auditoría final contra el enunciado

Se contrastó el código contra los criterios de aceptación de INFRA-01, RE-01, RE-02 y
RE-04, y contra los requisitos técnicos transversales.

**Resultado.** Los cuatro requerimientos cumplen sus criterios. La verificación con
más valor fue la de la separación de capas: cero importaciones de clases del framework
en las capas de dominio y aplicación, comprobado por búsqueda directa y no por
inspección visual.

**Dos hallazgos honestos:**

1. La leyenda obligatoria de RE-02 está implementada con la técnica correcta para que
   se repita en todas las páginas impresas, **pero** ningún docente del conjunto de
   datos tiene suficientes grupos para generar un PDF de más de una página. El criterio
   se cumple por diseño y no por observación. Queda pendiente forzar un caso multipágina
   antes de la defensa.
2. Un script de prueba usado durante el desarrollo escribió sobre códigos de grupo que
   ya usaba el sembrador, desviando uno de los escenarios diseñados. El código del
   sembrador quedó intacto; la base de datos local se restaura resembrando.

**Aprendizaje.** Ambos hallazgos son del mismo tipo: la diferencia entre "el código lo
hace" y "se comprobó que lo hace". Un criterio de aceptación que nunca se ejecutó no
está verificado, por correcto que se vea el código.

---

## D-09 · El coste que nadie estaba buscando: el PDF etiquetado

**Situación.** Con el sidecar ya funcionando, el objetivo pasó a ser volumen:
2 500 filas en menos de 7 s desde un arranque limpio, y llegar a 10 000 para
conocer el techo. La línea base medida fue 5,0 s para 2 500 filas y 24,9 s para
10 000 — escalando peor que lineal.

**Qué se probó primero, y por qué estaba mal.** Lo evidente: el CSS. Se midieron,
una a una y acumuladas sobre el HTML real, la eliminación del `overflow: hidden` y
el `border-radius` de la tarjeta (el mismo tipo de problema que la sombra que ya se
había quitado antes), el rayado `nth-child`, los bordes por celda y la fuente web
en la tabla. **Todas quedaron dentro del ruido de medición.** También se probó
partir la tabla en tablas pequeñas, por si el coste era la fragmentación de Chrome:
no lo era. Y se probó ensanchar las columnas, que bajó el documento de 207 a 94
páginas con las mismas filas y mejoró sólo un 19 % — la señal de que el coste iba
con las celdas, no con las páginas.

**Cómo apareció la causa real.** En vez de seguir probando hipótesis, se abrió el
PDF y se hizo un inventario de sus objetos. De 8,9 MB, **7,9 MB eran 52 519 objetos
`/StructElem`** — el árbol de accesibilidad PDF/UA que Chrome emite por defecto,
uno por cada `<td>`, más 1 MB de tabla `xref` que sólo existe para indexarlos.

| Filas | Etiquetado | Sin etiquetar | Tamaño |
|---|---|---|---|
| 2 500 | 2,66 s | 1,96 s | 8,9 MB → 0,8 MB |
| 10 000 | 18,33 s | 10,93 s | 36,0 MB → 3,2 MB |

**Qué se aceptó.** Apagarlo por configuración (`PDF_TAGGED`, por defecto `false`),
en las dos rutas de render. Browsershot tiene `taggedPdf()` para encenderlo y nada
para apagarlo, y el valor por defecto de Puppeteer es encendido: hubo que pasarlo
por `setOption('tagged', …)`, que `browser.cjs` reenvía tal cual a `page.pdf()`.

**El intercambio, dicho en voz alta.** Lo que se pierde es la semántica de tabla
para lectores de pantalla. Se consideró aceptable porque RE-01 genera el `.xlsx`
con las mismas filas en la misma acción, y una hoja de cálculo es mejor superficie
asistiva para datos tabulares que un PDF etiquetado. El texto sigue siendo texto
real. La decisión es reversible con una variable de entorno, no con un cambio de
código.

**Aprendizaje.** Es la segunda vez en este proyecto (D-01 fue la primera) que el
instinto —propio y de la IA— apuntó a la parte visible, y la medición encontró el
coste en una capa que nadie había mirado. La diferencia esta vez es el método: lo
que resolvió el problema no fue probar otra hipótesis, fue **inspeccionar el
artefacto producido** en lugar de razonar sobre el código que lo produce.

---

## D-10 · Un tercer error, y esta vez propio: medir una vez no es medir

**Qué pasó.** Al reescribir el sidecar se cambió la página de Chrome reutilizada
por una nueva en cada petición, porque la reutilizada moría con
`Protocol error (Page.printToPDF): Printing failed` tras varios reportes grandes.
Una medición dio que la página nueva costaba ~770 ms más, así que se implementó un
esquema de "reutilizar y reciclar cada N filas" para quedarse con las dos cosas.

**Por qué estaba mal.** El esquema introdujo su propio bug —un primer presupuesto
de 40 000 filas era demasiado laxo y degradaba— y, al volver a medir las dos
estrategias **la una contra la otra en el mismo estado de máquina**, el ahorro de
770 ms no reapareció: a 2 500 filas eran indistinguibles (1,5–3,1 s en ambas). Esa
máquina tiene casi 2× de dispersión entre ejecuciones idénticas, y el "770 ms" era
exactamente eso.

**La corrección.** Página nueva siempre. Sin presupuesto, sin variable que ajustar.
La comparación decisiva, back-to-back, a 10 000 filas: reutilizada → 14,4 s, 20,5 s,
**muere**; nueva → 17,5 s, 14,8 s, 13,4 s y sigue. No había velocidad reproducible
que ganar y sí una forma reproducible de fallar.

**Coste asumido y declarado.** La página nueva añade ~60 ms a cada render pequeño.
El auto-chequeo del sidecar pasó de ~45 ms a ~105 ms de mediana, así que su
presupuesto se subió de 140 ms a 200 ms — con el motivo escrito en el propio
archivo, para que sea un intercambio documentado y no un umbral aflojado hasta que
la prueba pasara.

**Otros dos errores propios de la misma sesión, por el mismo motivo.** El primer
barrido para encontrar el techo declaró "NOT A PDF" todos los archivos válidos:
`page.pdf()` devuelve un `Uint8Array` cuyo `toString()` es `"37,80,68,70,45"`, no
`"%PDF-"`. Y el primer arranque automático del sidecar colgaba cualquier proceso
que leyera la salida de PHP: en Windows, `start /B`, `start` en consola nueva y
`proc_open` con NUL en los tres flujos **heredan igualmente los descriptores**, así
que el sidecar se quedaba con el `stdout` de PHP y nadie veía nunca el EOF. Los
cinco lanzadores obvios se midieron; el único que suelta el pipe y deja el proceso
vivo es `Start-Process` de PowerShell.

**Aprendizaje.** D-02 y D-04 fueron sobre no fiarse de una respuesta sin medirla.
Éste es el escalón siguiente: **no fiarse de una medición sin repetirla contra su
alternativa**. Una cifra aislada no distingue una mejora de la varianza de la
máquina.

---

## D-11 · Dónde se rompe, y por qué eso se escribe en el código

**Qué se midió.** Barrido de tamaños contra la plantilla real: 15 000 filas
renderizan (22,8 s, 1 155 páginas, PDF válido); **16 000 no** — el proceso de
impresión de Chrome muere. No hay degradación intermedia ni salida parcial.

**El problema operativo.** Sin guarda, ese fallo además es lento y doble: el
sidecar muere, el cliente cae a Browsershot, y Browsershot pasa otro minuto
fallando con el documento idéntico. Dos minutos para un error genérico.

**Qué se hizo.** `config('exports.pdf.max_rows')` (12 000 por defecto) y una
comprobación en el job **antes** de tocar Chrome, que falla al instante con el
motivo. El margen entre 15 000 medidas y 12 000 configuradas es deliberado y está
justificado en el archivo: el límite real es contenido total, no número de filas, y
un reporte con celdas más largas llega antes a la misma pared.

**Qué protege la prueba.** `PdfExportLimitsTest` fija las dos garantías invisibles
—que a Chrome se le pide no etiquetar, y que un reporte pasado del techo se rechaza
sin llegar al render— y se comprobó que **saben ponerse en rojo**: quitando la
línea `setOption('tagged', …)` fallan dos pruebas, y quitando la llamada a la
guarda falla la tercera. La primera vez que se intentó esa comprobación el parche
no llegó a aplicarse y la prueba pasó igual: es literalmente D-04 otra vez, y se
detectó porque el conteo posterior al parche no dio cero.

---

## Balance del uso de IA

**Dónde aportó valor real.** Diagnóstico de rendimiento con medición, exploración
rápida de un código base grande, y detección de patrones (el N+1, las excepciones como
flujo de control) que estaban repartidos en varios archivos.

**Dónde falló.** Dos veces, ambas documentadas arriba (D-02 y D-04), y las dos del
mismo tipo: código correcto que no cumplía su objetivo. Ninguna se habría detectado
leyendo el código; las dos se detectaron midiendo.

**Qué se aprendió sobre la herramienta.** La IA es fiable produciendo código que
funciona y poco fiable juzgando si ese código resuelve el problema. Es rápida generando
verificaciones, y es capaz de generar una verificación que no verifica nada — como en
D-04. El criterio que hubo que aportar no fue sintáctico ni de arquitectura: fue
insistir en que toda afirmación viniera con una medición reproducible, y comprobar que
las pruebas supieran fallar.

---

*Documento sujeto a revisión y ampliación por el equipo con las fases no cubiertas aquí.*

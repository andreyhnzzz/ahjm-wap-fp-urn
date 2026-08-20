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

## D-09 · Comparación de motores de PDF: por qué no reemplazar Chromium

**Situación.** La exportación de tablas grandes (Grupos, 1500+ filas) tardaba
~15-20 s con Spatie/Browsershot, que depende de Chromium — un proceso pesado en
memoria. Se pidió evaluar si una librería PHP nativa (sin navegador) podía
igualar la fidelidad visual actual con mejor rendimiento.

**Qué se consultó.** Un spike reproducible (`php artisan spike:pdf-compare`)
que renderiza el HTML **real** de producción — capturado desde el propio
`exportPdf()` de los componentes, no una plantilla de prueba aparte — a través
de mPDF, Dompdf y Spatie, midiendo tiempo y memoria en subprocesos aislados
para que un fallo de un motor no arrastrara la medición de los demás.

**Qué se rechazó.**

- **mPDF.** Genera 238 páginas en blanco tanto con 2 filas como con 1541 —
  un defecto real de paginación con esta plantilla (se probó remover el
  `position: absolute` decorativo y todo `overflow: hidden`; el fallo
  persistió). Además, 161.6 s en el caso grande: 8-9× más lento que la ruta
  actual, no solo visualmente roto.
- **Dompdf.** Con pocas filas el resultado es aceptable y el más rápido de
  los tres (173 ms), pero con huecos reales: el logo AVIF no decodifica, el
  header con `flex` se apila porque no implementa flexbox. A 1541 filas se
  queda sin memoria a los 111 s **incluso con 1024 MB** (8× el límite por
  defecto de PHP-FPM) y nunca termina.

**Qué se aceptó.** Mantener Spatie/Browsershot. Es el único de los tres que
reproduce la plantilla sin diferencias en ambos tamaños, y a escala real
sigue siendo el más rápido de los que efectivamente terminan (19.3 s frente a
161.6 s de mPDF). El diagnóstico de "Chromium es pesado" era cierto para el
arranque en frío, pero el proyecto ya lo había resuelto antes (D-01): el
problema real no estaba en qué motor usar.

**Aprendizaje.** La pregunta "¿hay una librería más liviana?" tenía una
respuesta medible y era no — pero solo se pudo afirmar eso después de
reproducir el fallo de cada alternativa con datos reales, no con la tabla de
ejemplo de cada librería.

---

## D-10 · De petición bloqueante a cola: desacoplar el render de la respuesta HTTP

**Situación.** Aun con el motor correcto, exportar 1500+ filas seguía
tardando ~15-20 s **dentro** de la petición HTTP — bloqueando un worker de
PHP-FPM por ese tiempo. Bajo carga concurrente (varios usuarios exportando a
la vez) eso agota los workers disponibles y tumba el sitio para todos, no
solo para quien exporta.

**Qué se aceptó.** Mover el renderizado a un job en cola
(`GenerateReportExportJob`), con una tabla `report_exports` que trackea el
estado (pendiente/procesando/listo/fallido). El componente Livewire encola y
responde de inmediato; la UI hace polling ligero (`wire:poll`) y dispara la
descarga automática cuando el estado pasa a "listo".

**Resultado medido.**

| Métrica | Antes (síncrono) | Después (cola) |
|---|---|---|
| Respuesta HTTP al usuario | 15-20 s (bloqueaba) | ~200-300 ms |
| Render en segundo plano | — | Sin cambio, pero ya no bloquea nada |

El tiempo de render no bajó con este cambio — bajó con D-12. Lo que cambió
acá es **a quién le pertenece la espera**: ya no es del navegador ni del
worker de PHP-FPM, es del job en segundo plano.

**Aprendizaje.** Dos problemas que se sentían como uno solo ("la exportación
es lenta") eran en realidad independientes: uno de arquitectura (qué bloquea
a quién) y uno de rendimiento puro (cuánto tarda Chrome). Resolver el primero
sin el segundo ya habría sido una mejora real — la disponibilidad del sitio
ya no depende del tamaño del reporte que alguien esté exportando.

---

## D-11 · Efecto colateral no anticipado: la cola rompió la herramienta de diagnóstico

**Qué pasó.** El spike de D-09 capturaba el HTML real interceptando la
interfaz `PdfExporterInterface` que `exportPdf()` llamaba de forma directa y
síncrona. Al migrar `exportPdf()` a la cola (D-10), esa interfaz dejó de
invocarse en el mismo hilo — la llamada capturada por el spike nunca llegaba
a ejecutarse, y la herramienta fallaba con un error genérico en vez de
producir HTML.

**Cómo se detectó.** Se volvió a correr el spike al retomar la optimización
de rendimiento (D-12) y falló de inmediato, antes de medir nada.

**La corrección.** El spike pasó a usar `Queue::fake()` y a capturar la
instancia del job efectivamente encolado (`Queue::assertPushed(...)`), leyendo
su `title`/`headers`/`rows` para renderizar la misma vista que el job real
renderiza. Sigue sin duplicar lógica de negocio — solo cambió el punto de
intercepción, del puerto síncrono al job asíncrono.

**Aprendizaje.** Una herramienta de diagnóstico que depende de *cómo* se
implementa una ruta (no solo de *qué* hace) queda expuesta a cualquier
refactor de esa ruta. No se detectó por revisión de código al hacer D-10 —
se detectó porque la siguiente sesión de trabajo la volvió a ejecutar y
falló. Vale la pena correr las herramientas de verificación existentes antes
de asumir que un cambio fue completo.

---

## D-12 · Una sola regla CSS explicaba el 89 % del tiempo de render

**Situación.** Con la arquitectura de colas ya en su lugar (D-10), se pidió
llevar el tiempo de render de 2541 filas a 11 s o menos. La medición previa
(D-09) daba ~20 s para 1541 filas — insuficiente margen.

**Qué se consultó.** Perfilado dirigido: se tomó el HTML real de producción
(vía el job capturado, ver D-11) y se envió al sidecar de Chrome con
variantes puntuales — sin `box-shadow`, sin `border-radius`/`overflow`, sin
el gradiente decorativo — para aislar cuál regla CSS explicaba el costo,
en vez de adivinar u optimizar a ciegas.

**Qué se encontró.** La variante sin `box-shadow` sola bajó el tiempo de
29.87 s a 3.40 s. El motor de impresión de Chrome rasteriza la sombra de una
caja fragmentada en **cada página** que esa caja atraviesa — con ~200
páginas para 2541 filas, una sola declaración CSS se pagaba doscientas veces.

**Qué se aceptó.** Quitar el `box-shadow` de `.table-card`. A 8 % de opacidad
era casi invisible impreso, y el borde de 1px ya delimita la tarjeta — el
costo no compraba nada visualmente.

**Hallazgo adicional en el camino.** El perfilado también expuso que
`page.pdf()` de Puppeteer usa un timeout por defecto de 30 s: un reporte que
rondaba ese límite fallaba con un 500 intermitente en vez de terminar lento.
Se corrigió con un timeout explícito de 60 s en el sidecar, documentando por
qué ese número (el límite de 30 s en el lado PHP igual actúa como tope
efectivo).

**Resultado medido.** Render: 29.9 s → 3.2-3.6 s (3 corridas). Extremo a
extremo (clic → archivo listo, incluyendo el job en cola): 3.8-6.6 s. Tamaño
del PDF: 18.7 MB → 9.4 MB, sin cambio visual verificado página por página
(inicio, intermedio y final del documento de ~200 páginas).

**Aprendizaje.** Es el mismo patrón que D-01: el instinto es optimizar lo
que se ve grande (arquitectura, motor, cantidad de datos) cuando el costo
real puede estar en un detalle decorativo de una sola línea. La diferencia
con D-01 es que acá el perfilado fue dirigido por hipótesis explícitas
(cuatro variantes CSS probadas por separado) en vez de perfilado por fases —
ambas técnicas llegaron al mismo tipo de hallazgo por caminos distintos.

---

## Balance del uso de IA

**Dónde aportó valor real.** Diagnóstico de rendimiento con medición, exploración
rápida de un código base grande, y detección de patrones (el N+1, las excepciones como
flujo de control) que estaban repartidos en varios archivos. La ronda de D-09 a D-12
sumó otro tipo de valor: comparar alternativas reales antes de descartarlas (D-09, tres
motores de PDF probados contra el HTML de producción, no contra un ejemplo de manual) y
aislar una causa raíz de un solo detalle CSS entre varios candidatos plausibles (D-12)
en vez de optimizar por instinto.

**Dónde falló.** Tres veces documentadas arriba (D-02, D-04, D-11), y las tres del mismo
tipo de fondo: una solución quedó incompleta porque nadie —ni la IA, ni una revisión de
código— la puso a prueba contra el escenario que la habría expuesto. D-02 y D-04 son
código que no cumplía su objetivo; D-11 es una herramienta de verificación que un
refactor posterior dejó de proteger, y que solo se notó al volver a ejecutarla. Ninguna
de las tres se habría visto leyendo el diff.

**Qué se aprendió sobre la herramienta.** La IA es fiable produciendo código que
funciona y poco fiable juzgando si ese código resuelve el problema. Es rápida generando
verificaciones, y es capaz de generar una verificación que no verifica nada — como en
D-04 — o que deja de correr sin que nadie lo note — como en D-11. El criterio que hubo
que aportar no fue sintáctico ni de arquitectura: fue insistir en que toda afirmación
viniera con una medición reproducible, comprobar que las pruebas supieran fallar, y
volver a correr las herramientas de diagnóstico existentes antes de asumir que un
cambio anterior las dejó intactas.

---

---

## D-13 · 45.000 filas: cuando el problema deja de ser la velocidad

**Situación.** Se pidió que la exportación a PDF aguantara 45.000 filas en 23 s o
menos. El punto de partida medido era 2.541 filas en ~3,4 s tras D-12, así que la
pregunta parecía de optimización.

**Qué se consultó.** Dónde se va el tiempo a esa escala, separando etapas
—consulta, construcción del HTML y render de Chromium— con un comando nuevo
(`php artisan bench:pdf`) que mide las tres por separado.

**Qué se rechazó, y por qué el diagnóstico inicial estaba mal.** La primera
hipótesis fue optimizar CSS, y se midió antes de aplicarla: quitar el gradiente de
puntos, el rayado `nth-child` y el borde redondeado de la tarjeta llevó 5.000 filas
de 11,5 s a 10,6 s. Un 8 %. Se descartó como línea principal.

La medición real fue otra:

| filas | printToPDF | ms/fila |
|-------|-----------|---------|
| 1.000 | 1,47 s | 1,47 |
| 5.000 | 6,60 s | 1,32 |
| 15.000 | 49,55 s | 3,30 |
| 45.000 | **falla** | — |

A 45.000 Chromium no tarda: devuelve `Protocol error (Page.printToPDF): Printing
failed`. **El objetivo no era inalcanzable por lento, era inalcanzable por
imposible**, y ninguna cantidad de CSS lo cambiaba.

**Qué se aceptó.** Partir el informe en documentos de ~1.500 filas, renderizarlos
en paralelo contra el sidecar y unirlos con mPDF —que ya estaba instalado desde el
spike D-09 que lo *rechazó* como renderizador, así que la unión no costó ninguna
dependencia nueva—. Medido: 13,8-17,9 s en ocho corridas consecutivas, con un PDF
final de 14,7 MB y 3.145 páginas.

**Un error propio, detectado midiendo.** Se asumió que las fuentes embebidas en
base64 eran el cuello, porque se re-parsean en cada trozo. Antes de extraerlas a
archivo se contaron: 96 KB por documento. No eran el cuello. El cuello real estaba
en el sidecar, que atendía las peticiones **en serie** —un comentario del propio
código lo había predicho: *"swap for a small page pool if concurrent exports ever
queue up noticeably"*—. Con un pool de páginas, 63 s pasaron a 31 s. Haber
"arreglado" las fuentes habría costado trabajo y fidelidad visual para no ganar
nada.

**Un error introducido, y cómo se detectó.** Trocear rompió la numeración de filas:
cada trozo es su propio render de Blade, así que `$loop->iteration` reiniciaba en 1
cada 1.500 filas. No lo detectó ninguna prueba, sino abrir el PDF y leer las
páginas del corte (la 108 terminaba en 1.500 y la 109 empezaba en 1). Se corrigió
con un desplazamiento explícito y quedó fijado en `ChunkedPdfWriterTest`.

**Lo que queda abierto.** En dos ocasiones, siempre en la corrida siguiente a
reiniciar el sidecar, el total se disparó a 38 s y 58 s. En régimen estable (ocho
corridas seguidas sin reinicio) no reaparece. Se mitigó calentando las páginas al
arrancar y vaciando el DOM al soltarlas, pero la causa raíz no está confirmada y
se deja anotada en lugar de darla por cerrada.

**Aprendizaje.** Dos veces en la misma tarea, la respuesta plausible y la correcta
no coincidieron: el CSS parecía el problema y valía un 8 %; las fuentes parecían el
cuello y pesaban 96 KB. Lo que las separó no fue análisis, fue medir cada una por
separado antes de tocarla. Y un PDF sigue sin poder validarse leyendo código: el
bug de numeración solo existía a la vista.

---

## D-14 · Autocompletado: por qué el filtrado se quedó en el servidor

**Situación.** Docente y aula se elegían con un `<select>` que renderizaba el
catálogo completo. Con 65 docentes es tolerable; el proyecto ya maneja tablas de
decenas de miles de filas y ese patrón no acompaña.

**Qué se consultó.** Cómo hacer un autocompletado sin salirse del stack ni
convertir la pantalla en una SPA.

**Qué se rechazó.** Filtrar en el navegador con Alpine sobre la lista completa. Es
lo más simple de escribir y traslada el problema: el payload sigue siendo
proporcional al catálogo, no a lo que se muestra. También se rechazó exponer un
endpoint de búsqueda: obligaría a re-autorizar en cada pulsación, mientras que
filtrar una lista que el propio caso de uso y su Policy ya aprobaron no puede
ampliar lo que el usuario ve.

**Qué se aceptó.** `InteractsWithAutocomplete` filtra en PHP y devuelve como mucho
ocho sugerencias; Alpine solo abre, cierra y mueve el resaltado. El componente
`<x-ui.autocomplete>` es reutilizable y ya sirve a dos pantallas (grupos y carga
docente).

**Un detalle que no es cosmético.** La comparación ignora acentos y mayúsculas. Los
datos son nombres en español: quien escribe "nunez" busca "Núñez", y no encontrarlo
se lee como que la función está rota, no como una lección de ortografía. Está
cubierto en `AutocompleteTest`.

**Aprendizaje.** La decisión de dónde filtrar parecía de rendimiento y terminó
siendo de autorización: el argumento más fuerte para dejarlo en el servidor no fue
el tamaño del payload sino que la lista ya venía autorizada.

---

---

## D-15 · Un comentario que afirmaba lo contrario de lo que hacía el código

**Situación.** Al validar el autocompletado en el navegador —no leyendo el
código, mirándolo— la tabla de grupos se vaciaba sola mientras se escribía:
pasaba a «Mostrando 0» y no volvía hasta recargar la página.

**La primera hipótesis, y por qué era falsa.** Se atribuyó a la lista de
sugerencias: un `@forelse` sin `wire:key` por elemento, que es el error clásico
de Livewire cuando el número de hijos cambia. Se añadieron las claves. **El bug
siguió igual.** Añadir la corrección plausible sin comprobar que era la causa
habría dejado el problema vivo y el diagnóstico cerrado.

**Lo que reveló medir.** Desde la consola del navegador, con la tabla cargada,
se llamó a un método que no toca nada:

```js
await w.call('pollExportStatus');   // 2541 -> 0
await w.call('openCreateModal');    // 2541 -> 0
```

Cualquier ida y vuelta a Livewire vaciaba la tabla, incluso una anterior a
todo este trabajo. **El autocompletado no causaba el fallo: lo hacía visible**,
porque dispara una petición por pulsación en vez de una por clic ocasional.

**La causa.** `data-table.ts` documentaba la premisa en un comentario:

> *"Livewire/Alpine's DOM morph deliberately preserves this component's
> existing reactive state across re-renders — which means a fresh
> `x-data="crudTable(...)"` attribute in newly-rendered HTML is never re-read
> after the first init."*

En esta versión **sí se relee**. Y como el modo cliente manda las filas una vez
y después `[]` (`isFirstRender`), cada render posterior reinicializaba el
componente con el array vacío. El comentario no describía el comportamiento:
describía la suposición sobre la que se escribió el código.

**Qué se aceptó.** Sacar las filas del atributo volátil: van en un
`<script type="application/json" wire:ignore>` que el morph no toca, y `x-data`
deja de cambiar entre renders. El payload es el mismo, el evento
`data-table-refresh` sigue igual, y afecta por igual a las cinco pantallas que
comparten el componente. Verificado en grupos (2541 filas) y docentes (65)
sobreviviendo a método, modal, escritura y selección.

**Aprendizaje.** Un comentario que explica *por qué* algo funciona es tan
falsable como el código, y envejece peor: nadie lo vuelve a ejecutar. Este
llevaba tiempo siendo falso y describía el sistema al revés. Y la propia
sesión repitió el error de D-13 en pequeño: la hipótesis plausible —las claves
faltantes— se aplicó antes de comprobarla, y no era.

---

*Documento sujeto a revisión y ampliación por el equipo con las fases no cubiertas aquí.*

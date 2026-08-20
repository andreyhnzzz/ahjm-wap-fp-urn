# 🎓 SIGA UTN — Módulo de Reportes y Tablero de Riesgos

![PHP](https://img.shields.io/badge/PHP-8.4%2B-777BB4?style=flat-square&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=flat-square&logo=laravel&logoColor=white)
![Livewire](https://img.shields.io/badge/Livewire-4-4E56A6?style=flat-square&logo=livewire&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white)
![Alpine.js](https://img.shields.io/badge/Alpine.js-TALL_stack-8BC0D0?style=flat-square&logo=alpinedotjs&logoColor=white)
![SQLite](https://img.shields.io/badge/SQLite-embedded-003B57?style=flat-square&logo=sqlite&logoColor=white)
![Architecture](https://img.shields.io/badge/architecture-DDD%20%2F%20Hexagonal-0f2547?style=flat-square)
![Tests](https://img.shields.io/badge/tests-126%20passing-2EA44F?style=flat-square&logo=checkmarx&logoColor=white)
![PHPStan](https://img.shields.io/badge/PHPStan-level%207-8B5CF6?style=flat-square&logo=php&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-3178C6?style=flat-square)

Analítica ejecutiva de la oferta académica para la Sede Regional San Carlos de la
Universidad Técnica Nacional. Implementa los requerimientos **INFRA-01**, **RE-01**,
**RE-02** y **RE-04** sobre el sistema SIGA existente (TALL stack + Arquitectura
Hexagonal con DDD).

---

## 1. 📋 Requisitos

| Herramienta | Versión mínima | Por qué |
|---|---|---|
| PHP | **8.4** | Lo exigen `laravel/framework ^13` y `symfony/* ^8.1` |
| Composer | 2.x | Dependencias PHP |
| Node.js | 20+ | Vite (assets) y Puppeteer (PDF) |
| Chrome/Chromium | — | Lo instala `npm install` vía Puppeteer; sólo hace falta para los PDF |

La base de datos por defecto es **SQLite**: no hay servidor que instalar ni
configurar. El archivo vive en `database/database.sqlite`.

## 2. ⚙️ Instalación

```bash
composer install
```

```bash
npm install
```

Los PDF se renderizan con Chromium a través de Puppeteer. Si `npm install` no
descargó el binario (la caché `~/.cache/puppeteer` queda vacía), hay que pedirlo
explícitamente una sola vez:

```bash
npx puppeteer browsers install chrome-headless-shell
```

```bash
php artisan migrate:fresh --seed
```

```bash
npm run build
```

Levantar el sistema:

```bash
composer run dev
```

Queda en `http://localhost:8000`. El `.env` ya viene listo (clave de aplicación
incluida); si se parte de cero, `cp .env.example .env` y luego
`php artisan key:generate`.

La API JSON de `routes/api.php` firma sus tokens con un secreto **aparte** de
`APP_KEY` (ver `config/jwt.php`), y `.env.example` lo trae vacío a propósito.
Partiendo de cero hay que generarlo, o `/api/login` responde 500:

```bash
php -r "echo 'JWT_SECRET='.bin2hex(random_bytes(32)).PHP_EOL;" >> .env
```

La suite no depende de eso: `phpunit.xml` fija su propio `JWT_SECRET` de prueba,
así que los tests dan igual con `.env` o sin él.

### 🔑 Usuarios sembrados

| Correo | Contraseña | Rol |
|---|---|---|
| `prueba@gmail.com` | `12345678` | Superadmin |
| `admin@gmail.com` | `12345678` | Admin |

## 3. 🌱 Qué queda cargado al sembrar

`AcademicDataSeeder` es **determinista** (sin `faker`): dos ejecuciones producen
exactamente el mismo tablero, de modo que una regresión en las reglas de riesgo se
ve como un número distinto y no se confunde con "datos aleatorios diferentes".

- **15 docentes**, **10 aulas**, **41 grupos** escritos a mano (33 en `2026-II`,
  8 en `2026-I`) — son los que hacen observable cada regla, y los únicos que
  importan para leer el tablero.
- Encima, **19 959 grupos de volumen** (`BULK-####`) y **50 docentes** propios
  (`BULK-####`), hasta **20 000 grupos** en total. Existen para probar paginación
  y reportes con carga real; usan su propia bolsa de docentes para no tocar
  ninguna jornada de la que dependan RE-02 y RE-04. Se escriben con un `upsert()`
  por lotes: sembrar los 20 000 tarda **1,8 s**, no los 94 s que costaba el
  `updateOrCreate()` por fila.
- Para bajar el volumen (o subirlo), `AcademicDataSeeder::BULK_GROUP_COUNT`.
  El seeder es idempotente: correrlo dos veces deja 20 000 filas, no 40 000.
- Al menos un caso de **cada** riesgo de RE-04 y de **cada** color de RE-02:

| Escenario sembrado | Qué demuestra |
|---|---|
| `ISW-211-G02`, `ADM-101-G01` sin docente | Riesgo **Alto** |
| `ISW-111-G01`, `ADM-101-G01` sin aula | Riesgo **Alto** |
| `ISW-111-G02` (3 est.), `ADM-101-G01` (4 est.) | Riesgo **Bajo** (umbral 5) |
| Ana Lucía Rodríguez: 5 grupos × 0.25 = **1.25** | Riesgo **Medio** + sobrecontratación en RE-02 |
| Carlos Eduardo Jiménez: 0.50 sobre 1.00 | Subcontratación significativa en RE-02 (< 80 %) |
| María Fernanda Solís: 1.00 sobre 1.00 | Sin alerta en RE-02 |
| `ADM-101-G02` cancelado y sin docente | Los grupos cancelados **no** generan ruido en el tablero |

## 4. 🖥️ Pantallas

| Ruta | Requerimiento | Qué hace |
|---|---|---|
| `/teachers` | INFRA-01 | CRUD de docentes (cédula, nombre, jornada estimada) |
| `/classrooms` | INFRA-01 | CRUD de aulas y sus capacidades |
| `/groups` | INFRA-01 | CRUD de grupos: curso, cuatrimestre, docente y aula **opcionales**, matrícula, jornada, modalidad y estado |
| `/reports/academic-offer` | RE-01 | Genera `.xlsx` **y** `.pdf` del cuatrimestre en una sola acción y registra el tiempo en el log |
| `/reports/teacher-load` | RE-02 | Jornada acumulada vs. estimada, con colores de alerta y la leyenda obligatoria en cada página del PDF |
| `/risk-board` | RE-04 | Tablero en vivo de los cuatro riesgos, agrupado por nivel, con enlace directo al registro |

Todas exigen permiso: la ruta se autoriza en `mount()`, cada acción se vuelve a
autorizar por su cuenta, y el enlace del sidebar sólo aparece si el usuario puede
verla.

## 5. 🏛️ Arquitectura

Cada módulo son cuatro capas bajo `src/{Contexto}/{Entidad}/`:

```
Domain/          PHP puro. Sin Eloquent, sin Illuminate, sin Livewire.
Application/     Casos de uso y DTOs. Orquesta; no decide reglas.
Infrastructure/  Adaptadores (Eloquent, sistema de archivos). Lo único que conoce el framework.
Presentation/    Componentes Livewire, Forms, Policies y rutas.
```

### 📦 Contextos acotados

| Contexto | Módulos | Por qué está separado |
|---|---|---|
| `IdentityAccess` | `Role`, `Permission` | "Quién puede hacer qué" (ya existía) |
| `Academic` | `Teacher`, `Classroom`, `Group` | Misma familia de conceptos, cambian juntos |
| `AcademicRisk` | `RiskBoard` | Ciclo de vida y reglas propias; nada se persiste |
| `Reporting` | `OfferReport`, `TeacherLoadReport` | Modelos de lectura; se consultan, no se editan |

`AcademicRisk` y `Reporting` **no importan el dominio de `Academic`**: cada uno
declara sus propios Value Objects (`GroupSnapshot`, `OfferRow`, `TeacherLoadRow`) y
su propio puerto de lectura, y su adaptador de infraestructura los produce. La única
excepción, documentada como *Shared Kernel*, son los enums `Modality` y
`GroupStatus`: un reporte *sobre* la oferta tiene que hablar su vocabulario, y
duplicar el enum garantizaría que ambos se desincronizaran.

### 📍 Dónde vive cada regla de negocio

| Regla | Archivo |
|---|---|
| Los cuatro riesgos y su nivel Alto/Medio/Bajo | `src/AcademicRisk/RiskBoard/Domain/Services/RiskEvaluator.php` |
| Clasificación del nivel por tipo de riesgo | `src/AcademicRisk/RiskBoard/Domain/ValueObjects/RiskType.php` |
| Sobrecontratación / subcontratación | `src/Reporting/TeacherLoadReport/Domain/ValueObjects/WorkloadStatus.php` |
| Jornada acumulada y comparación | `src/Reporting/TeacherLoadReport/Domain/Entities/TeacherLoadReport.php` |
| Un grupo sin docente no puede tener jornada | `src/Academic/Group/Domain/Entities/Group.php` |
| Un grupo cancelado no es un riesgo | `src/Academic/Group/Domain/ValueObjects/GroupStatus.php` |

Los umbrales configurables (`config/academic.php`, alimentado por variables de
entorno) se leen **una sola vez**, en `DomainServiceProvider`, y entran al dominio
como valores planos. Por eso **ninguna capa `Domain/` contiene un `config()` ni un
`env()`** — verificable con `grep -rE '\b(env|config)\(' src/*/*/Domain/`, que no
devuelve nada. Las capas `Presentation/` e `Infrastructure/` sí leen `config()`:
son adaptadores, es su trabajo conocer el framework.

## 6. 🧠 Decisiones técnicas

**Tiempo real del tablero (RE-04).** `wire:poll` con intervalo configurable
(15 s por defecto, acotado en código a 5–60 s). Se eligió sobre WebSockets porque el
requisito se mide en decenas de segundos y broadcasting implicaría un servidor de
sockets y publicar eventos de dominio desde cada escritura, para una pantalla que
abren unos pocos coordinadores. El tablero no cachea ni almacena alertas: se
recalcula entero en cada poll, y por eso un elemento desaparece en cuanto su causa
se corrige.

**Dos archivos en una sola acción (RE-01).** Un response HTTP sólo puede llevar un
archivo, así que `generate()` escribe el `.xlsx` y el `.pdf` en un disco privado a
partir de las **mismas filas en memoria** (que es lo que hace verdadera la exigencia
de "la misma información en ambos") y luego cada botón sirve el suyo. El nombre del
archivo se deriva del cuatrimestre, nunca de una ruta enviada por el navegador.

**La leyenda en cada página (RE-02).** El PDF usa una plantilla propia
(`resources/views/exports/teacher-load-pdf.blade.php`) con la leyenda en
`position: fixed`, que el motor de impresión de Chromium repite por página. Una
plantilla de tabla genérica no puede expresar eso.

**Precisión decimal.** Las jornadas se comparan redondeadas a 2 decimales, la
precisión real de la columna `decimal(4,2)`. Sin eso, `0.35 × 3` da
`1.0499999999999998` en binario y podría inventar —o perder— un conflicto de
jornada por ruido de coma flotante.

## 7. ⚡ Rendimiento del PDF con volúmenes grandes

Medido con `php artisan pdf:bench`, que recorre las mismas cuatro etapas que un
export real (payload de la cola → Blade SSR → Chrome → bytes) sobre filas
sintéticas con las nueve columnas de `/groups`.

| Filas | Antes | Ahora (arranque limpio) | Ahora (proceso ya caliente) | PDF antes → ahora |
|---|---|---|---|---|
| 2 500 | 5,0 s | **3,8 s** | **1,9 s** | 9,1 MB → 0,85 MB |
| 5 000 | 8,8 s | 3,9 s | 3,8 s | 18,2 MB → 1,6 MB |
| 10 000 | 24,9 s | ~10 s | ~10–15 s | 36,9 MB → 3,2 MB |

Reproducir:

```bash
php artisan pdf:bench --rows=2500,5000,10000 --runs=3 --breakdown
```

**Lo que costaba el tiempo no era Chrome ni el CSS.** Chrome emite por defecto un
PDF etiquetado (árbol de accesibilidad PDF/UA): un objeto `/StructElem` por cada
`<td>`. En un reporte de 2 500 filas × 9 columnas son **52 519 objetos extra**,
7,9 MB de los 8,9 MB del archivo más 1 MB de tabla `xref` que sólo existe para
indexarlos. Apagarlo (`PDF_TAGGED=false`) es la única causa dominante: ×10 menos
peso y ~35 % menos tiempo. Se documenta el intercambio en `config/exports.php` —
lo que se pierde es la semántica de tabla para lectores de pantalla, y RE-01 ya
genera el `.xlsx` con las mismas filas, que es mejor superficie asistiva para
datos tabulares. El texto del PDF sigue siendo texto real, seleccionable y
buscable.

Lo que **no** era: se midió y se descartó como ruido el `overflow`/`border-radius`
de la tarjeta, el rayado `nth-child`, los bordes por celda, partir la tabla en
tablas pequeñas (la fragmentación de Chrome no era el problema) y el ancho de
columnas — pasar de 207 a 94 páginas con las mismas filas mejoró sólo un 19 %,
porque el coste va con las **celdas**, no con las páginas.

**El arranque limpio.** El sidecar (`scripts/pdf-sidecar.mjs`) ya evitaba los
~2,2 s de arrancar node + puppeteer + Chromium en cada export, pero sólo si
alguien lo había arrancado. Ahora `composer run dev` lo levanta junto al servidor,
la cola y vite, y si aun así no hay nada escuchando, la aplicación lo lanza ella
misma y espera a que responda `/health`. Si no arranca, se cae a Browsershot como
siempre: el sidecar acelera, nunca es requisito.

**Techo de un solo documento, y qué pasa por encima de él.** 15 000 filas se
renderizan en un solo PDF (22,8 s, 1 155 páginas). **16 000 fallan**:
`Protocol error (Page.printToPDF): Printing failed`. Es un límite de Chrome, no de
la aplicación, y no degrada — falla en seco.

La primera versión de esta protección rechazaba el export al instante por encima
de 12 000 filas (con margen, porque el límite real es contenido total, no número
de filas). `ChunkedChromePdfWriter` la reemplazó: por encima de
`ChunkedChromePdfWriter::CHUNK_THRESHOLD`, el informe se parte en documentos de
`CHUNK_ROWS` filas, se renderizan en paralelo contra el mismo sidecar y se unen
con mPDF en un único archivo — sin límite práctico de filas, verificado con
45 000 filas reales (`ChunkedPdfWriterTest` fija el comportamiento; `pdf:bench`
mide el documento único subyacente, no el camino troceado). Rechazar dejó de
ser la respuesta honesta en cuanto existió algo que sí podía renderizarlo. El
`.xlsx` sigue sin techo comparable, y sigue siendo la mejor opción para lo que no
necesita maquetación de página.

### Memoria del click de exportar

El troceado quita el techo del **render**, no el de la **petición**. `exportPdf()`
construye todas las filas que coinciden en la misma petición web que despacha el
job, porque el job recibe filas y no una consulta. Medido sobre los datos reales:

| Filas | Pico de la petición | Payload del job |
|---|---|---|
| 20 000 | **116 MB** | 7,2 MB |
| 45 000 | **192 MB** | 12,2 MB |

Con el `memory_limit` de 128 MB por defecto, 20 000 filas cabe con 12 MB de
margen —demasiado justo para confiarse— y 45 000 muere con
`Allowed memory size exhausted`. Para exportar volúmenes así:

```bash
php -d memory_limit=512M artisan serve
php -d memory_limit=2G artisan queue:work --timeout=3600 --memory=2048
```

La pantalla en sí no tiene ese problema: `/groups` pagina en el servidor
(`GroupComponent::$tableMode`) y su coste no crece con el tamaño de la tabla.

## 8. ✅ Verificación

```bash
composer run test
```

Ejecuta Pint (estilo), PHPStan nivel 7 y PHPUnit.

Las pruebas de dominio (`tests/Unit/`) extienden `PHPUnit\Framework\TestCase`, **no**
la de Laravel: el dominio es PHP puro, así que no necesitan framework. Si alguna
llegara a necesitarlo, algo se filtró donde no debía.

Cubren los cuatro riesgos con sus casos límite (matrícula exactamente en el umbral,
jornada exactamente en el techo, ruido de coma flotante, acumulación por
cuatrimestre, grupos cancelados), las tres verdictos de RE-02 (incluido el 80 %
exacto) y los invariantes de los agregados.

## 9. 🔌 Requisitos técnicos transversales

- **TypeScript.** `resources/js/data-table.ts` (la fuente de datos Alpine que
  alimenta las tablas client-side) está tipado; `tsconfig.json` en modo `strict`,
  `npm run types:check` lo verifica.
- **API REST externa.** El widget de feriados del dashboard
  (`App\Services\PublicHolidays\PublicHolidaysClient`) consume la API pública de
  Nager.Date (sin llave), cacheada 24 h; ver `tests/Feature/HolidaysWidgetTest.php`.
- **JWT.** `routes/api.php` expone un API JSON stateless independiente de la sesión
  Livewire: `POST /api/login` entrega un token (firmado con `JWT_SECRET`, no con
  `APP_KEY`), `GET /api/me`, `/api/teachers` y `/api/risk-board` lo exigen vía el
  middleware `jwt.auth`. Cuentas con 2FA confirmado no pueden entrar por esta vía
  (423) — ver `tests/Feature/Api/JwtAuthTest.php`.

El resto del stack obligatorio (TALL, variables de entorno, pruebas unitarias,
Arquitectura Hexagonal/DDD, repositorio documentado) ya estaba cubierto.

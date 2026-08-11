# SIGA UTN — Módulo de Reportes y Tablero de Riesgos

Analítica ejecutiva de la oferta académica para la Sede Regional San Carlos de la
Universidad Técnica Nacional. Implementa los requerimientos **INFRA-01**, **RE-01**,
**RE-02** y **RE-04** sobre el sistema SIGA existente (TALL stack + Arquitectura
Hexagonal con DDD).

---

## 1. Requisitos

| Herramienta | Versión mínima | Por qué |
|---|---|---|
| PHP | **8.4** | Lo exigen `laravel/framework ^13` y `symfony/* ^8.1` |
| Composer | 2.x | Dependencias PHP |
| Node.js | 20+ | Vite (assets) y Puppeteer (PDF) |
| Chrome/Chromium | — | Lo instala `npm install` vía Puppeteer; sólo hace falta para los PDF |

La base de datos por defecto es **SQLite**: no hay servidor que instalar ni
configurar. El archivo vive en `database/database.sqlite`.

## 2. Instalación

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

### Usuarios sembrados

| Correo | Contraseña | Rol |
|---|---|---|
| `prueba@gmail.com` | `12345678` | Superadmin |
| `admin@gmail.com` | `12345678` | Admin |

## 3. Qué queda cargado al sembrar

`AcademicDataSeeder` es **determinista** (sin `faker`): dos ejecuciones producen
exactamente el mismo tablero, de modo que una regresión en las reglas de riesgo se
ve como un número distinto y no se confunde con "datos aleatorios diferentes".

- **15 docentes**, **10 aulas**, **41 grupos** (33 en `2026-II`, 8 en `2026-I`).
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

## 4. Pantallas

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

## 5. Arquitectura

Cada módulo son cuatro capas bajo `src/{Contexto}/{Entidad}/`:

```
Domain/          PHP puro. Sin Eloquent, sin Illuminate, sin Livewire.
Application/     Casos de uso y DTOs. Orquesta; no decide reglas.
Infrastructure/  Adaptadores (Eloquent, sistema de archivos). Lo único que conoce el framework.
Presentation/    Componentes Livewire, Forms, Policies y rutas.
```

### Contextos acotados

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

### Dónde vive cada regla de negocio

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
como valores planos. Por eso `src/` no contiene ni un `config()` ni un `env()`.

## 6. Decisiones técnicas

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

## 7. Verificación

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

## 8. Fuera del alcance de este módulo

Los requisitos técnicos transversales del curso que **no** cubre este entregable:
TypeScript, consumo de una API REST externa y autenticación JWT. El resto del stack
obligatorio (TALL, variables de entorno, pruebas unitarias, Arquitectura
Hexagonal/DDD, repositorio documentado) sí está.

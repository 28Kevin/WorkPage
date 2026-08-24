# Generador Automático de Exámenes Médicos Ocupacionales

Base del sistema descrito en *Especificaciones Técnicas - Generador de Exámenes Médicos Ocupacionales*.

| Capa | Tecnología |
|---|---|
| Frontend | Vue 3.5 (Composition API) + Vite + Vue Router + Pinia + Tailwind CSS 4 |
| Backend | Laravel 13 (API REST) + Sanctum + dompdf + endroid/qr-code |
| Base de datos | PostgreSQL 17 |
| Orquestación | Docker Compose (Postgres + PHP-FPM + Nginx + Vite) |

```
workpage/
├── backend/     API Laravel 13
├── frontend/    SPA Vue 3
├── docker/      Docker Compose, Dockerfiles, Nginx y Postgres
└── README.md
```

---

## Arranque con Docker (recomendado)

Requisito: Docker Desktop en ejecución.

```bash
cd docker
cp .env.example .env      # opcional: ajuste puertos y credenciales
docker compose up --build
```

El primer arranque instala dependencias, ejecuta migraciones y carga los catálogos.

| Servicio | URL | Variable en `docker/.env` |
|---|---|---|
| SPA Vue | http://localhost:5173 | `FRONTEND_PORT` |
| API Laravel | http://localhost:8080/api | `APP_PORT` |
| PostgreSQL | `localhost:5433` | `DB_PORT_HOST` |

> Los puertos 8000 y 5432 suelen estar ocupados por Laragon, por eso el API se publica en 8080
> y Postgres en 5433. Ajústelos en `docker/.env` si lo prefiere.

**Credenciales del módulo administrativo:** `admin@centromedico.test` / `password`
(configurables en `docker/.env` mediante `ADMIN_EMAIL` y `ADMIN_PASSWORD`).

Comandos útiles:

```bash
docker compose logs -f backend        # ver logs del API
docker compose exec backend php artisan migrate:fresh --seed
docker compose exec backend php artisan test
docker compose down -v                # borra también los datos de Postgres
```

---

## Arranque sin Docker

**Backend** (PHP ≥ 8.3, Composer, PostgreSQL):

```bash
cd backend
composer install
cp .env.example .env && php artisan key:generate
# ajuste DB_HOST / DB_DATABASE / DB_USERNAME / DB_PASSWORD en .env
php artisan migrate --seed
php artisan serve            # http://localhost:8000
```

**Frontend** (Node ≥ 20):

```bash
cd frontend
npm install
cp .env.example .env         # VITE_API_BASE_URL apunta al backend
npm run dev                  # http://localhost:5173
```

---

## Alcance implementado

### 1. Módulo administrativo (acceso restringido)

Autenticación por usuario y contraseña con tokens Sanctum, con límite de intentos.
Formulario de captura dividido en las tres secciones del documento:

- **A. Datos del trabajador** — nombre, cédula, fecha de nacimiento, correo, celular y estatura.
  El **peso adecuado se calcula automáticamente** desde la estatura (IMC objetivo 22; se muestra
  además el rango saludable 18.5–24.9).
- **B. Datos ocupacionales y de afiliación** — empresa y NIT, selectores de EPS, ARL, ciudad, cargo
  y lista de riesgos/especialidades (trabajo en alturas, espacio confinado, etc.).
  Al elegir una ARL aparece el **enlace directo a su plataforma** para descargar certificados.
- **C. Detalles del examen** — calendario de fecha y selector de tipo
  (Ingreso, Periódico, Seguimiento, Retorno, Cambio de ocupación).

### 2. Lógica de procesamiento automático

- **Numeración consecutiva** — secuencia dedicada con bloqueo pesimista (`SELECT … FOR UPDATE`),
  formato `EMO-<año>-<000001>`. Sin saltos ni duplicados con usuarios concurrentes.
- **Parámetros médicos** — signos vitales, antropometría, agudeza visual, audiometría, espirometría,
  laboratorio, examen físico y antecedentes se diligencian con valores aleatorios **dentro de rangos
  normales estándar**, garantizando resultado **APTO**.

### 3. Generación del documento (PDF)

- PDF descargable (`GET /api/exams/{id}/pdf`) con el examen completo: encabezado del centro médico,
  las tres secciones de datos, exámenes complementarios, banda de concepto **APTO**, recomendaciones
  y firmas.
- **Código QR impreso** que apunta a `FRONTEND_URL/verificar/{código}` y despliega la **leyenda oficial
  de verificación** confirmando que el examen fue emitido por el centro médico.

### 4. Módulo de consulta pública (acceso externo)

- Pestaña de libre acceso con **búsqueda por número de cédula**.
- Si el registro existe: mensaje de confirmación de realización exitosa del examen y la
  **fecha exacta de expedición**. Se listan todos los exámenes del trabajador, del más reciente al
  más antiguo.
- Por protección de datos personales solo se muestran las iniciales y los últimos cuatro dígitos
  del documento. Los endpoints públicos están limitados a 30 peticiones por minuto.

---

## API

### Público

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/public/exams/search?document_number=` | Consulta por cédula |
| `GET` | `/api/public/verify/{code}` | Destino del QR; leyenda oficial |

### Autenticación

| Método | Ruta | Descripción |
|---|---|---|
| `POST` | `/api/auth/login` | Devuelve token Bearer |
| `GET` | `/api/auth/me` | Usuario autenticado |
| `POST` | `/api/auth/logout` | Revoca el token actual |

### Administrativo (`Authorization: Bearer <token>`)

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/catalogs` | EPS, ARL, ciudades, riesgos y tipos de examen |
| `GET` | `/api/tools/ideal-weight?height_cm=` | Peso adecuado según estatura |
| `GET` | `/api/exams/next-order-number` | Próximo consecutivo |
| `GET` | `/api/exams` | Listado paginado (`search`, `exam_type`, `page`) |
| `POST` | `/api/exams` | Crea el examen y autocompleta los parámetros |
| `GET` | `/api/exams/{id}` | Detalle |
| `GET` | `/api/exams/{id}/pdf` | Descarga el PDF (`?inline=1` para previsualizar) |

---

## Pruebas

```bash
cd backend && php artisan test
```

O dentro de Docker:

```bash
cd docker && docker compose exec backend php artisan test
```

22 pruebas cubren autenticación, restricción del módulo administrativo, catálogos, cálculo de peso,
autocompletado de parámetros, consecutivos, validaciones, generación de PDF con QR y ambos flujos
de consulta pública.

### Las pruebas nunca tocan la base de datos de desarrollo

Usan sqlite en memoria. La configuración vive en `backend/phpunit.xml` y se declara con entradas
**`<server>`**, no solo `<env>`: PHPUnit aplica `<env force="true">` sobre `putenv()` y `$_ENV`, pero
el repositorio de variables de Laravel consulta `$_SERVER` primero. Como Docker Compose exporta
`DB_CONNECTION=pgsql` a `$_SERVER`, un `<env>` quedaba silenciado y `RefreshDatabase` truncaba la
base de datos real.

Como segunda barrera, `backend/tests/TestCase.php` aborta la suite si la conexión activa no es
sqlite en memoria, y lo hace antes de que `RefreshDatabase` pueda borrar nada.

Si alguna vez pierde los datos de arranque, se restauran con:

```bash
docker compose exec backend php artisan migrate --force --seed
```

---

## Personalización

Los datos del centro médico impresos en el PDF y en la leyenda de verificación se configuran en
`backend/.env` (`MEDICAL_CENTER_*`) y se leen desde `backend/config/medical_center.php`.

Los catálogos de EPS, ARL, ciudades y riesgos se cargan desde
`backend/database/seeders/CatalogSeeder.php`.

---

## Pendientes sugeridos para la siguiente iteración

- Roles y permisos (administrador / auxiliar / médico) sobre el módulo administrativo.
- Edición y anulación de exámenes con trazabilidad de auditoría.
- Envío del PDF por correo al trabajador y a la empresa.
- Firma digital del PDF y almacenamiento del archivo generado.
- Carga masiva de trabajadores y reportes por empresa.

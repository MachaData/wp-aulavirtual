# Guía técnica

Para quien mantenga o amplíe el plugin. La arquitectura de fondo (modelo de datos, roles,
seguridad, rendimiento) está en `ARCHITECTURE.md`; esto es el mapa práctico.

## 1. Cómo arranca

```
aula-virtual.php
  └── plugins_loaded (5) → Core\Plugin::boot()
        ├── register() de cada ServiceProvider   → solo enlaza servicios en el contenedor
        └── boot() de cada ServiceProvider       → registra hooks de WordPress
```

Un módulo = una carpeta en `includes/` con su `*ServiceProvider`. La lista está en
`Core\Plugin::service_providers()` y se puede ampliar con el filtro
`aula_virtual/service_providers`. El orden importa solo en `boot()`: Campus y Admin van al
final porque consumen servicios de todos los demás.

## 2. Mapa de módulos

| Carpeta | Qué hay | Depende de |
|---|---|---|
| `Core` | Plugin, Container, ServiceProvider, Logger, AuditLog, Events (bus + catálogo) | — |
| `Database` | Schema (15 tablas), Migrator (versionado), Repository (base), Installer | Core |
| `Permissions` | Capabilities, Roles, AccessControl, bloqueo de wp-admin | Core |
| `Security` | Sanitizer | — |
| `Courses` | CPT `av_course`, meta, taxonomías, repositorio | Permissions |
| `Editions` | EditionStatus, repositorio, servicio | Courses |
| `Curriculum` | LessonType, Module/Lesson repositorios, LessonService | Editions |
| `Enrollments` | EnrollmentStatus, matrícula, enlaces, solicitudes, formulario público | Editions |
| `Progress` | ProgressCalculator, repositorio, servicio | Curriculum, Enrollments |
| `LiveClasses` | repositorio, servicio (ventana, zonas horarias) | Curriculum |
| `Videos` | VideoEmbed: registro de proveedores, embeds firmados de Bunny, saneado de iframes | — |
| `Materials` | repositorio, servicio (lista blanca de extensiones) | Curriculum |
| `Emails` | plantillas, variables, renderer, mailer, notificador | Enrollments, Curriculum |
| `WooCommerce` | ProductLink, OrderHandler, Settings | Enrollments |
| `Migration` | TutorReader, TutorMapping, TutorMigrator | Editions, Curriculum, Enrollments, Progress |
| `Landing` | LandingData, renderer, plantilla, meta box | Editions, Curriculum, Enrollments |
| `Campus` | CampusController (dashboard, temario, sesión, perfil) | casi todo |
| `Admin` | AdminMenu y pantallas (ediciones, sesión, solicitudes, emails, migración) | casi todo |
| `REST` | AbstractController, CoursesController | Courses |

Reglas de dependencia: los servicios de negocio **no** conocen a Admin, Campus ni Emails. La
comunicación hacia afuera es por eventos.

## 3. Convenciones

- `declare(strict_types=1)`, clases `final`, guardia `ABSPATH` en todo archivo.
- **Repositories** solo saben de filas: cada uno declara `columns()` (columna → placeholder) y
  el base rechaza cualquier columna no declarada, tanto en `where` como en `order_by`.
- **Services** contienen las reglas y devuelven `int|true|WP_Error`; nunca imprimen ni
  redirigen.
- **Screens/Controllers** validan nonce, capacidad y propiedad (en ese orden), llaman al
  servicio y redirigen con `av_notice` / `av_message`.
- Las fechas se guardan en UTC (`current_time('mysql', true)`); se convierten al mostrar.
- Escapar en la salida (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`), sanear en la
  entrada (`Security\Sanitizer`).
- Todo texto visible pasa por `__()` con el text domain `aula-virtual`.

## 4. Eventos de dominio

`Core\Events\EventBus::dispatch( Events::X, $payload )` publica `aula_virtual/event/x` y el
genérico `aula_virtual/event`. El payload lleva ids, nunca datos personales. Marcar
`'silent' => true` evita que salgan correos (lo usan la migración y el recálculo masivo).

| Evento | Quién lo emite | Quién lo escucha |
|---|---|---|
| `registration_request_created/approved/rejected` | RegistrationService | Emails |
| `enrollment_created/approved/suspended/cancelled/expired` | EnrollmentService | Emails |
| `lesson_started/completed`, `course_completed` | ProgressService | Emails |
| `live_class_recorded`, `material_added` | LiveClassService, MaterialService | Emails (sin plantilla por defecto) |
| `order_processed` | OrderHandler | — (silencioso) |

Para añadir un correo a un evento nuevo: incluirlo en `EmailDefaults::events()` y, si debe
venir instalado, en `EmailDefaults::definitions()`.

## 5. Hooks para temas y add-ons

| Hook | Tipo | Para qué |
|---|---|---|
| `aula_virtual/service_providers` | filter | Añadir módulos |
| `aula_virtual/can_access_edition` | filter | Cambiar la regla de acceso al contenido |
| `aula_virtual/campus_redirect_url` | filter | A dónde va un alumno que intenta entrar a wp-admin |
| `aula_virtual/email_variables` | filter | Añadir variables a las plantillas |
| `aula_virtual/material_extensions` | filter | Extensiones admitidas como material |
| `aula_virtual/video_providers` | filter | Añadir proveedores de video al selector |
| `aula_virtual/render_video` | filter | Renderizar un proveedor propio (devolver markup) |
| `aula_virtual/embed_hosts` | filter | Hosts admitidos en códigos incrustados |
| `aula_virtual/use_landing_template` | filter | Desactivar la plantilla de landing del plugin |
| `aula_virtual/course_post_type_args` | filter | Ajustar el CPT |
| `aula_virtual/rest_controllers` | filter | Añadir controladores REST |
| `aula_virtual/edition_created`, `lesson_created` | action | Reaccionar a altas |
| `aula_virtual/schema_migrated`, `activated`, `deactivated`, `booted` | action | Ciclo de vida |

**Plantillas sobrescribibles desde el tema:** `aula-virtual/campus/*.php`,
`aula-virtual/registration/*.php`, `aula-virtual/landing/**/*.php`,
`aula-virtual/emails/layout.php`.

**Shortcodes:** `[av_campus]`, `[av_course_landing id=""]`, `[av_course_editions id=""]`.

## 6. Esquema y migraciones

`Database\Schema::VERSION` (hoy `1.1.0`) se compara con la opción `av_db_version`. Para
cambiar una tabla: editar su `CREATE TABLE` en `Schema::definitions()` (dbDelta aplica la
diferencia), subir `VERSION` y, si hace falta un backfill, añadir una entrada en
`Migrator::data_migrations()` con esa versión. El migrador corre en la activación y en
`admin_init` cuando la versión instalada es menor.

## 7. Añadir un módulo

1. Carpeta `includes/NuevoModulo/` con repositorio (si hay tabla), servicio y
   `NuevoModuloServiceProvider`.
2. Registrarlo en `Core\Plugin::service_providers()` antes de Campus y Admin.
3. Pantalla: clase en `Admin/`, vista en `admin/views/`, binding y handlers en
   `AdminServiceProvider`, entrada en `AdminMenu`.
4. Pruebas: reglas puras en `tests/smoke-test.php` y una comprobación de cableado que
   resuelva el servicio desde el contenedor.

## 8. Pruebas

```bash
php tests/smoke-test.php      # logica pura + cableado del contenedor, sin WordPress
php tests/lint-classes.php    # carga todas las clases (detecta imports y namespaces rotos)
composer lint                 # WordPress Coding Standards
```

`tests/wp-stubs.php` define solo las funciones de WordPress que las pruebas tocan, con su
comportamiento real (por ejemplo, `esc_url_raw` rechaza `javascript:`). Si una prueba necesita
una función nueva, añadirla ahí imitando a WordPress, no simplificándola.

## 8b. Videos

`Videos\VideoEmbed::render( $provider, $source )` es el único punto que produce markup de
video (campus, landing, grabaciones). Con proveedor vacío detecta por la URL. Bunny se
resuelve a `iframe.mediadelivery.net/embed/{lib}/{guid}` y, si `av_bunny_token_key` está
definida, añade `token=sha256(clave + guid + expires)` y `expires`, según la autenticación por
token de Bunny Stream. Los iframes (pegados o devueltos por oEmbed) solo se conservan si su
host está en la lista blanca; `wp_kses_post` los eliminaría, por eso no se usa ahí.

## 9. Limitaciones conocidas

- Los archivos de materiales viven en la biblioteca de medios: quien tenga la URL directa
  puede abrirlos. La opción "solo lectura" oculta el enlace, no protege el archivo. Un
  endpoint de descarga con verificación de matrícula queda para una fase posterior.
- La liberación programada de sesiones (content drip) se guarda pero todavía no se aplica en
  el campus.
- Las URLs del campus usan argumentos de consulta (`?av_edicion=`, `?av_leccion=`,
  `?av_perfil=`); las URLs limpias llegan con las reglas de reescritura de la fase Campus.
- No hay pantalla de configuración: las opciones se listan en la guía del administrador.
- REST expone solo `/aula-virtual/v1/courses`; el resto de recursos llegan con la integración
  externa.
- Sin importación por Excel ni anuncios todavía (siguiente bloque).

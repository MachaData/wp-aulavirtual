# Aula Virtual — Arquitectura propuesta (Entrega 1: Core)

> Documento de la **primera entrega** exigida en el punto 72 del brief: analizar los
> requerimientos y proponer arquitectura, modelo de datos, roles, eventos, endpoints,
> proceso de instalación y plan de desarrollo **antes** de construir la UI.
>
> Estado: **Core implementado y verificado** (38 comprobaciones automáticas en verde).
> La UI de administración y el Campus **no** se han construido: esperan aprobación de este documento.

---

## 0. Nota de contexto

Este plugin es un producto **distinto** de la plataforma SIQA (Django + HTMX + Postgres) que vive en
la raíz de este repositorio. El brief pide expresamente un plugin para WordPress, de modo que el stack
es WordPress/PHP por decisión explícita del encargo, no por sustitución del stack de SIQA. Ambos
productos conviven sin acoplarse: el plugin está aislado en `wordpress-plugin/aula-virtual/`.

Nombres técnicos (todos configurables antes de publicar comercialmente, punto 74):

| Concepto | Valor |
|---|---|
| Plugin | Aula Virtual |
| Slug | `aula-virtual` |
| Namespace | `SIQA\AulaVirtual` |
| Prefijo de tablas | `{$wpdb->prefix}av_` |
| REST namespace | `aula-virtual/v1` |
| Text domain | `aula-virtual` |
| Prefijo de hooks | `aula_virtual/` |
| Prefijo de opciones | `av_` |

---

## 1. Principio rector: Curso → Edición → Matrícula

Todo el modelo se apoya en la separación que pide el punto 1 del brief:

```
Curso (producto educativo permanente, público, con landing y SEO)
  └── Edición (cohorte concreta: fechas, cupo, modalidad, instructor, producto Woo)
        ├── Temario propio (módulos + lecciones)
        ├── Matrículas (alumnos)
        ├── Sesiones en vivo
        └── Comunicaciones
```

Consecuencias de diseño que atraviesan todo el plugin:

1. **El Curso es contenido; la Edición es operación.** Por eso el Curso es un Custom Post Type y la
   Edición vive en tabla propia.
2. **Toda matrícula apunta siempre a una Edición**, nunca sólo a un Curso. `course_id` se guarda
   desnormalizado en `enrollments`, `progress`, `lessons` y `materials` para evitar joins en las
   consultas de listado (punto 50: nada de N+1).
3. **El progreso se mide contra las lecciones de la Edición**, no del Curso. Duplicar una edición
   duplica el temario, nunca los alumnos.
4. **El catálogo público muestra Cursos**; las Ediciones aparecen sólo si el curso decide mostrar
   "próximas ediciones" (punto 60).

---

## 2. Reparto de responsabilidades

| Capa | Responsable | Qué NO hace |
|---|---|---|
| WordPress | Usuarios, auth, roles, Media Library, emails, cron, REST, permalinks, plantillas | No modela cursos ni matrículas |
| WooCommerce (opcional) | Productos, precios, checkout, pasarelas, pedidos, cupones, reembolsos | No decide quién accede al contenido |
| Aula Virtual | Cursos, ediciones, temario, matrículas, progreso, clases en vivo, materiales, campus, certificados, reportes | No reimplementa pagos ni autenticación |

WooCommerce es **integración opcional y desacoplada**: el LMS arranca y funciona sin él (matrícula
manual, Excel, enlace privado y gratuita). El módulo WooCommerce se registra sólo si la clase
`WooCommerce` existe, y su único trabajo es traducir "pedido pagado" a un evento de dominio.

---

## 3. Arquitectura de código

### 3.1 Bootstrap y contenedor

```
aula-virtual.php
  ├── comprueba PHP >= 8.1 y WP >= 6.4  (aviso en admin y salida limpia si no)
  ├── define constantes (AV_FILE, AV_PATH, AV_URL, ...)
  ├── registra el autoloader (Composer si hay vendor/, PSR-4 propio si no)
  ├── register_activation_hook   → Database\Installer::activate()
  ├── register_deactivation_hook → Database\Installer::deactivate()
  └── plugins_loaded (prioridad 5) → Core\Plugin::instance()->boot()
```

`Core\Plugin` es el único orquestador. No hay funciones globales salvo `SIQA\AulaVirtual\aula_virtual()`, que
devuelve la instancia, y los helpers del bootstrap.

**Ciclo de arranque en dos fases** (`Core\ServiceProvider`):

1. `register(Container $c)` — sólo enlaza servicios. Ningún hook.
2. `boot(Container $c)` — registra hooks de WordPress. Ejecuta cuando **todos** los servicios ya
   están enlazados, de modo que cualquier callback puede resolver cualquier dependencia.

`Core\Container` es un contenedor mínimo (`singleton`, `instance`, `has`, `get`) con detección de
dependencias circulares. No se usa autowiring por reflexión: las fábricas explícitas hacen que las
dependencias de cada módulo sean legibles de un vistazo.

Extensibilidad: `apply_filters( 'aula_virtual/service_providers', $providers )` permite que un add-on
añada su propio módulo sin tocar el core. Las entradas que no implementan el contrato se descartan.

### 3.2 Árbol de carpetas

```
wordpress-plugin/aula-virtual/
├── aula-virtual.php                  # bootstrap
├── uninstall.php                # borrado opt-in
├── composer.json                # PSR-4 + PhpSpreadsheet
├── ARCHITECTURE.md              # este documento
├── CHANGELOG.md
├── readme.txt
├── includes/
│   ├── Core/                    # Plugin, Container, ServiceProvider, Logger, AuditLog
│   │   └── Events/              # EventBus, catálogo Events
│   ├── Database/                # Schema, Migrator, Installer, Repository base
│   ├── Permissions/             # Capabilities, Roles, AccessControl, provider
│   ├── Courses/                 # CoursePostType, CourseMeta, CourseRepository, provider
│   ├── Editions/                # EditionStatus, EditionRepository, EditionService
│   ├── Curriculum/              # LessonType, LessonRepository, LessonService
│   ├── Enrollments/             # EnrollmentStatus, repositorio y servicio
│   ├── Progress/                # ProgressCalculator, repositorio y servicio
│   ├── Students/                # (pendiente) ficha de alumno
│   ├── LiveClasses/             # (pendiente)
│   ├── Videos/                  # (pendiente) registry de proveedores
│   ├── Materials/               # (pendiente)
│   ├── WooCommerce/             # (fase Core-4) integración opcional
│   ├── Emails/                  # (fase Core-4) plantillas + suscriptores de eventos
│   ├── Notifications/           # (fase Core-4) puente eventos → emails
│   ├── Imports/                 # (fase Core-4) Excel/CSV por lotes
│   ├── Campus/                  # CampusController: dashboard, temario, sesion
│   ├── Announcements/           # (pendiente)
│   ├── Certificates/            # (fase 2)
│   ├── Reports/                 # (fase 2)
│   ├── Security/                # Sanitizer, verificación de nonces y uploads
│   ├── REST/                    # AbstractController + controladores
│   ├── Admin/                   # AdminMenu y EditionsScreen
│   └── Support/                 # helpers compartidos
├── admin/{views,assets}/
├── frontend/{templates,assets}/
├── templates/{campus,course,lesson,emails}/   # sobrescribibles por el tema
├── assets/{css,js,images}/
├── languages/
└── tests/                       # stubs de WP + smoke test ejecutable
```

Carpetas marcadas "(fase …)" ya están reservadas en el plan; se crean con su módulo.

### 3.3 Convenciones (punto 73)

- **Repositories** para acceso a tablas (`Database\Repository` base), **Services** para lógica de
  negocio, **Controllers** para REST y pantallas, **Templates** separados y sobrescribibles.
- Clases pequeñas, `final` por defecto, `declare(strict_types=1)` en todos los archivos.
- Inyección de dependencias por constructor; nada de `new` de servicios dentro de la lógica.
- WordPress Coding Standards (`composer lint` con WPCS) + PSR-4 vía Composer.
- Cada archivo empieza con `if ( ! defined( 'ABSPATH' ) ) { exit; }`.

---

## 4. Modelo de datos

### 4.1 Qué es CPT y qué es tabla propia (decisión y motivo)

**Custom Post Type — sólo `av_course`.**
El curso necesita permalink, SEO, imagen destacada, Gutenberg, Elementor, revisiones y autor. Todo
eso lo da WordPress gratis. Sus campos comerciales se registran con `register_post_meta()`, lo que
aporta esquema REST, `sanitize_callback` y `auth_callback` sin escribir código repetido.

Taxonomías: `av_course_cat` (jerárquica) y `av_course_tag` (plana).

**Tablas propias — todo lo transaccional.**
Ediciones, módulos, lecciones, matrículas y progreso se consultan por rangos de fecha, por estado y
con conteos; en `postmeta` eso significa `meta_query` gigantes que se degradan con unos pocos miles
de alumnos. Con tablas propias e índices compuestos, "alumnos activos de la edición Julio 2027"
es un índice, no un escaneo.

**Lecciones: tabla, no CPT.** Una edición de 12 sesiones duplicada 4 veces al año son ~50 posts por
curso y año, con su `postmeta` asociado, contaminando `wp_posts` y las consultas del sitio. El
contenido enriquecido de la lección se guarda en `lessons.content` (sanitizado con `wp_kses_post`).
Si en el futuro se necesitara Gutenberg dentro de la lección, la migración es aditiva: se añade
`lessons.post_id` apuntando a un CPT `av_lesson` sin tocar el resto del modelo.

### 4.2 Tablas (15 en el esquema 1.0.0)

| Tabla | Para qué | Índices clave |
|---|---|---|
| `editions` | Cohortes de un curso | `UNIQUE code`, `course_id`, `status`, `product_id`, `start_date` |
| `modules` | Agrupación del temario | `(course_id, edition_id)`, `position` |
| `lessons` | Sesiones/lecciones | `(course_id, edition_id)`, `module_id`, `position`, `status` |
| `materials` | Adjuntos de lección y de curso | `(course_id, edition_id)`, `lesson_id` |
| `enrollments` | Matrículas | `UNIQUE (user_id, edition_id)`, `(edition_id, status)`, `order_id` |
| `progress` | Avance por lección | `UNIQUE (user_id, lesson_id)`, `(edition_id, user_id)` |
| `registration_requests` | Solicitudes de enlace privado | `(edition_id, status)`, `email` |
| `enrollment_links` | Tokens de inscripción privada | `UNIQUE token`, `edition_id` |
| `announcements` | Anuncios de curso/edición | `(course_id, edition_id)`, `created_at` |
| `live_classes` | Clases en vivo y grabación | `lesson_id`, `(edition_id, start_datetime)` |
| `certificates` | Certificados emitidos | `UNIQUE certificate_code`, `user_id` |
| `email_templates` | Plantillas por evento | `UNIQUE (event, recipient)` |
| `import_jobs` | Importaciones Excel por lotes | `status`, `edition_id` |
| `logs` | Log operativo | `level`, `channel`, `created_at` |
| `audit_log` | Auditoría administrativa | `actor_id`, `(object_type, object_id)` |

Decisiones concretas que conviene revisar en la aprobación:

- **`UNIQUE (user_id, edition_id)` en `enrollments`**: la base de datos garantiza que no haya
  matrículas duplicadas (punto 56), aunque WooCommerce dispare el mismo pedido dos veces.
- **`UNIQUE (user_id, lesson_id)` en `progress`**: una fila por alumno y lección; el avance se
  actualiza, no se acumula en filas nuevas.
- **`enrollments.progress_percentage` y `last_activity` cacheados**: el listado de matrículas
  (punto 18) muestra progreso y última actividad sin recorrer `progress` por alumno.
- **Sin fechas cero.** Todas las columnas `datetime` son `DEFAULT NULL`, no `'0000-00-00'`; así el
  esquema funciona con `NO_ZERO_DATE` activo. Las fechas las escribe siempre PHP en UTC.
- **`live_classes.access_code`** guarda la clave de la sala (Zoom/Meet), que es un código
  compartido de acceso, no la contraseña de una persona. Nunca se guarda ni se envía la contraseña
  de un usuario (punto 47).
- **Zona horaria**: `editions.timezone` guarda la zona de la cohorte; los `datetime` se almacenan en
  UTC y se presentan convertidos. Esto evita que una edición de Lima se rompa si el sitio cambia de
  zona.

### 4.3 Versionado del esquema

`Database\Schema::VERSION` (hoy `1.3.0`) se guarda en la opción `av_db_version`.
`Database\Migrator`:

- ejecuta `dbDelta()` sobre todas las definiciones sólo cuando la versión instalada es menor;
- aplica, en orden, las migraciones de datos registradas por versión (backfills, limpiezas — lo que
  `dbDelta` no sabe hacer);
- usa un *transient* como cerrojo para que dos peticiones simultáneas no migren a la vez;
- dispara `aula_virtual/schema_migrated` al terminar.

Comprobación en `admin_init` (prioridad 1) además de en la activación, para cubrir el caso de
actualización por FTP o por Git, donde el hook de activación no vuelve a ejecutarse.

---

## 5. Roles y capacidades

### 5.1 Roles

| Rol | Slug | Para quién |
|---|---|---|
| Administrador LMS | `av_admin` | Coordinación académica; todo el LMS sin ser admin de WordPress |
| Instructor LMS | `av_instructor` | Docente a cargo de sus cursos y sus alumnos |
| Estudiante LMS | `av_student` | Alumno; sólo Campus |

El `administrator` de WordPress recibe todas las capacidades del LMS al activar. Las capacidades se
resincronizan solas cuando cambia `Roles::VERSION`, porque los roles viven en base de datos y una
versión nueva del plugin no los actualizaría por sí sola.

### 5.2 Capacidades

**Del curso (delegadas al CPT con `map_meta_cap`):** `edit_av_course`,
`edit_av_courses`, `edit_others_av_courses`, `publish_av_courses`,
`delete_av_courses`, `read_private_av_courses`, etc.
Al delegar en `map_meta_cap`, la propiedad ("un instructor sólo edita **sus** cursos") la resuelve
el core de WordPress; no la reimplementamos.

**Del LMS (tablas propias):** `av_manage_lms`, `av_manage_editions`,
`av_manage_curriculum`, `av_manage_materials`, `av_manage_live_classes`,
`av_view_students`, `av_enroll_students`, `av_import_students`,
`av_approve_requests`, `av_manage_announcements`, `av_view_reports`,
`av_issue_certificates`, `av_manage_commerce`, `av_manage_prices`,
`av_access_campus`.

Matriz resumida (punto 40):

| Capacidad | Admin LMS | Instructor | Alumno |
|---|:--:|:--:|:--:|
| Crear / editar curso propio | ✔ | ✔ | — |
| Editar cursos ajenos | ✔ | — | — |
| Publicar curso | ✔ | ✔ | — |
| Gestionar ediciones / temario / materiales / clases en vivo | ✔ | ✔ | — |
| Ver alumnos, matricular, aprobar solicitudes | ✔ | ✔ | — |
| Importar Excel | ✔ | — | — |
| Emitir certificados | ✔ | — | — |
| WooCommerce y precios | ✔ | — | — |
| Configuración global | ✔ | — | — |
| Acceso al Campus | ✔ | ✔ | ✔ |

`Permissions\AccessControl` es el **único** lugar donde se responde "¿puede este usuario hacer X?".
El acceso al contenido de una edición (que depende de una matrícula activa y de las fechas) se
resuelve con el filtro `aula_virtual/can_access_edition`, que implementará el módulo de matrículas.

Los alumnos no ven wp-admin: se les redirige al Campus y se les oculta la barra de administración.
La comprobación exige que el rol **sea exactamente** `av_student`, para no alterar el
comportamiento de los suscriptores u otros roles ya existentes en el sitio.

---

## 6. Eventos internos (punto 35)

`Core\Events\EventBus` publica sobre el sistema de hooks de WordPress
(`aula_virtual/event/{nombre}` y el genérico `aula_virtual/event`), de modo que las integraciones externas
sean idiomáticas y el plugin tenga un único punto de entrada rastreable.

**Regla de oro: la lógica de matrícula nunca envía emails.** Dispara un evento; el módulo de
notificaciones decide qué plantilla usar y a quién.

Catálogo inicial: `registration_request_created|approved|rejected`,
`enrollment_created|approved|suspended|cancelled|expired|expiring`,
`lesson_started|completed|released`, `course_completed`, `edition_starting|finished`,
`live_class_upcoming|recorded`, `announcement_created`, `material_added`, `certificate_issued`,
`import_completed`, `order_processed`.

Cada evento lleva un payload `array<string,mixed>` con ids (nunca datos personales innecesarios) y
la marca `dispatched` en UTC.

---

## 7. REST API (punto 48)

Namespace `aula-virtual/v1`. `REST\AbstractController` extiende `WP_REST_Controller` y aporta
`forbidden()` / `not_found()` / `invalid()` (401 vs 403 según haya sesión), cabeceras
`X-WP-Total` y `X-WP-TotalPages`, y parámetros de colección validados.

**Regla:** ninguna ruta se registra sin `permission_callback`, y ninguna operación de escritura sin
verificación de propiedad, además de la capacidad.

| Recurso | Rutas previstas | Permiso |
|---|---|---|
| `/courses` | `GET` lista, `GET /{id}` | Público sólo para publicados; los borradores exigen poder editarlos |
| `/editions` | `GET`, `POST`, `PATCH /{id}`, `POST /{id}/duplicate` | `manage_editions` + propiedad del curso |
| `/modules`, `/lessons` | CRUD + `POST /reorder` | `manage_curriculum` + propiedad |
| `/enrollments` | `GET`, `POST`, `PATCH /{id}` | `enroll_students`; el alumno sólo ve las suyas |
| `/registration-requests` | `GET`, `POST /{id}/approve`, `POST /{id}/reject` | `approve_requests` |
| `/progress` | `GET`, `POST /{lesson}/complete` | Matrícula activa del propio usuario |
| `/announcements` | `GET`, `POST` | Lectura: matriculados. Escritura: `manage_announcements` |
| `/certificates` | `GET`, `GET /verify/{code}` | Verificación pública, mínima: nombre, curso, fecha |
| `/students` | `GET` | `view_students`, limitado a las ediciones del instructor |

Hoy está implementado `/courses` como referencia del patrón; el resto llega con su módulo.

---

## 8. Integración WooCommerce (opcional)

Se carga sólo si `class_exists( 'WooCommerce' )`.

**Campos añadidos al producto:** curso relacionado, edición relacionada, acción posterior a la compra.

**Hooks previstos:**

| Hook | Qué hace |
|---|---|
| `woocommerce_order_status_processing` / `_completed` | Según configuración, crea la matrícula |
| `woocommerce_order_status_refunded` / `_cancelled` | Aplica la política elegida: nada, suspender, cancelar o revocar |
| `woocommerce_product_data_tabs` / `_panels` | Pestaña "LMS" en el producto |
| `woocommerce_process_product_meta` | Guarda curso y edición con nonce y capacidad |
| `woocommerce_thankyou` | Enlace directo al Campus tras la compra |

El manejador de pedidos es idempotente: busca el usuario por email, crea la matrícula si no existe
(el índice único la protege), guarda `order_id`, registra en auditoría y dispara
`enrollment_created`. **Nunca borra historial** en reembolsos: cambia estados (punto 24).

---

## 9. Proceso de instalación (punto 64)

Al activar, `Database\Installer::activate()`:

1. Ejecuta el migrador (crea las 16 tablas y guarda `av_db_version`).
2. Crea los tres roles y otorga las capacidades al `administrator`.
3. Añade las opciones por defecto **sin sobrescribir** valores existentes.
4. Crea la página del Campus si no existe (`[av_campus]`), reutilizando una página existente
   con el mismo slug antes de crear otra. Login, recuperación de contraseña y catálogo se
   crearán con su módulo: publicar ahora esas páginas mostraría el shortcode en crudo.
5. Registra el CPT y hace `flush_rewrite_rules()` (necesario para los permalinks del curso).
6. Guarda `av_version` y dispara `aula_virtual/activated`.

Al desactivar: sólo `flush_rewrite_rules()`. **No se borra nada.**

Al desinstalar (`uninstall.php`): no hace nada salvo que `av_delete_data_on_uninstall` esté
activada (por defecto **OFF**). Si lo está, elimina tablas, roles y opciones `av_*`, y deja
intactos los posts de curso, que son contenido normal de WordPress.

---

## 10. Seguridad (punto 47)

Aplicado ya en el Core, y obligatorio en cada módulo siguiente:

- **SQL**: los repositorios sólo aceptan columnas declaradas en un mapa `columna => placeholder`.
  Una columna no declarada lanza excepción, así que ningún identificador llega interpolado a la
  consulta y todos los valores pasan por `$wpdb->prepare()`. `ORDER BY` se valida contra el mismo
  mapa. Hay pruebas que verifican los tres casos.
- **Entrada**: `Security\Sanitizer` normaliza texto, textarea, HTML (`wp_kses_post`), URL, email,
  entero, booleano, clave, enum cerrado, fecha y listas. Nada llega crudo a la base de datos.
- **Salida**: escapado en el punto de render (`esc_html`, `esc_attr`, `esc_url`), nunca al guardar.
- **Permisos**: `AccessControl` centraliza las comprobaciones; los meta del curso llevan
  `auth_callback` propio; toda ruta REST lleva `permission_callback`.
- **Formularios**: nonce obligatorio + capacidad + verificación de propiedad, en ese orden.
- **Uploads**: validación de MIME real con `wp_check_filetype_and_ext()` y lista blanca de
  extensiones; los materiales van a la Media Library de WordPress, nunca a rutas propias.
- **Contraseñas**: no se guardan ni se envían. Las altas usan enlace seguro de establecimiento de
  contraseña (`get_password_reset_key()`), punto 20.
- **Privacidad**: el log guarda ids y contexto, no datos personales; la verificación pública de
  certificados devuelve el mínimo imprescindible.

---

## 11. Rendimiento y trabajos en segundo plano (puntos 50 y 51)

- Índices compuestos pensados para las consultas reales de listado, no uno por columna.
- Paginación obligatoria (`Repository::paginate()`, máximo 200 filas por página).
- `course_id` desnormalizado para no unir tres tablas en cada listado.
- Progreso y última actividad cacheados en `enrollments`.
- Todas las opciones se registran con `autoload = false`.
- Transients para conteos de dashboard, invalidados por evento.
- **Cola**: Action Scheduler cuando esté disponible (viene con WooCommerce); si no, respaldo con
  WP-Cron y lotes de tamaño acotado. Trabajos por lotes: importación Excel, emails masivos,
  generación de certificados, avisos de clase próxima y de expiración de acceso.

---

## 12. Plan de desarrollo

**Entrega 1 — Core (hecha)**
Bootstrap, contenedor, providers, esquema completo con migrador, Repository base, roles y
capacidades, `AccessControl`, CPT de curso con meta y taxonomías, EventBus, Logger, AuditLog,
Sanitizer, base REST + `/courses`, instalación/desinstalación.

**Entrega 2 — Vertical fina (hecha)**
Un camino completo y estrecho de punta a punta, para poder instalar el plugin y verlo andar:

- **Ediciones**: estados, ventana de acceso, código único autogenerado, validación de fechas.
- **Temario**: lecciones por edición, tipos, orden, reordenado que ignora ids ajenos.
- **Matrículas**: alta manual idempotente, control de cupo, estados, rol de alumno automático.
- **Progreso**: marcar sesión completada, porcentaje cacheado en la matrícula, cierre automático
  del curso al completar todas las sesiones.
- **Administración**: menú Aula Virtual, listado y alta de ediciones, detalle con alta rápida de
  sesiones y matrícula manual.
- **Campus**: shortcode `[av_campus]` con login, lista de mis cursos, temario con progreso y
  vista de sesión con "marcar como completada".

Lo que la vertical deja fuera a propósito: módulos del temario, materiales, clases en vivo,
solicitudes con enlace privado, importación Excel, WooCommerce, emails y anuncios.

**Entregas 3 a 7 (hechas, 2026-09-28)**
Inscripción con aprobación y emails editables (0.3), matrícula por compra en WooCommerce
(0.4), migración desde Tutor LMS (0.5), landing por secciones (0.6), perfil del alumno,
editor de sesión, clases en vivo y materiales (0.7). El detalle operativo está en
`docs/GUIA-ADMINISTRADOR.md`; el técnico en `docs/GUIA-TECNICA.md`.

**Pendiente del MVP:** importación por Excel, anuncios, duplicado de curso y de edición,
content drip aplicado en el campus, URLs limpias del campus, pantalla de configuración.

**Core-2 — Contenido (parcialmente hecho):** duplicado de curso y de edición, registry de
proveedores de vídeo.

**Core-3 — Personas:** solicitudes y enlaces privados, ficha de alumno, modo estricto de
progreso, extensión y expiración de accesos.

**Core-4 — Entradas y salidas:** WooCommerce, importación Excel por lotes, plantillas de email,
notificaciones suscritas a eventos.

**Core-5 — Campus completo:** login propio con diseño configurable, URLs limpias
(`/campus/curso/{codigo}/`), anuncios, configuración y diseño global.

**Fase 2 (punto 69):** calendario avanzado, certificados, quizzes, tareas, reseñas, reportes
avanzados, content drip, notificaciones automáticas.

**Fase 3 (punto 70):** Zoom/Meet API, WhatsApp, SCORM, gamificación, membresías, multi-instructor,
API externa, app móvil.

Cada módulo sigue el ciclo del punto 71: archivos afectados → modelo de datos → hooks → permisos →
implementación → revisión de seguridad → pruebas → documentación → validación → verificación de que
no rompe lo anterior. `CHANGELOG.md` y las migraciones se actualizan en cada entrega.

---

### 12.1 Rutas del campus: decisión provisional

El campus vive hoy en una sola página con vistas seleccionadas por argumentos de consulta
(`?av_edicion=` y `?av_leccion=`). Es deliberado: funciona con cualquier tema y cualquier
estructura de enlaces permanentes, sin tocar reglas de reescritura ni obligar a vaciar
permalinks al activar. Las URLs limpias del punto 25 del brief llegan en Core-5, y el
`CampusController` ya concentra la construcción de URLs en un solo método para que ese cambio no
se propague por las plantillas.

## 13. Criterio de aceptación del Core

El flujo completo del punto 75 (compra → matrícula → campus → clase en vivo → progreso →
certificado, sin pisar wp-admin) es el criterio de la **primera versión comercial**, no de esta
entrega. Lo que esta entrega debe permitir afirmar es que el esqueleto lo soporta:

- el esquema cubre las 15 entidades del flujo y sus índices;
- las matrículas duplicadas son imposibles a nivel de base de datos;
- los eventos que dispararán los emails ya existen y están desacoplados;
- los permisos de cada paso están definidos y centralizados;
- instalar, actualizar y desinstalar no destruyen datos.

---

## 14. Cómo verificar esta entrega

```bash
cd wordpress-plugin/aula-virtual
php -l aula-virtual.php && php -l uninstall.php     # sintaxis
php tests/smoke-test.php                       # 139 comprobaciones
php tests/lint-classes.php                     # carga las 80 clases del plugin
composer install && composer lint              # WordPress Coding Standards (opcional)
```

Ambas comprobaciones corren sin WordPress, con stubs mínimos (`tests/wp-stubs.php`). El smoke
test cubre contenedor, esquema, repositorio (incluida la defensa contra columnas no declaradas),
sanitizador, matriz de capacidades, ventana de acceso de la edición, estados de matrícula,
aritmética del progreso y el cableado completo del contenedor: resuelve el grafo de dependencias
del campus y del administrador, de modo que un servicio mal registrado falla aquí y no en
producción.

---

## 15. Decisiones que necesitan confirmación antes de Core-2

1. **Nombre comercial y prefijos.** `Aula Virtual` / `aula-virtual` es temporal (punto 74). Cambiarlo después
   de tener datos en producción obliga a renombrar tablas y opciones: conviene decidirlo ahora.
2. **Lecciones como tabla** (propuesto) frente a CPT. La propuesta prioriza rendimiento; el coste es
   no tener Gutenberg dentro de la lección en el MVP.
3. **Slug público del curso**: hoy `/curso/{slug}/`, configurable. Confirmar antes de que se indexe.
4. **Zona horaria por edición** frente a una global del sitio. La propuesta es por edición.
5. **Instructor único por edición** en el MVP; multi-instructor queda en fase 3 (punto 70).
6. **Política de reembolso por defecto**: propuesta "suspender matrícula" (reversible) en lugar de
   "cancelar".
7. **Retención de logs**: propuesta de purga automática a 90 días para `logs`, sin purga para
   `audit_log`.

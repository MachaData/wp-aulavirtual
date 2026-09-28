# API REST — `aula-virtual/v1`

Base: `https://astronumerologia.com/campus/wp-json/aula-virtual/v1`

Pensada para que el WordPress de la tienda (o cualquier integrador) lea el catálogo de
ediciones, envíe inscripciones desde sus formularios y, con credenciales, gestione matrículas.

## Autenticación

| Uso | Mecanismo |
|---|---|
| Catálogo (`GET /editions`, `GET /editions/{id}`) | Público |
| Inscribir desde la tienda (`POST /registrations`) | Cabecera `X-AV-Key` con la clave de *Configuración → Avanzado → Clave de integración*, **o** un usuario autenticado con `av_enroll_students` |
| Todo lo demás | *Application Passwords* de WordPress (Basic auth sobre HTTPS) de un usuario con la capacidad indicada |

La clave de integración vive en el servidor de la tienda (constante en `wp-config.php`),
nunca en JavaScript. Vacía = acceso por clave desactivado.

## Rutas

| Método | Ruta | Requiere | Parámetros | Respuesta |
|---|---|---|---|---|
| GET | `/editions` | — | `course`, `status`, `open_only`, `page`, `per_page` | Lista + cabeceras `X-WP-Total`, `X-WP-TotalPages` |
| GET | `/editions/{id}` | — | — | Edición |
| GET | `/editions/{id}/students` | `av_view_students` | `page`, `per_page`, `status` | Alumnos paginados |
| POST | `/registrations` | `X-AV-Key` o `av_enroll_students` | `edition_id`*, `email`*, `first_name`, `last_name`, `phone`, `document`, `notes` | 201 `{request_id, edition_id, status, message}` |
| GET | `/enrollments?email=` | `av_enroll_students` | `email`* | Usuario y sus matrículas |
| POST | `/enrollments` | `av_enroll_students` | `edition_id`*, `email`*, `first_name`, `last_name`, `phone`, `document`, `status`, `send_welcome`, `notes` | 201 `{enrollment_id, user_id, created_user, status}` |
| PATCH | `/enrollments/{id}` | `av_enroll_students` | `status`* | Matrícula actualizada |

Sin autenticación solo se listan ediciones `upcoming`, `open`, `running` y `finished` de cursos
publicados. Las `draft` y `archived` requieren `av_manage_editions` o `av_view_students`.

**Forma de una edición:** `id, code, name, course_id, course_title, status, status_label,
accepts_enrollments, modality, modality_label, start_date, end_date, timezone, schedule_days,
schedule_time, price_display, capacity, seats_taken, seats_available` (null si no hay cupo),
`product_id, checkout_url` (null sin producto o sin WooCommerce), `landing_url`.

## Comportamiento de `POST /registrations`

1. Valida que la edición exista y admita matrícula (410 si no).
2. Usa un enlace de inscripción activo de la edición (prefiere el etiquetado `api`); si no
   hay ninguno, lo crea.
3. Envía la solicitud con las mismas reglas que el formulario público: duplicados (409),
   cupo, correos al solicitante y al administrador.
4. El enlace creado **requiere aprobación** por defecto, igual que uno creado desde el admin:
   la solicitud queda pendiente en *Solicitudes*. Para matricular al instante desde la tienda,
   un add-on puede devolver `['label' => 'api', 'requires_approval' => false]` en el filtro
   `aula_virtual/rest_registration_link_args`.
5. Límite: 20 intentos por IP y hora (429). No aplica a usuarios con `av_enroll_students`.

## Ejemplos

```bash
BASE=https://astronumerologia.com/campus/wp-json/aula-virtual/v1
AUTH="usuario_api:xxxx xxxx xxxx xxxx xxxx xxxx"   # Application Password

# Catálogo público
curl -s "$BASE/editions?open_only=true&course=123&per_page=50"
curl -s "$BASE/editions/45"

# Alumnos de una edición
curl -s -u "$AUTH" "$BASE/editions/45/students?page=1&per_page=100&status=active"

# Inscripción desde la tienda
curl -s -X POST "$BASE/registrations" \
  -H "X-AV-Key: LA_CLAVE" -H "Content-Type: application/json" \
  -d '{"edition_id":45,"first_name":"Carlos","last_name":"Mendoza","email":"carlos@example.com","phone":"+51 955 000 000"}'

# Matrículas de un correo
curl -s -u "$AUTH" "$BASE/enrollments?email=carlos@example.com"

# Matricular (crea el usuario si hace falta, sin correo de bienvenida)
curl -s -X POST "$BASE/enrollments" -u "$AUTH" -H "Content-Type: application/json" \
  -d '{"edition_id":45,"email":"carlos@example.com","first_name":"Carlos","status":"active","send_welcome":false}'

# Cambiar estado
curl -s -X PATCH "$BASE/enrollments/987" -u "$AUTH" -H "Content-Type: application/json" -d '{"status":"suspended"}'
```

## Notas para el WordPress de la tienda (Elementor)

1. **Botón de compra.** Leer `GET /editions?course={id}&open_only=true` y usar `checkout_url`
   (lleva al checkout del campus con el producto en el carrito). Si es `null` (edición
   gratuita o sin producto), apuntar a `landing_url` o al formulario propio. Mostrar
   `seats_available`, `start_date`, `schedule_days`, `price_display`. En Elementor el botón
   puede llevar la URL fija del checkout, o un shortcode PHP que consulte la API con
   `wp_remote_get` y guarde el resultado en un transient de 5 a 10 minutos. No llamar a la
   API desde el navegador en cada carga.
2. **Formulario de inscripción.** En el hook del formulario (`elementor_pro/forms/new_record`
   o un `admin_post_` propio) hacer `wp_remote_post` a `/registrations` con la cabecera
   `X-AV-Key`. Interpretar: 201 → mostrar `message`; 409 → "ya estás inscrito"; 410 → edición
   cerrada; 429 → reintentar más tarde. Un honeypot en el formulario sigue siendo recomendable.
3. **Una edición por producto WooCommerce.** La siguiente cohorte es otro producto y otra
   edición; la API devuelve siempre el `product_id` vigente.

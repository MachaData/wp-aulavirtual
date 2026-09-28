# Guía del administrador

Cómo operar Aula Virtual día a día. Todo se hace desde el menú **Aula Virtual** de wp-admin;
el alumno nunca entra a wp-admin.

## 1. Conceptos en un minuto

- **Curso**: el producto educativo, con su landing pública. Se crea en *Aula Virtual → Cursos*.
- **Edición**: una cohorte del curso (Julio 2027, Noviembre 2027...). Tiene fechas, horario,
  cupo, producto de WooCommerce, sesiones y alumnos propios.
- **Sesión**: cada clase de una edición. Puede ser video, texto, clase en vivo o material.
- **Matrícula**: un alumno dentro de una edición. Llega por compra, por inscripción aprobada,
  por alta manual o por migración.

## 2. Crear un curso

*Aula Virtual → Cursos → Añadir*. Título, descripción, imagen destacada, categoría. Los campos
comerciales (nivel, duración, instructor, beneficios, requisitos, FAQ, precio informativo)
están en el panel lateral y alimentan la landing.

La caja **Landing del curso** (debajo del editor) tiene las 8 secciones de la landing en orden.
Cada una se puede apagar con su casilla. Guardar el curso guarda la landing. El enlace *Ver
landing* la abre en el sitio.

## 3. Crear una edición

*Aula Virtual → Ediciones → Nueva edición*:

| Campo | Para qué |
|---|---|
| Curso | A qué curso pertenece |
| Nombre | Cómo la identificas: "Julio 2027" |
| Código | Se genera solo; aparece en URLs y reportes |
| Modalidad | Grabado, en vivo o híbrido |
| Estado | Solo *Próxima*, *Matrícula abierta* y *En curso* admiten matrículas |
| Inicio y fin | Fechas de la cohorte |
| Ventana de acceso | Desde y hasta cuándo el alumno puede entrar al contenido. Vacío = sin límite |
| Días y horario | Texto libre: "Martes y jueves", "19:00 - 21:00". Va a la landing y a la bienvenida |
| Precio y producto | Precio informativo y el ID del producto de WooCommerce. **Con producto la edición es de pago; sin producto es gratuita** |
| Cupo | 0 = sin límite |

Al guardar se abre el detalle de la edición con cuatro bloques: enlace de inscripción,
sesiones, alumnos y matrícula manual.

## 4. Sesiones

En el detalle de la edición, el formulario rápido crea una sesión con título, tipo y video.
Al pulsar el título se abre el **editor de sesión**, con tres zonas:

- **Contenido**: título, tipo, estado, descripción, video (YouTube, Vimeo, Bunny, MP4),
  duración, contenido enriquecido y liberación programada.
- **Clase en vivo**: plataforma (Zoom, Meet, Teams, otra), inicio y fin **en la hora de la
  edición**, enlace, ID y código, cuántos minutos antes aparece el botón y cuántos después
  desaparece, mensaje para el alumno y URL de la grabación cuando termine. El enlace de la
  reunión solo se muestra al alumno dentro de esa ventana.
- **Materiales**: archivos de la biblioteca de medios (PDF, Office, ZIP, imágenes, audio,
  video) o enlaces externos. Se pueden marcar como "solo lectura" para ocultar la descarga.

## 5. Cómo llegan los alumnos

### Compra directa (sin aprobación)

1. En WooCommerce, crear un producto (o duplicar uno) y en su pestaña **Aula Virtual** elegir
   la edición que vende. Un producto vende una sola edición.
2. En la landing, el botón *Comprar* lleva directo al checkout de ese producto.
3. Al confirmarse el pago (pedido en *Procesando*), el comprador queda matriculado y recibe la
   bienvenida con el enlace para crear su contraseña. Si no tenía cuenta, se le crea.
4. Un reembolso o cancelación suspende la matrícula (configurable). Nada se borra.

### Inscripción con aprobación

1. En el detalle de la edición, **Generar enlace**. Copiar el enlace
   (`/inscripcion/xxxxxxxx/`) a la landing, WhatsApp o correo.
2. Quien lo abre llena nombre, apellido, correo, teléfono y documento. Recibe un correo de
   "solicitud recibida" y tú un aviso.
3. En *Aula Virtual → Solicitudes*: **Aprobar**. Si la edición es gratuita, queda matriculado.
   Si es de pago, recibe el correo de aprobación con el enlace de pago; al pagar, queda
   matriculado. Si pagó por Yape, Plin o transferencia, usa **Marcar pagado y matricular**.
4. **Rechazar** pide un motivo, que se envía al solicitante.

### Alta manual

En el detalle de la edición, elegir un usuario existente y **Matricular alumno**. Recibe la
bienvenida.

## 6. Alumnos y progreso

En el detalle de la edición se ve cada alumno con origen, estado y porcentaje. El alumno entra
por la página del campus, ve sus cursos, el temario y marca cada sesión como completada. Al
completar todas, la matrícula pasa a *Completada* y recibe el correo de curso finalizado.

En *Mis datos* el alumno cambia nombre, teléfono, documento y contraseña (pidiendo la actual).
El correo es de solo lectura: solo lo cambia un administrador desde *Usuarios*.

## 7. Emails

*Aula Virtual → Emails*. Cada correo es una plantilla con asunto y contenido editables, una
casilla para activarla y un botón para enviarte una prueba. Las variables van entre llaves:
`{{first_name}}`, `{{course_name}}`, `{{edition_name}}`, `{{start_date}}`, `{{schedule_days}}`,
`{{schedule_time}}`, `{{price}}`, `{{payment_url}}`, `{{set_password_url}}`, `{{campus_url}}`...
La lista completa está junto al editor.

Los correos salen por el sistema de correo de WordPress: si el sitio tiene un plugin SMTP, se
usa. Remitente configurable con las opciones `av_email_from_name` y `av_email_from_address`.

## 8. Landing del curso

La landing vive en la URL del curso (`/curso/nombre-del-curso/`). Se edita desde la caja
*Landing del curso* del propio curso:

| Sección | Qué edita |
|---|---|
| Presentación | Título, subtítulo, texto, video o imagen, fondo, botón |
| Beneficios | Lista, una por línea |
| Contenido | Texto, video, temario automático de la próxima edición o lista manual |
| Instructor | Nombre, bio, foto, redes |
| Información | Tarjeta por edición abierta (fechas, horario, cupo, botones) + datos extra |
| Precio | Precio, precio tachado, qué incluye, nota, botón |
| Preguntas frecuentes | Pregunta y respuesta |
| Inscripción / Compra | Título, texto, botón, WhatsApp, fondo |

Los botones *Comprar* e *Inscribirse* se resuelven solos con la edición abierta: no hay que
poner URLs a mano. Para usar la landing dentro de una página de Elementor: `[av_course_landing
id="ID"]` o solo el bloque de ediciones con `[av_course_editions id="ID"]`.

## 9. Migrar desde Tutor LMS

*Aula Virtual → Migrar desde Tutor* (aparece solo si hay cursos de Tutor). Un curso por
pulsación. Crea el curso, una edición "Alumnos actuales", los módulos y sesiones, y matricula a
los alumnos con su fecha, su progreso y si terminaron. No envía correos, no toca Tutor y se
puede repetir sin duplicar. Los alumnos se procesan de 300 en 300: si quedan pendientes, el
botón pasa a *Reprocesar alumnos*.

Hacerlo primero en un sitio de pruebas y comparar los conteos con los de Tutor.

## 10. Permisos

| Rol | Puede |
|---|---|
| Administrador de WordPress | Todo |
| Administrador LMS | Todo el plugin, sin tocar el resto de WordPress |
| Instructor LMS | Sus cursos: ediciones, sesiones, materiales, clases en vivo, alumnos, solicitudes |
| Estudiante LMS | Solo el campus |

## 11. Configuración por opciones (sin pantalla todavía)

| Opción | Valores | Por defecto |
|---|---|---|
| `av_wc_enroll_status` | `processing`, `completed` | `processing` |
| `av_wc_refund_action` | `none`, `suspend`, `cancel` | `suspend` |
| `av_enrollment_auto_approve` | `1`, `0` | `0` |
| `av_block_wp_admin` | `1`, `0` | `1` |
| `av_campus_slug` | slug | `campus` (`aula` si el sitio vive en `/campus/`) |
| `av_brand_color` | `#rrggbb` | `#1d4ed8` |
| `av_admin_notification_email` | correo | el del sitio |
| `av_log_level` | `debug`, `info`, `warning`, `error`, `off` | `info` |

Se cambian con `wp option update` o con un plugin de opciones hasta que exista la pantalla
de configuración.

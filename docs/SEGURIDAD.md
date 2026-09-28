# Capa de seguridad — Aula Virtual SIQA

Versión del plugin: **0.12.3** · Última revisión: 28 de septiembre de 2026

Este documento explica **quién puede hacer qué**, **cómo se protege cada parte** del plugin,
**qué datos personales guarda** y **qué debe configurarse en el servidor**. Cierra con el
resultado de la revisión de seguridad y lo que queda pendiente.

> Nada de esto se ha probado todavía contra un WordPress real. La sección 9 es la lista de
> comprobaciones de seguridad para el sitio de pruebas.

---

## 1. Actores y qué puede salir mal

| Actor | Qué puede hacer | Qué hay que impedir |
|---|---|---|
| Visitante anónimo | Ver landings, catálogo, formulario de inscripción, verificar certificados | Mandar correos en masa, averiguar quién está matriculado, ver cursos en borrador, descargar materiales |
| Alumno (`av_student`) | Entrar al campus, ver sus ediciones, completar sesiones, comentar, editar su perfil | Ver ediciones ajenas o sesiones no liberadas, actuar sobre datos de otros alumnos, entrar a wp-admin |
| Instructor (`av_instructor`) | Gestionar **sus** cursos: ediciones, sesiones, materiales, alumnos, solicitudes, anuncios, reportes | Tocar cursos de otros instructores, ver datos personales de alumnos ajenos, ejecutar código en el sitio |
| Administrador LMS (`av_admin`) | Todo el LMS: correos, configuración, importación, certificados, migración | Acceder a secretos por pantalla, dejar datos tras desinstalar |
| Sitio externo (tienda) | Leer el catálogo, enviar inscripciones con clave | Usar la clave para algo más que inscribir, saturar el sistema |

---

## 2. Roles y capacidades

El plugin crea tres roles. El administrador de WordPress recibe todas las capacidades del plugin.

| Capacidad | Alumno | Instructor | Admin LMS |
|---|:-:|:-:|:-:|
| `av_access_campus` (entrar al campus) | ✔ | ✔ | ✔ |
| Crear y editar **sus** cursos (`edit_av_courses`) | | ✔ | ✔ |
| Editar cursos **de otros** (`edit_others_av_courses`) | | | ✔ |
| `av_manage_editions`, `av_manage_curriculum`, `av_manage_materials`, `av_manage_live_classes` | | ✔ (sus cursos) | ✔ |
| `av_view_students`, `av_enroll_students`, `av_approve_requests` | | ✔ (sus cursos) | ✔ |
| `av_manage_announcements`, `av_view_reports` | | ✔ (sus cursos) | ✔ |
| `av_import_students` | | | ✔ |
| `av_issue_certificates` | | | ✔ |
| `av_manage_commerce`, `av_manage_prices` | | | ✔ |
| `av_manage_lms` (correos, configuración, migración) | | | ✔ |

**La capacidad nunca basta sola.** Toda acción de un instructor comprueba además que el curso
sea suyo (`AccessControl::can_manage_editions( course_id )`), que se apoya en el autor del
curso gracias a `map_meta_cap`. Ningún rol del plugin recibe `manage_options`,
`edit_others_posts` ni `unfiltered_html`.

**wp-admin para alumnos.** Con *Configuración → Matrículas → Bloquear wp-admin* (activo por
defecto), un usuario cuyo único rol es alumno es redirigido al campus y no ve la barra de
administración. Quedan exentos `admin-ajax.php`, el cron y `admin-post.php`, por donde el
campus envía sus formularios; cada uno de esos manejadores comprueba sesión, nonce y permisos.

---

## 3. Controles por capa

### 3.1 Formularios y acciones (CSRF)

- Toda escritura desde el admin o el campus va por `admin-post.php` con nonce propio de la
  acción (`check_admin_referer`), capacidad y propiedad del objeto.
- La duplicación de curso es un enlace GET con nonce ligado al id del curso.
- Las escrituras por REST dependen de la autenticación de WordPress (cookie + nonce REST o
  Application Password) más la capacidad y la propiedad.

### 3.2 Validación y saneado de entradas

- Todo dato pasa por `Security\Sanitizer` (texto, email, URL, enteros, enumeraciones, fechas)
  o por las funciones de WordPress equivalentes.
- El HTML de sesiones y anuncios se filtra con `wp_kses_post` al guardar y al mostrar: sin
  `<script>`, sin atributos `on*`, sin `javascript:`.
- Los iframes de video solo se aceptan de una lista blanca de hosts (YouTube, Vimeo, Bunny…).
- Los videos por shortcode solo aceptan reproductores: `video`, `audio`, `playlist`, `embed`,
  `presto_player`, `bunny_video` (ampliable con el filtro `aula_virtual/allowed_video_shortcodes`).
  Se valida al guardar y otra vez al mostrar.
- Los estados de matrícula siguen una tabla de transiciones: por ejemplo, no se pasa de
  *pendiente* a *completada*, una *rechazada* no se reactiva y reactivar exige cupo.

### 3.3 Salida (XSS)

- Plantillas del admin, del campus, de la landing y del certificado escapan con `esc_html`,
  `esc_attr`, `esc_url` y `esc_js`.
- **Correos:** cada variable se escapa antes de entrar a la plantilla HTML (texto con
  `esc_html`, enlaces con `esc_url`). El asunto se genera en texto plano. El cuerpo final vuelve
  a pasar por `wp_kses_post`.
- **Exportación CSV:** las celdas que empiezan por `=`, `+`, `-` o `@` se neutralizan con un
  apóstrofo para que Excel no las ejecute como fórmula.

### 3.4 Base de datos

- Las 16 tablas propias se consultan por un repositorio con **lista blanca de columnas** para
  `WHERE` y `ORDER BY`, y todo valor va por `$wpdb->prepare`.
- Los informes agregados también usan `prepare`; los nombres de columna son literales.

### 3.5 Archivos de materiales

- Extensiones permitidas: PDF, Office, OpenDocument, ZIP, imágenes, MP3, MP4, TXT, CSV. **No**
  se admiten `svg`, `html`, `js` ni `xml`.
- Solo se puede adjuntar un archivo propio (o con permiso de editar medios ajenos), que no sea
  material de otro curso y que no sea imagen del sitio (destacada, logo, icono).
- Con *Proteger materiales* activo, el archivo se mueve a `uploads/aula-virtual/private/` con
  **nombre aleatorio**, y la carpeta lleva un `.htaccess` que niega el acceso directo.
- La descarga pasa por `admin-post.php?action=av_download`, que comprueba la matrícula y envía
  `nosniff` y `noindex`. Los enlaces externos solo redirigen a `http` o `https`.
- Los archivos protegidos no aparecen en la API de medios ni en la biblioteca para quien no
  puede editarlos.

### 3.6 Importación de alumnos

- Máximo 5 MB y 5000 filas. Las fórmulas de Excel **no** se evalúan al leer.
- Nada se escribe ni se envía hasta el último paso. La sesión de importación queda ligada al
  usuario que subió el archivo.
- Las cuentas nuevas reciben una contraseña aleatoria de 32 caracteres que **nunca** se
  muestra ni se envía; el alumno la crea con el enlace seguro de WordPress.

### 3.7 API REST (`aula-virtual/v1`)

| Ruta | Autenticación | Control de objeto |
|---|---|---|
| `GET /editions`, `/editions/{id}` | Pública | Solo cursos publicados; el total no delata borradores |
| `GET /editions/{id}/students` | Application Password + `av_view_students` | Solo ediciones de cursos propios |
| `POST /registrations` | `X-AV-Key` o `av_enroll_students` | Mismas reglas que el formulario público |
| `GET/POST/PATCH /enrollments` | Application Password + `av_enroll_students` | Solo cursos propios; transiciones de estado; 404 idéntico exista o no la cuenta |

- La clave de integración se compara en tiempo constante (`hash_equals`), nunca se registra ni
  se devuelve, y vacía desactiva el acceso por clave.
- Las inscripciones por API solo reutilizan enlaces etiquetados `api`, nunca los creados por el
  administrador para otro público.

### 3.8 Límites de tasa

| Punto | Límite |
|---|---|
| Formulario público de inscripción | 10 envíos por IP y 3 por correo cada hora |
| `POST /registrations` sin clave | 20 por IP y hora |
| `POST /registrations` con clave | 300 por hora para toda la integración, y 3 por correo |
| Verificación de certificados | 30 códigos inválidos por IP y hora |
| Comentarios | 15 segundos entre comentarios del mismo alumno |

Los alias `nombre+algo@` cuentan como el mismo correo. Detrás de Cloudflare, usar el filtro
`aula_virtual/client_ip` para leer la IP real (ver sección 6).

### 3.9 Enumeración de personas

- El formulario de inscripción responde lo mismo si el correo ya estaba matriculado, si ya
  tenía una solicitud abierta o si es nuevo.
- Los mensajes de error del formulario salen de una lista cerrada; el texto nunca se toma de la
  dirección de la página.
- La página del certificado muestra nombre, curso, edición y fecha; **no** el correo.

### 3.10 Sesiones y contraseñas

- El plugin nunca guarda ni envía contraseñas. Los correos llevan el enlace de
  `get_password_reset_key`.
- El cambio de contraseña desde el campus exige la actual, mínimo 8 caracteres y confirmación,
  y **cierra todas las demás sesiones**.
- El correo del alumno es de solo lectura en el campus.

### 3.11 Secretos

- Claves de Bunny y de integración: en Configuración se muestran como campo de contraseña vacío;
  dejarlo en blanco conserva el valor. La auditoría registra que se cambió una opción, no su
  valor.
- Al navegador solo llegan URLs firmadas de video, con validez de 5 minutos a 24 horas.
- `hls.js` se carga desde cdnjs con **integridad SHA-512** (`integrity` + `crossorigin`): si el
  CDN sirviera otro archivo, el navegador no lo ejecuta.

---

## 4. Datos personales (Ley N° 29733)

| Dato | Dónde se guarda | Quién lo ve |
|---|---|---|
| Nombre, apellido, correo | Usuario de WordPress; solicitudes de inscripción | El propio alumno; instructores y admins de **sus** cursos |
| Teléfono, documento | Metadatos `av_phone`, `av_document`; solicitudes | Igual que arriba y en el CSV de la edición |
| Progreso, matrículas, comentarios | Tablas `av_progress`, `av_enrollments`, `av_lesson_comments` | Alumno; staff del curso. Otros alumnos solo ven el nombre visible en comentarios |
| Certificados | Tabla `av_certificates` | Público con el código: nombre, curso, fecha |
| Filas de una importación | Transients, máximo 24 horas | Solo el usuario que importa |

- **Registro de auditoría** (`av_audit_log`): guarda ids, estados y nombres de opciones. No
  guarda contenido de comentarios, contraseñas ni valores de secretos.
- **Registro de errores** (`av_logs`): los fallos de correo guardan un hash del destinatario,
  no el correo ni el asunto.
- **Desinstalación:** solo con la opción explícita *Eliminar todos los datos al desinstalar*.
  Borra las 16 tablas, las opciones del plugin (lista explícita, no todo lo que empiece por
  `av_`), sus transients, `av_phone` y `av_document` de todos los usuarios, y quita las
  capacidades. No borra los cursos (entradas de WordPress) ni los usuarios.
- **Pendiente de decisión legal:** texto de consentimiento en el formulario de inscripción y
  política de retención de solicitudes rechazadas.

---

## 5. Resultado de la revisión de seguridad

Dos revisores independientes leyeron todo el código en las versiones 0.12.1 y 0.12.2. No hubo
hallazgos críticos (ni inyección SQL, ni ejecución de código, ni acceso anónimo indebido).

| Severidad | Hallazgo | Estado |
|---|---|---|
| Alta | Bloqueo de wp-admin cortaba el campus para alumnos | Corregido en 0.12.2 |
| Alta | Editor de sesión con error fatal | Corregido en 0.12.2 (+ prueba genérica) |
| Alta | Instructor podía adjuntar y mover archivos de otros cursos o del sitio | Corregido en 0.12.2 y 0.12.3 |
| Alta | API: instructor veía y cambiaba alumnos y matrículas de cursos ajenos | Corregido en 0.12.3 |
| Alta | Formulario público sin límite: envío masivo de correos a terceros | Corregido en 0.12.3 |
| Media | Shortcodes arbitrarios en videos de sesión | Corregido en 0.12.2 |
| Media | Sesiones en borrador accesibles por URL | Corregido en 0.12.2 |
| Media | Solicitudes de cursos ajenos visibles para instructores | Corregido en 0.12.2 |
| Media | Formulario revelaba si un correo estaba matriculado | Corregido en 0.12.3 |
| Media | API reutilizaba enlaces del administrador | Corregido en 0.12.3 |
| Media | Inyección de fórmulas en el CSV | Corregido en 0.12.3 |
| Media | Materiales protegidos dependían solo de `.htaccess` | Mitigado en 0.12.3 (nombre aleatorio, ocultos en API de medios). En Nginx sigue haciendo falta la regla |
| Baja | Correos con HTML de formularios; asuntos con entidades | Corregido en 0.12.1 y 0.12.2 |
| Baja | Cambio de contraseña no cerraba otras sesiones | Corregido en 0.12.2 |
| Baja | Importación: tamaño, fórmulas, sesión no ligada al usuario | Corregido en 0.12.2 y 0.12.3 |
| Baja | Certificados: hash sin separador, sin límite de consultas | Corregido en 0.12.3 (acepta el hash anterior) |
| Baja | Mensajes de error inyectables en la URL del formulario | Corregido en 0.12.3 |
| Baja | Enlace externo de material con esquemas no web | Corregido en 0.12.3 |
| Baja | `hls.js` sin integridad | Corregido en 0.12.3 |
| Baja | Token de video sin tope de validez | Corregido en 0.12.3 (máximo 24 h) |
| Baja | Total del catálogo delataba cursos en borrador | Corregido en 0.12.3 |
| Baja | Desinstalación borraba opciones por prefijo y dejaba DNI/teléfono | Corregido en 0.12.3 |
| Baja | Registro de errores de correo con datos personales | Corregido en 0.12.2 |

Cada corrección tiene su comprobación en `tests/smoke-test.php` cuando es comprobable sin
WordPress (263 comprobaciones en total).

---

## 6. Pendientes conocidos

Son de severidad baja y no bloquean el uso en pruebas.

1. **Listados generales para instructores.** Un instructor ve la lista de todas las ediciones,
   los anuncios y el resumen de reportes del sitio, aunque no los datos personales ni las
   acciones sobre cursos ajenos. Si habrá varios instructores externos, conviene filtrarlos.
2. **Avisos en la dirección de la página.** En el admin y en el perfil del campus, el texto del
   aviso viaja en la URL (escapado). Alguien podría fabricar un enlace con un mensaje falso.
   Solución prevista: guardar el aviso en un transient por usuario.
3. **Enlaces de inscripción sin límite de usos por defecto.** El límite de tasa frena el abuso,
   pero un enlace filtrado sigue sirviendo indefinidamente. Recomendación operativa: poner
   fecha de vencimiento o número de usos al crear enlaces que se publiquen abiertamente.
4. **Certificado en PDF firmado.** Hoy es una página imprimible; no hay PDF generado en
   servidor.

---

## 7. Configuración recomendada del servidor

1. **HTTPS obligatorio** en todo el sitio. Application Passwords no funciona sin HTTPS.
2. **Nginx:** añadir al bloque del servidor, antes de las reglas de PHP:

   ```nginx
   location ~* /wp-content/uploads/aula-virtual/private/ { deny all; return 403; }
   ```

   La expresión cubre también una instalación en subcarpeta como `/campus/wp-content/…`.

   Apache y LiteSpeed usan el `.htaccess` que crea el plugin. Comprobarlo con la sección 9.
3. **Cloudflare u otro proxy:** para que los límites de tasa usen la IP real, añadir en un
   plugin propio o en `functions.php`:

   ```php
   add_filter( 'aula_virtual/client_ip', function ( $ip ) {
       return isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) : $ip;
   } );
   ```

   Hacerlo **solo** si el servidor acepta tráfico exclusivamente desde Cloudflare; si no,
   cualquiera podría falsear esa cabecera.
4. **Errores:** en producción, `WP_DEBUG_DISPLAY` en `false`. El `debug.log` fuera de la
   carpeta pública o protegido.
5. **Administradores:** doble factor para todas las cuentas con `av_admin` o administrador.
   Asignar `av_admin` solo a quien gestiona el LMS completo.
6. **Integración con la tienda:** crear un usuario dedicado para la API con rol `av_admin`,
   generar su Application Password y revocarla si el sitio de la tienda se ve comprometido.
   La clave `X-AV-Key` vive en `wp-config.php` de la tienda, nunca en JavaScript. Cambiarla si
   se sospecha que se filtró.
7. **Copias de seguridad diarias** de base de datos y de `uploads/` (incluida la carpeta
   protegida).
8. **Actualizaciones:** WordPress, WooCommerce y pasarelas al día. Revisar el plugin tras cada
   actualización mayor de WooCommerce.
9. **Correo:** SMTP autenticado con SPF, DKIM y DMARC para que los correos del campus no
   terminen en spam y no se puedan suplantar.

---

## 8. Cómo reportar un problema de seguridad

Escribir a **siqa.educativa@gmail.com** con el asunto "Seguridad Aula Virtual", sin publicar
el detalle en canales abiertos. Incluir versión del plugin, pasos para reproducir y el impacto.

---

## 9. Comprobaciones de seguridad en el sitio de pruebas

- [ ] Con un usuario **solo alumno**: `/wp-admin/` redirige al campus; completar una sesión,
      comentar, cambiar la contraseña y descargar un material **sí** funcionan.
- [ ] Tras cambiar la contraseña, la sesión abierta en otro navegador queda cerrada.
- [ ] Con un alumno, abrir la URL de una sesión en borrador y de una no liberada: acceso denegado
      o pantalla de bloqueo.
- [ ] Añadir un PDF como material: su URL directa bajo `/uploads/aula-virtual/private/` devuelve
      **403**; el enlace del campus lo descarga con el alumno y pide login sin sesión.
- [ ] `GET /wp-json/wp/v2/media/{id}` de ese PDF sin sesión: 403 o sin datos.
- [ ] Con dos instructores, A y B: A no ve solicitudes de B, no puede abrir ediciones de B desde
      el admin ni por `GET /editions/{id_de_B}/students` (403), ni cambiar matrículas de B por
      `PATCH /enrollments/{id}` (403).
- [ ] Como instructor, intentar adjuntar como material el logo del sitio o un archivo subido por
      otro usuario: rechazado.
- [ ] Como instructor, poner un video por shortcode `[woocommerce_checkout]`: se guarda vacío.
- [ ] Formulario público: inscribirse 4 veces con el mismo correo (o `correo+1@`, `+2@`…) en
      una hora: la cuarta muestra "demasiadas solicitudes". Inscribir un correo ya matriculado:
      la respuesta es igual que la de uno nuevo y no llega ningún correo.
- [ ] `https://…/inscripcion/{token}/?av_resultado=error&av_error=<b>hola</b>` muestra el
      mensaje genérico, no el texto de la URL.
- [ ] Inscribirse con nombre `=HYPERLINK("http://ejemplo.com";"x")`, exportar el CSV y abrirlo en
      Excel: la celda muestra el texto, no un enlace.
- [ ] Inscribirse con nombre `<b>Ana</b>`: el correo al administrador muestra las etiquetas como
      texto.
- [ ] Verificar 31 códigos de certificado inventados desde la misma IP: el 31 devuelve 429.
- [ ] `POST /registrations` sin clave ni sesión: 401 o 403. Con clave incorrecta: igual.
- [ ] En Configuración, las claves de Bunny y de integración aparecen vacías tras guardarlas.
- [ ] Con la opción de borrado activada, desinstalar en el sitio de pruebas: no quedan tablas
      `wp_av_*`, ni opciones del plugin, ni `av_phone`/`av_document` en `wp_usermeta`.

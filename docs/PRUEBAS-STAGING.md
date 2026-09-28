# Plan de pruebas en staging

Lista de verificación para la **primera activación real** del plugin. Nada de esto se ha
ejecutado todavía contra un WordPress: las comprobaciones automáticas cubren la lógica y el
cableado, no el SQL ni las pantallas. Cada punto se marca cuando pasa; lo que falle se anota
con el mensaje exacto.

## 0. Preparación

- [ ] Clonar `astronumerologia.com/campus/` (archivos + base de datos) a un subdominio de
      pruebas. Nunca probar en producción.
- [ ] Activar `WP_DEBUG` y `WP_DEBUG_LOG` en el staging.
- [ ] Instalar el plugin en `wp-content/plugins/aula-virtual/`.
- [ ] Configurar un SMTP de pruebas (o un plugin que capture correos) para ver los emails
      sin enviarlos a alumnos reales.
- [ ] En WooCommerce, activar una pasarela en modo prueba (Culqi sandbox) o "Transferencia
      bancaria".
- [ ] **Tutor tiene sus propias páginas `/cart` y `/checkout`.** Comprobar en *WooCommerce →
      Ajustes → Avanzado* que las páginas de carrito y finalizar compra son las de WooCommerce
      (con el bloque o shortcode de checkout de Woo), no las de Tutor. El botón *Comprar* del
      plugin usa la página de finalizar compra configurada en WooCommerce.
- [ ] *Aula Virtual → Configuración*: rellenar las pestañas General y Videos con los valores de
      la sección 0b (ya no hace falta `wp option update`).

## 0b. Opciones recomendadas para este sitio

Antes de la sección 2, definir en *Aula Virtual → Configuración*:

| Opción | Valor | Por qué |
|---|---|---|
| `av_brand_color` | `#3e64de` | Color principal actual del campus (Tutor → Diseño) |
| `av_bunny_library_id` | ID de la biblioteca de Stream | Convierte las playlist del CDN al embed oficial |
| `av_bunny_token_key` | clave de la biblioteca | Solo si la biblioteca exige token |
| `av_admin_notification_email` | correo de coordinación | A dónde llegan los avisos de solicitud |

`av_course_slug` se deja en `curso` mientras Tutor esté activo; se cambia a `cursos` al
retirarlo para conservar las URLs antiguas.

## 1. Activación

- [ ] Activar el plugin sin errores fatales ni avisos en `debug.log`.
- [ ] Existen las 15 tablas `wp_av_*` (`SHOW TABLES LIKE 'wp_av_%'`).
- [ ] Opción `av_db_version` = `1.1.0`.
- [ ] Roles `av_admin`, `av_instructor`, `av_student` en *Usuarios → Roles* (o con un plugin
      de roles).
- [ ] Página *Campus* creada como `/campus/aula/` con `[av_campus]`.
- [ ] *Aula Virtual → Emails* muestra 6 plantillas.
- [ ] Menú *Aula Virtual* con: Ediciones, Solicitudes, Importar alumnos, Anuncios, Emails,
      Migrar desde Tutor, Cursos, Configuración.
- [ ] Desactivar y reactivar: no se duplican páginas ni plantillas.

## 2. Curso y edición

- [ ] Crear curso "Prueba Numerología" con imagen destacada. Publicar.
- [ ] La URL del curso muestra la landing con 8 secciones y sin errores.
- [ ] Editar la caja *Landing del curso*: apagar Beneficios, cambiar el título de
      Presentación, subir una imagen de fondo, añadir 2 FAQ. Guardar. Ver que la landing lo
      refleja.
- [ ] Crear edición "Julio 2027", estado *Matrícula abierta*, días "Martes y jueves", horario
      "19:00 - 21:00", precio "S/ 250", cupo 3, **sin** producto.
- [ ] La landing muestra la tarjeta de la edición con esos datos.
- [ ] Crear 3 sesiones desde el formulario rápido. Abrir una, poner un video de YouTube y
      guardar. Abrir otra, programar clase en vivo para dentro de 10 minutos con un enlace
      cualquiera. Añadir un PDF como material a la tercera.
- [ ] Intentar añadir un `.php` como material: debe rechazarse.

## 3. Inscripción con aprobación (edición gratuita)

- [ ] Generar enlace de inscripción. Abrirlo en ventana privada: formulario dentro del tema.
- [ ] Enviar con un correo nuevo. Pantalla "Recibimos tu inscripción".
- [ ] Llegan dos correos: al solicitante y al administrador.
- [ ] Enviar otra vez con el mismo correo: no se crea segunda solicitud.
- [ ] *Solicitudes*: aparece pendiente. **Aprobar y matricular**.
- [ ] Se creó el usuario con rol `av_student` y llegó la bienvenida con
      `{{set_password_url}}` funcionando (abre la pantalla de crear contraseña).
      **Atención:** Tutor tiene activado "Enable Tutor LMS Login", que sustituye
      `wp-login.php`. Si el enlace no abre la pantalla de crear contraseña, desactivar esa
      opción en *Tutor → Ajustes → Avanzado* y repetir.
- [ ] Rechazar otra solicitud con motivo: llega el correo con el motivo.

## 4. Campus del alumno

- [ ] Con el usuario nuevo, entrar a `/campus/aula/`. Login → Mis cursos con la tarjeta.
- [ ] Intentar entrar a `/campus/wp-admin/`: redirige al campus.
- [ ] Abrir el temario: 3 sesiones. Abrir la de video: se ve el reproductor.
- [ ] Abrir la de clase en vivo antes de la hora: mensaje "aparecerá poco antes". Esperar a
      que entre en la ventana (o ajustar "mostrar desde" a 60 min): aparece **Entrar a la
      clase** con el enlace. Poner una URL de grabación desde el admin: se ve la grabación.
- [ ] Abrir la de material: el PDF se descarga.
- [ ] Marcar las 3 como completadas. La barra llega a 100%, la matrícula pasa a *Completada*
      en el admin y llega el correo de curso finalizado.
- [ ] *Mis datos*: cambiar teléfono y contraseña (pidiendo la actual). Volver a entrar con la
      nueva.

## 5. Compra directa por WooCommerce

- [ ] Crear producto "Numerología Julio 2027" a S/ 250. Pestaña *Aula Virtual* → elegir la
      edición. Guardar. En la edición aparece el producto.
- [ ] En la landing, el botón *Comprar* lleva al checkout con el producto ya en el carrito.
- [ ] Comprar como invitado con un correo nuevo, pagando con la pasarela de prueba.
- [ ] Al pasar el pedido a *Procesando*: se creó el usuario, la matrícula (origen
      `woocommerce`, con el número de pedido) y llegó la bienvenida. El pedido tiene una nota
      "alumno matriculado".
- [ ] Página de gracias: bloque "Tu acceso al curso".
- [ ] Reembolsar el pedido: la matrícula pasa a *Suspendida* y el alumno ya no ve el temario.
- [ ] Marcar el pedido *Completado* de nuevo: no se duplica la matrícula.

## 6. Inscripción con aprobación (edición de pago)

- [ ] Con la edición ya vinculada al producto, enviar una inscripción y aprobarla: el estado
      queda "Aprobada, pago pendiente" y el correo lleva `{{payment_url}}` al checkout.
- [ ] Pagar desde ese enlace: la solicitud pasa a *Matriculada* sola.
- [ ] Con otra solicitud aprobada, usar **Marcar pagado y matricular**: queda matriculada sin
      pasar por WooCommerce.

## 7. Cupo y estados

- [ ] Con cupo 3 y 3 matriculados, una cuarta aprobación falla con "alcanzó su cupo".
- [ ] Pasar la edición a *Finalizada*: el enlace de inscripción muestra "ya no admite
      inscripciones" y la landing deja de mostrar la tarjeta.

## 8. Migración desde Tutor LMS

- [ ] *Migrar desde Tutor* lista los 3 cursos con sus conteos. Anotar: lecciones, alumnos,
      terminaron.
- [ ] Migrar el curso con menos alumnos. Comparar el resultado con los conteos anotados.
- [ ] Abrir la edición "Alumnos actuales": sesiones en el mismo orden que en Tutor, con sus
      videos. Alumnos con fecha original.
- [ ] Entrar al campus con un alumno migrado que había completado lecciones: aparecen como
      completadas y el porcentaje coincide.
- [ ] Volver a pulsar *Reprocesar alumnos*: 0 matriculados nuevos, todo omitido.
- [ ] Confirmar que **no llegó ningún correo** a los alumnos migrados.
- [ ] Repetir con los otros dos cursos.

## 8a. Duplicar, reordenar, importar y repetir

- [ ] En el detalle de la edición, mover una sesión con ↑ y ↓: el orden cambia también en el
      temario del alumno.
- [ ] *Duplicar edición* con nueva fecha de inicio 30 días después: aparece la copia en
      *Borrador* con las 3 sesiones, la clase en vivo 30 días más tarde y sin grabación, los
      materiales y ningún alumno.
- [ ] En *Cursos*, *Duplicar* sobre el curso de prueba: se abre el editor del nuevo curso en
      borrador, con imagen, landing y una edición "Copia de …" con el temario.
- [ ] *Importar alumnos*: subir un CSV con 5 filas (una con correo inválido, una repetida y
      una ya matriculada). La revisión cuenta 2 válidas, 1 inválida, 1 repetida y 1 ya
      matriculada; no llegó ningún correo todavía.
- [ ] Confirmar **sin** marcar bienvenida: 2 matriculados con origen *excel*, usuarios nuevos
      con rol alumno y ningún correo.
- [ ] Repetir con otro CSV marcando bienvenida: llega el correo con el enlace para crear la
      contraseña (nunca una contraseña en claro).
- [ ] Subir un `.xlsx`: si `composer install` no se ejecutó, el mensaje indica que solo se
      admite CSV.
- [ ] Con un alumno al 100 %, pulsar *Volver a hacer el curso*: confirma, vuelve a 0 %, la
      matrícula pasa a *Activa* y las sesiones aparecen sin completar. Desactivar la opción en
      Configuración: el botón desaparece.

## 8b. Anuncios y modo enfoque

- [ ] *Anuncios*: publicar uno para la edición de prueba con envío por correo. Aparece en el
      panel del alumno y en el temario; llega el correo con título y contenido.
- [ ] Activar *modo enfoque* en Configuración → Matrículas y abrir una sesión: se ve a pantalla
      limpia, con el video y el botón de completar, y "Volver al temario" funciona.

## 9. Emails

- [ ] Editar la plantilla de bienvenida (añadir una línea) y enviarse una prueba.
- [ ] Desactivar la plantilla de "solicitud recibida" al administrador y enviar una
      inscripción: no llega el aviso.

## 10. Rendimiento y limpieza

- [ ] Con los alumnos migrados, la lista de la edición y el campus cargan en menos de 2 s.
- [ ] `debug.log` sin avisos del plugin tras todo lo anterior.
- [ ] Revisar `wp_av_logs`: sin errores; `wp_av_audit_log` tiene una fila por aprobación,
      matrícula y migración.

## Resultado

| Sección | Pasa | Observaciones |
|---|---|---|
| 1 Activación | | |
| 2 Curso y edición | | |
| 3 Inscripción gratuita | | |
| 4 Campus | | |
| 5 Compra WooCommerce | | |
| 6 Inscripción de pago | | |
| 7 Cupo y estados | | |
| 8 Migración | | |
| 9 Emails | | |
| 10 Rendimiento | | |

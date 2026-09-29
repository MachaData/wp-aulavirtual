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
| Zona horaria | En la que se programan y se muestran las clases en vivo |
| Precio y producto | Precio informativo y el ID del producto de WooCommerce. **Con producto la edición es de pago; sin producto es gratuita** |
| Cupo | 0 = sin límite |

Al guardar se abre la pantalla de la edición, organizada así:

- **Cabecera:** curso, nombre, estado en color, código, modalidad, fechas, horario y precio.
  Botones *Ver landing* y *Reporte*.
- **Tarjetas de resumen:** alumnos y plazas, sesiones publicadas, avance medio y solicitudes
  pendientes. Esta última se resalta en amarillo cuando hay algo que aprobar.
- **Pestañas:**
  - *Sesiones:* el temario en orden, con flechas para reordenar, estado y liberación
    programada. Debajo, el formulario para añadir una sesión.
  - *Alumnos:* cada matrícula con correo, origen, estado, barra de avance y fecha. Exportar
    a CSV, matricular a una persona o ir a la importación por Excel.
  - *Inscripción:* los enlaces de inscripción con su dirección, botón *Copiar*, si piden
    aprobación, los usos y *Desactivar*. Debajo, el formulario para crear otro enlace.
  - *Ajustes:* todos los datos de la edición para editarlos, y *Duplicar edición*.

Después de cada acción vuelves a la misma pestaña.

### Duplicar una edición

En la pestaña *Ajustes*, **Duplicar edición** crea una nueva edición en borrador del mismo curso
con las mismas sesiones (título, tipo, video, descripción, contenido, duración). Opciones:

- **Nombre**: si se deja vacío, "Copia de …".
- **Nueva fecha de inicio**: si se indica, las fechas de acceso y las de cada clase en vivo se
  desplazan los mismos días que hay entre el inicio original y el nuevo. Si se deja vacía, la
  copia queda sin fechas.
- **Clases en vivo**: se copian con la misma plataforma, enlace y duración, en estado
  *programada* y sin grabación.
- **Materiales**: se copian los enlaces a los mismos archivos de la biblioteca (no se duplican
  los archivos).

No se copian alumnos, enlaces de inscripción, anuncios ni el producto de WooCommerce. La copia
nace en *Borrador*: revisar fechas, estado y producto antes de abrirla.

### Duplicar un curso

En *Cursos*, al pasar el ratón sobre un curso aparece **Duplicar**. Crea un curso en borrador
con el mismo contenido, imagen, categorías, landing (con el título nuevo) y una edición
"Copia de …" con el temario de la edición más reciente. Sirve para lanzar una variante del
curso (por ejemplo, "Numerología básica" → "Numerología básica online") sin empezar de cero.

## 4. Sesiones

En el detalle de la edición, el formulario rápido crea una sesión con título, tipo y video.
Las flechas ↑ ↓ de la columna *Mover* cambian el orden; el alumno las ve en ese orden.
Al pulsar el título se abre el **editor de sesión**, con tres zonas:

- **Contenido**: título, tipo, estado, descripción, video (YouTube, Vimeo, Bunny, MP4),
  duración, contenido enriquecido y liberación programada. La liberación admite tres modos:
  *de inmediato*, *a partir de una fecha* o *X días después de la matrícula* de cada alumno.
  Una sesión no liberada aparece en el temario con "Disponible el …", no se puede abrir ni
  marcar como completada, y no cuenta como pendiente hasta que se abre.
- **Clase en vivo**: plataforma (Zoom, Meet, Teams, otra), inicio y fin **en la hora de la
  edición**, enlace, ID y código, cuántos minutos antes aparece el botón y cuántos después
  desaparece, mensaje para el alumno y URL de la grabación cuando termine. El enlace de la
  reunión solo se muestra al alumno dentro de esa ventana.
- **Materiales**: archivos de la biblioteca de medios (PDF, Office, ZIP, imágenes, audio,
  video) o enlaces externos. Se pueden marcar como "solo lectura" para abrirlos en el
  navegador en vez de descargarlos.

### Materiales protegidos

Con *Configuración → Materiales → Proteger los archivos* activado (por defecto), el archivo de
cada material nuevo se mueve a `uploads/aula-virtual/private/`. Esa carpeta lleva un
`.htaccess` que niega el acceso directo, así que la URL de la biblioteca de medios deja de
funcionar y el único camino es el enlace del campus, que comprueba la matrícula antes de
entregar el archivo. Para los materiales que ya existían (por ejemplo los migrados desde
Tutor), el botón **Mover ahora a la carpeta protegida** los traslada todos de una vez.

Dos avisos: el archivo movido ya no sirve para otros usos (una imagen protegida no se verá en
la landing), y en servidores Nginx el `.htaccess` no aplica; hay que añadir la regla que
indica la guía técnica.

## 4b. Videos: YouTube, Vimeo y Bunny

En el editor de sesión, el campo *Video* acepta cualquiera de estas fuentes; con "Detectar
automáticamente" basta pegar la URL:

| Fuente | Qué pegar |
|---|---|
| YouTube / Vimeo | La URL normal del video |
| **Bunny Stream** | La URL de embed (`iframe.mediadelivery.net/embed/{biblioteca}/{video}`), la URL de reproducción, o solo el GUID del video si la biblioteca está configurada |
| Archivo MP4 / WebM | La URL directa del archivo |
| Código incrustado | El `<iframe>` que da la plataforma. Solo se aceptan hosts conocidos (YouTube, Vimeo, Bunny, Drive, Loom, Wistia) |
| Shortcode | Por ejemplo, el de un reproductor externo |

**Cómo están hoy los videos en Tutor.** Las sesiones usan "URL externa" con la playlist del
CDN de Bunny (`https://vz-….b-cdn.net/{guid}/playlist.m3u8`). La migración las reconoce como
Bunny y el campus las reproduce de una de estas dos formas:

| Modo | Cuándo | Qué hace |
|---|---|---|
| **Embed de Stream** (recomendado) | `av_bunny_library_id` está configurada | Convierte la playlist en el reproductor oficial de Bunny (`iframe.mediadelivery.net/embed/{biblioteca}/{guid}`), con calidad adaptativa, y lo firma con la clave de la biblioteca si existe |
| **HLS directo** | Sin biblioteca configurada | Reproduce la playlist en el propio campus (en Chrome y Firefox carga `hls.js` desde cdnjs), firmándola con la clave del CDN si existe |

**Videos privados.** Bunny puede exigir un token en cada reproducción. Hay dos claves
distintas según el modo:

| Opción | Valor | Dónde está en Bunny |
|---|---|---|
| `av_bunny_library_id` | ID numérico de la biblioteca de Stream | Stream → biblioteca → la URL o la pestaña API |
| `av_bunny_token_key` | *Token Authentication Key* de la biblioteca (modo embed) | Stream → biblioteca → Security → Embed view token authentication |
| `av_bunny_cdn_token_key` | *Token Authentication Key* de la pull zone (modo HLS directo) | CDN → pull zone → Security → Token authentication |
| `av_bunny_token_ttl` | Segundos de validez del token. Por defecto 21600 (6 horas) | — |

Con la clave del modo en uso configurada, cada reproducción lleva un token que caduca: copiar
el enlace fuera del campus deja de servir pasadas esas horas. Sin claves, los videos se
reproducen sin firmar, que es lo que necesita una biblioteca pública. Recomendación para este
sitio: configurar `av_bunny_library_id` y `av_bunny_token_key`, y activar la autenticación por
token en la biblioteca de Stream.

## 5. Cómo llegan los alumnos

### Compra directa (sin aprobación)

1. En WooCommerce, crear un producto (o duplicar uno) y en su pestaña **Aula Virtual** elegir
   la edición que vende. Un producto vende una sola edición.
2. En la landing, el botón *Comprar* lleva directo al checkout de ese producto.
3. Al confirmarse el pago (pedido en *Procesando*), el comprador queda matriculado y recibe la
   bienvenida con el enlace para crear su contraseña. Si no tenía cuenta, se le crea.
4. Un reembolso o cancelación suspende la matrícula (configurable). Nada se borra.

### Inscripción con aprobación

1. En la pestaña *Inscripción* de la edición, **Crear enlace**. La dirección se propone a
   partir del código de la edición, por ejemplo `/inscripcion/numbasica-set2026/`, y se puede
   cambiar por otra fácil de dictar, como `/inscripcion/numerologia-setiembre/` (minúsculas,
   números y guiones). Marca *Enlace privado* si prefieres una dirección aleatoria que no se
   pueda adivinar. Elige también si revisas cada solicitud o si se aprueba sola.
   Copia el enlace con el botón *Copiar* y pégalo en la landing, WhatsApp o correo.
   Puedes crear varios (landing, WhatsApp, Instagram) para ver cuántas personas llegan por
   cada uno, y **Desactivar** los que ya no quieras usar.
2. Quien lo abre llena nombre, apellido, correo, teléfono y documento. Recibe un correo de
   "solicitud recibida" y tú un aviso.
3. En *Aula Virtual → Solicitudes*: **Aprobar**. Si la edición es gratuita, queda matriculado.
   Si es de pago, recibe el correo de aprobación con el enlace de pago; al pagar, queda
   matriculado. Si pagó por Yape, Plin o transferencia, usa **Marcar pagado y matricular**.
4. **Rechazar** pide un motivo, que se envía al solicitante.

### Alta manual

En el detalle de la edición, elegir un usuario existente y **Matricular alumno**. Recibe la
bienvenida.

### Importar desde Excel o CSV

*Aula Virtual → Importar alumnos*, en cuatro pasos:

1. **Archivo**: elegir la edición destino y subir un `.csv` o `.xlsx` con una fila de
   cabecera (máximo 5000 filas). Sirven las exportaciones de Google Forms, Tutor o una hoja
   hecha a mano. El separador (coma, punto y coma, tabulador) se detecta solo.
2. **Columnas**: el plugin adivina qué columna es el correo, nombre, apellido, teléfono y
   documento por el nombre de la cabecera (en español o inglés); se puede corregir. Solo el
   correo es obligatorio.
3. **Revisión**: cuántas filas son válidas, cuántos usuarios se crearán, cuántos ya existen,
   cuántos ya estaban matriculados (se omiten) y las filas inválidas con su motivo. Hasta aquí
   no se ha escrito nada ni enviado ningún correo.
4. **Importar**: se procesan 200 alumnos por pulsación de *Continuar*, para que ningún servidor
   corte la petición. Cada alumno nuevo se crea con rol de alumno y una contraseña aleatoria
   que nunca se envía; si se marcó **Enviar el correo de bienvenida**, recibe la plantilla de
   bienvenida con el enlace para crear su contraseña. Si no se marca, no sale ningún correo
   (útil para cargas históricas).

Las matrículas quedan con origen *excel* y estado *activa*. El resultado (creados,
matriculados, omitidos, fallidos y el detalle de los errores) se conserva en el historial de
importaciones.

## 6. Alumnos y progreso

En el detalle de la edición se ve cada alumno con origen, estado y porcentaje. El alumno entra
por la página del campus, ve sus cursos, el temario y marca cada sesión como completada. Al
completar todas, la matrícula pasa a *Completada* y recibe el correo de curso finalizado.

En *Mis datos* el alumno cambia nombre, teléfono, documento y contraseña (pidiendo la actual).
El correo es de solo lectura: solo lo cambia un administrador desde *Usuarios*.

### Direcciones del campus

Con enlaces permanentes activos, el campus usa direcciones limpias: `/aula/` (mis cursos),
`/aula/curso/{código-de-la-edición}/` (temario), `/aula/sesion/{id}/` (sesión) y
`/aula/perfil/` (mis datos). Si se renombra la página del campus, guardar de nuevo
*Ajustes → Enlaces permanentes* para regenerar las reglas.

### Preguntas y comentarios en las sesiones

Debajo de cada sesión hay un hilo de comentarios (activable en *Configuración → Matrículas →
Comentarios en las sesiones*). Los alumnos preguntan y responden; el instructor del curso
responde desde la misma sesión en el campus y su comentario sale con la etiqueta *Instructor*.
Cada alumno puede eliminar sus propios comentarios; el instructor puede eliminar cualquiera,
tanto desde el campus como desde el editor de la sesión, donde ve el hilo completo. Hay un
respiro de 15 segundos entre comentarios del mismo alumno.

Dos correos editables acompañan al hilo: *Nueva pregunta* al docente del curso (el autor del
curso; si no tiene correo, el de avisos al administrador) cuando escribe un alumno, y
*Respondieron a tu comentario* al autor del comentario respondido.

### Repetir el curso

Si en *Configuración → Matrículas* está activo **Permitir repetir el curso** (lo está por
defecto, como en Tutor), el alumno ve al final del temario el botón *Volver a hacer el curso*
(o *Reiniciar mi progreso* si no lo terminó). Tras confirmar, se borra su progreso en esa
edición, la matrícula vuelve a *Activa* con 0 % y empieza desde la primera sesión. El
certificado que ya tuviera se conserva y no se envía ningún correo.

## 6b. Certificados

Con *Configuración → Certificados → Emitir automáticamente* (activo por defecto), al completar
todas las sesiones el alumno recibe su certificado: aparece como *Ver certificado* en su panel y
en el temario, y el correo "Curso finalizado" puede incluir `{{certificate_url}}`. El
certificado es una página pública de verificación, `/certificado/{código}/`, con formato A4
apaisado y botón *Imprimir / Guardar como PDF*. Cualquiera con el enlace puede comprobar que es
válido; si se anula, la página lo dice.

En *Aula Virtual → Certificados* se listan por edición, se anulan y se emiten a mano (para un
alumno con acceso, aunque no haya completado). En Configuración se define quién firma, su cargo
y el logo (ID de una imagen de la biblioteca de medios).

## 6c. Reportes

*Aula Virtual → Reportes* muestra el resumen del sitio (ediciones y matrículas por estado,
alumnos, cursos completados, las diez ediciones con más matrícula) y, por edición: matrículas
por estado y origen, avance medio, tasa de finalización, activos en los últimos 7 días,
comentarios y el avance sesión por sesión. Desde ahí, o desde el detalle de la edición, se
exporta la lista de alumnos a CSV (separado por punto y coma, abre directo en Excel).

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

Los videos de Bunny, YouTube, Vimeo, URL externa e incrustados se conservan; los adjuntos de
cada lección y del curso pasan a ser materiales.

**Cohortes que hoy son cursos separados.** Si en Tutor tienes "Numerología Básica G1-2026",
"G3-2025" y "G4-2025" como tres cursos, migra el primero *como curso nuevo* y los otros dos
eligiendo *Edición de: Numerología Básica...* en el desplegable. Quedará un solo curso con una
landing y tres ediciones, cada una con sus alumnos y su temario.

Hacerlo primero en un sitio de pruebas y comparar los conteos con los de Tutor.

## 10. Permisos

| Rol | Puede |
|---|---|
| Administrador de WordPress | Todo |
| Administrador LMS | Todo el plugin, sin tocar el resto de WordPress |
| Instructor LMS | Sus cursos: ediciones, sesiones, materiales, clases en vivo, alumnos, solicitudes |
| Estudiante LMS | Solo el campus |

## 11. Configuración

*Aula Virtual → Configuración*, en pestañas:

| Pestaña | Qué se define |
|---|---|
| General | Color principal, correo de avisos al administrador, remitente de los correos, slug de las landings (`/curso/…`). Enlace a la página del campus para cambiar su slug |
| Matrículas | Aprobación automática de solicitudes, bloqueo de wp-admin a alumnos, **modo enfoque** (sesiones a pantalla limpia, sin cabecera ni pie del tema) |
| Videos | Bunny Stream: ID de biblioteca, clave de token de la biblioteca, clave de token del CDN, validez del token |
| WooCommerce | Cuándo matricula un pedido (procesando o completado) y qué hacer ante reembolso (nada, suspender, cancelar) |
| Avanzado | Nivel de registro y borrado de datos al desinstalar (apagado por defecto) |

Las claves se guardan pero nunca se muestran de vuelta; dejar el campo vacío conserva la
guardada. Cambiar el slug de las landings regenera los enlaces permanentes.

## 12. Anuncios

*Aula Virtual → Anuncios*. Un anuncio va a todas las ediciones de un curso o a una sola. Queda
visible en el campus (panel del alumno y temario de la edición) y, si se marca **Enviar
también por correo**, cada alumno matriculado recibe la plantilla "Nuevo anuncio" de *Emails*,
editable como las demás. Es el equivalente del correo manual de Tutor: un mensaje a los
alumnos de una edición, con su título y contenido.

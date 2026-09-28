# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).
Este plugin sigue versionado semántico.

## [0.10.0] - 2026-09-28

Duplicar, reordenar, importar alumnos por Excel/CSV y repetir el curso.

### Anadido

- Duplicar edicion desde el detalle: nueva edicion en borrador con sesiones, materiales y
  clases en vivo (sin grabacion, programadas); con nueva fecha de inicio, las fechas de acceso
  y de las clases en vivo se desplazan los mismos dias. No copia alumnos, enlaces, anuncios ni
  producto.
- Duplicar curso desde la lista de cursos: post en borrador con contenido, imagen, categorias,
  meta y landing, mas una edicion con el temario de la edicion mas reciente.
- Reordenar sesiones con flechas en el detalle de la edicion.
- Importar alumnos (menu propio, capacidad `av_import_students`): CSV o XLSX, deteccion de
  separador y BOM, mapeo de columnas adivinado por cabecera (es/en) y corregible, revision sin
  escribir nada, ejecucion por lotes de 200 con historial en `av_import_jobs`. Los usuarios
  nuevos se crean con contrasena aleatoria que nunca se envia; la bienvenida con enlace para
  crear contrasena es opcional.
- Repetir el curso: el alumno reinicia su progreso en una edicion (opcion `av_allow_retake`,
  activa por defecto). Conserva el certificado y no envia correos.
- Smoke test ampliado a 199 comprobaciones y 93 clases.

## [0.9.0] - 2026-09-28

Pantalla de configuracion, anuncios con correo a la edicion y modo enfoque.

### Anadido

- Aula Virtual > Configuracion, en pestanas: General (color, correo de avisos, remitente, slug
  de landings), Matriculas (aprobacion automatica, bloqueo de wp-admin, modo enfoque), Videos
  (Bunny: biblioteca, claves, validez), WooCommerce (disparador y politica de reembolso) y
  Avanzado (registro, borrado al desinstalar). Las claves nunca se muestran de vuelta. El
  registro de campos es la lista canonica de opciones del plugin.
- Anuncios: a todas las ediciones de un curso o a una sola; visibles en el panel del alumno y
  en el temario; opcionalmente por correo a cada alumno matriculado con la plantilla editable
  "Nuevo anuncio" (variables `{{announcement_title}}` y `{{announcement_content}}`). Sustituye
  al correo manual de Tutor.
- Modo enfoque: las sesiones se muestran sin cabecera ni pie del tema, con barra propia de
  volver y salir. Se activa en Configuracion.
- Smoke test ampliado a 183 comprobaciones y 86 clases.

## [0.8.1] - 2026-09-28

Videos de Bunny cargados como "URL externa" del CDN, que es como estan hoy en Tutor.

### Anadido

- Una playlist HLS del CDN de Bunny (`vz-….b-cdn.net/{guid}/playlist.m3u8`) se reconoce como
  Bunny. Con `av_bunny_library_id` se convierte al embed oficial de Stream (firmado con la
  clave de la biblioteca); sin biblioteca se reproduce como HLS directo con `hls.js` en los
  navegadores que lo necesitan, firmada con la clave de la pull zone (`av_bunny_cdn_token_key`).
- Migracion: una "URL externa" de Tutor se reclasifica por su URL (Bunny, MP4, YouTube, Vimeo)
  para que el campus sepa reproducirla; la duracion en horas, minutos y segundos se conserva.
- Smoke test ampliado a 171 comprobaciones.

## [0.8.0] - 2026-09-28

Videos con Bunny Stream y migracion completa de las lecciones de Tutor.

### Anadido

- Registro de proveedores de video (`Videos\VideoEmbed`): YouTube, Vimeo, Bunny Stream, MP4,
  URL externa, codigo incrustado y shortcode, con deteccion automatica por URL. Selector de
  proveedor en el editor de sesion.
- Bunny Stream: acepta URL de embed, de reproduccion o solo el GUID; si la biblioteca usa
  autenticacion por token, cada reproduccion se firma con un token que caduca (opciones
  `av_bunny_library_id`, `av_bunny_token_key`, `av_bunny_token_ttl`).
- Codigos incrustados: solo se conserva el iframe si su host esta en la lista blanca.
- Migracion desde Tutor: la fuente `bunnynet` migra como Bunny; los adjuntos de cada leccion y
  del curso pasan a materiales; el precio de Tutor (oferta o normal) llega a la edicion.
- Migracion: opcion "Edicion de: [curso existente]" para agrupar cohortes que en Tutor son
  cursos separados (G1, G3, G4...) como ediciones de un solo curso.
- Smoke test ampliado a 160 comprobaciones y 81 clases.

### Corregido

- El embed de YouTube y Vimeo llegaba vacio al alumno: `wp_kses_post` elimina los iframes que
  devuelve oEmbed. Ahora se conservan si el host esta en la lista blanca.

## [0.7.0] - 2026-09-28

Perfil del alumno, editor de sesion, clases en vivo, materiales y documentacion de entrega.

### Anadido

- Campus > Mis datos: el alumno edita nombre, telefono y documento, y cambia su contrasena
  pidiendo la actual. El correo es de solo lectura porque identifica la matricula.
- Editor de sesion (desde el detalle de la edicion): contenido enriquecido, video, duracion,
  vista previa, estado y liberacion programada (se guarda; se aplica en la fase de content
  drip).
- Clases en vivo por sesion: plataforma, inicio y fin en la zona horaria de la edicion
  (guardados en UTC), enlace, ID y codigo, ventana de visibilidad del boton (minutos antes y
  despues), mensaje y grabacion. El enlace de la reunion solo sale del servidor dentro de la
  ventana. Evento `live_class_recorded` al anadir la grabacion.
- Materiales por sesion desde la biblioteca de medios o por enlace externo, con lista blanca
  de extensiones (nunca php, exe ni html) y marca de "solo lectura". Evento `material_added`.
- El campus muestra la clase en vivo (con estado antes / abierta / terminada), la grabacion y
  los materiales de la sesion.
- Documentacion: README, guia del administrador, guia tecnica y plan de pruebas en staging.
- Smoke test ampliado a 139 comprobaciones y 80 clases.

## [0.6.0] - 2026-09-28

Landing por curso, administrable por secciones y orientada a conversion.

### Anadido

- Caja "Landing del curso" en el editor del curso con ocho secciones en orden fijo
  (presentacion, beneficios, contenido, instructor, informacion, precio, preguntas frecuentes,
  cierre), cada una con su interruptor mostrar/ocultar y campos propios: titulos, textos,
  imagen, imagen de fondo, color, video (YouTube, Vimeo o MP4), listas, FAQ, boton con texto y
  comportamiento (comprar, inscribirse, WhatsApp, enlace propio o ninguno).
- Los botones salen de las ediciones abiertas: "Comprar" va al checkout del producto de la
  edicion y "Inscribirse" a su enlace de inscripcion. La seccion de informacion muestra una
  tarjeta por edicion con fechas, dias, horario, cupo y botones; la de contenido muestra el
  temario de la proxima edicion.
- Plantilla propia para el permalink del curso (solo si el tema no trae la suya, desactivable
  por filtro), con barra fija de compra en movil, etiquetas Open Graph y datos estructurados
  Course de schema.org.
- Shortcodes `[av_course_landing id=""]` y `[av_course_editions id=""]` para armar la landing
  en Elementor u otra pagina.
- Todo se guarda como una sola meta JSON del curso: duplicar el curso duplica su landing. Las
  plantillas se sobrescriben desde el tema en `aula-virtual/landing/`.
- Smoke test ampliado a 123 comprobaciones y 73 clases.

### Corregido

- El saneado de la landing conserva el valor por defecto de las claves que no vienen en el
  envio, en vez de vaciarlas; una clave enviada vacia se guarda vacia.

## [0.5.0] - 2026-09-28

Migracion desde Tutor LMS, dentro de la misma base de datos.

### Anadido

- Pantalla Aula Virtual > Migrar desde Tutor (solo aparece si hay cursos de Tutor). Un curso por
  pulsacion: crea el curso con su meta y categorias, una edicion "Alumnos actuales" en curso con
  el producto de WooCommerce, los temas como modulos y las lecciones con video y contenido.
- Matricula a cada alumno conservando estado, fecha original y pedido; copia las lecciones
  completadas y marca como completado a quien termino el curso.
- Aditiva y repetible: mapa de ids en opciones, indices unicos y comprobacion previa por alumno.
  Tutor no se modifica ni se borra. No se envia ningun correo. Lotes de 300 alumnos.
- Origen de matricula `migration`, modo silencioso en matricula y recalculo de progreso, fecha
  de matricula preservable, y repositorio de modulos.
- Smoke test ampliado a 104 comprobaciones y 68 clases.

### Corregido

- El evento "matricula aprobada" ya no se emite para matriculas que nacen canceladas o pendientes.

## [0.4.0] - 2026-09-28

Compra directa por WooCommerce: el tramo Landing -> Comprar -> Pago -> Matricula -> Bienvenida.

### Anadido

- Pestana "Aula Virtual" en el producto de WooCommerce para vincularlo a una edicion. Un
  producto vende una edicion; el vinculo se guarda en el producto y se refleja en la edicion.
- Matricula automatica al pasar el pedido a Procesando o Completado (configurable). Idempotente:
  un pedido notificado dos veces no duplica matriculas ni correos.
- Compra como invitado: si el comprador no tiene cuenta se crea sin contrasena y la bienvenida
  lleva el enlace seguro para establecerla. El pedido queda asociado a la cuenta.
- Si el comprador tenia una solicitud de inscripcion aprobada, el pago la cierra como matriculada.
- Politica ante reembolso o cancelacion: no hacer nada, suspender o cancelar. Nunca se borra.
- Aviso de acceso al campus en la pagina de gracias y notas en el pedido con cada matricula.
- El modulo solo engancha hooks si WooCommerce esta cargado; sin el, el plugin funciona igual.
- En una instalacion que vive bajo /campus/, la pagina del campus se crea como /aula/ para no
  producir /campus/campus/.
- Smoke test ampliado a 87 comprobaciones y 62 clases.

## [0.3.0] - 2026-09-28

Inscripcion con aprobacion y correos 100% editables: el tramo
Landing -> Inscripcion -> Aprobacion -> Email de bienvenida -> Acceso.

### Anadido

- Enlaces de inscripcion por edicion (`/inscripcion/{token}/`) con etiqueta, limite de usos y
  vencimiento. Se generan desde el detalle de la edicion.
- Formulario publico de inscripcion dentro del tema del sitio, con nonce, honeypot y
  deduplicacion por correo. La solicitud queda pendiente.
- Pantalla Solicitudes: aprobar, rechazar con motivo y marcar pagado a mano. Aprobar una edicion
  gratuita matricula al instante; aprobar una de pago envia el enlace de pago y espera.
- Al matricular se crea la cuenta de WordPress sin contrasena: el alumno la establece desde un
  enlace seguro de un solo uso incluido en la bienvenida.
- Modulo de emails: plantillas editables desde el panel (asunto, contenido, activar/desactivar,
  envio de prueba), 24 variables (`{{first_name}}`, `{{course_name}}`, `{{schedule_time}}`,
  `{{payment_url}}`, `{{set_password_url}}`...) y seis plantillas por defecto que se siembran sin
  pisar las editadas.
- Los correos salen por `wp_mail()`, con lo que cualquier plugin SMTP del sitio sigue valiendo.
- Ediciones: dias, horario y precio informativo, que llegan a la landing y a la bienvenida.
- Esquema 1.1.0 con migracion que regenera las reglas de reescritura en sitios ya instalados.
- Smoke test ampliado a 75 comprobaciones y 58 clases.

## [0.2.0] - 2026-09-02

Vertical fina de punta a punta: crear una edicion, cargarle sesiones, matricular a mano un
alumno y que ese alumno entre al campus, vea el temario y marque una sesion como completada.

### Anadido

- Ediciones: estados (borrador, proxima, matricula abierta, en curso, finalizada, archivada),
  ventana de acceso independiente del estado, codigo unico autogenerado y validacion de fechas.
- Temario: lecciones por edicion con tipo, orden y estado; reordenado que ignora los ids que no
  pertenecen a la edicion.
- Matriculas: alta manual idempotente, control de cupo, cambio de estado con auditoria y rol de
  alumno asignado sin pisar roles existentes.
- Progreso: marcar sesion completada, porcentaje cacheado en la matricula y cierre automatico del
  curso con evento `course_completed` cuando se completan todas las sesiones.
- Administracion: menu Aula Virtual con listado y alta de ediciones, y detalle con alta rapida de
  sesiones y matricula manual.
- Campus: shortcode `[av_campus]` con login, mis cursos, temario con progreso y vista de sesion.
  Las plantillas se pueden sobrescribir desde el tema en `aula-virtual/campus/`.
- El modulo de matriculas responde al filtro `aula_virtual/can_access_edition`, que es como
  `AccessControl` decide si un alumno puede abrir el contenido.
- Smoke test ampliado a 66 comprobaciones, incluida la resolucion completa del grafo de
  dependencias del contenedor.

### Cambiado

- El plugin pasa a llamarse Aula Virtual SIQA: namespace `SIQA\AulaVirtual`, slug y text domain
  `aula-virtual`, prefijo de tablas y opciones `av_`, constantes `AV_`, hooks `aula_virtual/` y
  REST `aula-virtual/v1`.
- El post type de curso deja de tener menu propio y se muestra dentro de Aula Virtual.
- La activacion crea solo la pagina del Campus. Login, recuperacion de contrasena y catalogo se
  crearan con su modulo: publicarlas ahora mostraria el shortcode en crudo.
- El bloqueo de wp-admin para alumnos redirige a la pagina de Campus creada en la activacion, no
  a `/campus/` a ciegas.

## [0.1.0] - 2026-09-02

Primera entrega: **Core**. Corresponde al punto 72 del brief (arquitectura y núcleo antes
de la interfaz). No incluye pantallas de administración ni Campus.

### Añadido

- Bootstrap del plugin con comprobación de PHP 8.1+ / WordPress 6.4+ y autoloader PSR-4
  (Composer con respaldo propio).
- `Core\Plugin`, `Core\Container` y el contrato `Core\ServiceProvider` con arranque en dos
  fases (registro de servicios y registro de hooks).
- Esquema de base de datos 1.0.0 con 15 tablas, índices compuestos y restricciones únicas
  para matrículas y progreso.
- `Database\Migrator` con versionado, cerrojo por transient y migraciones de datos por versión.
- `Database\Repository`: base de repositorios con listas blancas de columnas, paginación y
  consultas preparadas.
- `Database\Installer`: activación aditiva (tablas, roles, opciones, páginas del campus,
  permalinks) y desactivación que no borra datos.
- Roles `av_admin`, `av_instructor` y `av_student` con su matriz de capacidades,
  resincronización por versión y bloqueo de wp-admin para alumnos.
- `Permissions\AccessControl` como punto único de comprobación de permisos.
- Post type `av_course` con taxonomías, capacidades propias vía `map_meta_cap` y campos
  registrados con `register_post_meta()`.
- `Core\Events\EventBus` y catálogo de eventos de dominio sobre el sistema de hooks.
- `Core\Logger` con niveles configurables y `Core\AuditLog` para la traza administrativa.
- `Security\Sanitizer` con los saneadores usados por todos los módulos.
- Base REST (`aula-virtual/v1`) y controlador de cursos.
- `uninstall.php` con borrado de datos opcional, desactivado por defecto.
- Smoke test ejecutable sin WordPress (`php tests/smoke-test.php`), 38 comprobaciones.

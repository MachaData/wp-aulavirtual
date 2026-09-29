<!--
Brief original del proyecto, tal como se entregó al iniciar el desarrollo (2026-09-02).
Se conserva sin cambios como referencia de los requisitos.

Diferencias con lo construido (decididas durante el desarrollo):
- El plugin se llama "Aula Virtual SIQA" (no "SEV LMS"): text domain `aula-virtual`,
  namespace `SIQA\AulaVirtual`, prefijo de tablas `wp_av_` y de opciones/meta `av_`
  (el brief usa `sev_lms`).
- Quizzes y tareas (puntos 42 y 43) se descartaron el 2026-09-28: no se necesitan.
- Qué está hecho y qué no, punto por punto: docs/TRASPASO.md, sección "Cobertura del brief".
-->

# PROYECTO
Desarrollo de Plugin LMS Profesional para WordPress

## OBJETIVO GENERAL

Construir un plugin LMS completo, modular, escalable y profesional para WordPress que permita a academias, profesionales, instituciones y empresas crear, vender y gestionar:

- Cursos grabados.
- Cursos en vivo.
- Cursos híbridos.
- Diferentes ediciones/cohortes de un mismo curso.
- Matrículas manuales.
- Matrículas mediante enlaces privados.
- Matrículas masivas mediante Excel.
- Matrículas automáticas mediante WooCommerce.
- Clases grabadas.
- Clases en vivo.
- Materiales descargables.
- Progreso del estudiante.
- Comunicaciones.
- Certificados.
- Reportes.
- Campus virtual independiente.

Tomar como referencias funcionales Moodle y Tutor LMS, pero NO copiar su arquitectura ni interfaz literalmente.

El sistema debe ser considerablemente más sencillo de administrar que Moodle y debe tener como diferenciador principal el concepto:

CURSO → EDICIONES → MATRÍCULAS

---

# 1. PRINCIPIO FUNDAMENTAL DEL SISTEMA

Separar completamente los conceptos de:

## Curso

Representa el producto educativo permanente.

Ejemplo:

Certificación en Numerología Terapéutica – Nivel 1

El Curso contiene:

- Nombre.
- Descripción.
- Landing pública.
- Imagen.
- Video introductorio.
- Instructor.
- Categorías.
- Temario.
- Información comercial.
- Qué aprenderá el alumno.
- Público objetivo.
- Requisitos.
- Material incluido.
- Beneficios.
- Nivel.
- Duración estimada.
- Modalidad posible.
- Precio visible.
- Reseñas.
- FAQ.
- Integración WooCommerce.

El curso puede existir durante varios años.

---

## Edición

Representa una cohorte, grupo o ejecución concreta de un Curso.

Ejemplo:

Curso:
Certificación Numerología Terapéutica – Nivel 1

Ediciones:

- Marzo 2026
- Julio 2026
- Noviembre 2026
- Marzo 2027

Las Ediciones NO deben aparecer necesariamente públicamente en el catálogo.

Cada Edición tendrá:

- Nombre.
- Código.
- Curso padre.
- Instructor o instructores.
- Fecha de inicio.
- Fecha de finalización.
- Fecha desde la que el alumno puede ingresar.
- Fecha máxima de acceso.
- Modalidad.
- Cupo máximo.
- Estado.
- Horarios.
- Zona horaria.
- Producto WooCommerce relacionado.
- Métodos de matrícula.
- Alumnos.
- Sesiones.
- Calendario.
- Comunicaciones.
- Configuraciones específicas.

Estados sugeridos:

- Borrador.
- Próxima.
- Matrícula abierta.
- En curso.
- Finalizada.
- Archivada.

---

# 2. TIPOS DE CURSO

El plugin debe manejar:

### Grabado / Asíncrono

El alumno puede avanzar a su ritmo.

### En vivo / Síncrono

Las sesiones poseen:

- Fecha.
- Hora.
- Enlace.
- Plataforma.
- Instructor.

### Híbrido

Combina:

- Clases en vivo.
- Grabaciones.
- Materiales.
- Actividades.

---

# 3. ARQUITECTURA GENERAL

WordPress será responsable de:

- Usuarios.
- Autenticación.
- Roles.
- Media Library.
- Gestión administrativa.
- Sistema de emails.
- REST API.
- Seguridad.
- Cron.
- URLs.
- Plantillas.

WooCommerce será responsable opcionalmente de:

- Productos.
- Precios.
- Checkout.
- Pasarelas de pago.
- Pedidos.
- Cupones.
- Clientes.
- Reembolsos.

El plugin LMS será responsable de:

- Cursos.
- Ediciones.
- Currículum.
- Módulos.
- Lecciones.
- Sesiones.
- Matrículas.
- Progreso.
- Clases en vivo.
- Videos.
- Materiales.
- Importación.
- Emails LMS.
- Campus.
- Anuncios.
- Calendario.
- Certificados.
- Reportes.

---

# 4. ESTRUCTURA TÉCNICA

Utilizar programación orientada a objetos.

No crear un plugin monolítico.

Estructura aproximada:

/sev-lms
    sev-lms.php

    /includes
        /Core
        /Database
        /Admin
        /Courses
        /Editions
        /Curriculum
        /Lessons
        /Enrollments
        /Students
        /Progress
        /WooCommerce
        /Emails
        /LiveClasses
        /Videos
        /Certificates
        /Announcements
        /Reports
        /Imports
        /REST
        /Permissions
        /Security

    /admin
        /views
        /assets

    /frontend
        /templates
        /assets

    /templates
        /campus
        /course
        /lesson
        /emails

    /assets
        /css
        /js
        /images

    /languages

    /vendor

    composer.json
    uninstall.php
    readme.txt

Utilizar namespaces.

Evitar funciones globales salvo bootstrap absolutamente necesario.

---

# 5. MODELO DE DATOS

Usar una arquitectura híbrida.

## Custom Post Type

Crear:

sev_lms_course

Los Cursos deben ser Custom Post Type para permitir:

- Permalinks.
- SEO.
- Gutenberg.
- Elementor.
- Imágenes destacadas.
- Contenido WordPress.
- Landing pages.

Opcionalmente:

sev_lms_lesson

si resulta conveniente mantener contenido de las lecciones compatible con WordPress.

---

## Tablas personalizadas

Crear tablas específicas para información transaccional.

Ejemplo:

wp_sev_lms_editions

Campos:

id
course_id
name
code
status
modality
start_date
end_date
access_start
access_end
timezone
capacity
created_at
updated_at

---

wp_sev_lms_modules

id
course_id
edition_id nullable
title
description
position
status

---

wp_sev_lms_lessons

id
course_id
edition_id
module_id
title
description
lesson_type
video_provider
video_url
duration
featured_image
preview
position
release_date
status
created_at
updated_at

---

wp_sev_lms_enrollments

id
user_id
course_id
edition_id
status
source
order_id
enrolled_at
approved_at
completed_at
expires_at
approved_by

Fuentes:

woocommerce
manual
excel
private_link
free
admin

Estados:

pending
approved
active
completed
expired
cancelled
rejected

---

wp_sev_lms_progress

id
user_id
course_id
edition_id
lesson_id
status
percentage
started_at
completed_at
last_activity

---

wp_sev_lms_registration_requests

id
edition_id
first_name
last_name
email
phone
document
custom_data
status
created_at
approved_at
approved_by

---

wp_sev_lms_announcements

id
course_id
edition_id
author_id
title
content
send_email
created_at

---

wp_sev_lms_live_classes

id
lesson_id
edition_id
provider
meeting_url
meeting_id
password
start_datetime
end_datetime
timezone
recording_url
status

---

wp_sev_lms_certificates

id
user_id
course_id
edition_id
certificate_code
issued_at
file_url
verification_hash

Usar índices adecuados.

Usar dbDelta y versionado de schema.

---

# 6. ADMINISTRACIÓN LMS

Crear menú principal:

Aula Virtual

Submenús:

Dashboard
Cursos
Ediciones
Temario
Estudiantes
Matrículas
Solicitudes
Importar alumnos
Anuncios
Calendario
Certificados
Reportes
Emails
Configuración

---

# 7. DASHBOARD ADMINISTRATIVO

Mostrar:

Cursos publicados.

Ediciones activas.

Alumnos activos.

Matrículas del mes.

Solicitudes pendientes.

Cursos vendidos.

Próximas clases.

Últimas matrículas.

Actividad reciente.

Alertas.

Gráficas simples.

---

# 8. CURSOS

Permitir:

Crear.
Editar.
Publicar.
Archivar.
Eliminar.
Duplicar.
Previsualizar.

Campos:

Título.

Slug.

Descripción.

Descripción corta.

Imagen destacada.

Video introductorio.

Instructor.

Categoría.

Etiquetas.

Nivel:

Principiante.
Intermedio.
Avanzado.
Todos los niveles.

Duración.

Qué aprenderás.

Beneficios.

Material incluido.

Público objetivo.

Requisitos.

FAQ.

Información del instructor.

Reseñas.

Precio informativo.

Producto WooCommerce.

Configuración de visibilidad.

---

# 9. DUPLICACIÓN DE CURSO

Botón:

Duplicar curso

Opciones:

Copiar descripción.
Copiar imagen.
Copiar estructura.
Copiar módulos.
Copiar clases.
Copiar materiales.
Copiar videos.
Copiar configuraciones.

NO copiar:

Alumnos.
Matrículas.
Progreso.
Comentarios.
Certificados.

---

# 10. EDICIONES

Dentro de cada curso mostrar pestaña:

EDICIONES

Tabla:

Edición
Modalidad
Inicio
Fin
Alumnos
Cupo
Estado
Acciones

Acciones:

Editar.
Duplicar.
Ver alumnos.
Ver sesiones.
Ver calendario.
Copiar enlace.
Archivar.

---

# 11. CREAR NUEVA EDICIÓN

Campos:

Nombre.

Código.

Curso.

Instructor.

Modalidad.

Fecha inicio.

Fecha fin.

Fecha inicio de acceso.

Fecha fin de acceso.

Cupo.

Zona horaria.

Estado.

Producto WooCommerce.

Métodos de matrícula.

Permitir simultáneamente:

WooCommerce.
Enlace privado.
Manual.
Excel.
Gratuito.

---

# 12. DUPLICAR EDICIÓN

Permitir:

Crear nueva edición basada en otra.

Copiar:

Temario.
Módulos.
Sesiones.
Material.
Videos.
Horarios opcionalmente.
Configuración.

No copiar:

Alumnos.
Progreso.
Certificados.
Comentarios.
Matrículas.

---

# 13. CURRÍCULUM

Crear Course Builder.

Estructura:

Curso
    Módulo
        Lección
        Lección
        Lección

Debe permitir:

Drag & Drop.

Crear módulo.

Editar módulo.

Duplicar módulo.

Eliminar módulo.

Crear sesión.

Duplicar sesión.

Ordenar sesiones.

---

# 14. LECCIONES / SESIONES

Campos:

Título.

Descripción.

Imagen destacada.

Duración.

Fecha de liberación.

Tipo.

Contenido.

Video.

Materiales.

Clase en vivo.

Preview.

Estado.

Tipos:

Video.
Texto.
Live class.
Material.
Quiz.
Assignment.

---

# 15. VIDEO

Soportar:

HTML5 MP4.
Media Library.
URL externa.
YouTube.
Vimeo.
Bunny Stream.
Iframe.
Embed.
Shortcode.

Crear interfaz extensible para agregar proveedores posteriormente.

No almacenar archivos de video grandes directamente en WordPress salvo decisión expresa del administrador.

---

# 16. CLASE EN VIVO

Cada lección podrá activar:

Esta sesión contiene clase en vivo.

Campos:

Plataforma.

Opciones iniciales:

Zoom.
Google Meet.
Microsoft Teams.
Otro.

Fecha.

Hora.

Zona horaria.

URL.

Meeting ID.

Password.

Duración.

Mensaje.

Mostrar botón desde X minutos antes.

Ocultar botón después de X tiempo.

Después de terminar la clase permitir agregar:

URL de grabación.

---

# 17. MATERIALES

Cada sesión podrá adjuntar múltiples materiales.

Permitir:

PDF.
DOC.
DOCX.
XLS.
XLSX.
PPT.
PPTX.
ZIP.
Imágenes.
URLs.
Otros formatos configurables.

Campos:

Nombre.
Descripción.
Archivo.
Permitir descargar sí/no.
Orden.

También debe existir:

Materiales generales del curso.

---

# 18. MATRÍCULAS

Pantalla:

Aula Virtual → Matrículas

Mostrar:

Alumno.
Email.
Curso.
Edición.
Origen.
Fecha.
Estado.
Progreso.
Última actividad.

Filtros por:

Curso.
Edición.
Estado.
Origen.
Fecha.

Acciones:

Aprobar.
Suspender.
Cancelar.
Finalizar.
Eliminar.
Cambiar edición.
Extender acceso.
Enviar email.
Ver progreso.

---

# 19. MATRÍCULA MANUAL

Administrador:

Selecciona usuario existente.

o

Crea alumno.

Selecciona:

Curso.
Edición.
Fecha acceso.
Fecha expiración.

Botón:

Matricular alumno.

Opcional:

Enviar email de bienvenida.

---

# 20. ENLACE PRIVADO DE MATRÍCULA

Cada edición puede generar:

URL única.

Ejemplo:

/campus/inscripcion/ABC123XYZ/

Formulario:

Nombre.
Apellido.
Email.
Teléfono.
Documento opcional.
Campos personalizados.

Al enviarlo:

Crear solicitud.

Estado:

Pendiente.

Administrador recibe aviso.

Pantalla:

Solicitudes

Acciones:

Aprobar.
Rechazar.
Ver.

Al aprobar:

Buscar usuario por email.

Si no existe:

crear usuario WordPress.

Crear matrícula.

Enviar email de bienvenida.

Nunca enviar contraseña en texto plano.

Enviar enlace seguro para establecer contraseña.

---

# 21. IMPORTACIÓN EXCEL

Implementar importación masiva mediante:

.xlsx
.csv

Utilizar PhpSpreadsheet mediante Composer.

Proceso:

1. Seleccionar Curso.
2. Seleccionar Edición.
3. Subir archivo.
4. Mapear columnas.
5. Previsualizar.
6. Validar.
7. Importar.

Columnas sugeridas:

first_name
last_name
email
phone
document

Mostrar:

Registros encontrados.

Usuarios nuevos.

Usuarios existentes.

Errores.

Duplicados.

Antes de ejecutar:

checkbox:

Enviar correo de bienvenida.

No enviar ningún correo durante la validación.

Procesar importaciones grandes por lotes.

---

# 22. WOOCOMMERCE

WooCommerce debe ser integración opcional.

El LMS debe seguir funcionando sin WooCommerce.

Cuando WooCommerce esté activo:

Agregar campos al producto:

Curso relacionado.

Edición relacionada.

Acción posterior a compra.

Ejemplo:

Producto:
Certificación Numerología Septiembre

Curso:
Numerología Terapéutica Nivel 1

Edición:
Septiembre 2026

---

# 23. MATRÍCULA POR COMPRA

Configurable según estado del pedido:

Processing.

Completed.

Al activarse:

Obtener comprador.

Buscar usuario.

Crear matrícula.

Relacionar:

order_id.

Enviar bienvenida.

Evitar duplicados.

Guardar log.

---

# 24. CANCELACIONES Y REEMBOLSOS

Configuración global:

Cuando un pedido sea:

Refunded.
Cancelled.

Elegir:

No hacer nada.

Suspender matrícula.

Cancelar matrícula.

Revocar acceso.

No borrar historial.

---

# 25. CAMPUS INDEPENDIENTE

NO mostrar wp-admin a alumnos.

Crear:

/campus/

/campus/login/

/campus/recuperar-contrasena/

/campus/cursos/

/campus/curso/{slug}/

/campus/perfil/

/campus/certificados/

Utilizar usuarios WordPress por detrás.

---

# 26. LOGIN

Login personalizado.

Campos:

Email/usuario.
Contraseña.

Funciones:

Recordarme.

Olvidé contraseña.

Login.

Logo configurable.

Imagen/fondo configurable.

Color configurable.

Redirect automático a Campus.

Bloquear wp-admin para rol alumno.

---

# 27. DASHBOARD DEL ALUMNO

Mostrar:

Bienvenida.

Mis cursos.

Progreso.

Próxima clase.

Calendario.

Anuncios recientes.

Certificados.

Actividad reciente.

Tarjeta por curso:

Imagen.
Nombre.
Edición.
Progreso.
Próxima sesión.
Botón Continuar.

---

# 28. INTERFAZ DEL CURSO

Diseño similar conceptualmente a plataformas LMS modernas.

Desktop:

Sidebar izquierda:

Temario.

Zona principal:

Contenido.

Header:

Nombre del curso.
Progreso.
Botón completar.

Debajo:

Video.

Descripción.

Tabs:

Resumen.
Materiales.
Comentarios.
Anuncios.

Responsive mobile obligatorio.

---

# 29. PROGRESO

Registrar:

Lecciones iniciadas.

Lecciones completadas.

Porcentaje.

Última sesión vista.

Fecha última actividad.

Permitir:

Marcar como completado.

Configuraciones:

Modo Flexible.

Alumno puede completar manualmente.

Modo Estricto.

Debe cumplir condiciones.

Auto completar curso cuando todas las sesiones estén completadas.

---

# 30. CONTENT DRIP

Preparar módulo para liberar contenido:

Inmediatamente.

X días después de matrícula.

Fecha específica.

Después de completar lección anterior.

Por semana.

---

# 31. ANUNCIOS

Instructor o admin puede crear anuncio.

Destinatarios:

Curso completo.

Edición específica.

Mostrar:

Campus.

Email.

Campos:

Título.
Contenido.
Fecha.

---

# 32. CALENDARIO

Crear calendario para alumno y administrador.

Eventos:

Clases en vivo.

Inicio de curso.

Entrega de actividades.

Evaluaciones.

Eventos personalizados.

Dashboard mostrará:

Próxima clase.

---

# 33. EMAILS

Panel:

Aula Virtual → Emails

Crear plantillas editables.

Eventos mínimos:

Solicitud recibida.

Solicitud aprobada.

Solicitud rechazada.

Bienvenida.

Nuevo anuncio.

Próxima clase.

Nuevo material.

Curso próximo a iniciar.

Acceso próximo a expirar.

Curso finalizado.

Certificado disponible.

---

# 34. VARIABLES EMAIL

Soportar:

{{first_name}}

{{last_name}}

{{email}}

{{course_name}}

{{edition_name}}

{{start_date}}

{{end_date}}

{{login_url}}

{{campus_url}}

{{lesson_name}}

{{class_url}}

{{certificate_url}}

---

# 35. NOTIFICACIONES

Crear sistema interno de eventos.

Ejemplo:

EnrollmentApproved
EnrollmentCreated
CourseCompleted
LessonReleased
LiveClassUpcoming
CertificateIssued

Los emails deben reaccionar a eventos.

Evitar código de email mezclado directamente con lógica de matrícula.

---

# 36. DISEÑO GLOBAL

Configuración:

Logo.

Logo del Campus.

Color principal.

Hover.

Color secundario.

Color texto.

Color fondo.

Color bordes.

Tipografía opcional.

Custom CSS opcional.

---

# 37. VISIBILIDAD DE ELEMENTOS

Pantalla similar a Tutor LMS.

Toggles:

Instructor.

Autor.

Nivel.

Duración.

Total inscritos.

Fecha actualización.

Barra progreso.

Materiales.

Descripción.

Beneficios.

Requisitos.

Público objetivo.

Anuncios.

Reseñas.

Compartir redes.

Precio.

Modalidad.

Próxima edición.

Fecha inicio.

Cada curso podrá:

Usar configuración global.

o

Sobrescribirla.

---

# 38. CATÁLOGO DE CURSOS

Configurar:

1 columna.
2 columnas.
3 columnas.
4 columnas.

Cursos por página.

Filtros.

Ordenamiento.

Categorías.

Buscar.

Solo publicados.

---

# 39. ROLES

Crear:

LMS Administrator.

LMS Instructor.

LMS Student.

No depender solamente de nombres de roles.

Utilizar capabilities.

---

# 40. PERMISOS

Crear matriz de permisos.

Admin / Instructor.

Permitir configurar:

Crear curso.

Editar curso.

Publicar curso.

Eliminar curso.

Crear edición.

Editar edición.

Gestionar módulos.

Gestionar sesiones.

Subir video.

Subir materiales.

Programar live class.

Ver alumnos.

Matricular alumnos.

Aprobar solicitudes.

Crear anuncios.

Ver reportes.

Emitir certificados.

Administrar WooCommerce.

Modificar precios.

---

# 41. CERTIFICADOS

Preparar módulo de certificados.

Condiciones:

Curso completado.

O aprobación manual.

Campos:

Nombre alumno.

Curso.

Edición.

Fecha.

Código.

Firma.

QR.

URL validación.

Generar código único.

Página pública:

/certificado/verificar/{codigo}

Mostrar únicamente datos necesarios.

---

# 42. QUIZZES

Construir como módulo desacoplado.

Tipos:

Opción única.

Selección múltiple.

Verdadero/falso.

Respuesta corta.

Pregunta abierta.

Configuraciones:

Nota mínima.

Intentos.

Tiempo.

Mostrar respuestas.

Orden aleatorio.

Banco de preguntas posteriormente.

No bloquear desarrollo del Core por este módulo.

---

# 43. TAREAS

Módulo opcional.

Instructor crea tarea.

Alumno adjunta archivo/texto.

Instructor revisa.

Estado:

Pendiente.

Entregado.

Revisado.

Aprobado.

Requiere cambios.

---

# 44. COMENTARIOS

Permitir comentarios por lección.

Configurable:

Global.

Curso.

Edición.

Instructor/admin puede moderar.

---

# 45. REPORTES

Panel inicial:

Alumnos por curso.

Alumnos por edición.

Progreso promedio.

Cursos completados.

Matrículas.

Ventas WooCommerce.

Última actividad.

Alumnos sin actividad.

Exportar CSV.

---

# 46. BÚSQUEDA Y FILTROS

En tablas administrativas implementar:

Search.

Filtros.

Paginación.

Ordenamiento.

Evitar cargar miles de registros en una sola consulta.

---

# 47. SEGURIDAD

OBLIGATORIO.

Usar:

Nonces.

Capability checks.

sanitize_text_field.

sanitize_email.

sanitize_key.

wp_kses_post.

esc_html.

esc_attr.

esc_url.

$wpdb->prepare.

Nunca confiar en datos enviados por frontend.

Validar ownership y permisos en cada endpoint REST.

Validar MIME type real de archivos.

No permitir ejecución de PHP mediante uploads.

No exponer rutas internas.

No guardar passwords.

No enviar passwords por email.

Usar APIs oficiales de WordPress.

---

# 48. REST API

Namespace:

sev-lms/v1

Endpoints organizados:

/courses
/editions
/enrollments
/students
/progress
/lessons
/announcements
/certificates

Cada endpoint:

permission_callback obligatorio.

Validación.

Sanitización.

Errores WP_Error.

Responses consistentes.

---

# 49. LOGS

Crear sistema de logs.

Registrar:

Matriculación.

Importaciones.

WooCommerce.

Emails fallidos.

Errores críticos.

Cambios de estado.

No guardar información sensible innecesariamente.

Permitir desactivar logs detallados en producción.

---

# 50. PERFORMANCE

Evitar:

Consultas N+1.

Carga completa de usuarios.

Autoload excesivo.

Meta queries gigantes.

Usar:

Índices.

Paginación.

Caching WordPress.

Transients cuando sea conveniente.

Procesos background.

---

# 51. BACKGROUND JOBS

Para:

Importaciones grandes.

Emails masivos.

Generación de certificados.

Procesamiento de matrículas.

Utilizar Action Scheduler cuando esté disponible.

Crear estrategia fallback si WooCommerce no se encuentra instalado.

---

# 52. COMPATIBILIDAD

Compatible con:

WordPress actual estable.

PHP moderno, mínimo recomendado 8.1.

MySQL/MariaDB soportados por WordPress.

WooCommerce actual estable.

Gutenberg.

Elementor.

No acoplar el plugin obligatoriamente a Elementor.

---

# 53. INTERNACIONALIZACIÓN

Todo texto:

esc_html__.

__.

_e.

Crear text domain:

sev-lms

Preparar carpeta languages.

Nada importante debe quedar hardcodeado únicamente en español.

---

# 54. CONFIGURACIÓN

Aula Virtual → Configuración

Tabs:

General.

Cursos.

Ediciones.

Matrículas.

Contenido.

Video.

WooCommerce.

Emails.

Campus.

Diseño.

Permisos.

Certificados.

Avanzado.

---

# 55. CONFIGURACIÓN GENERAL

Nombre del LMS.

Logo.

Página Campus.

Página Login.

Página catálogo.

Zona horaria.

Formato fecha.

Formato hora.

Moneda informativa.

---

# 56. CONFIGURACIÓN DE MATRÍCULAS

Permitir:

WooCommerce.

Manual.

Excel.

Private Link.

Gratuito.

Aprobación automática.

Aprobación manual.

Crear usuario automáticamente.

Duración acceso.

Evitar duplicados.

Enviar bienvenida.

---

# 57. CONFIGURACIÓN DE CURSO

Curso visible solo matriculados.

Permitir previews.

Mostrar progreso.

Modo Flexible / Strict.

Auto completion.

Permitir repetir curso.

Comentarios.

Reseñas.

---

# 58. CONFIGURACIÓN VIDEO

Habilitar providers:

HTML5.

URL.

YouTube.

Vimeo.

Bunny.

Embed.

Shortcode.

---

# 59. PÁGINA LANDING

La Landing del curso debe ser independiente del contenido privado.

Debe poder construirse con:

Gutenberg.

Elementor.

Theme.

Shortcodes/blocks del plugin.

Crear componentes:

Título.

Imagen.

Precio.

Instructor.

Temario.

Próxima edición.

CTA comprar.

CTA inscripción.

FAQ.

---

# 60. PRODUCTO + EDICIÓN

Una landing puede mostrar varias próximas ediciones.

Ejemplo:

Certificación Nivel 1

Septiembre 2026
En vivo
Comprar

Noviembre 2026
Híbrido
Comprar

Cada botón lleva al producto WooCommerce correspondiente.

---

# 61. ESTADOS Y WORKFLOWS

Utilizar estados claros.

No eliminar información importante automáticamente.

Mantener auditoría.

Ejemplo:

SOLICITUD

pending
↓
approved
↓
ENROLLMENT
active
↓
completed

También:

rejected
cancelled
expired
suspended

---

# 62. UX ADMINISTRADOR

No replicar visualmente el WordPress clásico innecesariamente.

Crear interfaz moderna.

Tabs.

Cards.

Toggles.

Modales.

Confirmaciones.

Breadcrumbs.

Estados visuales.

Mensajes claros.

Priorizar sencillez.

---

# 63. UX ALUMNO

El alumno nunca debería necesitar entender WordPress.

Todo se realiza desde Campus.

Responsive.

Rápido.

Limpio.

Accesible.

---

# 64. INSTALACIÓN

Al activar:

Crear tablas.

Crear roles.

Crear capabilities.

Crear páginas necesarias si no existen:

Campus.

Login.

Recuperar contraseña.

Catálogo opcional.

Guardar versión DB.

No borrar contenido al desactivar.

---

# 65. DESINSTALACIÓN

No eliminar datos automáticamente.

Crear configuración:

"Eliminar todos los datos al desinstalar".

Default:

OFF.

Solo uninstall.php podrá eliminar datos cuando esta opción se encuentre activa.

---

# 66. AUDITORÍA

Registrar eventos administrativos importantes:

Quién matriculó.

Quién aprobó.

Quién cambió acceso.

Quién eliminó.

Quién modificó edición.

Fecha.

---

# 67. PRIVACIDAD

Integrarse posteriormente con herramientas de exportación/eliminación de datos de WordPress.

Evitar información personal innecesaria.

---

# 68. MVP PRIORITARIO

Construir primero:

Core.

Database.

Roles/capabilities.

Cursos.

Ediciones.

Course Builder.

Módulos.

Lecciones.

Videos.

Materiales.

Live Classes.

Estudiantes.

Matrículas.

Private registration links.

Aprobación.

Excel import.

WooCommerce integration.

Emails.

Campus.

Login.

Progress.

Announcements.

Settings.

---

# 69. SEGUNDA FASE

Después:

Calendar avanzado.

Certificates.

Quizzes.

Assignments.

Reviews.

Advanced reports.

Content Drip.

Automated notifications.

---

# 70. TERCERA FASE

Posteriormente:

Zoom API.

Google Meet integrations.

WhatsApp.

SCORM.

Gamification.

Badges.

Subscriptions.

Membership.

Multi-instructor.

Corporate training.

Teams.

API externa.

Mobile app.

---

# 71. REGLA DE DESARROLLO CON CLAUDE

NO intentar construir todo en una sola respuesta.

Trabajar por módulos.

Antes de implementar cada módulo:

1. Explicar qué archivos serán creados/modificados.
2. Definir modelo de datos.
3. Definir hooks.
4. Definir permisos.
5. Implementar.
6. Revisar seguridad.
7. Crear pruebas.
8. Documentar.
9. Ejecutar validación.
10. Confirmar que no rompe módulos anteriores.

Nunca reemplazar archivos completos innecesariamente.

Mantener changelog.

Mantener migrations.

---

# 72. PRIMERA ENTREGA QUE DEBE REALIZAR CLAUDE

Antes de escribir funcionalidades:

Analizar estos requerimientos.

Proponer:

Arquitectura final.

Árbol de carpetas.

Clases principales.

Service Container o bootstrap.

Modelo DB.

Custom Post Types.

Custom tables.

Roles.

Capabilities.

Eventos internos.

Hooks WooCommerce.

REST endpoints.

Proceso de instalación.

Plan de desarrollo.

No comenzar desarrollando la UI completa hasta tener aprobado el Core.

---

# 73. CONVENCIÓN DE CÓDIGO

Usar:

WordPress Coding Standards.

PSR-4 donde sea viable mediante Composer.

Clases pequeñas.

Single Responsibility.

Dependency injection donde aporte claridad.

Repositories para operaciones complejas de tablas.

Services para lógica de negocio.

Controllers para UI/API.

Templates separados.

---

# 74. NOMBRES TÉCNICOS

Nombre temporal del plugin:

SEV LMS

Slug:

sev-lms

Namespace:

SEV\LMS

Database prefix:

sev_lms_

REST:

sev-lms/v1

Text domain:

sev-lms

Todos deben poder modificarse antes de publicar comercialmente.

---

# 75. CRITERIO FINAL

El plugin debe permitir realizar este flujo completo:

Administrador crea:

Curso:
Certificación Numerología Terapéutica

Crea:

Edición Julio 2027

Agrega:

12 sesiones.

Programa:

12 clases en vivo.

Relaciona:

Producto WooCommerce.

Publica landing.

Alumno compra.

WooCommerce confirma pago.

Plugin detecta pedido.

Plugin crea/encuentra usuario.

Plugin matricula alumno en:

Curso X
Edición Julio 2027

Plugin envía bienvenida.

Alumno establece contraseña.

Alumno entra:

/campus/

Ve:

Su curso.

Próxima clase.

Progreso.

Materiales.

Entra a clase en vivo.

Posteriormente:

Ve grabación.

Marca sesión completada.

Avanza.

Completa curso.

Recibe certificado.

Todo sin acceder a wp-admin.

Este flujo deberá funcionar de extremo a extremo antes de considerar estable la primera versión comercial.

TOMA COMO REFERENCIA TUTOR LMS
https://tutorlms.com/docs/course-builder/basics/
https://www.crehana.com/blog/product-releases/nuevo-gamificacion-marzo-2023/
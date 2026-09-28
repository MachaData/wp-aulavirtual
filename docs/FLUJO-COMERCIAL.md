# Flujo comercial: landing, inscripción, pago, matrícula y acceso

> Revisión de la funcionalidad de cursos, inscripciones, pagos, emails y landing pages,
> tomando como base lo que ya existe en el plugin y en el sitio actual. Fecha: 2026-09-28.

## 1. Qué hay hoy

**La landing actual** (`testh1.my.canva.site/numerologiabasica`) es un sitio de Canva: título
"Numerología y Vidas Pasadas 2026", unas 40 imágenes, 4 videos y **un único enlace saliente, a
WhatsApp**. No hay formulario, no hay pago en línea, no hay nada que el plugin pueda reutilizar.
Toda la conversión ocurre a mano por WhatsApp. Eso fija el punto de partida: la landing no se
migra, se reconstruye dentro de WordPress con inscripción y pago integrados.

**El plugin** (`wp-aulavirtual`, v0.3.0) ya cubre, verificado con 75 comprobaciones:

| Tramo del flujo | Estado |
|---|---|
| Curso (CPT con landing pública, meta y taxonomías) | Hecho, sin constructor de secciones |
| Edición (fechas, días, horario, modalidad, cupo, precio, producto Woo) | Hecho |
| Enlace de inscripción `/inscripcion/{token}/` + formulario público | Hecho |
| Solicitud pendiente → aprobar / rechazar / marcar pagado | Hecho |
| Cuenta sin contraseña + enlace seguro para crearla | Hecho |
| Emails editables desde el panel, con variables y prueba | Hecho |
| Matrícula, campus, temario, progreso | Hecho |
| Compra directa por WooCommerce → matrícula automática | **Pendiente** (depende de la decisión 1) |
| Landing por secciones administrable | **Pendiente** (diseño en §4) |
| Integración con otro WordPress | **Pendiente** (depende de la decisión 3) |

## 2. El flujo propuesto

El flujo pedido es `Landing → Inscripción/Compra → Aprobación → Pago → Matrícula → Bienvenida →
Acceso`. Tiene una tensión: si la compra en línea exige aprobación **antes** de pagar, la compra
deja de ser rápida. La propuesta resuelve eso con **dos entradas que convergen en la misma
matrícula**:

```
                 ┌── Comprar ──► Checkout Woo (1 paso) ──► Pago confirmado ──┐
Landing ─────────┤                                                           ├──► Matrícula ──► Bienvenida ──► Campus
                 └── Inscribirse ──► Solicitud pendiente ──► Aprobación ─┬─► gratuita ────────┘
                                                                         └─► de pago ──► email con enlace de pago ──► Pago confirmado ──┘
```

- **Compra directa**: el pago *es* la aprobación. Nadie revisa a mano. Es el camino para
  cursos abiertos al público.
- **Inscripción con aprobación**: para cursos con requisitos, cupos que se asignan a mano, becas,
  grupos corporativos o pagos por transferencia. El administrador aprueba y el sistema envía el
  enlace de pago (o matricula directo si la edición es gratuita). También puede marcar "pagado"
  a mano cuando el pago llegó por Yape, Plin o transferencia.
- **Ambos** terminan en la misma fila de `enrollments`, con el mismo email de bienvenida y el
  mismo acceso. La matrícula es idempotente: un pedido que WooCommerce notifique dos veces no
  duplica nada.

Cada edición decide qué botones muestra su landing: *Comprar*, *Inscribirse*, o ambos.

## 3. Pagos: reutilizar WooCommerce, no duplicarlo

El plugin **no** tendrá carrito, pedidos ni pasarelas propias. WooCommerce ya resuelve producto,
precio, checkout, pasarela, pedido, cupón, reembolso y factura. El plugin sólo hace dos cosas:

1. **Vincular producto ↔ edición.** En el producto de WooCommerce aparece una pestaña "Aula
   Virtual" con el curso y la edición. Un producto = una edición (una cohorte se vende una vez;
   la siguiente cohorte es otro producto, que se puede duplicar en Woo en dos clics).
2. **Escuchar el pedido.** Al pasar a *Procesando* o *Completado* (configurable), busca o crea
   al comprador por su correo, lo matricula en la edición del producto y dispara la bienvenida.
   En *Reembolsado* o *Cancelado* aplica la política elegida (no hacer nada, suspender, cancelar).
   Nunca borra historial.

**Pasarelas.** Culqi y PayPal se instalan como pasarelas de WooCommerce, no del plugin:

| Pasarela | Cómo | Nota |
|---|---|---|
| Culqi | Plugin oficial "Culqi WooCommerce" | Tarjetas, Yape, PagoEfectivo; en soles |
| PayPal | "WooCommerce PayPal Payments" (oficial) | Alumnos fuera de Perú |
| Yape / Plin / transferencia | Pasarela nativa "Transferencia bancaria" de Woo, o pago manual | El admin marca pagado; el plugin matricula |

Cambiar o añadir una pasarela después no toca el plugin.

**Compra en el menor número de pasos.** Con Woo esto se logra por configuración, no por código:
el botón *Comprar* de la landing lleva directo a `checkout/?add-to-cart={producto}` (se salta el
carrito); el checkout permite compra como invitado y crea la cuenta con el correo; sólo se piden
nombre, correo y, si Culqi lo exige, documento. Resultado: landing → checkout → pago → campus,
tres pantallas.

**Dónde vive WooCommerce** es la decisión 1 (§6). Si está en el mismo WordPress que el campus,
la integración son hooks en el mismo proceso, sin red de por medio. Si está en otro sitio, hace
falta un webhook firmado entre los dos y una forma de dar acceso al alumno en el sitio del
campus. El primer caso es la mitad de trabajo y la mitad de puntos de fallo.

## 4. Landing por curso, administrable

La landing es del **curso**; las **ediciones** aportan las fechas, horarios y botones. Un curso
con dos ediciones abiertas muestra las dos con su botón cada una.

**Cómo se construye.** No un page builder genérico (Elementor, Gutenberg libre): sección por
sección, con campos fijos, como piden los requisitos. El curso tendrá en su pantalla de edición
una pestaña *Landing* con las secciones en orden, cada una con su interruptor
*mostrar/ocultar* y sus campos:

| # | Sección | Campos editables |
|---|---|---|
| 1 | Presentación | Título, subtítulo, imagen o video de fondo, texto, botón principal |
| 2 | Beneficios | Título, lista de beneficios (icono + texto), imagen |
| 3 | Contenido | Título, temario por módulos (se toma de la edición o se escribe) |
| 4 | Instructor | Foto, nombre, bio, redes |
| 5 | Información del curso | Fechas, días, horario, modalidad, duración, plataforma, zona horaria (desde la edición, editables) |
| 6 | Precio | Precio, precio tachado, qué incluye, botón *Comprar* / *Inscribirse* |
| 7 | Preguntas frecuentes | Lista pregunta/respuesta |
| 8 | Inscripción / Compra | Texto de cierre, botones, WhatsApp opcional |

Además: imagen de fondo y color por sección, video (YouTube, Vimeo o MP4) en Presentación y en
Contenido, y textos de todos los botones con su enlace. Todo se guarda como meta del curso en un
único JSON, de modo que duplicar un curso duplica su landing.

**Cómo se ve.** El plugin trae su plantilla de landing (`single-av_course`), pensada para
conversión: un solo objetivo por pantalla, botón visible siempre (barra fija en móvil), precio y
próxima fecha repetidos en tres puntos, prueba social opcional (reseñas, fase 2). El tema puede
sobrescribirla. Se mantiene la posibilidad de no usarla y armar la landing en Elementor con los
shortcodes del plugin (precio, próxima edición, botón), para quien lo prefiera.

**SEO.** Título, descripción, imagen para compartir y datos estructurados (`Course` de
schema.org) se generan desde los mismos campos.

## 5. Integración con otro WordPress

Antes de diseñarla hace falta saber **qué es el otro WordPress** y en qué dirección viaja la
información (decisión 3). Tres escenarios, de menor a mayor complejidad:

| Escenario | Qué se comparte | Cómo |
|---|---|---|
| A. Un solo WordPress (ventas + campus) | Nada que integrar | Recomendado si es posible |
| B. Sitio de ventas (Woo) + sitio de campus (plugin) | Pedido pagado → matrícula | Webhook firmado de Woo al campus; el campus crea usuario y matrícula; el alumno entra al campus con su enlace de contraseña. Sin SSO. |
| C. Dos campus / dos instituciones que comparten alumnos | Alumnos, cursos, matrículas | API REST del plugin (`aula-virtual/v1`) con contraseñas de aplicación de WordPress; sincronización programada; SSO opcional después |

En todos los casos la API REST del plugin ya existe como base: falta añadir los endpoints de
alumnos y matrículas con sus permisos. Compartir "accesos" entre dos WordPress distintos
(que el alumno entre a uno y ya esté dentro del otro) es SSO y es lo único de la lista que no
es corto: conviene confirmar si de verdad hace falta o si basta con que cada sitio tenga su
sesión.

## 6. Decisiones necesarias

1. **¿Dónde está WooCommerce?** ¿En el mismo WordPress donde irá el campus o en otro? Define
   si la matrícula por compra son hooks locales (una semana) o un webhook entre sitios (dos y
   media, más pruebas).
2. **Aprobación y pago, en ese orden sólo para el camino de inscripción.** La compra directa no
   pasa por aprobación: el pago aprueba. ¿Conforme?
3. **¿Qué es "el otro WordPress" y qué debe viajar?** ¿Ventas → campus? ¿Dos campus? ¿Hace falta
   que el alumno entre a los dos con una sola sesión?
4. **Pasarela para arrancar.** Culqi cubre Perú (tarjeta, Yape, PagoEfectivo). PayPal se suma
   cuando haya alumnos fuera. ¿Empezamos sólo con Culqi?

## 7. Orden de trabajo propuesto

1. **WooCommerce** (en cuanto se responda la decisión 1): pestaña en el producto, matrícula por
   pedido, política de reembolso, botón *Comprar* directo al checkout.
2. **Landing por secciones**: modelo JSON, pestaña *Landing* en el curso, plantilla de
   conversión, shortcodes.
3. **Perfil del alumno** en el campus (datos, teléfono, contraseña).
4. **Integración con el otro WordPress**, según la decisión 3.
5. Después: materiales, clases en vivo con enlace, importación Excel, anuncios.

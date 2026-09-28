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
| Compra directa por WooCommerce → matrícula automática | Hecho (v0.4.0) |
| Landing por secciones administrable | Hecho (v0.6.0) |
| Integración con otro WordPress | Resuelto por diseño: el plugin va en el WordPress del campus (§6) |

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

## 6. El sitio destino: astronumerologia.com

Revisado el 2026-09-28 desde fuera (HTML público y `wp-json`). Son **dos instalaciones de
WordPress distintas** bajo el mismo dominio:

| | `astronumerologia.com/` | `astronumerologia.com/campus/` |
|---|---|---|
| Qué es | Tienda y marketing | Campus |
| WordPress / Woo | 7.1.2 / WooCommerce 10.1.4 | Propio (tiene su `wp-content` y su `wp-json`) |
| Tema | Minimog + Elementor Pro | HiStudy (child) + Elementor Pro |
| LMS | Ninguno (plugin `cn-cursos`, a identificar) | **Tutor LMS Pro**, con WooCommerce propio |
| Contenido | Velas numerológicas (S/ 35), calculadora, sesiones, eventos | 3 cursos publicados: Certificación Numerología Terapéutica Nivel 1 2026, Introducción a Numerología y Propósito de Vida, Numerología y Sinastría de Parejas |
| Formularios / chat | FluentForms, WhatsApp (Creame) | WhatsApp (Creame) |
| Pasarelas visibles | Ninguna detectable desde fuera | Ninguna detectable desde fuera |

**Recomendación: instalar Aula Virtual en `/campus/`, no en la raíz, y no fusionar los dos
sitios ahora.** Motivos:

1. Ahí están las cuentas de los alumnos y ahí entran a estudiar. La matrícula tiene que vivir
   donde vive la sesión del alumno.
2. Ahí hay ya un WooCommerce (el que usa Tutor). La matrícula por compra queda como hooks en el
   mismo proceso, sin webhook entre sitios.
3. La raíz sigue siendo la tienda de velas y el escaparate: su menú *Escuela* enlaza a las
   landings de los cursos en `/campus/curso/{slug}/`. No hace falta tocarla.
4. Fusionar dos sitios con dos tiendas, dos temas y dos Elementor es una migración de días y no
   aporta nada al flujo pedido.

**Convivencia con Tutor LMS.** No hay choque técnico: Tutor usa `courses` y `course-category`;
el plugin usa `av_course` y `av_course_cat`; los roles y las tablas tienen prefijos distintos.
Lo que sí hay que decidir es el destino de Tutor: si los cursos nuevos se venden y cursan con
Aula Virtual, Tutor queda para los alumnos actuales hasta que terminen, y se desactiva después.
Un producto de WooCommerce no debe estar vinculado a la vez a un curso de Tutor y a una edición
de Aula Virtual, o el comprador quedará matriculado en los dos.

**Detalle de instalación.** Como el WordPress del campus vive bajo `/campus/`, el plugin crea su
página de campus como `/campus/aula/` en vez de `/campus/campus/` (detección automática,
configurable con la opción `av_campus_slug`). Las inscripciones quedan en
`/campus/inscripcion/{token}/`.

## 7. Decisiones tomadas (2026-09-28)

| Decisión | Respuesta |
|---|---|
| Dónde va el plugin | En el mismo WordPress que WooCommerce: la instalación de `/campus/` |
| Aprobación y pago | Compra directa sin aprobación (el pago aprueba); inscripción con enlace sí pasa por aprobación |
| Pasarelas | Culqi, PayPal y transferencia manual, las tres como pasarelas de WooCommerce |

## 8. Respuestas del 2026-09-28 y migración desde Tutor LMS

| Pregunta | Respuesta |
|---|---|
| ¿Hay alumnos en Tutor LMS? | **Sí, y se migran.** |
| ¿Qué es `cn-cursos`? | Un plugin propio para enlazar a mano los cursos desde la raíz. **Se puede eliminar** cuando las landings nuevas existan; el menú *Escuela* enlazará a `/campus/curso/{slug}/`. |
| ¿Pasarela actual en `/campus/`? | Ninguna. **Se instalan Culqi y PayPal** como pasarelas de WooCommerce en `/campus/`. |

**Cómo se migra (v0.5.0).** Como el plugin se instala en el mismo WordPress que Tutor, la
migración lee la misma base de datos: no hay exportación ni archivos. Pantalla *Aula Virtual →
Migrar desde Tutor*, un curso por pulsación:

| Tutor LMS | Aula Virtual |
|---|---|
| Curso (`courses`) con imagen, nivel, duración, beneficios, requisitos, público, categorías | Curso `av_course` publicado o borrador según estuviera, con la misma meta y categorías |
| Producto WooCommerce vinculado | El mismo producto, en la edición |
| — | Edición "Alumnos actuales", estado *En curso* |
| Temas (`topics`) | Módulos |
| Lecciones (`lesson`) con su video (YouTube, Vimeo, URL, embed, HTML5) y contenido | Lecciones con proveedor, URL, duración y contenido |
| Matrícula (`tutor_enrolled`): activa / cancelada / pendiente, fecha, pedido | Matrícula con el mismo estado, **la fecha original**, el pedido y el origen `migration` |
| Lección completada (user meta) | Fila de progreso completada con su fecha |
| Curso terminado (comentario `course_completed`) | Matrícula *Completada* con su fecha |
| Quizzes y tareas | **No se migran** (fase 2) |

Reglas: Tutor **no se modifica ni se borra**; la migración es aditiva y repetible sin duplicar
(mapa de ids + índices únicos); **no sale ningún correo** durante la migración; los alumnos se
procesan en lotes de 300 por pulsación. Recomendación: ejecutarla primero en un staging de
`/campus/`, comparar conteos, y sólo después en producción.

**Qué pasa con Tutor después.** Los alumnos siguen entrando por el campus nuevo; Tutor se
mantiene activo, sin enlazar desde ningún menú, hasta confirmar que todo está migrado, y luego
se desactiva. Mientras los dos convivan, un producto de WooCommerce no debe estar vinculado a
la vez a un curso de Tutor y a una edición de Aula Virtual.

## 8b. Ajustes actuales de Tutor LMS y su equivalente (capturas del 2026-09-28)

| Ajuste en Tutor | Valor hoy | En Aula Virtual |
|---|---|---|
| Monetización | **Nativa de Tutor** (Pedidos, Métodos de pago, Suscripciones); WooCommerce instalado pero no vinculado a los cursos | Las ediciones migradas llegan **sin producto** (gratuitas para el plugin: ya se vendieron). Para vender ediciones nuevas: crear el producto en WooCommerce y vincularlo desde su pestaña *Aula Virtual*. Culqi y PayPal van en WooCommerce, no en Tutor |
| Precios | Precio normal y de oferta en el curso ($612 → $430) | La migración copia el precio de oferta como precio informativo de la edición |
| Proceso de finalización | Flexible; autocompletar curso al completar lecciones | Igual: `av_progress_mode = flexible`; la matrícula pasa a *Completada* sola |
| Acceso sin inscribirse para admin e instructores | Activado | Igual: los roles con `view_students` siempre pasan |
| Volver a hacer el curso | Activado | No existe todavía (fase siguiente: "repetir curso") |
| Comentarios en lecciones | Activado | No existe todavía (fase 2, punto 44 del brief) |
| Modo de enfoque (sin cabecera ni pie en las lecciones) | Activado | El campus se muestra dentro del tema. Plantilla "enfoque" pendiente para la fase Campus |
| Adjuntos: abrir en pestaña nueva | Activado | Igual: los materiales abren en pestaña nueva |
| Fuentes de video | Todas, incluida BunnyNet; reproductor de Tutor desactivado | Todas cubiertas (v0.8.1). Los videos reales están como "URL externa" del CDN de Bunny |
| Emails activos | Bienvenida tras registro, Curso inscrito, Nuevo pedido, Estado del pedido, suscripciones | Cubiertos por las plantillas por defecto (solicitud, aprobación, bienvenida). "Curso finalizado" viene activa: desactivarla si no se quiere, como en Tutor |
| Email manual (a un tipo de destinatario) | Disponible | Llega con **Anuncios** (siguiente bloque): mensaje a los alumnos de una edición, con envío por correo |
| Diseño: color principal `#3E64DE`, hover `#395BCA`, texto `#212327` | Personalizado | `av_brand_color = #3e64de` para landing, campus y correos |
| Diseño: elementos visibles en el curso (nivel, duración, barra de progreso, material, beneficios, requisitos, anuncios sí; instructor, autor, reseñas, público objetivo, compartir no) | Configurado | Las secciones de la landing se apagan por curso (v0.6). Los toggles globales (punto 37 del brief) quedan para la pantalla de configuración |
| Enlace permanente del curso | `/campus/cursos/{slug}/`, lecciones `/clases/` | El plugin usa `/campus/curso/{slug}/` mientras Tutor siga activo (sin choque). Al retirar Tutor, poner `av_course_slug = cursos`: los cursos migrados conservan el mismo slug, así que **las URLs antiguas siguen funcionando** |
| Enable Tutor LMS Login | Activado | Tutor sustituye `wp-login.php` por su propio login. **Riesgo:** los enlaces de crear contraseña y de recuperación del plugin usan `wp-login.php?action=rp`. Verificar en staging (punto 3 del plan); si Tutor los intercepta, desactivar esa opción: el campus tiene su propio login |
| Página del escritorio | `/campus/escritorio/` | El campus del plugin vive en `/campus/aula/`; el menú del sitio debe apuntar ahí cuando se retire Tutor |
| Borrar al desinstalar | Desactivado | Correcto: Tutor se desactiva, nunca se desinstala, hasta confirmar la migración |
| Membresía: "los estudiantes no podrán registrarse" (aviso del tema) | Desactivada | No afecta: el alta la hace el plugin al aprobar o al pagar, sin registro abierto |

**Volumen real:** unos 180 estudiantes (18 páginas de 10) y 220 matrículas, de las que 219
activas y 1 cancelada. Un solo lote de 300 por curso alcanza; la migración completa son unos
pocos clics.

## 9. Preguntas que siguen abiertas

1. **Correo de los alumnos migrados.** No se les avisa automáticamente. ¿Se les envía un correo
   único "tu campus cambió, entra por aquí" cuando esté todo listo? Se puede hacer desde la
   pantalla de Emails con una plantilla nueva.
2. ~~Quizzes y tareas de Tutor.~~ Confirmado el 2026-09-28: no se necesitan.

## 10. Decisiones necesarias (histórico, ya respondidas)

1. **¿Hay alumnos activos en Tutor LMS?** Si los hay, ¿terminan ahí o se migran sus matrículas?
2. **¿Qué es el plugin `cn-cursos` de la raíz?** Si es un listado de cursos hecho a medida,
   conviene que apunte a las landings nuevas.
3. **¿Qué pasarela tiene configurada hoy el WooCommerce de `/campus/`?** Desde fuera no se ve.
   Culqi y PayPal se instalan ahí, no en la raíz.

## 10. Decisiones necesarias (histórico, ya respondidas)

1. **¿Dónde está WooCommerce?** ¿En el mismo WordPress donde irá el campus o en otro? Define
   si la matrícula por compra son hooks locales (una semana) o un webhook entre sitios (dos y
   media, más pruebas).
2. **Aprobación y pago, en ese orden sólo para el camino de inscripción.** La compra directa no
   pasa por aprobación: el pago aprueba. ¿Conforme?
3. **¿Qué es "el otro WordPress" y qué debe viajar?** ¿Ventas → campus? ¿Dos campus? ¿Hace falta
   que el alumno entre a los dos con una sola sesión?
4. **Pasarela para arrancar.** Culqi cubre Perú (tarjeta, Yape, PagoEfectivo). PayPal se suma
   cuando haya alumnos fuera. ¿Empezamos sólo con Culqi?

## 11. Orden de trabajo

1. ~~**WooCommerce**~~ Hecho en v0.4.0: pestaña en el producto, matrícula por pedido (invitado
   incluido), política de reembolso, aviso en la página de gracias, botón *Comprar* directo al
   checkout.
1b. ~~**Migración desde Tutor LMS**~~ Hecho en v0.5.0.
2. ~~**Landing por secciones**~~ Hecho en v0.6.0: caja "Landing del curso" en el editor del
   curso con las 8 secciones, plantilla propia de conversión, barra fija en móvil, etiquetas
   sociales y datos estructurados, shortcodes `[av_course_landing]` y `[av_course_editions]`.
3. ~~**Perfil del alumno**~~ Hecho en v0.7.0, junto con el editor de sesión, clases en vivo
   y materiales.
4. **Integración con el otro WordPress**, según la decisión 3.
5. Después: importación Excel, anuncios, content drip, URLs limpias del campus, pantalla de
   configuración, endpoint de descarga protegido para materiales.

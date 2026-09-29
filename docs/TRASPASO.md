# Traspaso del proyecto — Aula Virtual SIQA

Documento para retomar el desarrollo desde otra cuenta (persona o sesión de Claude) sin
depender de conversaciones anteriores. Última actualización: **2026-09-29**, versión
**0.15.0**.

Si solo vas a leer una cosa, lee la sección 1 y la 7.

---

## 1. Resumen en un minuto

- **Qué es:** plugin de WordPress (PHP 8.1) que reemplaza a Tutor LMS en
  `astronumerologia.com`. Modelo **Curso → Ediciones → Matrículas**: el curso es el producto
  permanente con su landing; cada edición es una cohorte con fechas, cupo, precio, producto de
  WooCommerce, temario y alumnos propios.
- **Dónde está el código:** `https://github.com/siqatech/wp-aulavirtual`, rama **`main`** (se
  trabaja directo en `main`, sin pull requests, por decisión del equipo).
- **Estado:** 116 clases, 16 tablas, 290 comprobaciones automáticas en verde. Todo
  el alcance del MVP del brief está construido, más certificados, reportes, API REST,
  comentarios, content drip, gestión de alumnos y landing rediseñada.
- **En producción:** el plugin está instalado en el WordPress raíz de `astronumerologia.com`
  (tema Minimog + Elementor + WooCommerce). El 2026-09-29 la landing en vivo cargaba la
  versión **0.13.0**. Las versiones 0.14.0 y 0.15.0 se entregaron como zip; **falta confirmar
  que estén instaladas**.
- **Lo siguiente:** instalar 0.15.0, configurar color y limpiar contenido de prueba (sección
  7A), recorrer `docs/PRUEBAS-STAGING.md` en el sitio real (7B), y decidir la migración de
  Tutor (7E).

---

## 2. Mapa de documentos

| Documento | Para qué |
|---|---|
| `CLAUDE.md` (raíz) | Instrucciones que Claude Code carga solo al abrir el repo: reglas, comandos, convenciones |
| `docs/TRASPASO.md` | Este documento: estado, historia, pendientes, cómo trabajar |
| `docs/BRIEF-ORIGINAL.md` | Requisitos originales completos (75 puntos) tal como se entregaron |
| `ARCHITECTURE.md` | Arquitectura de fondo: modelo de datos, roles, seguridad, rendimiento (primera entrega) |
| `docs/GUIA-TECNICA.md` | Mapa de módulos, convenciones, eventos, hooks, shortcodes, esquema y migraciones |
| `docs/SEGURIDAD.md` | Capa de seguridad completa, resultado de las dos revisiones, pendientes de seguridad |
| `docs/GUIA-ADMINISTRADOR.md` | Manual de uso para el equipo académico (en español llano) |
| `docs/PRUEBAS-STAGING.md` | Lista de verificación manual en un WordPress real |
| `docs/API-REST.md` | API `aula-virtual/v1` para integraciones |
| `docs/FLUJO-COMERCIAL.md` | Flujo de venta, análisis del sitio, ajustes de Tutor y su equivalente |
| `CHANGELOG.md` | Qué cambió en cada versión, con detalle |

---

## 3. Cómo retomarlo desde otra cuenta

### 3.1 Accesos necesarios

1. **GitHub:** acceso de escritura a `siqatech/wp-aulavirtual` (lo da un owner de la
   organización `siqatech`). En Claude Code en la web: conectar GitHub y añadir el repositorio
   al entorno.
2. **WordPress:** una cuenta de administrador en `astronumerologia.com` para instalar el zip y
   revisar pantallas. Las credenciales **no** están ni deben estar en el repositorio.
3. Opcional: acceso al panel de Bunny Stream si se tocan videos (las claves se guardan en
   *Aula Virtual → Configuración → Videos* y nunca se muestran de vuelta).

### 3.2 Arrancar una sesión nueva

```bash
git clone https://github.com/siqatech/wp-aulavirtual.git
cd wp-aulavirtual
php tests/smoke-test.php       # debe terminar en "290 comprobaciones, 0 fallos"
php tests/lint-classes.php     # "116 clases cargadas, 0 fallos"
```

Primer mensaje sugerido para una sesión de Claude:

> Lee `CLAUDE.md` y `docs/TRASPASO.md` del repositorio wp-aulavirtual. Vamos a continuar con
> [tarea]. Trabaja en la rama main y haz push al terminar.

### 3.3 Generar el zip instalable

```bash
bin/build-zip.sh               # → dist/aula-virtual-<versión>.zip (~1.8 MB)
```

Usa el último commit (no incluye cambios sin confirmar), deja fuera `tests/`, `bin/`, `docs/`,
`CLAUDE.md` y `ARCHITECTURE.md` (en el servidor quedarían públicos), instala solo
PhpSpreadsheet y la recorta. **Nota:** los zips hasta 0.15.0 sí incluían `docs/`; al instalar
la siguiente versión desaparecen del servidor. Instalar en WordPress: *Plugins → Añadir nuevo → Subir plugin →
Reemplazar la versión actual*. Los datos (tablas, opciones) se conservan; el migrador de
esquema corre solo.

---

## 4. Historia de versiones

| Versión | Commit | Contenido |
|---|---|---|
| 0.1.0 | `dd55ce1` | Núcleo: contenedor, esquema de 16 tablas, roles, eventos, REST base, instalación |
| 0.2.0 | `51aba1c` | Ediciones, sesiones, matrícula manual, campus, progreso |
| 0.3.0 | `a251fba` | Enlace de inscripción, solicitudes con aprobación, emails editables |
| — | `bea9b6c` | Revisión del flujo comercial (docs/FLUJO-COMERCIAL.md) |
| 0.4.0 | `ee4ccbc` | Matrícula automática por compra en WooCommerce |
| 0.5.0 | `86477fd` | Migración desde Tutor LMS (misma base de datos) |
| 0.6.0 | `43e722f` | Landing por curso administrable por secciones |
| 0.7.0 | `328b7c3` | Perfil del alumno, editor de sesión, clases en vivo, materiales |
| 0.8.x | `0058d28`, `4928378` | Videos con Bunny Stream (embeds firmados), migración completa de lecciones |
| 0.9.0 | `e9caa4b` | Configuración, anuncios con correo, modo enfoque |
| 0.10.0 | `116b420` | Duplicar ediciones y cursos, reordenar sesiones, importar Excel/CSV, repetir curso |
| 0.11.0 | `4819be4` | Content drip, materiales protegidos, URLs limpias del campus, comentarios |
| 0.12.0 | `a0bf3c3` | Certificados, reportes, API REST de integración, correos de comentarios |
| 0.12.1–0.12.3 | `4504483`…`5571632` | Dos revisiones de seguridad aplicadas y `docs/SEGURIDAD.md` |
| 0.13.0 | `2a7145d` | Pantalla de edición con pestañas, enlaces de inscripción legibles, tildes |
| 0.14.0 | `0b0bcb9` | Gestión de alumnos: ficha, reenviar acceso, contraseñas, mover, lote |
| 0.15.0 | `e045e3a` | Rediseño completo de la landing del curso |

El detalle de cada una está en `CHANGELOG.md`.

---

## 5. Arquitectura en una página

```
aula-virtual.php → Core\Plugin::boot() (plugins_loaded)
   ├─ register() de cada ServiceProvider  (enlaza servicios en el Container)
   └─ boot()     de cada ServiceProvider  (registra hooks)

includes/<Módulo>/
   *Repository   → solo filas; columnas en lista blanca; todo por $wpdb->prepare
   *Service      → reglas de negocio; devuelve int|true|WP_Error; nunca imprime
   *Screen/*Controller → nonce → capacidad → propiedad → servicio → redirect con aviso
   *ServiceProvider

Comunicación entre módulos: EventBus (acción aula_virtual/event/{nombre}); los correos
escuchan eventos. 'silent' => true evita correos (migración, recálculos).
```

**Módulos (25 carpetas en `includes/`):** Core, Database, Permissions, Security, Courses,
Editions, Curriculum, Enrollments, Progress, LiveClasses, Videos, Announcements,
Certificates, Reports, Comments, Materials, Emails, WooCommerce, Migration, Landing, Imports,
Students, Campus, Admin, REST. Tabla completa con dependencias en `docs/GUIA-TECNICA.md` §2.

**Datos:** CPT `av_course` (curso + meta `_av_landing` para la landing) y 16 tablas `wp_av_*`
(`editions`, `modules`, `lessons`, `materials`, `enrollments`, `progress`,
`registration_requests`, `enrollment_links`, `announcements`, `live_classes`, `certificates`,
`email_templates`, `import_jobs`, `lesson_comments`, `logs`, `audit_log`). Esquema
versionado en `Database\Schema::VERSION` (hoy **1.3.2**) con migraciones de datos en
`Database\Migrator::data_migrations()`.

**URLs públicas:**

| URL | Qué es |
|---|---|
| `/curso/{slug}/` | Landing del curso (slug configurable: `av_course_slug`) |
| `/inscripcion/{codigo}/` | Formulario de inscripción de un enlace de edición |
| Página del campus (`[av_campus]`, p. ej. `/aula/`) + `/aula/curso/{codigo}/`, `/aula/sesion/{id}/`, `/aula/perfil/` | Campus del alumno con URLs limpias |
| `/certificado/{codigo}/` | Verificación pública del certificado |
| `admin-post.php?action=av_download&material={id}` | Descarga protegida de materiales |
| `/wp-json/aula-virtual/v1/…` | API REST (`docs/API-REST.md`) |

**Menú de administración (*Aula Virtual*):** Ediciones, Alumnos, Solicitudes, Importar
alumnos, Anuncios, Certificados, Reportes, Emails, Migrar desde Tutor (solo si hay Tutor),
Cursos, Configuración.

**Roles:** Administrador LMS (`av_admin`), Instructor LMS (solo sus cursos), Estudiante LMS
(solo el campus). Matriz completa en `docs/SEGURIDAD.md` §1.

---

## 6. Cobertura del brief

Referencia: `docs/BRIEF-ORIGINAL.md`, puntos 68–70.

**MVP (punto 68): completo.** Core, base de datos, roles, cursos, ediciones, constructor de
temario, módulos, sesiones, videos, materiales, clases en vivo, alumnos, matrículas, enlaces
privados, aprobación, importación Excel, WooCommerce, correos, campus, login, progreso,
anuncios y configuración.

**Segunda fase (punto 69):**

| Punto | Estado |
|---|---|
| Certificados | Hecho (0.12): página imprimible y verificable; **sin PDF generado en servidor** |
| Reportes avanzados | Hecho (0.12): resumen, detalle por edición, CSV |
| Content drip | Hecho (0.11) |
| Notificaciones automáticas | Hecho: correos por evento, editables |
| Calendario avanzado | **No hecho** (no hay vista de calendario ni archivo .ics) |
| Reseñas | **No hecho** |
| Quizzes y tareas | **Descartados** el 2026-09-28 por decisión del equipo |

**Tercera fase (punto 70):** nada construido salvo una **API externa** básica (0.12). Zoom y
Meet se manejan como enlaces en las clases en vivo, sin integración de API. WhatsApp existe
solo como botón en la landing.

**Otros puntos del brief sin cubrir:** catálogo de cursos propio (punto 38; hoy se usa
Elementor), toggles globales de visibilidad (punto 37; la landing se configura por curso) y
archivo de traducción `.pot` (punto 53; los textos ya pasan por `__()` con el dominio
`aula-virtual`, falta generar el `.pot`).

---

## 7. Pendientes, en orden de prioridad

### 7A. Operativos en el sitio (hacer primero, sin programar)

1. Instalar el zip **0.15.0** y confirmar la versión en *Plugins*.
2. *Aula Virtual → Configuración → General → Color principal*: poner el dorado de la marca
   (p. ej. `#c9a45c`). Hoy vale el azul por defecto `#1d4ed8` o el `#3e64de` heredado de Tutor.
3. En el curso "Numerología Básica y Vidas Pasadas" (`/curso/numerologia-basica-y-vidas-pasas/`):
   borrar el contenido de prueba de la caja *Landing del curso* ("hhhhh", "jjjjj", "CERT")
   y revisar el título.
4. Si la cabecera fija de Minimog tapa el menú de secciones de la landing: *Apariencia →
   Personalizar → CSS adicional* → `.av-landing{--av-sticky-top:80px}`.
5. *Ajustes → Enlaces permanentes → Guardar* una vez tras actualizar (refresca las reglas de
   `/inscripcion/`, `/certificado/` y del campus).

### 7B. Verificación en WordPress real

La lógica pura está cubierta por las pruebas automáticas, pero **el SQL real, los hooks de
WooCommerce, los correos y las pantallas solo se han validado parcialmente en el sitio**.
Recorrer `docs/PRUEBAS-STAGING.md` completo (idealmente en una copia de staging) y anotar
fallos. Puntos de mayor riesgo:

- Compra en WooCommerce → matrícula → correo de bienvenida con enlace para crear contraseña.
- El enlace `wp-login.php?action=rp`: si "Enable Tutor LMS Login" sigue activo, Tutor puede
  interceptarlo (ver `docs/FLUJO-COMERCIAL.md` §8b).
- Pantalla *Alumnos* (0.14) y pestañas de edición (0.13) con datos reales.
- Landing 0.15 con los datos reales del curso, en escritorio y en celular.

### 7C. Seguridad (baja severidad, de `docs/SEGURIDAD.md` §6)

1. Instructores ven listados generales (todas las ediciones, anuncios, resumen de reportes).
   Filtrar por `AccessControl::own_course_ids()` si habrá instructores externos.
2. Avisos del admin y del perfil viajan en la URL; pasarlos a un transient por usuario.
3. Enlaces de inscripción sin límite de usos por defecto (recomendación operativa: poner
   vencimiento o número de usos).
4. Certificado sin PDF firmado en servidor.
5. Decisión legal pendiente: texto de consentimiento en el formulario de inscripción y
   retención de solicitudes rechazadas (Ley 29733).

### 7D. Funcionalidades no construidas (según prioridad del negocio)

- Correo único a los alumnos migrados: "tu campus cambió, entra por aquí".
- Calendario del alumno (vista y/o `.ics`).
- PDF del certificado generado en servidor.
- Catálogo de cursos propio o bloque de Elementor.
- Archivo `.pot` de traducción.
- Reseñas, integración con API de Zoom/Meet, suscripciones (fase 3 del brief).

### 7E. Decisiones abiertas (las toma el equipo, no el desarrollo)

1. **Dónde vive el campus definitivo.** La documentación original apuntaba a una instalación
   en `astronumerologia.com/campus/` (donde está Tutor LMS). El plugin hoy está en el WordPress
   **raíz**. La migración desde Tutor (`Migration\TutorMigrator`) lee las tablas de Tutor **en
   la misma base de datos**: si Tutor está en otra instalación, hay que instalar el plugin allí
   o exportar/importar alumnos (Importar alumnos acepta CSV/XLSX). Confirmar antes de migrar.
2. Cuándo retirar Tutor y cambiar `av_course_slug` a `cursos` para conservar las URLs viejas.
3. Si se avisa por correo a los alumnos migrados (7D).

---

## 8. Cómo trabajar en el código

### 8.1 Comandos

```bash
php tests/smoke-test.php                    # 290 comprobaciones de lógica y cableado
php tests/lint-classes.php                  # carga las 116 clases (detecta errores fatales)
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l | grep -v '^No syntax'
composer install && composer lint           # WordPress Coding Standards (opcional)
bin/build-zip.sh                            # zip instalable en dist/
php bin/preview-landing.php full '#c9a45c' > dist/landing.html   # vista previa de la landing
```

Las pruebas usan stubs mínimos de WordPress en `tests/wp-stubs.php`. Si una prueba nueva
necesita una función de WordPress, se añade ahí (no en la prueba).

### 8.2 Al terminar un cambio (lista de cierre)

1. Pruebas y `php -l` en verde; añadir comprobaciones al `smoke-test.php` para lo nuevo.
2. Si cambia una tabla: subir `Schema::VERSION` y, si hace falta, añadir migración de datos.
3. Subir versión en **tres** sitios: cabecera `Version:` y constante `AV_VERSION` de
   `aula-virtual.php`, y `Stable tag:` de `readme.txt`.
4. Entrada en `CHANGELOG.md` (en español, qué cambia para quien usa el plugin).
5. Documentar en la guía que corresponda (administrador, técnica, seguridad, pruebas) y, si
   cambia el número de comprobaciones o clases, en `README.md`.
6. Commit descriptivo en español, `git push origin main`, y `bin/build-zip.sh` si hay que
   entregar.

### 8.3 Convenciones que se respetan en todo el código

- `declare(strict_types=1)`, clases `final`, guardia `ABSPATH`, prefijo `av_` / `wp_av_`.
- Textos visibles en **español con tildes**, siempre por `__()` / `esc_html__()` con el
  dominio `aula-virtual`.
- Toda acción de administración: `admin-post.php` con nonce → capacidad →
  `AccessControl::can_manage_editions( $course_id )` (propiedad) → servicio → redirect.
- Escapar en la salida, sanear en la entrada con `Security\Sanitizer`.
- Fechas en UTC en la base; se convierten al mostrar.
- Los servicios no conocen Admin, Campus ni Emails: se comunican por eventos.
- Plantillas de front sobrescribibles desde el tema en `aula-virtual/…` (landing, campus,
  inscripción, layout de correo).

---

## 9. Reglas que no se negocian

1. **Contraseñas:** nunca se guardan en texto plano, nunca se envían por correo ni se muestran
   después de crearlas. El alumno recibe un enlace para crearla (`AccountHelper`, validez
   configurable 72 h por defecto, máximo 168 h).
2. **Seguridad por capas** en cada pantalla y endpoint (nonce, capacidad, propiedad, límites de
   tasa en formularios públicos y API). Cualquier cambio de seguridad se refleja en
   `docs/SEGURIDAD.md`.
3. **Datos personales** (Ley 29733): el payload de los eventos lleva ids, no datos; la
   desinstalación solo borra datos si se marca la opción explícita.
4. **Stack:** WordPress + PHP. Este repositorio es independiente del proyecto SIQA en Django
   (`siqatech/siqaapp`); la regla "Django + HTMX" de aquel proyecto no aplica aquí.
5. **Nunca** desactivar la verificación TLS ni tocar el proxy en el entorno de desarrollo
   (ver 10).
6. Sin pull requests salvo que el equipo lo pida: se trabaja en `main`.

---

## 10. Notas del entorno de Claude Code en la nube

- La salida HTTPS pasa por un proxy con CA propia (`/root/.ccr/ca-bundle.crt`). Para
  descargar una página: `wget --ca-certificate=/root/.ccr/ca-bundle.crt …`. No usar
  `--no-check-certificate` ni desactivar TLS.
- Chromium (Playwright, en `/opt/pw-browsers/chromium`) no confía en esa CA. Para ver cómo se
  ve una página del sitio: descargar un espejo con `wget --mirror --page-requisites
  --convert-links --ca-certificate=…`, reemplazar el `<main class="av-landing">` por la salida
  de `bin/preview-landing.php` y abrir el archivo local con las peticiones de red bloqueadas.
- `composer` está instalado; `bin/build-zip.sh` lo usa.
- No hay WordPress local: la verificación funcional se hace en el sitio (sección 7B).

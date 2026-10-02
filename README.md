# Aula Virtual SIQA

Plugin de WordPress para gestionar cursos con el modelo **Curso → Ediciones → Matrículas**:
un curso es el producto educativo permanente (con su landing pública); cada edición es una
cohorte concreta con fechas, horario, cupo, producto de WooCommerce y alumnos propios; la
matrícula siempre apunta a una edición.

Sitio destino: `astronumerologia.com/campus/` (instalación de WordPress con WooCommerce y
Tutor LMS Pro, cuyos alumnos se migran con la herramienta incluida).

## Estado

| Versión | Contenido |
|---|---|
| 0.1 | Núcleo: contenedor, esquema, roles, eventos, REST, instalación |
| 0.2 | Ediciones, sesiones, matrícula manual, campus, progreso |
| 0.3 | Enlace de inscripción, solicitudes con aprobación, emails editables |
| 0.4 | Matrícula automática por compra en WooCommerce |
| 0.5 | Migración desde Tutor LMS |
| 0.6 | Landing por curso administrable por secciones |
| 0.7 | Perfil del alumno, editor de sesión, clases en vivo, materiales |
| 0.8 | Videos con Bunny Stream (embeds firmados), migración de adjuntos y cohortes como ediciones |
| 0.9 | Pantalla de configuración, anuncios con correo a la edición, modo enfoque |
| 0.10 | Duplicar ediciones y cursos, reordenar sesiones, importar Excel/CSV, repetir curso |
| 0.11 | Content drip, materiales protegidos, URLs limpias del campus, comentarios |
| 0.12 | Certificados, reportes, API REST; dos revisiones de seguridad (0.12.1–0.12.3) |
| 0.13 | Pantalla de edición con pestañas, enlaces de inscripción legibles |
| 0.14 | Gestión de alumnos: ficha, reenviar acceso, contraseñas, mover, acciones en lote |
| 0.15 | Rediseño completo de la landing del curso |
| 0.16 | Pantallas de acceso y de «Crea tu contraseña» con el logo y el color de la marca |
| 0.17 | Campus rediseñado con la línea gráfica del sitio; las direcciones limpias del campus ya no dan 404 |
| 0.18 | Vista de sesión tipo reproductor: panel «Contenido del curso» con secciones y recursos, pestañas bajo el video |
| 0.19 | Editor de sesión ordenado en tarjetas; la carga de materiales vuelve a funcionar |

El flujo completo funciona de punta a punta:
`Landing → Inscripción o Compra → Aprobación → Pago → Matrícula → Bienvenida → Campus →
Clase en vivo / Materiales → Progreso`.

El plugin está instalado en `astronumerologia.com` (0.13.0 confirmado en vivo el 2026-09-29).
Las 329 comprobaciones automáticas cubren la lógica pura y el cableado de los módulos; el SQL,
los hooks de WooCommerce y las pantallas se validan siguiendo `docs/PRUEBAS-STAGING.md`, que
todavía no se ha recorrido completo en el sitio real.

**Para retomar el proyecto desde otra cuenta: [`docs/TRASPASO.md`](docs/TRASPASO.md).**

## Documentación

| Documento | Para quién | Qué contiene |
|---|---|---|
| [`docs/TRASPASO.md`](docs/TRASPASO.md) | Quien retome el proyecto | Estado, historia, pendientes priorizados, decisiones abiertas, cómo trabajar |
| [`CLAUDE.md`](CLAUDE.md) | Sesiones de Claude Code | Reglas, comandos y lista de cierre de cada cambio |
| [`docs/BRIEF-ORIGINAL.md`](docs/BRIEF-ORIGINAL.md) | Desarrollo | Requisitos originales completos del proyecto |
| [`docs/GUIA-ADMINISTRADOR.md`](docs/GUIA-ADMINISTRADOR.md) | Coordinación académica | Cómo crear cursos, ediciones, sesiones, aprobar inscripciones, vincular productos, editar correos y landings, migrar desde Tutor |
| [`docs/PRUEBAS-STAGING.md`](docs/PRUEBAS-STAGING.md) | Quien instala | Lista de verificación para la primera activación en un sitio de pruebas |
| [`docs/GUIA-TECNICA.md`](docs/GUIA-TECNICA.md) | Desarrollo | Mapa de módulos, convenciones, hooks, eventos, shortcodes, opciones, cómo añadir un módulo |
| [`docs/SEGURIDAD.md`](docs/SEGURIDAD.md) | Dirección, desarrollo y quien administra el servidor | Roles y permisos, controles por capa, datos personales (Ley 29733), resultado de la revisión, configuración del servidor y pruebas de seguridad |
| [`docs/API-REST.md`](docs/API-REST.md) | Integrador (WordPress de la tienda) | Rutas, autenticación, ejemplos curl y notas para Elementor |
| [`docs/FLUJO-COMERCIAL.md`](docs/FLUJO-COMERCIAL.md) | Dirección | Revisión del flujo comercial, decisiones tomadas y análisis del sitio destino |
| [`ARCHITECTURE.md`](ARCHITECTURE.md) | Desarrollo | Arquitectura de fondo: modelo de datos, roles, seguridad, rendimiento |
| [`CHANGELOG.md`](CHANGELOG.md) | Todos | Qué cambió en cada versión |

## Instalación

1. Generar el zip con `bin/build-zip.sh` (queda en `dist/`) e instalarlo desde *Plugins →
   Añadir nuevo → Subir plugin*. Incluye PhpSpreadsheet para importar Excel; no incluye
   pruebas ni documentación interna.
2. Activar desde *Plugins*. La activación crea las tablas, los roles, las plantillas de correo
   por defecto y la página del campus (`/campus/aula/` en esa instalación).
3. Ir a *Aula Virtual → Configuración* (color, remitente, Bunny, WooCommerce) y a *Emails*
   para revisar los textos.
4. Si hay WooCommerce, instalar las pasarelas (Culqi, PayPal) desde *WooCommerce → Pagos*.
5. Seguir `docs/PRUEBAS-STAGING.md` antes de abrir el campus a alumnos reales.

## Requisitos

WordPress 6.4+, PHP 8.1+, MySQL 5.7+ o MariaDB 10.3+. WooCommerce es opcional.

## Verificación rápida

```bash
php tests/smoke-test.php      # 329 comprobaciones de logica y cableado
php tests/lint-classes.php    # carga las 117 clases del plugin
bin/build-zip.sh             # zip instalable en dist/
php bin/preview-landing.php > dist/landing.html   # vista previa de la landing sin WordPress
composer install && composer lint   # WordPress Coding Standards (opcional)
```

## Licencia

GPL-2.0-or-later. Propiedad de FARGIL S.A.C.

=== Aula Virtual ===
Contributors: fargil
Tags: lms, cursos, matriculas, elearning, woocommerce
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.12.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gestion academica para WordPress basada en el modelo Curso -> Ediciones -> Matriculas.

== Description ==

Aula Virtual separa el curso (producto educativo permanente, con su landing publica) de sus
ediciones (cohortes con fechas, cupo, modalidad e instructor propios). Las matriculas
apuntan siempre a una edicion, de modo que un mismo curso puede ejecutarse cada año sin
duplicar contenido ni mezclar alumnos.

Caracteristicas del nucleo:

* Cursos como contenido de WordPress: permalinks, SEO, Gutenberg, Elementor.
* Ediciones, temario, matriculas y progreso en tablas propias e indexadas.
* Roles y capacidades propios para administracion, docencia y alumnado.
* Sistema de eventos internos: la logica de negocio no envia correos, los emite.
* API REST con espacio de nombres propio y verificacion de permisos en cada ruta.
* Integracion opcional con WooCommerce: el LMS funciona sin el.

== Installation ==

1. Copiar la carpeta `aula-virtual` en `wp-content/plugins/`.
2. Ejecutar `composer install` dentro de la carpeta del plugin (o desplegar con `vendor/` incluido).
3. Activar el plugin desde el listado de plugins de WordPress.

La activacion crea las tablas, los roles y las paginas del campus. Desactivar el plugin no
elimina ningun dato.

== Upgrade Notice ==

= 0.1.0 =
Primera entrega del nucleo. Sin interfaz de administracion todavia.

== Changelog ==

= 0.1.0 =
* Nucleo del plugin: bootstrap, contenedor, esquema de base de datos, roles, eventos,
  post type de curso, base REST e instalacion.

# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).
Este plugin sigue versionado semántico.

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
- Roles `sev_lms_admin`, `sev_lms_instructor` y `sev_lms_student` con su matriz de capacidades,
  resincronización por versión y bloqueo de wp-admin para alumnos.
- `Permissions\AccessControl` como punto único de comprobación de permisos.
- Post type `sev_lms_course` con taxonomías, capacidades propias vía `map_meta_cap` y campos
  registrados con `register_post_meta()`.
- `Core\Events\EventBus` y catálogo de eventos de dominio sobre el sistema de hooks.
- `Core\Logger` con niveles configurables y `Core\AuditLog` para la traza administrativa.
- `Security\Sanitizer` con los saneadores usados por todos los módulos.
- Base REST (`sev-lms/v1`) y controlador de cursos.
- `uninstall.php` con borrado de datos opcional, desactivado por defecto.
- Smoke test ejecutable sin WordPress (`php tests/smoke-test.php`), 38 comprobaciones.

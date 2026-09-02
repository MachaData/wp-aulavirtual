# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).
Este plugin sigue versionado semántico.

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

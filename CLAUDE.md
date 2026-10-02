# CLAUDE.md — Aula Virtual SIQA (plugin de WordPress)

Plugin LMS para `astronumerologia.com`, propiedad de FARGIL S.A.C. Reemplaza a Tutor LMS con
el modelo **Curso → Ediciones → Matrículas**.

**Antes de trabajar, leer `docs/TRASPASO.md`**: estado actual, historia, pendientes
priorizados y decisiones abiertas. Los requisitos originales están en
`docs/BRIEF-ORIGINAL.md`.

## Reglas

- Trabajar en la rama `main` y hacer `git push origin main` al terminar. No abrir pull
  requests salvo que se pida.
- Interfaz y documentación en **español con tildes**. Todo texto visible por `__()` /
  `esc_html__()` con el dominio `aula-virtual`.
- Contraseñas: nunca guardarlas en claro, enviarlas por correo ni mostrarlas después. El alumno
  siempre recibe un enlace para crearla.
- Toda acción de administración: nonce → capacidad → propiedad
  (`AccessControl::can_manage_editions( $course_id )`) → servicio → redirect con aviso.
- Escapar en la salida, sanear en la entrada (`Security\Sanitizer`), SQL solo con
  `$wpdb->prepare` y columnas en lista blanca del repositorio.
- Los servicios no imprimen ni redirigen; devuelven `int|true|WP_Error`. Los módulos se
  comunican por eventos (`Core\Events\EventBus`), no por llamadas a Admin/Campus/Emails.
- Cualquier cambio que afecte la seguridad se documenta en `docs/SEGURIDAD.md`.
- Nunca desactivar la verificación TLS ni tocar el proxy del entorno (ver `docs/TRASPASO.md`
  §10).
- Este repositorio es WordPress/PHP y es independiente de `siqatech/siqaapp` (Django): las
  reglas de stack de aquel proyecto no aplican aquí.

## Comandos

```bash
php tests/smoke-test.php       # 329 comprobaciones, deben quedar 0 fallos
php tests/lint-classes.php     # carga las 117 clases
bin/build-zip.sh               # zip instalable en dist/ (usa el último commit)
php bin/preview-landing.php full '#c9a45c' > dist/landing.html   # vista previa de la landing
```

No hay WordPress local: los stubs de WordPress para pruebas viven en `tests/wp-stubs.php`.

## Al cerrar un cambio

1. Pruebas en verde y comprobaciones nuevas en `tests/smoke-test.php`.
2. Si cambia una tabla: subir `Database\Schema::VERSION` (hoy 1.3.2) y añadir migración en
   `Database\Migrator::data_migrations()` si hace falta.
3. Versión en `aula-virtual.php` (cabecera `Version:` y `AV_VERSION`) y `readme.txt`
   (`Stable tag:`).
4. `CHANGELOG.md`, la guía que corresponda y los conteos de `README.md`.
5. Actualizar `docs/TRASPASO.md` (historia de versiones y pendientes).
6. Commit en español, push, y `bin/build-zip.sh` si hay entrega.

## Dónde está cada cosa

- `aula-virtual.php` → `Core\Plugin::boot()` → ServiceProviders de `includes/<Módulo>/`.
- `admin/views/` pantallas de wp-admin; `templates/` front (landing, campus, inscripción,
  correos, certificado), sobrescribibles desde el tema en `aula-virtual/`.
- `assets/css`, `assets/js` estilos y scripts (sin build: CSS y JS planos).
- Mapa completo de módulos, hooks y eventos: `docs/GUIA-TECNICA.md`.

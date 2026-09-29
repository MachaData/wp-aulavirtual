#!/usr/bin/env bash
# Genera el zip instalable del plugin: dist/aula-virtual-<version>.zip
#
# Uso:  bin/build-zip.sh            (desde la raiz del repositorio)
#
# Toma el ultimo commit (git archive: no incluye cambios sin confirmar), quita pruebas y docs,
# instala solo las dependencias de produccion (PhpSpreadsheet, para importar XLSX) y las
# recorta a su codigo y licencia para que el zip pese ~2 MB en vez de ~120 MB.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="$(grep -m1 -E '^\s*\*\s*Version:' "$ROOT/aula-virtual.php" | awk '{print $NF}')"
DIST="$ROOT/dist"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

if [ -n "$(git -C "$ROOT" status --porcelain)" ]; then
	echo "Aviso: hay cambios sin confirmar; el zip usa el ultimo commit." >&2
fi

mkdir -p "$WORK/aula-virtual" "$DIST"
git -C "$ROOT" archive HEAD | tar -x -C "$WORK/aula-virtual"
# Fuera del zip: pruebas, herramientas y documentación interna (quedaría pública en
# wp-content/plugins/aula-virtual/ y describe la seguridad y el negocio).
rm -rf "$WORK/aula-virtual/tests" "$WORK/aula-virtual/bin" "$WORK/aula-virtual/docs" \
	"$WORK/aula-virtual/CLAUDE.md" "$WORK/aula-virtual/ARCHITECTURE.md"

composer install --working-dir="$WORK/aula-virtual" --no-dev --optimize-autoloader \
	--no-interaction --no-progress --quiet

# Cada paquete conserva solo su codigo (src/ o classes/), su licencia y su composer.json.
find "$WORK/aula-virtual/vendor" -mindepth 2 -maxdepth 2 -type d | while read -r pkg; do
	case "$pkg" in */vendor/composer) continue ;; esac
	find "$pkg" -mindepth 1 -maxdepth 1 \
		! -name src ! -name classes ! -iname 'license*' ! -name composer.json \
		-exec rm -rf {} +
done

# Comprobacion: el plugin y PhpSpreadsheet cargan desde el zip.
php -l "$WORK/aula-virtual/aula-virtual.php" >/dev/null
php -r 'require $argv[1]; exit( class_exists( "PhpOffice\\PhpSpreadsheet\\IOFactory" ) ? 0 : 1 );' \
	"$WORK/aula-virtual/vendor/autoload.php"

OUT="$DIST/aula-virtual-$VERSION.zip"
rm -f "$OUT"
( cd "$WORK" && zip -qr "$OUT" aula-virtual )
echo "Listo: $OUT ($(du -h "$OUT" | cut -f1))"

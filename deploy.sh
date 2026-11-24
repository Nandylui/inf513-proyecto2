#!/usr/bin/env bash
# =====================================================================================================================
# Despliegue del Sistema Web del Consejo Facultativo (INF513 · grupo07sa) en el servidor de la cátedra.
#
#   Uso:   ./deploy.sh <DIRECTORIO_WEB> [--seed]
#   Ej.:   ./deploy.sh /var/www/html/inf513/grupo07sa/proyecto2 --seed
#
#   <DIRECTORIO_WEB>  carpeta que Apache publica en https://www.tecnoweb.org.bo/inf513/grupo07sa/proyecto2
#   --seed            ejecuta además los seeders (roles, permisos, menú, conceptos de pago, admin y datos de ejemplo)
#
# Qué hace (es idempotente; puede ejecutarlo de nuevo en cada actualización):
#   1. Instala las dependencias de PHP (composer, sin paquetes de desarrollo).
#   2. Prepara backend/.env si no existe (APP_KEY, modo producción) - usted completa las credenciales de PostgreSQL.
#   3. Crea/actualiza el esquema de PostgreSQL (php artisan migrate --force) y, si pide --seed, los datos iniciales.
#   4. Publica backend/public (SPA React ya compilada + front controller de Laravel) en <DIRECTORIO_WEB>.
#      El código de Laravel queda FUERA de la carpeta pública (solo public/ es accesible desde la web).
# =====================================================================================================================
set -euo pipefail

WEB_DIR="${1:-}"; SEED="${2:-}"
if [[ -z "$WEB_DIR" ]]; then echo "Uso: $0 <DIRECTORIO_WEB> [--seed]"; exit 1; fi
RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP="$RAIZ/backend"
URL_PUBLICA="${URL_PUBLICA:-https://www.tecnoweb.org.bo/inf513/grupo07sa/proyecto2}"

command -v php >/dev/null || { echo "ERROR: PHP no está instalado o no está en el PATH."; exit 1; }
command -v composer >/dev/null || { echo "ERROR: composer no está disponible. Instálelo o use 'php composer.phar'."; exit 1; }
php -r 'exit(extension_loaded("pdo_pgsql") ? 0 : 1);' || { echo "ERROR: falta la extensión pdo_pgsql de PHP."; exit 1; }

cd "$APP"
echo "==> 1/4 Dependencias de PHP"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> 2/4 Configuración (.env)"
if [[ ! -f .env ]]; then
  cp .env.example .env
  php artisan key:generate --force
  sed -i "s|^APP_ENV=.*|APP_ENV=production|; s|^APP_DEBUG=.*|APP_DEBUG=false|; s|^APP_URL=.*|APP_URL=$URL_PUBLICA|" .env
  echo "    Se creó backend/.env. EDÍTELO ahora: DB_HOST=db.tecnoweb.org.bo, DB_DATABASE, DB_USERNAME, DB_PASSWORD, ADMIN_CLAVE, SEED_DEMO, SEED_HISTORICO."
  echo "    Luego vuelva a ejecutar:  $0 $WEB_DIR ${SEED:-}"
  exit 0
fi
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force
mkdir -p storage/logs storage/framework/{cache/data,sessions,views} storage/app/private bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache || true

echo "==> 3/4 Base de datos PostgreSQL"
php artisan migrate --force
if [[ "$SEED" == "--seed" ]]; then php artisan db:seed --force; fi
php artisan config:clear >/dev/null; php artisan route:clear >/dev/null

echo "==> 4/4 Publicación en $WEB_DIR"
mkdir -p "$WEB_DIR"
# La SPA compilada (index.html + assets/) y el front controller. Se copia en lugar de enlazar para no depender de symlinks del servidor.
cp -a "$APP/public/." "$WEB_DIR/"
# El front controller debe encontrar el código de Laravel, que está fuera de la carpeta pública.
sed -i "s#__DIR__.'/../#'$APP/#g" "$WEB_DIR/index.php"
# Los archivos subidos (documentos, excusas) se guardan en storage/app: no se exponen por la web.

echo
echo "Listo. Verifique:"
echo "   $URL_PUBLICA/            -> aplicación React (pantalla de ingreso)"
echo "   $URL_PUBLICA/api/        -> {\"sistema\":\"Consejo Facultativo FICCT\",...}"
echo "   Administrador inicial: ver ADMIN_CORREO / ADMIN_CLAVE en backend/.env"

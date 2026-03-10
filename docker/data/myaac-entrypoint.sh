#!/bin/sh
set -eu

DATA_DIR="/myaac-data"
APP_DIR="/var/www/html"
PATCH_THEME_CANARY="/opt/patch-theme-canary-characters.php"
PATCH_THEME_CANARY_INDEX="/opt/patch-theme-canary-index.php"
GENERATE_ITEM_CLIENT_ID_MAP="/opt/generate-item-client-id-map.php"

mkdir -p \
	"$DATA_DIR/system/cache" \
	"$DATA_DIR/system/cache/persistent" \
	"$DATA_DIR/system/cache/twig" \
	"$DATA_DIR/system/cache/plugins" \
	"$DATA_DIR/system/cache/signatures" \
	"$DATA_DIR/system/logs" \
	"$DATA_DIR/system/php_sessions" \
	"$DATA_DIR/images/guilds" \
	"$DATA_DIR/images/houses" \
	"$DATA_DIR/images/gallery" \
	"$DATA_DIR/plugins" \
	"$DATA_DIR/install"

# Rebuild transient caches on every boot to avoid stale routes/plugins after upgrades.
rm -rf "$DATA_DIR/system/cache"/*
mkdir -p \
	"$DATA_DIR/system/cache/persistent" \
	"$DATA_DIR/system/cache/twig" \
	"$DATA_DIR/system/cache/plugins" \
	"$DATA_DIR/system/cache/signatures"
touch \
	"$DATA_DIR/system/cache/index.html" \
	"$DATA_DIR/system/cache/persistent/index.html" \
	"$DATA_DIR/system/cache/twig/index.html" \
	"$DATA_DIR/system/cache/plugins/index.html"

if [ ! -f "$DATA_DIR/config.local.php" ]; then
	touch "$DATA_DIR/config.local.php"
fi

if [ -n "${MYAAC_INSTALL_ALLOWED_IPS:-}" ]; then
	printf '%s\n' "$MYAAC_INSTALL_ALLOWED_IPS" | tr ', ' '\n\n' | sed '/^$/d' >"$DATA_DIR/install/ip.txt"
fi

# Seed persistent plugins directory with bundled plugins, but preserve user-installed ones.
cp -an "$APP_DIR/plugins/." "$DATA_DIR/plugins/"

if [ -f "$PATCH_THEME_CANARY" ]; then
	php "$PATCH_THEME_CANARY"
fi

if [ -f "$PATCH_THEME_CANARY_INDEX" ]; then
	php "$PATCH_THEME_CANARY_INDEX"
fi

if [ -f "$GENERATE_ITEM_CLIENT_ID_MAP" ]; then
	php "$GENERATE_ITEM_CLIENT_ID_MAP"
fi

rm -rf "$APP_DIR/system/cache" \
	"$APP_DIR/system/logs" \
	"$APP_DIR/system/php_sessions" \
	"$APP_DIR/images/guilds" \
	"$APP_DIR/images/houses" \
	"$APP_DIR/images/gallery" \
	"$APP_DIR/plugins"
rm -f "$APP_DIR/config.local.php" "$APP_DIR/install/ip.txt"

ln -s "$DATA_DIR/system/cache" "$APP_DIR/system/cache"
ln -s "$DATA_DIR/system/logs" "$APP_DIR/system/logs"
ln -s "$DATA_DIR/system/php_sessions" "$APP_DIR/system/php_sessions"
ln -s "$DATA_DIR/images/guilds" "$APP_DIR/images/guilds"
ln -s "$DATA_DIR/images/houses" "$APP_DIR/images/houses"
ln -s "$DATA_DIR/images/gallery" "$APP_DIR/images/gallery"
ln -s "$DATA_DIR/plugins" "$APP_DIR/plugins"
ln -s "$DATA_DIR/config.local.php" "$APP_DIR/config.local.php"
ln -s "$DATA_DIR/install/ip.txt" "$APP_DIR/install/ip.txt"

chown -R www-data:www-data "$DATA_DIR" "$APP_DIR"
chmod 660 "$DATA_DIR/config.local.php"
if [ -f "$DATA_DIR/install/ip.txt" ]; then
	chmod 664 "$DATA_DIR/install/ip.txt"
fi
chmod -R 775 \
	"$DATA_DIR/system/cache" \
	"$DATA_DIR/system/logs" \
	"$DATA_DIR/system/php_sessions" \
	"$DATA_DIR/images/guilds" \
	"$DATA_DIR/images/houses" \
	"$DATA_DIR/images/gallery" \
	"$DATA_DIR/plugins"

exec docker-php-entrypoint "$@"

#!/usr/bin/env bash
# Isolated database/site only. Requires PHP, MySQL client/server and network access.
set -euo pipefail
archive="$(cd "$(dirname "$1")" && pwd)/$(basename "$1")"
test_dir="$2"
mkdir -p "$test_dir"
curl -fsSL --retry 3 'https://raw.githubusercontent.com/wp-cli/builds/ad68e4ad3b29c3d3bdb361e5ed9b37e709123a9b/phar/wp-cli.phar' -o "$test_dir/wp-cli.phar"
# shellcheck disable=SC2016 # PHP variables must reach PHP literally.
php -r 'if (hash_file("sha256", $argv[1]) !== "ce34ddd838f7351d6759068d09793f26755463b4a4610a5a5c0a97b68220d85c") { exit(1); }' "$test_dir/wp-cli.phar"
wp_cli=(php -d memory_limit=512M "$test_dir/wp-cli.phar" --path="$test_dir/site")
"${wp_cli[@]}" core download --version=7.1.4
database="gm_release_$(date +%s)_${RANDOM}"
"${wp_cli[@]}" config create --dbname="$database" --dbuser="${WP_DB_USER:?}" --dbpass="${WP_DB_PASSWORD-}" --dbhost="${WP_DB_HOST:?}" --skip-check
"${wp_cli[@]}" db create
trap '"${wp_cli[@]}" db drop --yes' EXIT
"${wp_cli[@]}" core install --url=http://gridmaster-release.test --title='Release test' --admin_user=release_test --admin_password="$(openssl rand -hex 20)" --admin_email=release@example.invalid --skip-email
"${wp_cli[@]}" plugin install "$archive" --activate
"${wp_cli[@]}" eval-file "$(dirname "$0")/../tests/smoke.php"
"${wp_cli[@]}" plugin deactivate ajax-filter-posts
"${wp_cli[@]}" plugin activate ajax-filter-posts
"${wp_cli[@]}" plugin is-active ajax-filter-posts

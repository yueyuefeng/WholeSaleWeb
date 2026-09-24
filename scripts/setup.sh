#!/bin/sh
set -eu
cd /var/www/html
if ! wp core is-installed; then
  case "${WP_ADMIN_PASSWORD:-}" in ''|replace-*) echo 'Set a unique WP_ADMIN_PASSWORD in .env first.' >&2; exit 1;; esac
  wp core install --url="$WP_URL" --title="$WP_TITLE" --admin_user="$WP_ADMIN_USER" --admin_password="$WP_ADMIN_PASSWORD" --admin_email="$WP_ADMIN_EMAIL" --skip-email
  wp option update blog_public 0
  wp option update permalink_structure '/%postname%/'
fi
for plugin in woocommerce woocommerce-gateway-stripe woocommerce-paypal-payments wordpress-seo; do
  if ! wp plugin is-installed "$plugin"; then wp plugin install "$plugin"; fi
  if ! wp plugin is-active "$plugin"; then wp plugin activate "$plugin"; fi
done
wp plugin activate shadowalker-core
wp theme activate shadowalker
wp rewrite flush --hard
wp plugin list --format=table
echo 'Installation ready. Optional demo: wp shadowalker seed. Configure languages, shipping, taxes and sandbox payments before launch.'

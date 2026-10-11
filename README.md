<div align="center">

# Slawman

**Self-hosted, security-first blog CMS built on Symfony 8.**
Upload to any PHP host, open the installer, start publishing. No terminal needed.

![PHP](https://img.shields.io/badge/PHP-8.4+-777bb4?logo=php&logoColor=white)
![Symfony](https://img.shields.io/badge/Symfony-8.1-000?logo=symfony)
![MySQL](https://img.shields.io/badge/MySQL%20%2F%20MariaDB-supported-4479a1?logo=mysql&logoColor=white)
[![Release](https://img.shields.io/github/v/release/slaweally/slawman)](https://github.com/slaweally/slawman/releases/latest)

</div>

## Why Slawman?

- **One-upload install**: web installer sets up the database, config and admin account.
- **Security built in**: 2FA (TOTP), WAF, rate limiting, security headers, Turnstile, HTML/SVG sanitizing, audit log.
- **Migrate from WordPress**: import your WXR export in the admin panel.
- **SEO ready**: sitemap, RSS, JSON-LD, 301 redirect manager, IndexNow, Yandex Metrika.
- **Own your data**: built-in visitor analytics and newsletter, no third-party trackers.
- **Fast**: Redis and Varnish support, Tailwind 4 frontend, Tabler admin UI.

## Install

1. Download `slawman-vX.Y.Z.zip` from [**Releases**](https://github.com/slaweally/slawman/releases/latest) and upload it to your host.
2. Point the domain's (or subdomain's) document root to `public/`, or just upload to the web root and let the bundled `.htaccess` route to it. Installing in a subfolder (`example.com/blog/`) is not supported.
3. Create an empty MySQL/MariaDB database.
4. Open `https://your-domain.com/install.php` and fill in the form.

The installer deletes itself when finished. Admin panel: `https://your-domain.com/backstage` (path is configurable).

**Requirements:** PHP 8.4+ (`pdo_mysql`, `mbstring`, `xml`, `intl` recommended), MySQL 8 / MariaDB 10.6+, Apache with `mod_rewrite` or Nginx.

## Development

```bash
composer install
cp .env.dist .env.local          # set APP_ENV=dev, APP_SECRET, DATABASE_URL
php console doctrine:migrations:migrate
php console app:create-admin
cd app && npm ci && npm run build
```

## Author

Built by **Ali Çömez**, [comez.dev](https://comez.dev) · [@slaweally](https://github.com/slaweally)

If Slawman is useful to you, a ⭐ helps others find it.

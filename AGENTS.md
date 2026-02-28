# AGENTS.md

## Cursor Cloud specific instructions

This is a WordPress plugin project ("mpeti Booking Calendar") — an appointment booking system. The repo ships the full WordPress installation under `app/public/` with the plugin at `app/public/wp-content/plugins/mpeti-booking-calendar/`.

### Services (LEMP stack)

Three services must run for the application to work:

| Service | Start command | Notes |
|---------|--------------|-------|
| **MySQL** | `sudo mysqld --user=mysql --datadir=/var/lib/mysql --socket=/var/run/mysqld/mysqld.sock --pid-file=/var/run/mysqld/mysqld.pid &` | DB: `local`, user: `root`, password: `root`. Run `sudo mkdir -p /var/run/mysqld && sudo chmod 755 /var/run/mysqld` first if the directory doesn't exist. |
| **PHP-FPM** | `sudo php-fpm8.3 --nodaemonize &` | Listens on `/run/php/php8.3-fpm.sock`. |
| **Nginx** | `sudo nginx` | Config at `/etc/nginx/sites-available/wordpress`, serves `app/public/` on port 80. |

### WordPress credentials

- Admin URL: `http://localhost/wp-admin/`
- Username: `admin`, Password: `admin`

### WP-CLI

WP-CLI is installed at `/usr/local/bin/wp`. Always use `--allow-root` flag. Run from `app/public/` directory:

```
cd /workspace/app/public && wp plugin list --allow-root
```

### Linting

No dedicated linter is configured. Use PHP's built-in syntax checker:

```
find /workspace/app/public/wp-content/plugins/mpeti-booking-calendar -name "*.php" -exec php -l {} \;
```

### Testing

No automated test suite exists. Test via:
- REST API: `curl http://localhost/wp-json/mpeti-booking-calendar/v1/available-slots?date=YYYY-MM-DD`
- WP-CLI: `cd /workspace/app/public && wp eval '<php code>' --allow-root`
- Browser: Navigate to `http://localhost/book-an-appointment/` for the frontend booking form

### Key gotchas

- The `.hbs` config files in `conf/` are Local by Flywheel templates — they are NOT used in this dev environment. The actual configs are in `/etc/nginx/`, `/etc/php/`, and MySQL defaults.
- The plugin creates a custom DB table `wp_mbc_timeslots` on activation. If you deactivate/reactivate, this table is recreated.
- The `wp-config.php` connects to MySQL via `localhost` socket (not TCP), so `/var/run/mysqld/mysqld.sock` must be accessible.
- WordPress debug logging is enabled; check `app/public/wp-content/debug.log` for PHP errors.

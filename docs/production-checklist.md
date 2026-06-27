# DCI-SIS Production Deployment Checklist

Target: ~10,000 accounts, 500–1,000 peak concurrent users  
Stack: PHP 8.3, MySQL 8.0, Apache or Nginx

---

## 1. Environment & Runtime

- [ ] Set `APP_ENV=production` as a system/web server environment variable
- [ ] Set `APP_TIMEZONE=Asia/Bangkok` (or server's timezone)
- [ ] Verify `display_errors` is Off in production (enforced in `config/session.php` when APP_ENV=production)
- [ ] Verify `log_errors` is On and `error_log` points to a writable path outside web root
- [ ] Remove or restrict access to MAMP-style dev php.ini before deploying

---

## 2. Database

- [ ] Set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` as environment variables — do NOT keep root/root
- [ ] Create a dedicated MySQL user with least-privilege (`SELECT, INSERT, UPDATE, DELETE` on `dci_sis.*` only)
- [ ] Verify `charset=utf8mb4` in DSN (already set in `config/database.php`)
- [ ] Enable MySQL slow query log: `slow_query_log=1`, `long_query_time=1`
- [ ] Set up automated database backups (daily minimum, test restore quarterly)

---

## 3. Session & Cookies

- [ ] Session cookie name is `dci_sess` (not `PHPSESSID`) — enforced in `config/session.php`
- [ ] `session.use_strict_mode = 1` — enforced in `config/session.php`
- [ ] Cookie `HttpOnly = true` — enforced
- [ ] Cookie `SameSite = Lax` — enforced
- [ ] Cookie `Secure = true` — auto-enabled when HTTPS detected (set `$_SERVER['HTTPS']` correctly via reverse proxy)
- [ ] Idle timeout 7,200 s (2 h) — enforced via `SESSION_IDLE_TTL`
- [ ] Session regenerated on login — enforced in `actions/login-action.php`

---

## 4. HTTPS

- [ ] Obtain and install a valid TLS certificate (Let's Encrypt or institutional CA)
- [ ] Configure web server to redirect HTTP → HTTPS (example below)
- [ ] If behind a reverse proxy, set `X-Forwarded-Proto: https` and verify `$_SERVER['HTTPS']` is populated
- [ ] Do NOT add `Strict-Transport-Security` (HSTS) until HTTPS is fully stable

**Apache example:**
```apache
<VirtualHost *:80>
    ServerName your-domain.ac.th
    Redirect permanent / https://your-domain.ac.th/
</VirtualHost>
```

**Nginx example:**
```nginx
server {
    listen 80;
    server_name your-domain.ac.th;
    return 301 https://$host$request_uri;
}
```

---

## 5. Security Headers

The following headers are sent automatically by `config/session.php`:

- [x] `X-Frame-Options: SAMEORIGIN`
- [x] `X-Content-Type-Options: nosniff`
- [x] `Referrer-Policy: strict-origin-when-cross-origin`
- [x] `Permissions-Policy: camera=(), microphone=(), geolocation=()`

**Future (phase 1J+):**
- [ ] `Content-Security-Policy` — audit all inline scripts/styles before enabling
- [ ] `Strict-Transport-Security` — enable only after HTTPS is stable

---

## 6. File & Directory Permissions

- [ ] Web root files owned by deployment user, not www-data/apache
- [ ] `config/database.php` readable only by web server process: `chmod 640`
- [ ] `config/session.php` readable only by web server process: `chmod 640`
- [ ] No world-writable files: `find /var/www/dci-sis -perm -002 -type f`
- [ ] No `.sql`, `.log`, `.bak` files in web root (enforced by `.htaccess`)
- [ ] `scripts/`, `database/`, `config/`, `includes/` not directly accessible via HTTP (enforced by `.htaccess`)

---

## 7. Web Server Configuration

**Apache:**
- [ ] `AllowOverride All` enabled for project directory (required for `.htaccess` to work)
- [ ] `Options -Indexes` in `.htaccess` (already set) or server config

**Nginx (no `.htaccess` support — add these to server block):**
```nginx
server {
    root /var/www/dci-sis;
    index index.php;

    autoindex off;

    # Block sensitive directories
    location ~ ^/(config|includes|scripts|database)/ {
        return 403;
    }

    # Block sensitive file types
    location ~* \.(sql|log|env|ini|sh|bak)$ {
        return 403;
    }

    # PHP via FPM
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Static asset caching
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|woff2?)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
```

---

## 8. OPcache

Recommended `php.ini` settings for production:

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=4000
opcache.revalidate_freq=0      ; 0 = never recheck timestamps (deploy: opcache_reset())
opcache.validate_timestamps=0  ; disable in production, enable temporarily when debugging
opcache.interned_strings_buffer=8
opcache.fast_shutdown=1
```

After each deployment: call `opcache_reset()` or restart PHP-FPM.

---

## 9. Monitoring & Logging

- [ ] PHP error log monitored and alerted (e.g., logwatch, Graylog, or tail -f)
- [ ] MySQL slow query log enabled and reviewed weekly
- [ ] `audit_logs` table retention policy defined (e.g., keep 12 months, archive older)
- [ ] Server disk space alerted at 80% (logs can grow large)
- [ ] Uptime monitoring configured (e.g., UptimeRobot, Pingdom)

---

## 10. Pre-Launch Checklist

- [ ] `APP_ENV=production` confirmed
- [ ] Demo credentials NOT visible on login page (APP_DEBUG=false hides them)
- [ ] Database not using root user
- [ ] No `.sql` or `.md` files in web root
- [ ] HTTPS working, `Secure` cookie confirmed in browser DevTools
- [ ] Admin login → audit_logs shows AUTH.LOGIN_SUCCESS
- [ ] PHP error log has no startup errors
- [ ] All role dashboards load without PHP warnings
- [ ] OPcache status page shows cache is primed
- [ ] Backup restored successfully in staging (test restore, not just backup)

# Marginama

Self-hosted companion for the Marginama video-review Chrome extension.
Capture time-stamped critiques from YouTube, Sybill, or Google Drive videos;
store them against your account; share a review with a public link.

Small by design: vanilla PHP 8.2, MySQL, zero Composer packages.

## Run locally

Requires PHP 8.2+ and MySQL 8. On macOS:

```sh
brew install php mysql
brew services start mysql

# Create the database and a local user (one-time setup).
mysql -u root <<'SQL'
CREATE DATABASE IF NOT EXISTS marginama;
CREATE USER IF NOT EXISTS 'marginama'@'localhost' IDENTIFIED BY 'dev';
GRANT ALL PRIVILEGES ON marginama.* TO 'marginama'@'localhost';
FLUSH PRIVILEGES;
SQL

mysql -u marginama -pdev marginama < schema.sql

cp .env.example .env
# Edit .env:
#   DB_USER=marginama
#   DB_PASS=dev
#   SESSION_SECRET=<long random string, e.g. php -r 'echo bin2hex(random_bytes(32));'>

php -S localhost:8000 -t public_html
```

Then open <http://localhost:8000/signup>.

## Install the extension

1. Open `chrome://extensions`, toggle **Developer mode** on.
2. **Load unpacked** → pick the `extension/` folder.
3. Open the extension's **Options** page.
4. Set API base URL to `http://localhost:8000` (dev) or your Marginama host.
5. Mint a token at `/settings/api-tokens`, paste, **Save**, **Test connection**.

## Deploy to Hostinger (shared hosting)

1. In Hostinger hPanel, create a website of type **Custom PHP/HTML website**.
2. Create a MySQL database; note the host, name, user, password.
3. In **Git**, connect this repo and set the deploy path to the domain root.
4. Via File Manager, import `schema.sql` once into the database.
5. Upload `.env` (not committed) next to the `app/` directory with prod values.
6. Confirm Apache serves `public_html/` as the web root; if not, either set the
   document root to `public_html/` or move the contents of `public_html/` up a
   level (and update the `require` paths in `index.php` accordingly).
7. Enable the free SSL certificate.

DNS lives at Cloudflare. Start with a grey-cloud A record pointing
`marginama.com` at the Hostinger shared-hosting IP. Flip to proxied once the
cert is stable.

## Project layout

```
public_html/        web root (index.php + static assets)
app/                application code, not web-accessible
  config.php        reads .env
  db.php            PDO connection
  auth.php          session + bearer-token helpers
  video_reviews.php URL canonicalization, timestamp formatting
  handlers/         one file per route
  views/            PHP templates
schema.sql          CREATE TABLEs
extension/          the Chrome extension (upload as unpacked)
```

## License

MIT.

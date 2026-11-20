# Games Club BD — PHP + MySQL

A small PHP 8+ / MySQL starting project with PHP sessions, PDO, and Goriber Pay deposits. Manual wallet deposit forms and manual transaction/PIN verification are not included. Payment settings contain only the Goriber Pay Brand Key.

## Files

- `index.php` — Games Club BD home page, user login and registration.
- `add-money.php` — Goriber Pay amount form; on return with `?transactionId=...`, verifies the provider payment.
- `admin/` — admin login, dashboard and Goriber Pay Brand Key settings.
- `api/` — PDO/session helpers and JSON endpoints for auth, payment create/verify and saving the Brand Key.
- `database.sql` — empty users, matches, transactions and admin tables plus the required default Brand Key setting.
- `create_admin.php` — CLI-only first administrator creation.

## Requirements and setup

1. Install PHP 8.0+ with `pdo_mysql`, `curl` and MySQL/MariaDB. The web server must have outbound HTTPS access to `pay.goriberpay.cyou`.
2. Create/import the database. In phpMyAdmin select **Import** and choose `database.sql`, or use:

   ```sh
   mysql -u root -p < database.sql
   ```

   The SQL creates and uses the `gamesclubbd` database. It leaves user, match, transaction and admin tables empty; the Brand Key row is seeded as required.
3. Set database credentials in environment variables `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`. Alternatively copy `config.php` to `config.local.php` and edit the returned array. Do not commit credentials. Set `APP_URL` to the project base URL when deployed in a subdirectory or behind a proxy, for example `https://example.com/gamesclubbd`.
4. Create the first admin from the project directory using a terminal:

   ```sh
   php create_admin.php admin
   ```

   Enter a long password when prompted. The script runs only in CLI and permits creation only when the admin table is empty. Sign in at `/admin/login.php`.
5. Open `/index.php`, register a user, and use **ADD MONEY**. Configure the Brand Key on `/admin/settings.php` if it differs from the seeded value.

## XAMPP

Copy the project folder under `xampp/htdocs/gamesclubbd`, start Apache and MySQL, import `database.sql` in phpMyAdmin, and update the database credentials in `config.php` (default XAMPP MySQL is often `root` with an empty password). Use XAMPP Shell in the project directory to run `php create_admin.php admin`, then visit `http://localhost/gamesclubbd/`.

## cPanel

Create a MySQL database and user in cPanel, grant that user privileges, import `database.sql` in phpMyAdmin (or adjust its `CREATE DATABASE`/`USE` lines if your host restricts database creation), and set the assigned credentials in `config.local.php`. Upload the files to `public_html` or a subdirectory, ensure PHP's PDO MySQL and cURL extensions are enabled, and run `php create_admin.php admin` using Terminal/SSH. If terminal access is unavailable, create an admin hash securely outside the public directory and insert it into `admins`; do not expose an admin-creation web script.

## Payment callback and crediting

The create endpoint saves a pending deposit before calling Goriber Pay. It sends `cus_name`, `cus_email`, normalized `amount`, `success_url`, `cancel_url`, and metadata containing the local reference and user ID. A successful response must have boolean `status: true` and a valid HTTPS `payment_url`.

The success URL points to `add-money.php`; the gateway/browser must return there with `?transactionId=...`. `api/verify_payment.php` verifies that ID server-to-server with the Brand Key, checks the local user's pending deposit, checks the returned amount/reference when supplied, then locks the transaction and credits the deposit balance exactly once in a DB transaction. It expects the provider's verification response to report a `COMPLETED` status (at the top level or under `data`). Review the actual Goriber Pay response schema against this parser before production, since provider field names can vary. The return verification is a convenience; for reliable asynchronous payments, configure a provider-signed webhook if Goriber Pay offers one.

Serve the site over HTTPS in production. Keep `config.local.php` and database credentials outside version control, use a unique admin password, and restrict `/admin` as appropriate. The default Brand Key is included because it was explicitly requested; replace it in admin settings if it is not the key issued for your merchant account.

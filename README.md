# WhatsGrolink.com

Plain PHP 8 and MySQL directory of WhatsApp group invite links. No Composer and no framework.

The public name is **WhatsGrolink.com**. The interface language is English. Code and this file are in English.

## Setup

1. Create the database and a user:

```sql
CREATE DATABASE whatsgroup CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'whatsgroup'@'127.0.0.1' IDENTIFIED BY 'choose-a-password';
GRANT ALL PRIVILEGES ON whatsgroup.* TO 'whatsgroup'@'127.0.0.1';
FLUSH PRIVILEGES;
```

2. Import the schema (categories, demo groups, and the admin user):

```bash
mysql -u whatsgroup -p whatsgroup < schema.sql
```

3. Copy the config and set the password plus the public site URL (no trailing slash):

```bash
cp config.sample.php config.php
```

For a local server, `base_url` can stay `http://localhost:8080`. On the real site set it to `https://whatsgrolink.com`.

4. Start PHP from this folder:

```bash
php -S localhost:8080 router.php
```

`router.php` serves `sitemap.xml` and `robots.txt`. Open `http://localhost:8080/`.

The PHP `curl` extension is required for the admin page scanner.

## Default admin

- Username: `admin`
- Password: `changeme123`

Change this before anyone else can reach the site. The public pages do not link to admin. Open `admin/login.php` directly.

The login page has **Forgot password**. It creates a one-time link that expires in 30 minutes. On a local copy with no `mail_from`, the next page shows that link. When `base_url` is a public address, the link is emailed to the admin address and is not shown. Store that address in `admins.email`. You can still use **Change password** on the dashboard, or replace the hash:

```bash
php -r "echo password_hash('your-new-password', PASSWORD_DEFAULT), PHP_EOL;"
```

```sql
UPDATE admins SET password_hash = 'PASTE_HASH' WHERE username = 'admin';
```

## What the site does

- Home lists approved groups, except groups in an adult category. Those stay out of the front page and still show in search and on that category page. Join opens only `https://chat.whatsapp.com/...` or `https://wa.me/...`.
- Categories are grouped into countries and topics, with search.
- Search matches a group name or description.
- Each approved group has a detail page and a report form.
- The public submit form keeps new groups pending until an admin approves them.
- About, contact, disclaimer, privacy, and terms are linked from the footer. Contact messages are stored for the admin.
- `robots.txt` and `sitemap.xml` list the home page, categories, static pages, and approved groups.

Admin can approve, reject, or delete groups, add or delete categories, read reports and contact messages, and paste ad code at `admin/ads.php`.

**Add group** saves a group as approved immediately.

**Fetch group** reads one `https://chat.whatsapp.com/` or `https://wa.me/` invite. It fills the group name, and the description and image when the public page includes them. The admin chooses a category, can edit the fields, and saves the group as approved. A missing or private page is refused. The default WhatsApp logo is not stored as the group image.

**Scan** fetches one public `http` or `https` page (about 10 seconds, a few redirects) and lists invite links for import. It does not ask for a country or category. Each group takes a category from a nearby heading, breadcrumb, or section title. If that category is missing, import creates it. A nearby image URL is saved when the page has one. Groups without an image are still imported. Localhost, link-local, and private addresses are refused. Import saves new links as approved and skips duplicates. The scanner does not log in, send cookies, or try to bypass a login.

Images are URLs only. There is no file upload.

## Security notes

Queries use PDO prepared statements. Pages escape output with `htmlspecialchars`. The four ad slots are the exception: a logged-in admin saves them, and the public site prints that HTML as stored so ad-network script tags keep working. POST forms send a session CSRF token. Invite links are allow-listed. The admin password is stored with `password_hash`.

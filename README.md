# 🏏 Inter-University Cricket Tournament Management System

A web-based tournament management system for organizing an inter-university cricket championship — built with HTML, CSS, JavaScript, PHP, and MySQL, with Stripe payment integration for team registration fees.

## 🛠️ Tech Stack

* 🎨 **Frontend:** HTML, CSS, JavaScript
* ⚙️ **Backend:** PHP (vanilla, no framework)
* 🗄️ **Database:** MySQL
* 💻 **Local server:** WAMP
* 💳 **Payments:** Stripe (test mode)

## ✨ Features

* 👨‍💼 **Admin** — manage users, approve/reject team registrations, configure tournament settings (fees, deadlines, points system), view reports
* 📋 **Coordinator** — schedule matches, manage venues, enter match results
* 🏏 **Team Manager** — register a team, add players, upload team logo, pay registration fee via Stripe, view receipt
* 🌐 **Public** — browse homepage, schedule, results, teams, venues, and standings without logging in

## 📁 Project Structure

```text
├── admin/              Admin dashboard and management pages
├── auth/               Login, signup, logout
├── config/
│   ├── config.example.php   Template — copy to config.php
│   └── db.php                Database connection (PDO)
├── coordinator/        Match scheduling, venues, result entry
├── database/
│   └── schema.sql      Full database schema + seed admin account
├── includes/           Shared code: auth helpers, header, footer, Stripe wrapper, stats/points logic
├── manager/            Team registration, players, payments, receipts
├── public/             Public-facing pages + assets (CSS, JS, images, logos)
├── cacert.pem          SSL CA bundle (used by Stripe's cURL calls on some WAMP setups)
└── .gitignore
```

## 🚀 Setup Instructions

### 1️⃣ Clone the repository

```bash
git clone https://github.com/Thisandu04/Inter-University-Cricket-Championship-System.git
cd Inter-University-Cricket-Championship-System
```

### 2️⃣ Set up your local config

```bash
cd config
cp config.example.php config.php
```

Open `config.php` and fill in your own values:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'cricket_tournament');
define('DB_USER', 'root');
define('DB_PASS', '');

define('SITE_NAME', 'Inter-University Cricket Tournament');
define('BASE_URL', 'http://localhost/your-folder-name');

define('STRIPE_SECRET_KEY', 'sk_test_...');
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_...');
```

> ⚠️ **Note:** `config.php` is gitignored and must never be committed — each team member creates their own local copy.

### 3️⃣ Set up the database

1. 🟢 Start WAMP, open phpMyAdmin
2. 📥 Import `database/schema.sql` — creates the `cricket_tournament` database, all tables, and one seeded admin row
3. 🔐 The seeded admin's password hash is a placeholder and won't work as-is. Generate a real one:

* 📝 Create a temporary file in your project root, e.g. `generate_hash.php`, with the following content:

```php
<?php
echo password_hash('YourChosenPassword', PASSWORD_DEFAULT);
```

* 🌐 Open it in your browser: `http://localhost/your-folder-name/generate_hash.php`
* 📋 Copy the full generated hash (60 characters, starts with `$2y$10$`)

Then run in phpMyAdmin's SQL tab:

```sql
UPDATE users
SET password_hash = 'PASTE_HASH_HERE'
WHERE email = 'admin@cricket-tournament.local';
```

🗑️ Delete the temporary PHP file afterward.

### 4️⃣ Run the site

```text
http://localhost/your-folder-name/public/index.php
```

## 💳 Stripe Test Mode

```text
Card number: 4242 4242 4242 4242
Expiry: any future date
CVC: any 3 digits
```

✅ No real charges occur in test mode.

## 🔒 What's Never Committed (`.gitignore`)

* 🔐 `config/config.php` — real credentials, local-only
* 🖼️ `public/assets/logos/team_*` — uploaded team logos (user-generated content, not source code)

## ⚠️ Known Setup Gotchas

* 📦 Uploaded team logos over 2MB are correctly rejected with a clear error message
* 🛡️ SVG logo uploads are disabled intentionally (security) — only PNG/JPG accepted, validated by actual file content
* 🐛 If you see `Undefined constant "_DIR_"`, that typo bug is fixed — pull latest `main`

## 🛡️ Security Notes

* 🔒 Database queries use prepared statements (PDO) throughout
* 🔑 Passwords are hashed with `password_hash()` / verified with `password_verify()`
* 💳 Stripe payments are verified server-side via `retrieveCheckoutSession()` before marking complete

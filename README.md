# Acc-Monitor

Read-only monitoring for NairaTrader MT5 account availability, with a PHP
dashboard, MySQL history, configurable alerts, and an optional WhatsApp worker.
It does not create, change, assign, or close trading accounts.

## Components

- `main-app/` — PHP application, database migrations, dashboard, monitoring,
  Telegram webhook/commands, and scheduled monitor entry point.
- `whatsapp-worker/` — optional Node.js notification worker.
- `documentation/` — installation, security, and troubleshooting guidance.

## Requirements

- PHP 8.2+, PDO MySQL, cURL, DOM, and OpenSSL
- MySQL 5.7+ or a compatible MariaDB release
- Node.js LTS for the optional WhatsApp worker

## Setup

1. Create a MySQL database and import the migrations in
   `main-app/database/migrations/` in order.
2. Copy `main-app/.env.example` to a private `.env` file and configure the
   application and database settings. Never commit the real `.env`.
3. Configure the web server to serve `main-app/public/` and keep application
   configuration, storage, and secrets outside the public web root.
4. Follow `documentation/INSTALLATION.md` for the worker and monitoring setup.

The monitor reads account availability from NairaTrader's authorized API.
Configure API credentials only through the supported private application
settings; do not put real tokens or passwords in source control.

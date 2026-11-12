# NairaTrader Account Pool Monitor - Installation Guide

## Prerequisites

### Main Application (PHP Hosting)
- PHP 8.2+
- MySQL 5.7+ or MariaDB 10.2+
- PDO MySQL extension
- cURL extension
- DOM extension
- OpenSSL extension
- Cron Jobs access

### WhatsApp Worker (External Linux VPS)
- Node.js LTS (v18+)
- Chromium
- Puppeteer dependencies
- PM2

## Installation Steps

### 1. Main Application

1. Upload all files from `main-app/` to your PHP hosting account
2. Place `.env` outside `public_html` (e.g., `/home/username/.env`)
3. Create the MySQL database and import `database/migrations/001_initial_schema.sql`
4. Copy `.env.example` to `.env` and configure your database credentials
5. Set `APP_SECRET_KEY` to a strong random string
6. Set `WORKER_SECRET` and `HMAC_SECRET` to strong random strings (shared with WhatsApp worker)
7. Configure Cron Job: `*/5 * * * * php /home/username/domains/yourdomain.com/cron/monitor.php`
8. Ensure `public/` is your web root directory
9. Set file permissions: `chmod 755` for directories, `644` for files

### 2. WhatsApp Worker (VPS)

1. Upload `whatsapp-worker/` to your VPS
2. Install dependencies: `npm install`
3. Copy `.env.example` to `.env` and configure:
   - `WORKER_SECRET` and `HMAC_SECRET` (must match main app)
   - `PHP_APP_URL` (your main application URL)
   - `SESSION_ENCRYPTION_KEY`
4. Start with PM2: `pm2 start ecosystem.config.js`
5. Save PM2 configuration: `pm2 save`

### 3. Initial Setup

1. Access your monitoring dashboard at `https://yourdomain.com/login`
2. Create an administrator account via database or registration
3. Log in and navigate to WhatsApp Connection
4. Click "Connect WhatsApp" and scan the QR code with your phone
5. Configure Telegram settings (optional)
6. Add up to 5 WhatsApp recipients
7. Enable monitoring and verify the account extraction works

## Security Notes

- Never commit `.env` files to version control
- Store secrets outside `public_html`
- Use HTTPS in production
- Restrict database access to localhost only
- Regularly rotate API secrets

## Support

For issues, check:
- Main app logs: `storage/logs/`
- WhatsApp worker logs: `pm2 logs nairatrader-whatsapp-worker`
- System health dashboard in the admin panel
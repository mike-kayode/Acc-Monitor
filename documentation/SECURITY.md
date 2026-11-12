# Security Implementation

## Authentication
- Admin passwords hashed with Argon2id (PHP `password_hash`)
- CSRF tokens on all state-changing requests
- Secure session cookies (HttpOnly, Secure, SameSite=Strict)
- Session regeneration on login
- Rate-limited login attempts (5 attempts, 30-minute lockout)

## Data Protection
- AES-256-GCM encryption for session cookies
- Secrets stored outside `public_html`
- No passwords stored in plain text
- WhatsApp session files stored on VPS only (not in PHP app)

## API Security
- HMAC-signed requests between main app and WhatsApp worker
- Timestamp validation (5-minute window)
- Nonce-based replay protection
- Worker secret authentication
- Rate limiting on internal endpoints

## WhatsApp Worker
- Dedicated service user
- Restricted filesystem permissions
- Encrypted session storage at rest
- Protected Chromium profile
- PM2 process monitoring
- Headless browser for server deployment

## Important Limitations
- `whatsapp-web.js` is unofficial WhatsApp Web automation
- Account restriction risk exists
- Use an account you control and obtain recipient consent
- Not a WhatsApp Business API integration
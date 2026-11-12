# Troubleshooting

## Common Issues

### Account Extraction Fails
1. Verify NairaTrader session is valid (re-authenticate if needed)
2. Check if the page structure has changed
3. Verify cURL can reach the target URL
4. Check storage/logs/ for detailed errors

### WhatsApp Not Connected
1. Check VPS worker is running: `pm2 status`
2. Verify worker logs: `pm2 logs nairatrader-whatsapp-worker`
3. Check if Chromium dependencies are installed
4. Scan QR code again if expired
5. Verify WORKER_SECRET and HMAC_SECRET match between servers

### Telegram Not Sending
1. Verify bot token and chat ID are correct
2. Test with /telegram/test in dashboard
3. Check if bot has been started by the recipient
4. Verify HTTPS outbound access

### Cron Not Running
1. Check Cron configuration in hPanel
2. Verify PHP path is correct
3. Check execution locks table for stale locks
4. Verify script permissions (executable)

### Duplicate Alerts
1. Verify duplicate_alert_prevention is enabled
2. Check notification_queue for stuck items
3. Verify alert_states table is correct

## Log Locations
- Main app: `storage/logs/YYYY-MM-DD.log`
- WhatsApp worker: PM2 logs or `/var/log/nairatrader-whatsapp-worker.log`
- MySQL: Check database error log

## Reset Everything
1. Clear execution_locks table
2. Reset alert_states table
3. Restart WhatsApp worker: `pm2 restart nairatrader-whatsapp-worker`
4. Re-authenticate NairaTrader session
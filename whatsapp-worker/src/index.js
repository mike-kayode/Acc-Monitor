const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode-terminal');
const express = require('express');
const cors = require('cors');
const helmet = require('helmet');
const crypto = require('crypto');
const path = require('path');
const fs = require('fs');

require('dotenv').config();

const app = express();
const PORT = process.env.WORKER_PORT || 3001;
const SESSION_NAME = process.env.SESSION_NAME || 'nairatrader-monitor';
const WORKER_SECRET = process.env.WORKER_SECRET || '';
const HMAC_SECRET = process.env.HMAC_SECRET || '';

app.use(helmet());
app.use(cors());
app.use(express.json({ limit: '10mb' }));

let client = null;
let qrCodeData = null;
let qrCodeExpiresAt = null;
let connectionStatus = 'disconnected';
let clientReady = false;

function validateAuth(req, res, next) {
  const workerSecret = req.headers['x-worker-secret'];
  const hmacSignature = req.headers['x-hmac-signature'];
  const timestamp = req.headers['x-timestamp'];
  const nonce = req.headers['x-nonce'];

  if (!workerSecret || workerSecret !== WORKER_SECRET) {
    return res.status(401).json({ success: false, error: 'Unauthorized' });
  }

  const ts = parseInt(timestamp);
  if (!ts || Math.abs(Date.now() / 1000 - ts) > 300) {
    return res.status(401).json({ success: false, error: 'Invalid timestamp' });
  }

  const rawBody = req.rawBody || '';
  const payload = rawBody + '.' + timestamp + '.' + nonce;
  const expectedSignature = crypto.createHmac('sha256', HMAC_SECRET).update(payload).digest('hex');


function initClient() {
  if (client) {
    client.destroy();
  }

  const sessionPath = path.join(__dirname, '..', 'sessions', SESSION_NAME);
  if (!fs.existsSync(sessionPath)) {
    fs.mkdirSync(sessionPath, { recursive: true });
  }

  client = new Client({
    authStrategy: new LocalAuth({
      clientId: SESSION_NAME,
      dataPath: sessionPath,
    }),
    puppeteer: {
      headless: process.env.HEADLESS === 'true',
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-accelerated-2d-canvas',
        '--no-first-run',
        '--no-zygote',
        '--disable-gpu',
      ],
      ...(process.env.PUPPETEER_EXECUTABLE_PATH && { executablePath: process.env.PUPPETEER_EXECUTABLE_PATH }),
    },
  });

  client.on('qr', (qr) => {
    console.log('[WhatsApp] QR code received');
    qrCodeData = qr;
    qrCodeExpiresAt = new Date(Date.now() + 5 * 60 * 1000);
    connectionStatus = 'qr_ready';
  });

  client.on('authenticated', () => {
    console.log('[WhatsApp] Authenticated successfully');
    connectionStatus = 'authenticated';
    qrCodeData = null;
    qrCodeExpiresAt = null;
    clientReady = true;
  });

  client.on('ready', () => {
    console.log('[WhatsApp] Client ready');
    connectionStatus = 'connected';
    clientReady = true;
  });

  client.on('auth_failure', (msg) => {
    console.error('[WhatsApp] Authentication failure:', msg);
    connectionStatus = 'expired';
    qrCodeData = null;
    clientReady = false;
  });

  client.on('disconnected', (reason) => {
    console.log('[WhatsApp] Disconnected:', reason);
    connectionStatus = 'disconnected';
    qrCodeData = null;
    clientReady = false;
  });

  client.on('message', async (message) => {
    console.log('[WhatsApp] Message received:', message.from, message.body);

app.get('/api/health', (req, res) => {
  res.json({
    success: true,
    status: connectionStatus,
    timestamp: new Date().toISOString(),
    uptime: process.uptime(),
  });
});

app.post('/api/qr', validateAuth, (req, res) => {
  if (connectionStatus === 'connected' || connectionStatus === 'authenticated') {
    return res.json({
      success: true,
      status: 'already_connected',
      message: 'WhatsApp is already connected',
    });
  }

  if (qrCodeData) {
    return res.json({
      success: true,
      qrCode: qrCodeData,
      expiresAt: qrCodeExpiresAt,
      status: connectionStatus,
    });
  }

  initClient();

  setTimeout(() => {
    if (qrCodeData) {
      res.json({
        success: true,
        qrCode: qrCodeData,
        expiresAt: qrCodeExpiresAt,
        status: connectionStatus,
      });
    } else {
      res.json({

app.post('/api/send', validateAuth, async (req, res) => {
  const { event_id, type, recipients, message, timestamp } = req.body;

  if (!recipients || !Array.isArray(recipients) || recipients.length === 0) {
    return res.status(400).json({ success: false, error: 'No recipients specified' });
  }

  if (!message) {
    return res.status(400).json({ success: false, error: 'No message specified' });
  }

  if (connectionStatus !== 'connected' && connectionStatus !== 'authenticated') {
    return res.status(503).json({
      success: false,
      error: 'WhatsApp not connected',
      status: connectionStatus,
    });
  }

  const results = [];

  for (const phone of recipients) {
    try {
      const chatId = phone + '@c.us';
      const result = await client.sendMessage(chatId, message);
      results.push({
        recipient: phone,
        success: true,
        messageId: result.id.id,
        status: 'sent',
      });
      console.log(`[WhatsApp] Message sent to ${phone}: ${result.id.id}`);
    } catch (err) {
      console.error(`[WhatsApp] Failed to send to ${phone}:`, err.message);
      results.push({
        recipient: phone,
        success: false,
        error: err.message,
        status: 'failed',
      });
    }
  }

  const allSuccess = results.every(r => r.success);
  res.json({
    success: allSuccess,
    results: results,
  });
});

app.post('/api/test', validateAuth, async (req, res) => {
  const { phone, message } = req.body;

  if (!phone) {
    return res.status(400).json({ success: false, error: 'Phone number required' });
  }

  const testMsg = message || '🧪 Test message from NairaTrader Account Pool Monitor';

  try {
    const chatId = phone + '@c.us';
    const result = await client.sendMessage(chatId, testMsg);
    res.json({
      success: true,
      messageId: result.id.id,
      message: 'Test message sent',
    });
  } catch (err) {
    res.status(500).json({
      success: false,
      error: err.message,
    });
  }
});

app.post('/api/reconnect', validateAuth, (req, res) => {
  connectionStatus = 'connecting';
  initClient();
  res.json({ success: true, message: 'Reconnection initiated' });
});

app.post('/api/disconnect', validateAuth, (req, res) => {
  if (client) {
    client.destroy();
    client = null;
  }
  connectionStatus = 'disconnected';
  qrCodeData = null;
  clientReady = false;
  res.json({ success: true, message: 'Disconnected' });
});

app.post('/api/webhook', validateAuth, (req, res) => {
  const { event_id, status, recipient, message_id, error } = req.body;
  console.log('[WhatsApp] Webhook received:', { event_id, status, recipient, message_id });
  res.json({ success: true });
});

app.listen(PORT, () => {
  console.log(`[WhatsApp Worker] Server running on port ${PORT}`);
  console.log(`[WhatsApp Worker] Session: ${SESSION_NAME}`);
  initClient();
});

process.on('SIGTERM', () => {
  console.log('[WhatsApp Worker] Shutting down...');
  if (client) { client.destroy(); }
  process.exit(0);
});

process.on('SIGINT', () => {
  console.log('[WhatsApp Worker] Shutting down...');
  if (client) { client.destroy(); }
  process.exit(0);
});
        success: true,
        qrCode: null,
        status: 'generating',
        message: 'QR code being generated, please retry',
      });
    }
  }, 3000);
});

app.get('/api/status', validateAuth, (req, res) => {
  const info = clientReady && client ? client.info : null;
  res.json({
    success: true,
    status: connectionStatus,
    phone: info ? info.wid.user : null,
    platform: info ? info.platform : null,
    qrCode: qrCodeData,
    qrExpiresAt: qrCodeExpiresAt,
  });
});
  });

  client.initialize().catch(err => {
    console.error('[WhatsApp] Failed to initialize:', err);
    connectionStatus = 'error';
  });
}
  if (!crypto.timingSafeEqual(Buffer.from(hmacSignature || ''), Buffer.from(expectedSignature))) {
    return res.status(401).json({ success: false, error: 'Invalid signature' });
  }

  next();
}

app.use((req, res, next) => {
  let data = '';
  req.on('data', chunk => { data += chunk; });
  req.on('end', () => {
    req.rawBody = data;
    next();
  });
});
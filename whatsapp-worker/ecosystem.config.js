module.exports = {
  apps: [{
    name: 'nairatrader-whatsapp-worker',
    script: 'src/index.js',
    cwd: '/opt/nairatrader/whatsapp-worker',
    instances: 1,
    autorestart: true,
    watch: false,
    max_restarts: 10,
    min_uptime: '10s',
    max_memory_restart: '1G',
    env: {
      NODE_ENV: 'production',
      WORKER_PORT: 3001,
      WORKER_HOST: '127.0.0.1',
      SESSION_NAME: 'nairatrader-monitor',
    },
    log_file: '/var/log/nairatrader-whatsapp-worker.log',
    error_file: '/var/log/nairatrader-whatsapp-worker-error.log',
    out_file: '/var/log/nairatrader-whatsapp-worker-out.log',
    merge_logs: true,
  }]
};
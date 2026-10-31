import express from 'express';
import http from 'node:http';
import { spawn, ChildProcess } from 'node:child_process';
import path from 'node:path';

const PORT = Number(process.env.PORT) || 3000;
const PHP_PORT = 8080;
const PHP_HOST = '127.0.0.1';

// Start PHP built-in server serving the public directory with public/index.php as router
console.log(`[Proxy] Spawning PHP 8.2 built-in server on ${PHP_HOST}:${PHP_PORT}...`);
const phpProcess: ChildProcess = spawn(
  'php',
  ['-S', `${PHP_HOST}:${PHP_PORT}`, '-t', 'public', 'public/index.php'],
  {
    stdio: ['ignore', 'inherit', 'inherit'],
    cwd: process.cwd(),
    env: {
      ...process.env,
      PHP_CLI_SERVER_WORKERS: '8',
    },
  }
);

phpProcess.on('error', (err) => {
  console.error('[Proxy] Failed to spawn PHP process:', err);
});

phpProcess.on('exit', (code, signal) => {
  console.log(`[Proxy] PHP process exited with code ${code} and signal ${signal}`);
});

// Clean up child process on exit
const cleanUp = () => {
  if (phpProcess && !phpProcess.killed) {
    try {
      phpProcess.kill('SIGTERM');
    } catch {
      // Ignore cleanup error
    }
  }
};
process.on('SIGINT', () => { cleanUp(); process.exit(0); });
process.on('SIGTERM', () => { cleanUp(); process.exit(0); });
process.on('exit', () => { cleanUp(); });

// Wait until PHP server is reachable
async function waitForPhpServer(maxAttempts = 50, delayMs = 100): Promise<void> {
  for (let i = 0; i < maxAttempts; i++) {
    try {
      await new Promise<void>((resolve, reject) => {
        const req = http.get(`http://${PHP_HOST}:${PHP_PORT}/`, (res) => {
          res.resume();
          resolve();
        });
        req.on('error', reject);
        req.setTimeout(500, () => {
          req.destroy();
          reject(new Error('timeout'));
        });
      });
      console.log(`[Proxy] PHP server is ready on http://${PHP_HOST}:${PHP_PORT}`);
      return;
    } catch {
      await new Promise((r) => setTimeout(r, delayMs));
    }
  }
  console.warn('[Proxy] Warning: PHP server did not respond before timeout, continuing proxy startup.');
}

const app = express();

// Disable x-powered-by
app.disable('x-powered-by');

// Proxy all requests directly to the PHP server
app.use((req, res) => {
  const options: http.RequestOptions = {
    hostname: PHP_HOST,
    port: PHP_PORT,
    path: req.url,
    method: req.method,
    headers: {
      ...req.headers,
      host: `${PHP_HOST}:${PHP_PORT}`,
      connection: 'close',
      'x-forwarded-for': req.headers['x-forwarded-for'] || req.socket.remoteAddress || '',
      'x-forwarded-proto': req.headers['x-forwarded-proto'] || 'http',
      'x-forwarded-host': req.headers.host || '',
    },
  };

  const proxyReq = http.request(options, (proxyRes) => {
    // Forward status and all headers
    res.writeHead(proxyRes.statusCode || 500, proxyRes.headers);
    proxyRes.pipe(res, { end: true });
  });

  proxyReq.on('error', (err) => {
    console.error(`[Proxy] Proxy error on ${req.method} ${req.url}:`, err.message);
    if (!res.headersSent) {
      res.status(502).type('text/html').send(`
        <!DOCTYPE html>
        <html>
        <head><title>N.A Fresh Fruits - Server Starting</title></head>
        <body style="font-family:sans-serif;text-align:center;padding:50px;background:#f8faf8;color:#1e3a29;">
          <h2>N.A Fresh Fruits & Coconuts</h2>
          <p>Application is initializing. Please refresh in a moment...</p>
        </body>
        </html>
      `);
    }
  });

  // Pipe raw incoming request stream to proxy request
  req.pipe(proxyReq, { end: true });
});

async function startServer() {
  await waitForPhpServer();
  app.listen(PORT, '0.0.0.0', () => {
    console.log(`[Proxy] Express reverse proxy listening on http://0.0.0.0:${PORT}`);
  });
}

startServer();

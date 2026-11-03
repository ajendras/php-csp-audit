# php-csp-audit

Tiny standalone PHP CLI that walks your project and emits a strict CSP header you can paste into .htaccess or your server config.

## Features
- Zero dependencies, works on any PHP 8+
- Scans HTML, JS, CSS and inline scripts
- Outputs nonce-ready policy with report-to endpoint
- 3-second run, perfect for CI or pre-deploy hook

## Install
```bash
curl -O https://raw.githubusercontent.com/yourname/php-csp-audit/main/csp-audit.php
chmod +x csp-audit.php
```

## Usage
```bash
./csp-audit.php /var/www/html > csp.txt
cat csp.txt
# Header add Content-Security-Policy "$(cat csp.txt)"
```

Example output:
```
default-src 'self'; script-src 'self' 'nonce-abc123'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;
```

## Roadmap
- Add JSON report format
- Support for worker-src and frame-ancestors

## Contributing
Open an issue or PR with a failing test case.
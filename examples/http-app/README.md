# Real HTTP App Example

This is a runnable example that behaves like a typical `public/index.php` entry point.

It relies on real HTTP globals (`$_SERVER`, `$_GET`, `$_POST`, …) as provided by PHP at runtime — no manual test environment setup.

## Run (PHP built-in server)

From the repo root:

```bash
php -S 127.0.0.1:8000 -t examples/http-app/public examples/http-app/public/index.php
```

Then try:

- `GET http://127.0.0.1:8000/health`
- `GET http://127.0.0.1:8000/users/123`
- `GET http://127.0.0.1:8000/secure` (will 404 unless you send header `x-role: admin`)

Example with curl:

```bash
curl -H 'x-role: admin' http://127.0.0.1:8000/secure
```

## Notes

- The example uses `decisionPolicy='first'` (single best match).
- The fallback route returns a JSON 404 payload.
- `errorFormat='json'` standardizes exception errors as JSON.

# Changelog

This project follows a pragmatic changelog format (human-focused release notes).

## 2026-01-07

### Highlights

- Introduced a protocol-agnostic routing core (`src/Engine/*`) with explicit match → decision phases.
- Added an HTTP attribute-router adapter (`Il4mb\\Routing\\Router`) that compiles attribute routes into the engine.
- Made routing decisions deterministic (priority → specificity → id) with configurable policies.

### Added

- Core engine:
  - Deterministic decision policies: `first`, `chain`, `error_on_ambiguous`.
  - Failure modes: `fail_closed`, `fail_open`.
  - Matchers: path patterns (named captures, wildcards), host, protocol, method, headers, attributes.
  - Tracing support (null tracer + array tracer) and lifecycle hooks.
  - Reloadable rule loading (`PhpRuleLoader`) for programmable routing.
- HTTP adapter improvements:
  - Priority and fallback routing.
  - 405 Method Not Allowed handling with `Allow` header.
  - Optional standardized error responses via router options:
    - `errorFormat: legacy|text|json`
    - `errorExposeDetails: bool`
  - Explicit mount path control:
    - `basePath` (preferred) / `pathOffset` (alias)
    - `autoDetectFolderOffset` to disable implicit script-folder detection.
  - Controller argument binding upgrades (typed scalars/unions/nullable, `$next` injection) + parameter resolvers for value objects.
- Middleware:
  - Engine-level middleware pipeline for gateway/infrastructure-style execution.
- Caching:
  - Path pattern compile cache (reuses compiled regex/specificity for repeated patterns).
  - Optional RouterEngine decision cache (LRU) with conservative safety guards.

### Fixed

- Greedy capture edge case: `/{path.*}` can match `/` and now binds an empty string (avoids controller `TypeError`).
- Response sending no longer hard-depends on `ext-mbstring` for `Content-Length` (falls back to `strlen`).

### Documentation

- Added/expanded docs:
  - `docs/architecture.md`, `docs/routing.md`, `docs/http.md`, `docs/http-controller.md`, `docs/extensions.md`.
  - Documented matching semantics, deterministic ordering, and caching constraints.

### Examples

- Added runnable examples under `examples/`, including a real `public/index.php`-style HTTP app:
  - `examples/http-app/public/index.php`

### CI

- Added GitHub Actions workflow to run the lightweight test suite on a PHP version matrix.

### Tests

- Added dependency-free test runner (`tests/run.php`) covering routing, binding, 405 behavior, basePath mounting, error formats, and caching.

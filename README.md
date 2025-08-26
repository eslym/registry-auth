# Registry Auth

A lightweight authentication backend for Docker Distribution (registry v2), written in Laravel. It issues short‑lived JWTs for pull/push and supports **Stable CA mode** so you can rotate signing keys without touching the registry.

> Status: early-stage. Use at your own risk.

---

## Table of contents

- [Why](#why)
- [Requirements](#requirements)
- [Install & build](#install--build)
- [Setup & configuration](#setup--configuration)
    - [dotenv generator (`jwt:setup`)](#dotenv-generator-jwtsetup)
    - [Keys & certificates (`jwt:key`)](#keys--certificates-jwtkey)
    - [Stable CA vs self‑signed](#stable-ca-vs-selfsigned)
    - [Docker Registry settings](#docker-registry-settings)

- [Access control](#access-control)
- [Token format](#token-format)
- [Operations](#operations)
    - [Rotate the leaf key](#rotate-the-leaf-key)
    - [Automatic rotation](#automatic-rotation)
    - [Verifying your setup](#verifying-your-setup)

- [Planning features](#planning-features)
- [License](#license)

---

## Why

- **Standards‑based token auth** for Docker Distribution.
- **Granular policy** by user/group with glob patterns (first match wins).
- **Stable CA mode**: the registry trusts a long‑lived CA; you rotate only the leaf key/cert used to sign JWTs.
- **Self‑signed fallback** when Stable CA is disabled.

## Requirements

- PHP 8.2+
- Composer 2.x
- Bun 1.2.3+
- A Docker v2 registry (e.g., `registry:2`).

## Install & build

```bash
git clone https://github.com/eslym/registry-auth.git
cd registry-auth
cp .env.example .env

composer install
bun install
bun run build

php artisan key:generate
php artisan migrate # or: ADMIN_INIT_PASSWORD=somepassword php artisan migrate
```

## Setup & configuration

Configuration lives in `config/registry.php` (overridable via `.env`). The two key commands are:

### dotenv generator (`jwt:setup`)

Generates a **dotenv** configuration based on your answers and **optionally writes** it to `.env`.

- It **does not** create keys or certificates.
- Run it whenever you want to (re)create a sane `.env`.

```bash
php artisan jwt:setup
```

### Keys & certificates (`jwt:key`)

Creates or updates the **leaf** signing key and its certificate **according to your config**.

- When **Stable CA is enabled**, it signs the leaf with your CA.
- When **Stable CA is disabled**, it creates a **self‑signed** leaf certificate.
- Use `--force` to rotate (replace) an existing keypair programmatically.

```bash
php artisan jwt:ca # if stable ca is enabled, run before jwt:key

# create if missing
php artisan jwt:key

# rotate programmatically
php artisan jwt:key --force
```

**Key/cert properties** are driven by your `.env`/`config/registry.php`:

- Algorithm: `REGISTRY_JWT_ALGORITHM` (`RS256/384/512`, `ES256/384/512`)
- Key type/size/curve: `REGISTRY_JWT_KEY_TYPE`, `REGISTRY_JWT_KEY_SIZE`, `REGISTRY_JWT_KEY_CURVE`
- Paths: `REGISTRY_JWT_KEY_PATH|FILENAME`, `REGISTRY_JWT_CERT_PATH|FILENAME`
- Optional key passphrase: `REGISTRY_JWT_KEY_PASS` **or** `REGISTRY_JWT_KEY_PASS_SECRET` (path to a file with the passphrase)

**Stable CA block** (when enabled):

- `REGISTRY_JWT_CA_ENABLED=true`
- CA files: `REGISTRY_JWT_CA_KEY_PATH|FILENAME`, `REGISTRY_JWT_CA_CERT_PATH|FILENAME`
- Optional CA key passphrase: `REGISTRY_JWT_CA_KEY_PASS` **or** `REGISTRY_JWT_CA_KEY_PASS_SECRET`
- CA‑driven auto‑rotation (cron): `REGISTRY_JWT_CA_ROTATE_LEAF_CRON` (see [Automatic rotation](#automatic-rotation))

### Stable CA vs self‑signed

- **Stable CA enabled** → tokens carry `x5c = [leaf, ca]`; the registry validates the chain to the CA you configured.
- **Stable CA disabled** → tokens carry `x5c = [leaf]` (self‑signed). For this mode, point the registry at the **leaf** cert.

### Docker Registry settings

Set token auth and point the registry at the right trust anchor.

**Stable CA enabled (recommended)**

```yaml
environment:
  REGISTRY_AUTH: token
  REGISTRY_AUTH_TOKEN_REALM: http://auth:80/api/token
  REGISTRY_AUTH_TOKEN_SERVICE: ${REGISTRY_SERVICE}
  REGISTRY_AUTH_TOKEN_ISSUER: ${REGISTRY_ISSUER}
  REGISTRY_AUTH_TOKEN_ROOTCERTBUNDLE: /certs/registry-ca.crt # CA!
```

**Stable CA disabled (self‑signed leaf)**

```yaml
environment:
  REGISTRY_AUTH: token
  REGISTRY_AUTH_TOKEN_REALM: http://auth:80/api/token
  REGISTRY_AUTH_TOKEN_SERVICE: ${REGISTRY_SERVICE}
  REGISTRY_AUTH_TOKEN_ISSUER: ${REGISTRY_ISSUER}
  REGISTRY_AUTH_TOKEN_ROOTCERTBUNDLE: /certs/registry.crt # leaf
```

Mount the referenced cert file(s) into the registry container (e.g., `- ./certs:/certs:ro`).

## Access control

Rules are evaluated **top-to-bottom**; **first match wins**. Glob patterns are supported (`*` and `**`).  
**User rules take precedence over group rules** when evaluating “account rules.”

**How effective access is computed**

- **Account rules** = union of **User rules** and **Group rules** (with first-match-wins within each list).
- **Token rules** narrow access further (for personal access tokens).
- **Final permissions** = **(Account rules) ∩ (Token rules)** (i.e., intersection).

**Example**

- **User rules**
    - `public/**` (**pull** only)
    - `username/**` (**push/pull**)
- **Group rules**
    - `team-a/**` (**push/pull**)
- **Token rules**
    - `**` (**pull** only)

**Result:** the token grants **pull-only** on `public/**`, `username/**`, and `team-a/**`.  
(“Account rules” would allow push to `username/**` and `team-a/**`, but the **token rule** `**` (pull-only) intersects those to **pull-only**.)

## Token format

- **Header**: `alg` (from config), `kid` (derived from leaf public key), `x5c` (see above)
- **Claims**: `iss`, `aud`, `iat`, `nbf`, `exp`, `jti`, `access` (+ optional `sub`)
- Default TTL: `REGISTRY_TOKEN_TTL` (seconds)

## Operations

### Rotate the leaf key

```bash
php artisan jwt:key --force
```

- With **Stable CA enabled**, rotation needs **no registry change** (same CA trust anchor).
- With **Stable CA disabled**, if you replace the leaf cert file, the registry picks it up on restart.

### Automatic rotation

If you set a cron expression in `.env`:

```env
REGISTRY_JWT_CA_ROTATE_LEAF_CRON="0 2 1 * *"  # example: 02:00 on the 1st of each month
```

…the app will schedule **automatic leaf rotation** on that cadence (requires Stable CA enabled).

### Verifying your setup

- Stable CA: confirm registry’s `ROOTCERTBUNDLE` points to the **CA**; ensure tokens include `x5c[0]=leaf`, `x5c[1]=ca`.
- Self‑signed: confirm `ROOTCERTBUNDLE` points to the **leaf**; tokens include only `x5c[0]=leaf`.

## Planning features

- [x] ~~Stable CA for easier key rotation~~
- [ ] Image management & push/pull events/webhooks (planned)
- [x] ~~Personal access tokens~~

## License

MIT

# Registry Auth

A simple registry authentication backend using token auth.

> [!WARNING]
> This project is still in early development stage, use at your own risk.

## Requirements

- **PHP** 8.2 or higher
- **Composer** 2.0 or higher
- **Bun** 1.2.3 or higher

## Installation

> [!IMPORTANT]
> This is just a quick start guide, all the code in this section is just example. Do not copy and run directly without
> understanding it.

### Manual Installation
1. Clone the repository:
   ```bash
   git clone https://github.com/eslym/registry-auth.git
   cd registry-auth
   cp .env.example .env
   ```
2. Install dependencies:
   ```bash
   composer install
   bun install
   ```
3. Build the frontend:
   ```bash
   bun run build
   ```
4. Set up the application:
   ```bash
   # 1. edit .env file to configure your environment
   
   # 2. generate application key and run migrations
   php artisan key:generate
   php artisan migrate
   # or if you want to set an initial admin user password:
   ADMIN_INIT_PASSWORD=somepassword php artisan migrate
   
   # 3. generate jwt key and cert (if you doesn't have one)
   php artisan jwt:generate
   
   # 4. read log file to get the initial admin user credentials
   ```
5. Optimize the application (optional but recommended):
   ```bash
   php artisan optimize
   ```

### With Docker Compose

```yaml
services:
    auth:
        image: eslym/registry-auth:latest
        restart: unless-stopped
        environment:
            APP_ENV: production
            # ...
            REGISTRY_SERVICE: registry-auth
            REGISTRY_ISSUER: registry-auth
            REGISTRY_JWT_KEY_FILENAME: certs/jwt.key
            REGISTRY_JWT_CERT_FILENAME: certs/jwt.crt
        volumes:
            - './auth-storage:/app/storage'
            - './certs:/app/storage/certs'
        ports:
            - '8000:80'
    registry:
        image: registry:2
        restart: unless-stopped
        environment:
            REGISTRY_AUTH: token
            REGISTRY_AUTH_TOKEN_REALM: http://localhost:8000/api/token
            REGISTRY_AUTH_TOKEN_SERVICE: registry-auth # make sure this matches the REGISTRY_SERVICE in auth service
            REGISTRY_AUTH_TOKEN_ISSUER: registry-auth # make sure this matches the REGISTRY_ISSUER in auth service
            REGISTRY_AUTH_TOKEN_ROOTCERTBUNDLE: /certs/jwt.crt
        volumes:
            - './certs:/certs:ro'
        ports:
            - '5000:5000'
```

# Usage

## Access Controls

The access control is loop and match the repository name against the rules in the given order (fist come first).
The rule pattern supports glob pattern. Users' access control rules is more prioritized than the group's rules.

# Planning Features

- [ ] Add image management
- [x] ~~Add personal access token~~
- [ ] Stable CA for easier key rotation

# License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details

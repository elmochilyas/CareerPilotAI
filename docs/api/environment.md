# Environment Configuration

## Sanctum SPA Authentication

CareerPilot uses Laravel Sanctum for SPA cookie-based authentication.

### Required environment variables

| Variable | Description | Default |
|---|---|---|
| `APP_URL` | Backend URL | `http://careerpilot-api.test` |
| `FRONTEND_URL` | Frontend SPA URL | `http://localhost:5173` |
| `SANCTUM_STATEFUL_DOMAINS` | Comma-separated list of stateful domains | `localhost,localhost:3000,localhost:5173,127.0.0.1,::1,careerpilot-api.test` |
| `SESSION_DOMAIN` | Cookie domain for the session cookie | `.localhost` (for Herd) |
| `SESSION_DRIVER` | Session storage driver | `file` or `database` |
| `SESSION_LIFETIME` | Session lifetime in minutes | `120` |
| `FILESYSTEM_DISK` | Filesystem disk | `local` |
| `CORS_ALLOWED_ORIGINS` | Allowed CORS origins (comma-separated) | `http://localhost:5173` |
| `MAIL_MAILER` | Mail driver | `log` (development) |

### SPA Authentication Flow

1. Frontend calls `GET /sanctum/csrf-cookie` to obtain the `XSRF-TOKEN` cookie
2. Frontend sends login request with `X-XSRF-TOKEN` header (Axios auto-sends this)
3. Sanctum validates credentials and starts a session
4. Subsequent authenticated requests include the session cookie
5. On logout, the session is invalidated and a new CSRF token is generated

### Important Notes

- `withCredentials: true` must be set on all API requests
- CSRF token cookie must be fetched before any state-changing request
- Session cookie is HTTP-only and SameSite is set to Lax/Strict
- In development, the `SESSION_DOMAIN` should include the backend domain
- Rate limiting: 5 attempts/minute for login and forgot-password; 3 attempts/minute for verification resend; 10 attempts/minute for registration

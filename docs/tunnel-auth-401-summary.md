# Tunnel Auth 401 — Full Summary

**Date:** 2026-03-06  
**Status:** Unresolved — 401 Unauthorized persists when accessing app via Cloudflare Tunnel

---

## 1. Architecture Context

- **Local app:** Laravel 12 + Inertia + Vue 3, served via Laravel Herd at `https://agent.test`
- **Tunnel:** Cloudflare Tunnel (cloudflared) exposes the app at `https://agent.garethdaine.com`
- **Tunnel config:** Uses `httpHostHeader: agent.test` so Laravel receives `Host: agent.test` for Herd routing
- **Flow:** Browser → `agent.garethdaine.com` → Cloudflare → cloudflared → `agent.test` (Laravel)

---

## 2. Issues Encountered and Fixes Applied

### Issue 1: 401 Unauthorized on API calls (initial)

**Symptom:** User could log in at `agent.garethdaine.com`, but API calls (e.g. `/agent/api/v1/interrogation/sessions`, `/agent/api/v1/notifications`, `/agent/api/v1/jobs`) returned 401.

**Root cause:** Session cookie domain mismatch. Laravel set the session cookie using the request `Host` header (`agent.test`). The browser stored the cookie for `agent.test`. When the user navigated to `agent.garethdaine.com`, the browser did not send the `agent.test` cookie (different domain). Sanctum saw no session → 401.

**Fix applied:** `TunnelSessionDomainMiddleware` — when `Origin` or `Referer` contains the tunnel hostname, set `config('session.domain', $host)` so the session cookie is scoped to `agent.garethdaine.com`.

**Files changed:**
- `app/Http/Middleware/TunnelSessionDomainMiddleware.php` (new)
- `bootstrap/app.php` — prepend middleware to both `web` and `api` groups

---

### Issue 2: 404 on API calls (regression)

**Symptom:** After the session-domain fix, API calls returned 404 instead of 401.

**Root cause:** Cloudflared ingress hostname mismatch. If the tunnel hostname was stored as a full URL (e.g. `https://agent.garethdaine.com`) instead of a plain host (`agent.garethdaine.com`), the ingress rule did not match. Requests hit the catch-all `http_status:404` rule and never reached Laravel.

**Fix applied:** `CloudflaredService::normalizeHostnameForIngress()` — extract the host from URL-style hostnames before writing the cloudflared config.

**Files changed:**
- `app/Services/Tunnel/CloudflaredService.php` — added `normalizeHostnameForIngress()`, use it when setting `rule['hostname']`
- `tests/Unit/Tunnel/CloudflaredServiceTest.php` — added test for URL hostname normalization

**Action required:** Restart the tunnel after this fix so the new config is generated.

---

### Issue 3: Sanctum `fromFrontend()` returning false when Referer/Origin absent

**Symptom:** 401 persisted even with session domain fix.

**Root cause:** Sanctum’s `EnsureFrontendRequestsAreStateful::fromFrontend()` requires either `Referer` or `Origin` to match a stateful domain. If both are null (possible for same-origin fetch/axios in some browsers or policies), it returns `false`, the session middleware is skipped, and auth fails.

**Fix applied:** In `TunnelSessionDomainMiddleware`, when both `Origin` and `Referer` are empty, set `Referer: https://{tunnel_host}/` so Sanctum treats the request as stateful.

**Files changed:**
- `app/Http/Middleware/TunnelSessionDomainMiddleware.php` — added `$request->headers->set('Referer', ...)` when both headers are null

---

### Supporting configuration (existing or added)

| Config | Purpose |
|--------|---------|
| `config/cors.php` | `allowed_origins` from `app.url`, `supports_credentials: true` |
| `AppServiceProvider::mergeTunnelHostnameIntoConfig()` | At boot, merges tunnel hostname into CORS `allowed_origins` and Sanctum `stateful` domains |
| `config/sanctum.php` | `stateful` includes `localhost`, `agent.test` (from `APP_URL`), plus tunnel hostname at runtime |

---

## 3. Remaining Issue: 401 Still Occurs

**Current state:** User can log in at `agent.garethdaine.com`, but API calls still return 401 Unauthorized.

**Observed:** DevTools showed cookies for both `agent.garethdaine.com` and `agent.test`, including `agent-ops-session` and `XSRF-TOKEN` for each domain.

---

## 4. What We’ve Tried

1. **Session domain override** — Set `session.domain` to tunnel host when Origin/Referer contain it.
2. **Referer injection** — Set `Referer` when both Origin and Referer are null.
3. **Cloudflared hostname normalization** — Ensure ingress hostname is plain host, not URL.
4. **Sanctum stateful merge** — Add tunnel hostname to `sanctum.stateful` at boot.
5. **CORS merge** — Add tunnel origin to `cors.allowed_origins` at boot.
6. **Cookie cleanup** — User advised to clear cookies for both domains and log in again.

---

## 5. Current Configuration

### Environment (assumed)

- `APP_URL` — `https://agent.test` (Herd)
- `TUNNEL_ENABLED` — `true`
- `TUNNEL_HOSTNAME` — `agent.garethdaine.com` (or stored in tunnel settings DB)

### Middleware order (API)

1. `TunnelSessionDomainMiddleware` (prepend)
2. Sanctum `EnsureFrontendRequestsAreStateful` (via `statefulApi()`)
3. Route-specific middleware (`auth:sanctum`, etc.)

### Request flow when using tunnel

1. Browser requests `https://agent.garethdaine.com/agent/api/v1/jobs`
2. Cloudflare → cloudflared → Laravel at `agent.test`
3. Laravel receives: `Host: agent.test`, `Referer: https://agent.garethdaine.com/...` (or null), `Cookie: agent-ops-session=...` (if sent)
4. `TunnelSessionDomainMiddleware` runs: sets `session.domain` and possibly `Referer`
5. Sanctum `fromFrontend()` checks Referer/Origin against stateful domains
6. If stateful: session middleware runs, auth:sanctum uses session
7. If not stateful: no session, 401

---

## 6. Hypotheses for Remaining 401

1. **Referer/Origin still not reaching Laravel** — Cloudflare or cloudflared may strip or alter these headers.
2. **Cookie not sent** — Browser may not send `agent.garethdaine.com` cookies (e.g. SameSite, Secure, or path).
3. **Session domain set too late** — `session.domain` may be read before our middleware runs, or config may be cached.
4. **Stateful domain matching** — Sanctum’s `Str::is()` pattern may not match the actual Referer/Origin format.
5. **Multiple cookies** — Presence of both `agent.test` and `agent.garethdaine.com` cookies may cause the wrong one to be used or validation to fail.

---

## 7. Suggested Next Steps (for debugging)

1. **Log request headers** — Add temporary logging in `TunnelSessionDomainMiddleware` to capture `Host`, `Origin`, `Referer`, and `Cookie` for failing API requests.
2. **Verify Sanctum `fromFrontend()`** — Add logging in a custom middleware or patch to see whether it returns true/false and why.
3. **Inspect cloudflared config** — Confirm `storage/app/tunnel/config.yml` has `hostname: agent.garethdaine.com` (no URL).
4. **Test without tunnel** — Access `agent.test` directly; if auth works, the issue is tunnel-specific.
5. **Cloudflare Transform Rules** — Consider adding a rule to preserve or set `X-Forwarded-Host: agent.garethdaine.com` so Laravel can use it as a fallback.
6. **Alternative: Bearer token** — Use Sanctum API tokens instead of session for tunnel access (different auth flow, avoids cookie domain issues).

---

## 8. Files Touched in This Session

| File | Change |
|------|--------|
| `app/Http/Middleware/TunnelSessionDomainMiddleware.php` | New — session domain + Referer injection |
| `bootstrap/app.php` | Register middleware on web + api |
| `app/Providers/AppServiceProvider.php` | `mergeTunnelHostnameIntoConfig()` (pre-existing) |
| `app/Services/Tunnel/CloudflaredService.php` | `normalizeHostnameForIngress()` |
| `config/cors.php` | `supports_credentials: true`, `allowed_origins` (pre-existing) |
| `config/sanctum.php` | No changes — stateful merge is at runtime |
| `tests/Unit/Tunnel/CloudflaredServiceTest.php` | Test for hostname normalization |

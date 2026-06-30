# Sky Connect — Plugin Reference

Quick lookup for every route, database variable, and file purpose.
Update this file every time we cover a new part of the plugin.

---

## 1. Custom Routes (URLs)

| Route | Method | File | Purpose |
|---|---|---|---|
| `/.well-known/oauth-authorization-server` | GET | oauth-metadata.php | Gives Claude 3 login addresses |
| `/.well-known/oauth-protected-resource` | GET | oauth-metadata.php | Tells Claude which API the token works on |
| `/oauth/register` | POST | oauth-register.php | Claude self-registers, gets client_id |
| `admin.php?page=sky-connect-authorize` | GET (browser) | oauth-authorize.php | Allow/Deny screen, human clicks here |
| `/oauth/token` | POST | oauth-token.php | Swaps 5 min code for real token |

---

## 2. Database Variables

| Variable name | Saved by file | Purpose |
|---|---|---|
| `sky_connect_dcr_clients` | oauth-register.php | List of every Claude that ever registered (client_id, name, redirect_uris) |
| `sky_connect_auth_code_{code}` | oauth-authorize.php | Temp 5 min ticket — stores code_challenge, client_id, redirect_uri, resource |
| `sky_connect_oauth_token_hash` | oauth-token.php | Hashed real access token — never plain |
| `sky_connect_client_id` | (older single-client version, may be unused now) | Old style single client ID |
| `sky_connect_token_hash` | activation.php (Warp token) | Hashed Warp terminal token |

---

## 3. File Purposes

| File | One-line job |
|---|---|
| `sky-connect.php` | Main plugin file — loads everything else |
| `activation.php` | Runs once on plugin activation — creates Warp token |
| `deactivation.php` | Runs on plugin deactivation — cleanup |
| `oauth-metadata.php` | Tells Claude where everything lives (2 silent URLs) |
| `oauth-register.php` | Lets Claude auto-register, gives back client_id |
| `oauth-authorize.php` | Shows Allow/Deny screen, creates 5 min ticket |
| `oauth-token.php` | Verifies everything, issues real access token |
| `auth.php` | Checks Bearer token on every future request |
| `rest_endpoint.php` | The actual `/mcp` endpoint — tools Claude can use |
| `admin-page.php` | WordPress admin settings page (shows Warp token, etc) |

---

## 4. Key Terms Glossary

| Term | Meaning |
|---|---|
| `client_id` | Plain ID card for Claude — not secret |
| `code_challenge` | Hashed puzzle, sent early by Claude |
| `code_verifier` | Original secret, revealed later — same as code_challenge before hashing |
| `auth_code` | 5 min temporary ticket — made by WordPress, unrelated to the secret |
| `access_token` | Final real, long-lived key — used in every future request |
| `state` | Random tracking string, matched to prevent mix-ups |
| `grant_type` | Label saying which login method is being used |
| `PKCE` | Proves same Claude that started the login also finished it |
| `DCR` | Dynamic Client Registration — Claude registers itself automatically |
| `CIMD` | Old manual way — human copy-pastes client_id (not used in final version) |
| `Bearer` | Label telling WordPress "a token follows" in the request header |

---

*Last updated: covering oauth-metadata.php, oauth-register.php, oauth-authorize.php, oauth-token.php*
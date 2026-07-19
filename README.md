# Sky Connect

Connect Claude to your WordPress plugins via MCP.
Read and safely edit plugin files from Claude Web or Warp — locked inside your plugins folder.

---

## What it does

Sky Connect turns your WordPress site into an MCP server. Claude can then:

- List your plugins
- Read plugin files
- Safely edit plugin files (single or many at once)

Everything stays locked inside the plugins folder.

---

## Features

- 🔌 MCP server in WordPress (one REST endpoint)
- 🖥️ Connect via Warp (token) or Claude Web (OAuth)
- 🔒 Safety jail — locked to the plugins folder, blocks `../` tricks and symlinks
- 🛡️ Admin-only access
- 🔑 Master ON/OFF switch (starts OFF — nothing is exposed until you turn it on)
- 👀 Diff preview before edits
- 💾 Auto-backup before saving (kept for 30 days)
- ✅ PHP syntax check — blocks site-breaking code before it saves
- 🩺 Health check after each edit — auto rolls back if the site breaks
- 📦 Multi-file write — save many files as one unit (all succeed or all undo)
- 🆘 Emergency restore URL — fixes a broken site even when the dashboard is down
- 📧 Restore link added to WordPress crash email
- 🔁 Auto self-rotating emergency key (changes after every use)
- 🧹 Daily auto-cleanup of old backups and logs
- 📝 Logs every read/write (last 50 shown in admin)
- 🗑️ Full uninstall cleanup — removes options, log table, and backups

---

## Tools

| Tool | Purpose |
|---|---|
| `list_plugins` | List plugin folders |
| `list_files` | List files in a plugin |
| `read_file` | Read a file |
| `write_file` | Save one file (after all safety checks) |
| `write_multiple_files` | Save many files at once (all-or-nothing) |

File size limit for read/write: 2 MB.

---

## All files

### Root

| File | One-line job |
|---|---|
| `README.md` | This guide |
| `sky-connect/sky-connect.php` | Main plugin file — loads and boots everything |
| `sky-connect/mu-installer.php` | Installs the emergency-restore must-use plugin (loads even during a crash) |
| `sky-connect/uninstall.php` | Runs on delete — cleans options, log table, and backups |
| `sky-connect/sky-connect-reference.md` | Developer quick-lookup for routes, DB keys, and files |

### admin/

| File | One-line job |
|---|---|
| `admin/admin-page.php` | WP admin page — master switch, Warp token, emergency URL, activity log |

### includes/

| File | One-line job |
|---|---|
| `includes/activation.php` | Runs once on activate — sets defaults and creates the Warp token |
| `includes/deactivation.php` | Runs on deactivate — flips switch OFF, stops daily cleanup |
| `includes/auth.php` | Checks the Bearer token on every request (allow or block) |
| `includes/jail.php` | Path guard — keeps every action inside the plugins folder |
| `includes/backup.php` | Saves a copy before each edit, can restore, deletes old backups |
| `includes/syntax.php` | Checks PHP is valid before saving (uses tokenizer, no shell needed) |
| `includes/health.php` | Tests homepage, a post, and REST API after an edit |
| `includes/logger.php` | Creates log table, records every action, cleans old logs |
| `includes/recovery-email.php` | Adds the emergency restore link to WordPress's crash email |
| `includes/rest_endpoint.php` | The `/mcp` endpoint — connects tools to Claude |
| `includes/oauth-metadata.php` | Tells Claude where the login URLs live |
| `includes/oauth-register.php` | Lets Claude auto-register and get a client_id (DCR) |
| `includes/oauth-authorize.php` | Shows the Allow/Deny screen, makes a 5-min ticket |
| `includes/oauth-token.php` | Swaps the ticket for the real access token (PKCE) |

### tools/

| File | One-line job |
|---|---|
| `tools/tools.php` | The 4 core tools: list plugins, list files, read, write |
| `tools/multi-file-write.php` | Writes many files atomically (all-or-nothing) |

---

## Routes (URLs)

| Route | Method | Purpose |
|---|---|---|
| `/.well-known/oauth-authorization-server` | GET | Gives Claude the login addresses |
| `/.well-known/oauth-protected-resource` | GET | Tells Claude which API the token works on |
| `/oauth/register` | POST | Claude self-registers, gets a client_id |
| `admin.php?page=sky-connect-authorize` | GET | Allow/Deny screen (human clicks here) |
| `/oauth/token` | POST | Swaps the 5-min code for a real token |
| `/?sky_restore=KEY&file=plugin/file.php` | GET | Emergency restore (works during a crash) |

---

## Connecting

**Warp:** copy your token → paste in Warp. Done.

**Claude Web:** copy your Client ID + Secret → add as a custom connector in Claude → click Allow.

---

## Safety flow (edits)

1. Path checked by the jail (must be inside plugins folder)
2. PHP syntax checked
3. Old file backed up
4. New file written
5. Site health checked
6. If the site breaks → auto roll back to the backup
7. Result logged

For multi-file writes, all files pass steps 1–4, then **one** health check decides: keep all, or undo all.

---

## Uninstall

Deleting the plugin removes all its options, the log table, and the backups folder — no leftovers.

---

*Author: Hafi · https://devbuggs.com · Version 1.0.0*

# Security Policy

## Supported Versions

Only the latest commit on `main` is actively maintained. No backported security fixes are provided for older versions.

| Version | Supported |
|---------|-----------|
| `main` (latest) | ✅ |
| Older commits | ❌ |

## Reporting a Vulnerability

**Do not open a public GitHub issue for security vulnerabilities.**

Report vulnerabilities privately by emailing: **jgontiverosp@gmail.com**

Include as much of the following as possible:

- Description of the vulnerability and its potential impact
- Steps to reproduce (proof-of-concept or exploit code if available)
- Affected component (auth, API endpoint, data handling, etc.)
- Suggested fix, if you have one

### What to expect

- **Acknowledgement** within 72 hours of receiving your report
- **Status update** (confirmed, disputed, or fix in progress) within 7 days
- Coordinated disclosure after a fix is available — you will be credited if you wish

## Scope

### In scope

- Authentication and session handling (Sanctum SPA cookie auth)
- API endpoint authorization bypasses or cross-user data access
- Injection vulnerabilities (SQL, command, prompt injection affecting server-side behavior)
- Sensitive data exposure (API keys, conversation content, user credentials)
- CSRF vulnerabilities on state-changing endpoints
- Insecure storage or transmission of credentials

### Out of scope

- Vulnerabilities in third-party LLM providers or their APIs (OpenAI, ElevenLabs, Deepgram, etc.)
- Theoretical attacks with no practical exploit path
- Issues requiring physical access to the server
- Social engineering
- Self-XSS

## Sensitive Areas

This application handles the following sensitive data — reports in these areas are taken seriously:

- **LLM provider API keys** — stored encrypted at rest; any decryption or leakage path is high priority
- **Conversation history and memory** — personally sensitive; cross-user access is critical severity
- **Voice data** — audio is transcribed transiently and not persisted; any evidence of unintended retention is in scope
- **Bot tokens** — Telegram and Discord credentials stored in environment variables

## Deployment Hardening Notes

If you are self-hosting VERA, ensure the following in production:

- `APP_DEBUG=false`
- `SESSION_SECURE_COOKIE=true`
- `SESSION_ENCRYPT=true`
- `SANCTUM_STATEFUL_DOMAINS` set to your exact production domain
- `APP_ENV=production`
- Clockwork profiler endpoint (`/__clockwork`) access-restricted or disabled

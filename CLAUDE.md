# Claude project instructions

Read and follow [AGENTS.md](AGENTS.md) as the canonical project guidance.
Read [ARCHITECTURE.md](ARCHITECTURE.md) before changing shared schema,
authorization, appointment management, or media storage; use
[README.md](README.md) for setup and validation commands.

Keep the existing `.claude` skills and references in place. Consult
`.claude/skills/laravel-permission-development/SKILL.md` for permission changes
and `.claude/skills/medialibrary-development/SKILL.md` for media changes.
Avoid duplicating their guidance. Shared-contract changes must account for
the separate frontend application.

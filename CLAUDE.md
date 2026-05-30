# Mastechnics — Claude Code Project Instructions

## Project

Laravel 12 website for Mastechnics, an HVAC company (heating, airco, plumbing, ventilation, water softeners, cold rooms). The site has a multilingual Smart Request Flow form, an admin dashboard, and a customer request storage system.

## Languages

The site is always multilingual: **nl / fr / en**. Every user-facing label, placeholder, error, and helper text must have translations for all three locales. Default fallback is `nl`.

## Non-Negotiable Constraints

These must never break:
- Customer request storage (`CustomerRequest` model, `customer_requests` table)
- File uploads (stored via `attachments[]`, linked via `CustomerRequestAttachment`)
- The request flow form (`/nl/aanvraag`, `/fr/demande`, `/en/request`)
- The admin dashboard (`/admin/requests`)
- Existing mail system (`NewCustomerRequestMail`, `CustomerRequestConfirmationMail`)
- All existing routes in `routes/web.php`

## Shell

This project runs on **Windows**. Use **PowerShell** syntax only. Never use Bash-style commands (`&&` chaining, `$VAR`, `/dev/null`, etc.).

PowerShell equivalents:
- Chain commands: `A; if ($?) { B }` — not `A && B`
- Null device: `$null` — not `/dev/null`
- Env vars: `$env:VAR` — not `$VAR`

## Architecture Rules

- Follow existing Laravel conventions: controllers in `app/Http/Controllers/`, models in `app/Models/`, config in `config/`, views in `resources/views/`.
- The request flow is driven by `config/request-flow.php` — UI and validation are derived from it. Do not hard-code step logic in the controller or blade.
- Admin auth uses `AdminUser` model + `Hash::check()`. Never revert to plain-text passwords or `config/admin.php` password storage.
- Keep the `metadata` JSON column for flexible answer storage alongside dedicated columns for structured fields.

## Security

- Never store plain-text passwords or secrets in config files.
- Validate all user input at the server (controller). Client-side JS is UX only, never trust it for security.
- File uploads: validate MIME type and size server-side (`mimes:jpg,jpeg,png,webp,pdf`, `max:5120`).
- Never expose admin routes without the `admin` middleware.

## Design & UX Approach

- **Mobile-first** — the form is used on mobile. Test mobile layout before claiming done.
- Apply **CRO thinking** to landing pages: clear headlines, trust signals, single focused CTA.
- Apply **form-CRO thinking** to the Smart Request Flow: reduce friction, guide with helper text, show only relevant fields, use option cards not dropdowns where possible.
- Apply **pricing-strategy thinking** when working on quotes or service pricing pages.
- Apply **frontend design skills** (clean spacing, readable type, accessible contrast) and **superpowers** (brainstorming, planning, verification) for any UI work.

## Available Skills

Installed and usable:
- `/copywriting` — for landing page copy, CTAs, email content
- `/seo-audit` — for on-page SEO review
- `/code-reviewer` — code review for security, performance, and correctness (Med Risk rating from Gen scanner — review before using on sensitive code)

**Not installed** — do not invoke these as slash commands:
- `/ui-ux-pro-max` — not installed
- `/page-cro` — not installed
- `/form-cro` — not installed
- `/pricing-strategy` — not installed

If CRO, pricing, or UX thinking is needed, apply it as plain reasoning — do not attempt to invoke missing skills.

## AI & Automation Placeholders

- `ai_summary` and `ai_detected_missing_fields` columns exist on `customer_requests` — leave them `null` for now.
- Do **not** implement OpenAI, Claude API calls, or any LLM integration yet.
- Do **not** implement WhatsApp API yet.
- Do **not** implement Google Calendar API yet.
- Only add these when explicitly requested in a sprint task.

## Task Discipline

- Work **task-by-task**. Do not bleed changes across tasks.
- For any change touching more than one file: stop and summarize changed files, risks, and how to test before continuing.
- After each task: list files changed, exact issue fixed or feature added, any risks, and the next recommended task.
- Use `superpowers:writing-plans` before multi-step implementation. Use `superpowers:subagent-driven-development` to execute plans task-by-task with review gates.
- Mark tasks complete in `TodoWrite` immediately after finishing — do not batch completions.

## Sprint History

- **Sprint 1** ✅ — Admin auth migrated to `AdminUser` DB model with `Hash::check()` and `Hash::make()`.
- **Sprint 2** 🔄 — Smart Request Flow: `service_category_selection` step, 4 conditional flows (CV onderhoud, Lek/dringend, Airco offerte, Airco onderhoud), new workflow DB columns.
  - Task 1 ✅ Migration fixed
  - Task 2 ✅ Model fixed
  - Tasks 3–8 pending

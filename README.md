# AI Receptionist

A Greek-speaking AI receptionist for a physiotherapy clinic. Visitors book,
check and cancel appointments through natural conversation ,the agent writes
to a real database ,and the result appears live on a weekly calendar next to
the chat.

**Live demo:** https://ai-receptionist-production-d890.up.railway.app
**Health check:** [/health.php](https://ai-receptionist-production-d890.up.railway.app/health.php)

Try: *«Θέλω ένα ραντεβού για μασάζ την Τρίτη το απόγευμα»* — pick a time, give
a name and a phone number, and watch the booking land on the calendar.

## The architecture principle

**The LLM is the interface, not the authority.** It cannot decide whether a
slot is free or whether 21:00 is within opening hours. It calls a tool, PHP
validates against the database, and the model reports the outcome. Every
business rule lives in PHP and in database constraints — never in the prompt.

The strongest guarantee is not code but schema:

    CREATE UNIQUE INDEX idx_no_double_booking
        ON appointments (session_id, date, time)
        WHERE status = 'confirmed';

Even a race between two concurrent requests cannot double-book a slot. The
agent's polite "that time is already taken, how about 15:00?" is this index
speaking, translated by the model.

## Stack

PHP 8.5 (no framework, no Composer dependencies) ~ SQLite ~ vanilla JS ~
Anthropic Messages API (Claude Sonnet 5, tool use) ~ Railway

The absence of a framework is deliberate: the whole request cycle is readable
in an afternoon, and the demo has nothing to hide behind.

## How a booking flows

    visitor types  ->  POST /api/chat.php
                       PHP loads history, sends it to the Messages API
                       with the system prompt and 3 tool definitions
                       <-> model calls check_availability / create_booking /
                           cancel_booking; PHP executes each against SQLite
                           and feeds the result back (max 6 iterations)
                       final text reply returns to the browser
    frontend       ->  GET /api/appointments.php, calendar re-renders,
                       the new card lands with a highlight animation

Three endpoints total: POST /api/chat.php, GET /api/appointments.php,
POST /api/reset.php.

## Decisions worth defending

**Per-visitor isolation.** Every browser gets its own demo clinic, keyed by a
random cookie. Ten people can demo simultaneously; one person's Reset never
touches another's bookings.

**Dates are injected fresh on every request.** The model has no idea what day
it is. The system prompt carries today's date and the exact demo week, so
"Τρίτη" always resolves to a real, bookable date. The calendar always shows
the *upcoming* Mon–Fri — a half-elapsed week makes a demo look broken.

**Cancellation identifies by phone, not name.** Greek names decline across
grammatical cases (Πέτρος → του Πέτρου). A name is display data; the phone
number is the key.

**API responses are JSON or nothing.** Warnings become exceptions, exceptions
become a logged 500 with a generic body. A single PHP notice printed before
the opening brace would break JSON.parse() client-side and disguise a
backend bug as a frontend one.

## Cost control (public URL, real API key)

Three layers, honestly ranked:

1. **Per-IP window** (30 req/hour) — stops the curious. Not airtight:
   X-Forwarded-For is client-controlled behind a proxy.
2. **Global daily budget** (200 req/day) — the one that actually holds. No
   header forges past a count of every request served.
3. **Prepaid credits with auto-reload off** — the real last line. Worst case
   is a spent balance, never a charged card.

The API key lives only in environment variables (.env locally, Railway
variables in production). It has never appeared in any commit.

## Security notes

- CSP on every HTML response: customer names written by the agent end up in
  the DOM, so even if an escaping bug slipped through, inline script would
  not execute.
- All user text is HTML-escaped before rendering; SQL goes through prepared
  statements exclusively.
- Sessions are httponly cookies; raw IPs are never stored (salted hash,
  pruned after 48h).
- The agent refuses medical advice, off-topic requests and role changes at
  the prompt level — and cannot fabricate bookings at the tool level, which
  is the level that matters.

## Run it locally

    git clone https://github.com/dislisdev/ai-receptionist.git
    cd ai-receptionist
    cp .env.example .env         # add your Anthropic API key
    php -S localhost:8000 -t public

Requires PHP >= 8.2 with pdo_sqlite, curl, mbstring (all bundled by default).
The database file and schema are created automatically on first request.

## Deployment

Pushing to main triggers a Railway build and deploy — GitHub is the source,
Railway is the mirror. SQLite lives on a mounted volume (/data), so bookings
survive redeploys. /health.php verifies the environment end to end: PHP
extensions, database writability, timezone, Anthropic reachability, and
today's demo budget.

## White-label

The clinic is configuration, not code: services, prices and colours live in
one catalogue array; opening hours are two constants; the brand is one HTML
block. The same codebase is an afternoon away from being a hair salon, a gym
or a dental office.

## Next steps

Multi-tenant configuration · admin dashboard · human handoff · automated
evals against transcripts stored in the conversations table ·
email/SMS confirmations · MySQL for multi-writer scale

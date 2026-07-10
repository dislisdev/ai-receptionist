# AI Receptionist

A Greek-speaking AI receptionist for a physiotherapy clinic. Visitors book, check
and cancel appointments through natural conversation — the agent writes to a real
database and the calendar updates live.

**Status:** work in progress.

## Architecture

The LLM is the interface, not the authority. It cannot decide whether a slot is
free or whether 21:00 is within opening hours. It calls a tool, PHP validates
against the database, and the model reports the outcome. Every business rule
lives in PHP and in database constraints — never in the prompt.

## Stack

PHP 8.4 · SQLite · vanilla JS · Anthropic Messages API (tool use) · Railway

## Next steps

Multi-tenant config, admin dashboard, human handoff, automated evals,
email/SMS notifications.

# 5. A generation runs inside the request, as a stream of events

Date: 2026-10-20. Status: accepted.

## Context

A generation takes ten to thirty seconds. The hosts the module runs on have
no job queue or worker, and adding one is infrastructure every site would
have to run.

## Decision

The screen posts the brief and the server answers with a stream of
server-sent events (`start`, `turn`, `delta`, `correction`, `done`,
`error`) while the generator runs in the same request, reading the answer of
the API as a stream too. Without the script of the screen, the same action
posts and comes back with a redirect. Either way the screen is reloaded with
the id of the ledger row and shows the outcome from there: a stream cannot
leave a flash message behind.

## Consequences

- `set_time_limit(0)` and `ignore_user_abort(true)` on the generation; the
  reverse proxy must not buffer (`X-Accel-Buffering: no`) and its read
  timeout must cover a generation (ten minutes on the test bench).
- A host that cuts long requests would need the job-and-worker variant,
  which this decision keeps out of the module.

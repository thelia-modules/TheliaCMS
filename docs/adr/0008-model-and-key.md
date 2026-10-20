# 8. Model, effort and key

Date: 2026-10-20. Status: accepted.

## Context

The model and how hard it thinks are choices a site revisits; a key is a
secret that must never travel with an export.

## Decision

`claude-opus-5-5` with effort `high` and 16 000 output tokens by default,
all three as settings of the module (CMS > Settings > Generator). The key is
read from `ANTHROPIC_API_KEY` in the environment only; without it the screen
says so and nothing is called.

## Consequences

- An export carries the model and the effort, never the key.
- Every environment that generates needs the variable in its deployment.

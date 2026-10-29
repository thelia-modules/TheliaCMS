# 8. Model, effort and key

Date: 2026-10-20. Status: superseded in 2.4.0 (model, effort and key as settings of the module).

Superseded in 2.4.0: the provider, the key and the model are set in the AI settings of Thelia and the range of each usage chooses the model; the module keeps only the effort, the length of an answer and the reading of web pages (`Generation/GeneratorSettings.php`). `ANTHROPIC_API_KEY` is no longer read. The text below is the decision as it was taken.

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

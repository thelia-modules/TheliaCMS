# 7. Only the envelope of the document is a structured output

Date: 2026-10-20. Status: accepted.

## Context

The API constrains an answer to a JSON schema, but its grammar compiler
refuses a schema of twenty components: at most 24 optional properties, 16
union types, no enum with null, a size limit, and strict tools join the
grammar.

## Decision

The output schema sent to the API is the envelope only
(`Content\DocumentSchema::strict()`): `version`, and sections of `id`,
`component` (an enum of the catalogue) and `props` written as a JSON
string. The generator decodes the props, the validator applies the full
schema of each component server side, and a refused document goes back to
the model with its problems, twice at most. Tools are not strict.

## Consequences

- The catalogue can grow without hitting the grammar; the price is one or
  two correction turns on some briefs.
- The full schema (`DocumentSchema::full()`) remains the documentation and
  the validator's reference.

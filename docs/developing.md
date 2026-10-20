# Working on the module

What a contributor runs into when the module is developed inside a Thelia 3
host rather than installed from Composer. Each point was met once; the cure
is the one that worked.

## Where the clone goes

The clone lives in `local/modules/TheliaCMS` of the host, as the CI lays it
out: a symbolic link to a directory outside the project does not resolve
inside the DDEV container. The host requires the dependencies of the module
directly (`composer.json` of the module says which) and no longer
`thelia/cms-module` itself.

## Caches that outlive a change

- **Propel models** (`var/propel/<env>/model`) are built once and kept while
  `var/propel/<env>/hash` matches. After any change to `Config/schema.xml`,
  remove `var/propel/dev` and `var/propel/test` in the host; `cache:clear`
  alone rebuilds nothing.
- **The test container** (`var/cache/test`) is compiled by the bootstrap of
  the integration tests, not by `cache:clear`. After a change to the
  constructor of a service, a new route or a new controller, remove it; the
  first test recompiles.
- **Compiled Twig** is invalidated on the mtime of the template. A template
  restored within the same second as it was changed keeps the compiled copy of
  the change: `touch` the template, then remove `var/cache/test`.
- **Module assets** served by `module_asset()` are copied once into
  `public/assets/backOffice/default-twig/TheliaCMS/`. After a change to a
  script or a stylesheet of `templates/backOffice/default-twig/assets/`, copy
  the file there again (or remove the published directory), and give the
  template an `assets_version` so the browser drops its copy too.

## Writing tests

- A test that runs outside a transaction (`ALTER TABLE`, `TRUNCATE`) cleans in
  `tearDown()` what it inserted; a run interrupted halfway otherwise leaves
  rows behind for the next one.
- A test is seen red once, by breaking the code it covers, before it is kept.
  For a template, that means `touch` and `var/cache/test` removed before and
  after the sabotage (see above).
- The content of a `StreamedResponse` is not in `$this->client->getResponse()`
  (its `getContent()` answers `false`): read
  `$this->client->getInternalResponse()->getContent()`, which holds what the
  callback wrote. The module flushes its event stream only outside the `cli`
  SAPI for the same reason.
- To put a fake in place of a service of the module in a screen test
  (`static::getContainer()->set()`), the service has to be public: a private
  service with one consumer is inlined into it and the replacement reaches
  nobody. `ClaudeClientProviderInterface` is public for that. A service is
  replaced once per kernel: install a delegating fake in `setUp()` and change
  what it delegates to.
- The parser of Thelia sets the `app` global of Twig at the first render of
  the process, with the request of that moment. A screen test that renders a
  section through the writer before it asks for a screen leaves `app.request`
  null for the rest of the process: ask for any screen first
  (`SectionEditScreenTest::renderedOnce()`).

## Calling the Claude API

- The SDK (`anthropic-ai/sdk`) ships no PSR-17 factory: the module requires
  `nyholm/psr7`, and so must a host that does not have one.
- A closure that reads a counter accumulated after its definition takes the
  variable by reference (`use (&$usage)`), never as an arrow function, which
  captures the value at definition.
- The grammar of structured outputs has limits the documentation does not
  spell out: a `const` needs a `type`, an enum holds strings only (a numeric
  PHP array key becomes an integer), at most 24 optional properties and 16
  union types, and a total size. With a catalogue of twenty components, only
  the envelope of the document fits (`DocumentSchema::strict()`); the props
  are validated server side and sent back for correction.

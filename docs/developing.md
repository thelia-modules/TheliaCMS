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

## Changing the schema

`TheliaCMS::update()` plays the files of `Config/update/` whose version is
strictly above the installed one, up to the new one. A change appended to the
file of a version some site already runs never reaches that site: the column
of the writing instructions, first added to `2.2.0.sql`, had to move to
`2.2.1.sql`. Every schema change therefore goes in a new
`Config/update/<version>.sql`, with the same version in `Config/module.xml`,
and in `Config/TheliaMain.sql` for a fresh install. Write the file to be
replayable (an `information_schema` check before an `ALTER`): a fresh install
plays it after `TheliaMain.sql`. `Tests/Integration/Module/PageTypeMigrationTest.php`
shows how to test an update, outside a transaction.

## Caches that outlive a change

- **Propel models** (`var/propel/<env>/model`) are built once and kept while
  `var/propel/<env>/hash` matches. After any change to `Config/schema.xml`,
  remove `var/propel/dev` and `var/propel/test` in the host; `cache:clear`
  alone rebuilds nothing.
- **The test container** (`var/cache/test`) is compiled by the bootstrap of
  the integration tests, not by `cache:clear`. After a change to the
  constructor of a service, a new route, a new controller or a new event
  listener, remove it; the first test recompiles. Without removing anything,
  `php Thelia cache:clear --env=test --no-debug` rebuilds the container the
  tests run; `--env=test` alone rebuilds only the debug one, and a new listener
  stays unknown to the tests. That container does not check the templates
  either: a screen test keeps rendering the compiled copy of a template changed
  since, so the same command is due after editing one. When the constructor of
  a console command changed, that command fails too: it boots the stale
  container, which cannot build the command. Move `var/cache/test` aside,
  then run it.
- **The prompt reads translated words.** The catalogue sheet sent to the
  generator gives the label of each component and prop through the
  translator (`Generation/CatalogSheet.php`): a new or changed key of `I18n/`
  that matches one of them changes the prompt in that language. Keep such a
  change out of a round of the determinism corpus, or measure it as one.
- **Compiled Twig** is invalidated on the mtime of the template. A template
  restored within the same second as it was changed keeps the compiled copy of
  the change: `touch` the template, then remove `var/cache/test`.
- **Rendered partials** are kept in the `cache.app` pool for as long as their
  `cacheTtl()` says (a day for a map). A theme template added or changed
  for a partial shows up when that fragment expires, or after
  `php Thelia cache:pool:clear cache.app`.
- **Module assets** served by `module_asset()` are copied once into
  `public/assets/backOffice/default-twig/TheliaCMS/`. After a change to a
  script or a stylesheet of `templates/backOffice/default-twig/assets/`, copy
  the file there again (or remove the published directory), and give the
  template an `assets_version` so the browser drops its copy too.

## Writing tests

- Under DDEV, the web container exports `DATABASE_NAME=db`, which wins over
  `.env.test` when the test caches are built: the integration tests then
  connect to the development database and their guard stops them. Build and
  run them with the variable unset (`env -u DATABASE_NAME vendor/bin/phpunit
  -c local/modules/TheliaCMS/phpunit.integration.xml.dist`), after removing
  `var/propel/test` and `var/cache/test` if they were built with it.

- A test that runs outside a transaction (`ALTER TABLE`, `TRUNCATE`) cleans in
  `tearDown()` what it inserted; a run interrupted halfway otherwise leaves
  rows behind for the next one.
- A test that uploads a file writes it where the site keeps its library
  (`local/library/images/`), the test database being separate but not the
  disk: remove what it left behind, or the directory shows files no row
  names.
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
- The answer is read as a stream (`messages->createStream()`, put back
  together by `Generation\StreamedMessage`). The stream opens a text block
  before a tool call and may leave it empty: such a block is dropped, or the
  API refuses the conversation on the next turn ("text content blocks must be
  non-empty"). An answer constrained to a schema is streamed in a burst at the
  end of the turn, measured on 2026-10-20: 4.3 s of silence, then every delta
  within a second.
- The grammar of structured outputs has limits the documentation does not
  spell out: a `const` needs a `type`, an enum holds strings only (a numeric
  PHP array key becomes an integer), at most 24 optional properties and 16
  union types, and a total size. With a catalogue of twenty components, only
  the envelope of the document fits (`DocumentSchema::strict()`); the props
  are validated server side and sent back for correction.

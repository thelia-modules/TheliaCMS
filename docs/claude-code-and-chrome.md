# Driving the CMS with Claude Code and Chrome

How a developer lets Claude Code work in the back office of a site the way an
editor does — write a page, add a section, translate it, make a component —
and check the result the way a visitor sees it. Claude Code drives a Chrome
window through the Chrome DevTools MCP server; the person stays the one who
logs in.

Everything below was met on a real site. It is a way of working, not a
feature of the module: nothing in the module depends on it.

## Setting up

### Chrome with a debugging port

Start a Chrome of its own, on a profile of its own, with the debugging port
open. Chrome refuses the port on the everyday profile.

```powershell
# Windows, PowerShell
& "C:\Program Files\Google\Chrome\Application\chrome.exe" --remote-debugging-port=9222 --user-data-dir="$env:TEMP\chrome-cms"
```

```bash
# Linux or macOS
google-chrome --remote-debugging-port=9222 --user-data-dir=/tmp/chrome-cms
```

Check it answers: `curl -s http://127.0.0.1:9222/json/version`.

While that window runs, any program of the machine can drive it. Close it
when the work is done, and never log into anything else in it.

### The MCP server

Declare a server that attaches to that Chrome rather than starting its own:

```bash
claude mcp add --scope user chrome-cms -- npx -y chrome-devtools-mcp@latest --browser-url=http://127.0.0.1:9222
```

Pin the version you tested instead of `latest` once it works. Restart the
Claude Code session: its tools appear as `mcp__chrome-cms__*`.

### Claude Code in WSL, Chrome in Windows

Chrome listens on `127.0.0.1` of Windows, which WSL in its default NAT mode
does not reach. Switch WSL to mirrored networking, then restart it
(`wsl --shutdown` stops every WSL program, DDEV included):

```ini
# %USERPROFILE%\.wslconfig
[wsl2]
networkingMode=mirrored
```

`curl http://127.0.0.1:9222/json/version` from WSL must answer before the
session is reopened.

### Logging in

The person logs into the back office in that window, by hand. Claude Code
never types a password; a session it opened itself would be one it was given
the credentials for. Everything it does then is done with the rights of that
account, so a profile limited to the CMS is the better choice.

## Working in the back office

Tell Claude Code which page or item, which language, and what to do; ask it
to say what it will write before it publishes. The screens it uses:

| What | Where |
|---|---|
| Content of a page | `/admin/cms/pages/{id}/content?edit_language_id={lang}` |
| Content of a catalogue item | `/admin/cms/catalogue/{product\|category\|content\|folder}/{id}/content?edit_language_id={lang}` |
| Take a published content offline | the **CMS content** tab of the edit screen of the item, **Take offline** |
| Components, a new one | **CMS > Components**, `/admin/cms/components/site/new` |
| Skills | **CMS > Skills** |

What works, and why:

- **Read the screen by its structure.** A text snapshot of the page (the
  accessibility tree) names every button and field; a screenshot is for
  judging a rendering, not for finding a button.
- **Add a section** from **+ Add a section** at the bottom of the draft,
  inside the preview frame: it opens a dialog listing the catalogue, the
  component code in each entry.
- **Free text** is edited in a rich text editor. To set exact markup, switch
  it to **Source HTML**, set the source, switch back, then save the section.
  The editor turns entities into characters (`&eacute;` becomes `é`): that is
  expected.
- **Every action reloads the screen.** Moving a section, saving one,
  publishing: wait for the message of the screen ("The section is saved in
  the draft") before the next action, never chain clicks blindly.
- **Removing a section asks for a confirmation** in a browser dialog, which
  the MCP server must accept.
- **A generator call takes 30 to 60 seconds.** Wait for the new row of the
  history of the page (date, outcome, cost), not for a text: the progress
  message of the screen stays in the page after the call.
- **Translating a page** is offered in the middle of the screen, not in the
  panel on the left, when the language on screen holds nothing and another
  does: **Write it in this language from the … version**.
- **An empty draft cannot be published.** To take a content off the site,
  use **Take offline** on the CMS content tab of the item.
- **A component that changes** is drawn again in drafts at once; a published
  page shows the new version at its next publication. Publish the pages that
  use it again.
- **A heading warning** at publication ("The heading structure jumps from
  level 1 to level 3") does not block it; it is worth fixing in the content.

## Checking as a visitor

- Open the public page in a **separate browser context** (an isolated
  context of the MCP server): it has no back-office session, so it shows what
  a visitor sees, cache and all.
- Check at **1440 px and at phone width**. Chrome on Windows does not shrink
  a window under about 500 px: measure, and say the width you measured.
- **Measure rather than eyeball.** Every section carries
  `data-cms-section="<id>"`: compare the left edge and the width of a section
  with its neighbours (`getBoundingClientRect()`), read the computed styles
  of the element in doubt, and check that the page does not scroll sideways.
- Compare the text of the page before and after a change (a hash of its text)
  to prove a change was undone.

## Habits that keep a site safe

- **Back up what you move.** Before moving the summary or the description of
  an item into its document, keep its current text (a copy of the fields,
  from the database or the screen), so it can be put back.
- **Test on content you can undo.** A test section on an item that is
  offline is invisible to visitors but stays published; take it offline once
  the test is done.
- **Count the cost.** Each generator call shows its cost in the history; a
  component made in the back office costs one call per version asked.
- **Leave the window closed** when no one is working in it.

## When not to use the browser

A page written again and again from the same brief, to compare the output of
two versions of a skill, is better run from the command line, without a
browser: `php Thelia thelia_cms:generate --page <id> --locale <locale>
--brief <file> --dry-run` prints the document and the cost (see
[evaluation/README.md](evaluation/README.md)).

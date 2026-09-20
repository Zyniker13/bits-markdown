# Impeccable.style

Status of [Impeccable](https://impeccable.style/) on Bristlecone Markdown. Visual craft sits **beside** product docs. It is **not** a redesign of wp-admin, Classic Editor, or published posts.

## Status

**Adopted for the block editor writing surface** (1.2.0).

- Cursor skill: [`.cursor/skills/impeccable/SKILL.md`](../.cursor/skills/impeccable/SKILL.md) (project install: `SKILL.md`, `reference/`, launcher scripts). Official installer: `npx impeccable install --providers=cursor --scope=project`. The 1.2.0 pass copied the upstream Cursor skill after the npm zip download failed (`invalid zip data`). Live-browser bundles and the font index are **not** vendored (Cloud Agents skip `/impeccable live`); restore them with `npx impeccable update --providers=cursor --scope=project --yes` when a human needs live mode.
- Hook: [`.cursor/hooks.json`](../.cursor/hooks.json) runs the design detector when the launcher binary is present.
- Product context: [`PRODUCT.md`](../PRODUCT.md)
- Visual system: [`DESIGN.md`](../DESIGN.md) plus [`.impeccable/design.json`](../.impeccable/design.json) — **locks the writing canvas** (inherit placeholder, mono source, borderless preview). Not FlexInvoice paper/ink/teal.
- Shared hook config: [`.impeccable/config.json`](../.impeccable/config.json). Ephemeral Impeccable output is gitignored.

## Complement, not replacement

Impeccable owns generic craft, slop checks, and polish/audit/critique. This plugin’s product docs own what Bristlecone Markdown *is*.

| Source | Owns |
| --- | --- |
| `readme.txt` / `docs/plugin-home.md` | Public product copy, syntax, Jetpack coexistence |
| `PRODUCT.md` | Impeccable-readable audience and constraints |
| `DESIGN.md` | Visual rules for the **block editor writing surface** |
| PHP (`includes/`) | Parser, storage, REST preview, Classic/document paths |
| `.cursor/skills/impeccable` | Craft commands (`typeset`, `quieter`, `distill`, `polish`, `audit`, `critique`, …) |

Do not let `DESIGN.md` invent Jetpack branding, FlexInvoice tokens, a bundler, or parser changes.

## Scope

**In:** `assets/js/block.js`, `assets/css/editor.css`, and the empty / selected / unselected states of `bristlecone/markdown` (including shared `markdownEdit` used by compatibility aliases).

**Out:** `assets/css/frontend.css` content styling, Classic Editor, comments, Tools converter, wp-admin settings screens, marketing pages.

## How Cloud Agents should use it

1. Load `PRODUCT.md` and `DESIGN.md` before touching the writing surface.
2. Visitor mode for this surface is **Operate**: the tool disappears into writing Markdown.
3. Prefer, in order: extract (document the canvas) → `typeset` → `quieter` → `distill` → `polish` (± `audit` / `critique`).
4. **Do not** run unconstrained `bolder`, trendy, or from-scratch direction unless a ticket says to replace the look.
5. Skip Impeccable on parser, storage, REST, Classic, comments, or Jetpack-adoption tickets.
6. `/impeccable live` needs a human picking elements in a local browser. Cloud Agents are not that.

Process templates (structure only, not tokens): `Zyniker13/FlexInvoice` `PRODUCT.md` / `DESIGN.md` / `docs/impeccable.md`; account note `Zyniker13/Documentation` `cursor-and-bots/08-impeccable-design-skills.md`.

## Install notes

- Skills must live under `.cursor/skills/impeccable` so Cloud Agents see them.
- `.distignore` and `.gitattributes` keep PRODUCT/DESIGN/Impeccable artifacts out of the WordPress.org zip.
- Re-run `/impeccable document` only when the **shipped** writing surface changed and `DESIGN.md` is stale. Do not use document as a chance to redesign.

## Upstream

- https://impeccable.style/
- https://github.com/pbakaus/impeccable
- Account note: [`Zyniker13/Documentation` `cursor-and-bots/08-impeccable-design-skills.md`](https://github.com/Zyniker13/Documentation/blob/main/cursor-and-bots/08-impeccable-design-skills.md)

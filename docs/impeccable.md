# Impeccable.style

Status of [Impeccable](https://impeccable.style/) on Bristlecone Markdown. Visual craft sits **beside** product docs. It is **not** a redesign of wp-admin, Classic Editor, or published posts.

## Status

**Process borrowed; full skill pack not installed here.**

Account policy (`Zyniker13/Documentation` `cursor-and-bots/08-impeccable-design-skills.md`): Impeccable is **not installed** pending Mahler go-ahead. The skill pack targets Next/Tailwind apps, not this WordPress PHP plugin.

The 1.2.0 writing-surface pass used Impeccable *as a craft process* (quieter chrome, inherit type, canvas not card). That is recorded in Bristlecone-owned notes only:

- Product context: [`PRODUCT.md`](../PRODUCT.md)
- Visual system: [`DESIGN.md`](../DESIGN.md) — **locks the writing canvas** (inherit placeholder, mono source, borderless preview). Not FlexInvoice paper/ink/teal.

Do **not** vendor `.cursor/skills/impeccable`, Cursor agents, `hooks.json`, launcher scripts, or `.impeccable/` (`config.json`, `design.json`) in this repo.

## Complement, not replacement

Impeccable’s generic craft vocabulary (quieter, typeset, distill, polish) informed the pass. This plugin’s product docs own what Bristlecone Markdown *is*.

| Source | Owns |
| --- | --- |
| `readme.txt` / `docs/plugin-home.md` | Public product copy, syntax, Jetpack coexistence |
| `PRODUCT.md` | Audience and constraints for writing-surface work |
| `DESIGN.md` | Visual rules for the **block editor writing surface** |
| PHP (`includes/`) | Parser, storage, REST preview, Classic/document paths |

Do not let `DESIGN.md` invent Jetpack branding, FlexInvoice tokens, a bundler, or parser changes.

## Scope

**In:** `assets/js/block.js`, `assets/css/editor.css`, and the empty / selected / unselected states of `bristlecone/markdown` (including shared `markdownEdit` used by compatibility aliases).

**Out:** `assets/css/frontend.css` content styling, Classic Editor, comments, Tools converter, wp-admin settings screens, marketing pages.

## How Cloud Agents should use it

1. Load `PRODUCT.md` and `DESIGN.md` before touching the writing surface.
2. This surface should disappear into writing Markdown.
3. Prefer quieter chrome, inherit type, and distill over adding UI. Do not run unconstrained bolder, trendy, or from-scratch direction unless a ticket says to replace the look.
4. Skip this process on parser, storage, REST, Classic, comments, or Jetpack-adoption tickets.

Process templates (structure only, not tokens): `Zyniker13/FlexInvoice` `PRODUCT.md` / `DESIGN.md` / `docs/impeccable.md`; account note `Zyniker13/Documentation` `cursor-and-bots/08-impeccable-design-skills.md`.

## Zip notes

`.distignore` and `.gitattributes` keep `PRODUCT.md`, `DESIGN.md`, and `docs/` out of the WordPress.org zip. These files are process notes for the GitHub repo, not plugin runtime.

## Upstream

- https://impeccable.style/
- https://github.com/pbakaus/impeccable
- Account note: [`Zyniker13/Documentation` `cursor-and-bots/08-impeccable-design-skills.md`](https://github.com/Zyniker13/Documentation/blob/main/cursor-and-bots/08-impeccable-design-skills.md)

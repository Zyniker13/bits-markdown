# Product

<!-- impeccable:product-schema 1 -->

<!--
  Bristlecone Markdown (WordPress plugin, repo Zyniker13/bits-markdown).
  Inferred from readme.txt, README.md, docs/plugin-home.md, and the 1.2.0
  block-editor writing-surface brief. Visual direction is not recorded here;
  see DESIGN.md.
-->

## Platform

web

## Users

Primary users are **writers and editors** who want to compose WordPress posts and pages in Markdown. They work in the block editor (Gutenberg), sometimes in Classic Editor, and sometimes from [iA Writer](https://ia.net/writer) over the REST API.

**Jetpack Markdown migrants** already have Markdown posts or `jetpack/markdown` blocks. They need those documents to stay editable after Jetpack Markdown is turned off, without a second conversion while Jetpack still owns the module.

**Site operators** (administrators) choose post types, comments, highlighting, math, an optional Markdown-first block editor, and opt-in Tools conversion. They are not a SaaS tenant; there is no account, plan, or phone-home.

## Product Purpose

Bristlecone Markdown exists so a WordPress site can write and keep Markdown **without Jetpack**, with syntax aligned to iA Writer (Markdown support only, not the rest of the iA Writer app). Success is: source stays editable, published HTML still displays if the plugin is deactivated, and the same server parser is used for preview and publish.

## Positioning

A self-contained replacement for **Jetpack’s Markdown features only**, not the rest of Jetpack and not every other Markdown plugin. Syntax follows CommonMark plus iA Writer extras (highlight, footnotes, tables, math, YAML, and related marks). Task lists and iA Writer Content Blocks are intentionally not converted.

## Operating Context

- **Markdown block** (`bristlecone/markdown`) in the block editor: source editing, server preview (`POST /bristlecone-markdown/v1/preview`), insert image, optional math-in-preview (KaTeX). Plain `wp.*` JavaScript in `assets/js/block.js` — **no JS build**.
- **Optional Markdown-first editor** (off by default): new posts of enabled types start with a Markdown block; Gutenberg’s default block prefers Markdown instead of a paragraph.
- **Whole-document Markdown** for Classic Editor, REST, and iA Writer Publish (HTML in `post_content`, source in `post_content_filtered`).
- **Comment Markdown**, enabled separately, sanitized more strictly than posts.
- Compatibility aliases for leftover Gutenberg markup (`jetpack/markdown`, `simple-markdown/markdown-block`, optional custom identifiers). Hidden from the inserter; convert on save. Bulk Tools converter is opt-in.
- Front-end CSS is `assets/css/frontend.css` under `.bristlecone-markdown`. Editor chrome is `assets/css/editor.css` under `.bristlecone-markdown-editor`.

## Capabilities and Constraints

Confirmed:

- Requires WordPress 6.4+ and PHP 8.4+.
- No bundler, webpack, or `@wordpress/scripts` for the block. Editor UI is an IIFE using `wp.element`, `wp.blockEditor`, `wp.components`.
- Preview HTML comes from the PHP parser, not a client Markdown library. The `html` attribute is persisted on the owned block as a deactivate fallback.
- KaTeX and highlight assets are bundled; loaded only when needed.
- No tracking, no remote conversion API, no paid tier.
- Named product: **Bristlecone Markdown**. Vendor: Bristlecone IT Services.
- Must not claim official affiliation with Automattic, Jetpack, iA, or iA Writer.
- Must not ship Jetpack logos or Jetpack purple as brand chrome.
- Must not import FlexInvoice (or other sibling-product) visual tokens: paper/ink/teal, Newsreader display, SaaS app chrome.

Undecided / out of scope for design tickets:

- Changing league/commonmark parsing, storage, Classic Markdown, comments, or Jetpack adoption logic.
- A JS build “for aesthetics.”
- Restyling published `.bristlecone-markdown` content except when a shared selector would otherwise leak editor chrome.
- Full Gutenberg RichText / block-transforms rewrite.
- Heading-permalink `#` presentation bugs unless a later patch ticket says so.

## Brand Commitments

- Name: **Bristlecone Markdown**.
- Voice: professional, precise, short. Prefer statements of behavior over slogans. Not a SaaS landing page.
- The block title in the inserter is **Markdown**. Inspector copy may say Bristlecone Markdown for orientation.
- Visual identity for the **block editor writing surface** is typography-first and minimal-chrome (see DESIGN.md). It is not Jetpack’s brand and not FlexInvoice’s ledger.

## Evidence on Hand

- Plugin header and constants: `bristlecone-markdown.php`
- Directory listing copy: `readme.txt`
- Homepage brief: `docs/plugin-home.md`
- Block editor: `assets/js/block.js`, `assets/css/editor.css`, `blocks/markdown/block.json`
- Front-end: `assets/css/frontend.css`
- No customer testimonials or press quotes. Do not fabricate them.

## Product Principles

1. **Source is the document.** Writers edit Markdown; HTML is derived on the server.
2. **Preview must match publish.** The block preview uses the same PHP parser as the front.
3. **The canvas, not the form.** In the block editor, the writing surface should feel like the post, not like a settings textarea.
4. **Stay native to WordPress.** Gutenberg toolbar, inspector, and theme/editor type. Do not invent a second admin skin.
5. **Do not invent product.** Syntax, storage, Jetpack coexistence, and iA Writer mapping stay in PHP and existing docs. Design work changes craft, not parser meaning.

## Accessibility & Inclusion

Ordinary web contrast and visible names for controls. Placeholder opacity must remain readable (~0.62 on the editor canvas). Source field has an accessible name (`aria-label`). Keyboard users must reach Source / Preview / Insert image through the block toolbar. Do not rely on color alone for the pressed tab. Placeholder copy may include literal Markdown `_` and `**` as teaching marks; those characters are not semantic HTML.

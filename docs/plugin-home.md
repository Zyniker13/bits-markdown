# Bristlecone Markdown — plugin homepage brief

Use this document to generate the public plugin page. It is the source of truth for copy, features, and standards claims. Do not invent features, partnerships, or compatibility that are not listed here.

## For the implementing agent

| Item | Value |
| --- | --- |
| **Canonical URL** | `https://bristleconeit.com/bristlecone-markdown` |
| **Page purpose** | Dedicated homepage for this WordPress plugin (WordPress.org **Plugin URI**) |
| **Author / company site** | `https://bristleconeit.com` (WordPress.org **Author URI** — must remain a different URL than the plugin page) |
| **Product name** | Bristlecone Markdown |
| **Slug** | `bristlecone-markdown` |
| **Vendor** | Bristlecone IT Services |
| **Version described** | 1.1.1 |
| **License** | GPL-2.0-or-later (GNU GPL v2 or later) |
| **Price** | Fully free. No paid tier, no phone-home, no account. |
| **WordPress.org listing** | In submission. Do not claim it is listed until it is. A “Download” control may say it will be available on WordPress.org, with GitHub as the current source. |
| **Source / issues** | `https://github.com/Zyniker13/bits-markdown` |

### Tone

Professional, precise, and short. Company site for Bristlecone IT Services — not a SaaS landing page. Prefer statements of behavior over slogans. Use “Bristlecone Markdown” on first reference in a section; “the plugin” is fine after that.

### Must not claim

- Official affiliation, partnership, or endorsement by Automattic, Jetpack, iA, or iA Writer.
- That the plugin *is* Jetpack, or that it replaces Jetpack as a whole. It replaces **Jetpack Markdown only**.
- That it replaces all Markdown plugins, or that a Tools scan auto-converts every block whose name contains “markdown”.
- That it implements the entire iA Writer *application* (library, Content Blocks, compile, typography, export). Alignment is **Markdown syntax**, not the app.
- Task-list conversion (`- [ ]` / `- [x]`). Those stay as text.
- File transclusion / iA Writer Content Blocks. WordPress cannot see the local library; authors must compile in iA Writer first.
- Tracking, analytics, or a remote conversion API. Parsing runs on the WordPress site. KaTeX and syntax highlighting are bundled.
- Compatibility below **WordPress 6.4** or **PHP 8.4**. iA Writer’s Publish command needs WordPress 5.6+ Application Passwords; the plugin itself requires 6.4+.
- “100% identical to the CommonMark dingus” for the *production* parser. Production enables extras and HTML restrictions (see Standards). The test suite includes the full CommonMark 0.31.2 spec against a core-only converter, plus a production ledger for expected deviations.
- The directory tag `jetpack` on WordPress.org.

### Suggested page structure

1. Title + one-sentence pitch
2. Requirements
3. What it does (writing surfaces)
4. How content is stored
5. Syntax (CommonMark + extras)
6. Standards it follows (dedicated section — required)
7. iA Writer Publish
8. Jetpack Markdown coexistence
9. Intentionally not converted
10. Settings
11. Privacy
12. License / source
13. Footer: Bristlecone IT Services, plugin URL, GitHub

Visuals are optional. If you generate screenshots, they must match real UI: Settings → Bristlecone Markdown; block editor Markdown block with source/preview; front-end of a Markdown post. Heading permalinks render as a `#` before the heading text (that is intentional).

---

## Page copy (adapt into HTML)

### Title

Bristlecone Markdown

### One-sentence pitch

Write WordPress posts, pages, comments, and iA Writer drafts in Markdown — without Jetpack — with syntax aligned to iA Writer.

### Lead

Bristlecone Markdown is a free WordPress plugin from [Bristlecone IT Services](https://bristleconeit.com). It converts Markdown to HTML on the server, keeps the Markdown source for later edits, and stores HTML so the site still displays if the plugin is deactivated.

It is a self-contained replacement for **Jetpack’s Markdown module only**, not the rest of Jetpack. Syntax is aligned with [iA Writer](https://ia.net/writer) Markdown (highlight, footnotes, YAML, math, and related extras). It does not reproduce iA Writer the application.

### Requirements

| Requirement | Version |
| --- | --- |
| WordPress | 6.4 or later (tested up to 7.1) |
| PHP | 8.4 or later |
| Block editor | Gutenberg Markdown block (`bristlecone/markdown`) |
| Classic Editor / REST / iA Writer | Whole-document Markdown for selected post types |
| iA Writer Publish | WordPress Application Passwords (WordPress 5.6+) |

---

## Functionality

### Writing surfaces

**Markdown block (block editor).** Insert a Markdown block, edit source, and preview with the same PHP parser used on publish (`POST /bristlecone-markdown/v1/preview`). Saved block markup includes HTML so the content still renders if the plugin is deactivated. An optional setting (off by default) starts new block-editor posts with that block and prefers it as Gutenberg’s default block instead of a paragraph.

**Whole-document Markdown.** Classic Editor, the REST API, and iA Writer’s Publish command send a Markdown body. The plugin converts on save for post types enabled in settings (posts and pages by default; other public types are a checklist, not auto-enabled).

**Comments.** Optional. Visitors may write Markdown in comments. Comment HTML is sanitized more strictly than posts.

**Mixed sites.** Gutenberg posts that already contain blocks should use the Markdown block. A post that is already a block document is not run through whole-document conversion (except extracting source from a lone Markdown block). Classic / REST / iA Writer documents are converted as a whole.

### Storage (Jetpack-compatible)

For document-mode posts:

- HTML in `post_content` (what themes display)
- Markdown source in `post_content_filtered`
- Flag meta `_bristlecone_markdown` (legacy `_bits_markdown` is still recognized)
- YAML front matter in `_bristlecone_markdown_front_matter` when present (legacy `_bits_markdown_front_matter`)

Existing Jetpack Markdown posts that use `_wpcom_is_markdown` (or `_wpcom_markdown`) and `post_content_filtered` are adopted automatically once Jetpack Markdown is off.

If you deactivate Bristlecone Markdown, published HTML remains. You cannot edit those posts as Markdown until the plugin is active again.

### Settings (Settings → Bristlecone Markdown)

- **Post types** — which types get whole-document Markdown (Classic, REST, iA Writer). The Markdown block is always available in the block editor.
- **Block editor** — optional, **off by default**. “Default to Markdown for new posts and pages”: new posts of the enabled types that use the block editor start with an empty Markdown block, and inserting a new block prefers Markdown instead of a paragraph. Existing content, the Classic Editor, and whole-document Markdown are unchanged. Safe to enable while Jetpack Markdown is still active (Bristlecone still skips conversion in that case).
- **Comments** — allow Markdown in comments.
- **Code highlighting** — server-side highlighting of fenced code (no extra JavaScript). Output uses `hljs` CSS classes. Themes may override `.hljs` or dequeue `bristlecone-markdown-highlight`.
- **Mathematics** — render `$inline$` and `$$block$$` with bundled KaTeX, enqueued only when a post contains math.
- **Jetpack Markdown blocks** — optional, **off by default**. Enables Tools → Bristlecone Markdown Converter so an administrator can rewrite existing `jetpack/markdown` blocks to `bristlecone/markdown` after a confirmation step. The same checkbox unlocks conversion of Simple Markdown / custom identifiers and a scanner for other unregistered blocks whose names contain “markdown”. This is not required for editing: with Jetpack Markdown inactive, those blocks already load in the editor and convert on save.
- **Other Markdown blocks (Advanced, collapsed)** — optional custom identifiers, one per line: `namespace/block-name|attribute` (example `acme/markdown|content`). If `|attribute` is omitted, the plugin tries `source`, then `content`, then `markdown`. `simple-markdown/markdown-block` is built in (`content`). This is leftover-block compatibility, not a replacement for those plugins.

### iA Writer Publish

In iA Writer, publish to WordPress over the REST API as Markdown. Bristlecone Markdown:

- Converts the body on save
- Returns Markdown source to non-Gutenberg clients on edit (so iA Writer round-trips source, not HTML)
- Maps YAML keys when present: `title`, `excerpt` (or `description`), `tags`, `categories`, `slug`
- If `title` is applied to an auto-draft and `slug` is omitted, the permalink is generated from the title
- Stores other YAML keys for `[%key]` interpolation in the document

Gutenberg’s editor is detected separately so the block editor still receives a Markdown block wrapper instead of a raw Markdown string.

### Jetpack Markdown coexistence

If Jetpack’s Markdown module is still active, Bristlecone Markdown **does not** convert posts or comments, so content is not processed twice. An admin notice offers a one-click control to disable that Jetpack module. After it is off, existing Jetpack Markdown documents (`_wpcom_is_markdown` or `_wpcom_markdown`, plus `post_content_filtered`) are adopted.

Gutenberg blocks named `jetpack/markdown` are a separate compatibility path. When Jetpack Markdown is inactive and that block type is not already registered, Bristlecone Markdown registers it as a hidden alias (`inserter` off) so posts do not show a missing-block warning. The editor uses the Bristlecone source/preview UI (attribute `source`). Saving rewrites the block to `bristlecone/markdown` (`markdown` ← `source`, HTML from the server parser). New blocks are still inserted as `bristlecone/markdown` only. This is compatibility, not impersonation of Jetpack.

A bulk converter under Tools can rewrite every matching post in enabled post types. It stays unavailable until Settings → Bristlecone Markdown → “Enable Jetpack Markdown block converter under Tools” is checked (default off), then requires a confirmation checkbox on the Tools page. Jetpack block conversion no-ops with a notice if Jetpack Markdown is still active. Posts are updated in batches.

The same Tools page can scan enabled post types for **unregistered** Gutenberg blocks whose names contain “markdown”. The scan only lists names, sample attribute keys, and counts. Conversion and optional “add to custom identifiers” happen only after the administrator selects names and confirms. Names that look like editor comments (for example `markdown-comment`) are flagged and left unchecked. A scan never registers aliases by itself.

`simple-markdown/markdown-block` uses the same alias / soft-migrate / bulk path as Jetpack when that plugin is not already registering the block (`content` → `markdown`). Custom identifiers from settings join that list. Aliases stay hidden from the inserter (`inserter: false`) and are not registered while another plugin already owns the block name.

If another Markdown plugin is active, an admin notice warns about double-processing. Bristlecone Markdown does not replace those plugins.

### Footnotes and archives

Footnote IDs are namespaced with the post ID (`bristlecone-markdown-fn-{id}-…`) so archive pages and WordPress’s core Footnotes block are less likely to clash. New posts are converted again after insert so those IDs are not left as a placeholder. `wpautop` is disabled for document-mode Markdown posts because it can scramble footnote markup. Footnote lists still render on archives for each post that contains them. Themes that restyle `sup` may need a small CSS tweak.

### Privacy and assets

- No tracking, no remote Markdown API, no “phone home.”
- KaTeX **0.16.22** (JS, CSS, fonts) is bundled.
- `highlight.php` runs on the server; GitHub-style CSS is bundled.
- Unsafe raw HTML tags (for example `script`, `iframe`, `form`) are disallowed by the parser; posts and comments also pass through WordPress KSES.

---

## Syntax

The production parser is **CommonMark** plus the extras below. One parser is used for the Markdown block preview, Classic/REST/iA Writer documents, and comments.

### CommonMark (core)

Headings, paragraphs, emphasis, strong, lists, links, images, block quotes, fenced and indented code, thematic breaks, HTML (restricted — see Standards).

### Also converted

| Feature | Syntax / notes |
| --- | --- |
| Strikethrough | `~~text~~` |
| Highlight | `==text==` |
| Tables | GFM-style pipe tables |
| Autolink | Bare URLs become links |
| Description lists | CommonMark description-list extension |
| Attributes | `{#id}` / classes on headings (explicit `{#id}` is preserved) |
| Footnotes | `[^1]` definitions and iA Writer inline footnotes `[^this is the note.]` |
| Heading permalinks | `#` permalink before ATX headings; optional `[Label]` on the heading |
| Cross-references | `[Heading][]` (and explicit ids) |
| Table of contents | Placeholder `{{TOC}}` |
| YAML front matter | Leading `---` / `---` block; `[%key]` interpolates scalar values |
| Math | `$inline$` and `$$block$$` (KaTeX; setting can disable) |
| Superscript | `^2` / `y^(a+b)^` |
| Subscript | `x~z` / closed `x~long~` |
| Page break | Line containing only `+++` (visual break, **not** WordPress `<!--more-->`) |
| Writer comments | `//` unpublished comments — stripped from HTML |
| Citations | MultiMarkdown-style `[p. 23][#CiteKey]` plus `[#CiteKey]:` bibliography lines |
| Safe inline HTML | Allowed tags only; dangerous tags escaped |

Fenced code may be highlighted on the server when the setting is on.

### Intentionally not converted

| iA Writer / Markdown feature | What happens |
| --- | --- |
| Task lists `- [ ]` / `- [x]` | Left as literal text (local writing aid) |
| Content Blocks / file transclusion | Not resolved. Compile or export in iA Writer so the published Markdown already includes those files |

Setext headings (`Heading` / `=======`) do not get the same auto cross-reference labels as ATX (`# Heading`) headings.

---

## Standards

State these on the page. Link the official documents. Do not imply certification.

### CommonMark 0.31.2

- **Spec:** [CommonMark 0.31.2](https://spec.commonmark.org/0.31.2/)
- **Implementation:** [league/commonmark](https://commonmark.thephpleague.com/) 2.10 (Composer constraint `^2.7`), CommonMark core extension as the single conversion engine.
- **Tests:** The plugin’s PHPUnit suite runs all **652** official CommonMark 0.31.2 examples against a core-only converter (`html_input: allow`). Production conversion enables extras and HTML restrictions; a small, documented set of spec examples is expected to differ (page break `+++` vs a thematic break, YAML `---` vs a thematic break, disallowed raw HTML, autolink, heading cross-reference rewriting).

CommonMark is the baseline. Extras are additive, not a different Markdown language.

### GitHub Flavored Markdown (selected)

Not full GFM. These GFM (or GFM-like) pieces are enabled via league/commonmark:

- Strikethrough
- Tables
- Autolink

**Not** implemented: GFM task-list items.

Reference: [GitHub Flavored Markdown spec](https://github.github.com/gfm/).

### iA Writer Markdown syntax

Target: **syntax parity with iA Writer’s Markdown**, not feature parity with the iA Writer app.

Documented extras we align with include highlight (`== ==`), footnotes (named and inline with spaces), heading links / `{#id}`, `{{TOC}}`, YAML front matter and content tokens, TeX delimiters `$` / `$$`, superscript/subscript, `+++` page breaks, `//` comments, and MultiMarkdown-style citations.

iA Writer’s own syntax notes: [iA Writer](https://ia.net/writer) (Markdown / syntax help in the app and on ia.net). This plugin is not an iA product.

### MultiMarkdown (citations only)

Bibliography/citation markers follow MultiMarkdown convention (`[locator][#Key]` / `[#Key]:`), not the full MultiMarkdown feature set.

### YAML

Front matter is parsed as YAML (Symfony YAML 8.1). Invalid YAML is left in the document rather than aborting the save. Mapped WordPress fields are listed under iA Writer Publish above.

### TeX / KaTeX

Math is rendered with [KaTeX](https://katex.org/) **0.16.22** (bundled CSS, JS, and fonts). Delimiters are Markdown `$` / `$$`, not WordPress shortcodes. Loaded only when math is present and the setting is on.

### Syntax highlighting

Fenced code highlighting uses [highlight.php](https://github.com/scrivo/highlight.php) (PHP port of highlight.js 9.x) on the server. No highlight.js runtime is added. CSS class names follow the `hljs` convention.

### HTML safety

- league/commonmark **DisallowedRawHtml** for a denylist of dangerous tags (`script`, `iframe`, `form`, and others).
- `allow_unsafe_links` is off (no `javascript:` URLs).
- WordPress **KSES** on post HTML (Markdown-oriented allowlist) and a stricter allowlist for comments.

This is defense in depth, not a claim of a formal security certification.

### WordPress platform

| Interface | Role |
| --- | --- |
| Plugin API | Bootstrap, settings, notices, uninstall |
| Block API (block.json v3) | `bristlecone/markdown` block, PHP `render_callback` |
| REST API | Document publish/edit for iA Writer; `bristlecone-markdown/v1/preview` for the editor |
| Application Passwords | iA Writer (and other REST clients) on WordPress 5.6+ |
| `post_content` / `post_content_filtered` | Same split Jetpack Markdown used |
| `wpautop` | Disabled for document-mode Markdown posts and Markdown comments so footnote and block HTML stay intact |

Requires WordPress **6.4+**, PHP **8.4+**. Tested up to WordPress **7.1**.

### Licensing of the plugin and bundled libraries

| Component | License (as bundled / depended) |
| --- | --- |
| Bristlecone Markdown | GPL-2.0-or-later |
| league/commonmark and related PHP packages | MIT (and other GPL-compatible OSI licenses as shipped by Composer) |
| KaTeX | MIT |
| highlight.php | BSD-3-Clause |

The distributed plugin is GPL-2.0-or-later as a WordPress plugin. Third-party notices ship with the code (`LICENSE`, vendor licenses, `assets/vendor/katex/LICENSE`).

---

## FAQ (for the page)

**Will posts break if I deactivate the plugin?**  
Published HTML stays in `post_content`. The site still displays. You cannot edit those posts as Markdown until Bristlecone Markdown is active again.

**Can I use the block editor and Classic Editor together?**  
Yes. Use the Markdown block in Gutenberg. Classic / REST / iA Writer posts are whole-document Markdown.

**Can Markdown be the default in the block editor?**  
Yes, optionally. Enable “Default to Markdown for new posts and pages” under Settings → Bristlecone Markdown (off by default). New posts of the enabled types that use the block editor then start with a Markdown block, and inserting a new block prefers Markdown instead of a paragraph. Existing content and the Classic Editor are unchanged.

**Does it replace Jetpack?**  
Only Jetpack Markdown. Leave Jetpack installed if you use other Jetpack modules. Turn off the Markdown module so Bristlecone Markdown can convert. Existing Markdown *blocks* from Jetpack remain editable and convert to Bristlecone Markdown on save; a bulk Tools converter is opt-in.

**Does it replace other Markdown plugins?**  
No. Simple Markdown and custom block identifiers are optional compatibility aliases for leftover Gutenberg markup. The Tools scanner never auto-converts or auto-registers names from a regex.

**Does it phone home?**  
No.

**Where do I get it?**  
WordPress.org (once listed) and [GitHub](https://github.com/Zyniker13/bits-markdown).

---

## Links to put on the page

- Plugin homepage (this page): https://bristleconeit.com/bristlecone-markdown
- Company: https://bristleconeit.com
- GitHub: https://github.com/Zyniker13/bits-markdown
- CommonMark 0.31.2: https://spec.commonmark.org/0.31.2/
- league/commonmark: https://commonmark.thephpleague.com/
- iA Writer: https://ia.net/writer
- KaTeX: https://katex.org/
- WordPress plugin handbook (context only, not a “certified” badge): https://developer.wordpress.org/plugins/
- GPL-2.0: https://www.gnu.org/licenses/gpl-2.0.html

---

## Meta (for `<title>` / description)

- **Title:** Bristlecone Markdown — WordPress Markdown without Jetpack | Bristlecone IT Services
- **Meta description:** Free WordPress plugin for Markdown in the block editor, Classic Editor, comments, and iA Writer. CommonMark 0.31.2 plus iA Writer-aligned extras. GPL-2.0-or-later.
- **H1:** Bristlecone Markdown
- **Canonical:** https://bristleconeit.com/bristlecone-markdown

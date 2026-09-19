=== Bristlecone Markdown ===
Contributors: bristleconeit
Tags: markdown, editor, writing, comments, gutenberg
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Write in Markdown without Jetpack. Block editor, Classic Editor, comments, and iA Writer publishing, with iA Writer-aligned syntax.

== Description ==

Bristlecone Markdown lets you write WordPress content in Markdown and keep the source for later edits. It is a self-contained replacement for Jetpack’s Markdown features, and its syntax is aligned with [iA Writer](https://ia.net/writer) (Markdown support only, not the rest of the iA Writer app).

The plugin is developed by [Bristlecone IT Services](https://bristleconeit.com). Plugin homepage: [bristleconeit.com/bristlecone-markdown](https://bristleconeit.com/bristlecone-markdown). Source and issues are on [GitHub](https://github.com/Zyniker13/bits-markdown).

= Writing surfaces =

* **Markdown block** in the block editor, with source and preview tabs
* **Whole-document Markdown** for the Classic Editor, the REST API, and iA Writer’s Publish command
* **Comment Markdown**, enabled separately

HTML is stored in `post_content` (and in the block’s saved markup). The Markdown source is kept in `post_content_filtered` for document-mode posts, so the site still displays if you deactivate the plugin.

= Syntax =

CommonMark plus:

* Strikethrough `~~text~~`
* Highlight `==text==`
* Tables
* Footnotes `[^1]` and iA Writer inline footnotes `[^this is the note.]`
* Heading permalinks and cross-references (`[Heading][]`, optional `{#id}` / `[Label]` on headings)
* Table of contents placeholder `{{TOC}}`
* YAML front matter and `[%key]` interpolation
* `$inline$` and `$$block$$` math (KaTeX, loaded only when needed)
* Superscript `^2` / `y^(a+b)^` and subscript `x~z`
* Page break `+++`
* Unpublished `//` comments (stripped from output)
* MultiMarkdown-style citations `[p. 23][#CiteKey]`
* Safe inline HTML

Fenced code blocks can be highlighted on the server (no extra JavaScript). Theme developers can override `.hljs` or dequeue `bristlecone-markdown-highlight`.

= Jetpack Markdown =

If Jetpack Markdown is still active, Bristlecone Markdown **does not** convert posts or comments, so content is not processed twice. An admin notice offers a one-click control to disable the Jetpack Markdown module. Existing Jetpack Markdown posts (`_wpcom_markdown` and `post_content_filtered`) are adopted automatically afterward.

When that module is off, existing `jetpack/markdown` Gutenberg blocks stay editable (they are not treated as missing blocks). Saving a post rewrites them to `bristlecone/markdown`. The inserter still offers only the Bristlecone Markdown block. An optional Tools converter can rewrite every matching post at once; it is off by default and requires Settings → Bristlecone Markdown → “Enable Jetpack Markdown block converter under Tools”, then Tools → Bristlecone Markdown Converter, then a confirmation checkbox.

= iA Writer =

In iA Writer you can publish drafts to WordPress 5.6+ over the REST API as Markdown. Bristlecone Markdown converts that body on save and returns Markdown source to non-Gutenberg clients on edit.

YAML keys such as `title`, `excerpt`, `tags`, `categories`, and `slug` are mapped onto the WordPress post when present. If `title` is applied to an auto-draft and `slug` is omitted, the permalink is generated from the title. Other keys are stored and available for `[%key]` interpolation.

= Intentionally not converted =

* **Task lists** (`- [ ]`) — local writing aid; skip on publish
* **Content Blocks / file transclusion** — these depend on iA Writer’s local library. WordPress cannot see that library. Compile or export the transcluded document in iA Writer before publishing.

= Footnotes =

Footnote IDs are namespaced with the post ID (`bristlecone-markdown-fn-{id}-…`) so archives and the core Footnotes block are less likely to clash. New posts are converted again after insert so those IDs are not left as a placeholder. Footnote markup can still be sensitive to theme CSS and to `wpautop` on classic (non-block) content; we disable `wpautop` for document-mode Markdown posts. If a theme styles `sup` unusually, footnotes may need a CSS tweak. Footnote lists still render on archive views for each post that contains them.

== Installation ==

1. Upload the `bristlecone-markdown` folder to `/wp-content/plugins/`, or install the zip from Plugins → Add New.
2. Activate **Bristlecone Markdown**.
3. Open Settings → Bristlecone Markdown to choose post types, comments, code highlighting, and math.
4. If Jetpack is installed, disable Jetpack Markdown when prompted.
5. Optional: to rewrite every `jetpack/markdown` block in the database, enable the Tools converter in settings, then confirm the run under Tools → Bristlecone Markdown Converter.

== Frequently Asked Questions ==

= Will my posts break if I deactivate the plugin? =

Published HTML remains in `post_content`. Document-mode posts continue to display. You will not be able to edit them as Markdown until the plugin is active again.

= Can I use this with the block editor and Classic Editor together? =

Yes. Mixed Gutenberg posts should use the Markdown block. Classic / REST / iA Writer posts are converted as a whole document.

= What happens to existing Jetpack Markdown blocks? =

When Jetpack Markdown is off, existing `jetpack/markdown` blocks open in the editor (no missing-block warning) and are rewritten to Bristlecone Markdown when you save. The inserter still only offers the Bristlecone Markdown block. A bulk converter under Tools is optional and off by default: enable it in settings, then confirm the run on the Tools page.

= Does this phone home? =

No. KaTeX and the highlighter are bundled. There is no tracking and no remote conversion API.

== Screenshots ==

1. Settings screen for Bristlecone Markdown.
2. Markdown block in the editor with source and preview.
3. A published Markdown post on the front end.

== Changelog ==

= 1.0.1 =
* Adopt existing `jetpack/markdown` Gutenberg blocks when Jetpack Markdown is inactive (hidden from the inserter; saved as `bristlecone/markdown`).
* Optional Tools converter (off by default) to rewrite those blocks site-wide after a settings checkbox and a confirmation step.

= 1.0.0 =
* Initial public release.
* Markdown block in the block editor, with source and preview tabs that use the same PHP parser as publish.
* Whole-document Markdown for the Classic Editor, the REST API, and iA Writer’s Publish command.
* Optional Markdown in comments.
* iA Writer-aligned extras: highlight, footnotes, heading permalinks and cross-references, table of contents, YAML front matter, math (KaTeX), super/subscript, page breaks, unpublished comments, and citations.
* Server-side highlighting for fenced code blocks (no extra JavaScript).
* Jetpack Markdown coexistence: skip conversion while that module is active, then adopt existing Markdown posts.

== Upgrade Notice ==

= 1.0.1 =
Existing Jetpack Markdown blocks are editable without Jetpack. Saving converts them to Bristlecone Markdown. Bulk rewrite is opt-in under Settings and Tools.

= 1.0.0 =
Initial public release of Bristlecone Markdown.

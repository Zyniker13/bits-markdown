# BITS Markdown

WordPress plugin by [Bristlecone IT Services](https://bristleconeit.com). Write in Markdown without Jetpack, with syntax aligned to iA Writer.

Plugin homepage: [https://bristleconeit.com/bits-markdown](https://bristleconeit.com/bits-markdown).

## Requirements

- WordPress 6.4+
- PHP 8.1+

## Development

```bash
composer install
composer test
```

The Gutenberg block is plain `wp.*` JavaScript (no build step).

## WordPress.org

This directory is the plugin root. `readme.txt` is the file WordPress.org uses. The directory contributor is `bristleconeit` (Bristlecone IT Services). Plugin URI is `https://bristleconeit.com/bits-markdown`; Author URI is `https://bristleconeit.com`.

Do not include Jetpack in directory tags.

WordPress.org assets (icon, banner, screenshots) belong in the plugin’s SVN `/assets` directory after approval, not in this zip.

The public plugin page is `https://bristleconeit.com/bits-markdown`. Copy and standards notes for that page live in `docs/plugin-home.md` (not shipped in the WordPress.org zip).

## Storage

Document-mode posts (Classic Editor, REST API, iA Writer) store HTML in `post_content` and Markdown in `post_content_filtered`, with `_bits_markdown` (Jetpack used `_wpcom_markdown`, which this plugin still honors). The Markdown block keeps source in block attributes and saves HTML as a fallback if the plugin is deactivated.

## Footnotes

Footnote IDs are namespaced as `bits-fn-{postId}-…` so archive pages and WordPress’s core Footnotes block are less likely to collide. New posts are converted a second time after insert so those IDs use the real post ID rather than a placeholder.

`wpautop` is disabled for document-mode Markdown posts because it can scramble footnote markup. Themes that restyle `sup` may still need a small CSS tweak. Footnote lists still appear on archives for every excerpted/full post that contains them. Inline footnotes (`[^text with spaces.]`) work alongside named `[^1]` definitions. The core Footnotes block is a separate feature; IDs do not overlap, but a post could contain both.

## Content Blocks

iA Writer Content Blocks transclude files from a local library WordPress cannot access. Compile the document in iA Writer (or publish the already-expanded Markdown) rather than expecting WordPress to resolve those paths.

## Release zip

Build a production zip (single root folder `bits-markdown`, Composer `--no-dev`):

```bash
composer release
```

The zip is written to `dist/bits-markdown-1.0.0.zip`. Upload that file at [Add Your Plugin](https://wordpress.org/plugins/developers/add/). After approval, tag updates go through WordPress.org SVN; this GitHub repository stays the development tree.

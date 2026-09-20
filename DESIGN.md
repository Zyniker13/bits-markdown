---
name: Bristlecone Markdown
description: Typography-first Markdown canvas in the WordPress block editor — inherit the post, hide the form.
colors:
  transparent: "transparent"
  inherit: "inherit"
  placeholder: "inherit"
typography:
  placeholder:
    fontFamily: inherit
    fontSize: inherit
    fontWeight: inherit
    lineHeight: inherit
    letterSpacing: inherit
    opacity: 0.62
  source:
    fontFamily: "ui-monospace, SFMono-Regular, Menlo, Consolas, monospace"
    fontSize: "15px"
    fontWeight: 400
    lineHeight: 1.6
    letterSpacing: "normal"
  preview:
    fontFamily: inherit
    fontSize: inherit
    lineHeight: inherit
rounded:
  none: "0"
spacing:
  source-min-height: "1.8em"
  notice-top: "12px"
  writing-padding: "0"
components:
  placeholder:
    backgroundColor: "{colors.transparent}"
    textColor: "{colors.placeholder}"
    opacity: 0.62
    padding: "0"
  source:
    backgroundColor: "{colors.transparent}"
    textColor: inherit
    rounded: "{rounded.none}"
    padding: "0"
    typography: "{typography.source}"
  preview:
    backgroundColor: "{colors.transparent}"
    textColor: inherit
    rounded: "{rounded.none}"
    padding: "0"
---

# Design System: Bristlecone Markdown

## Overview

**Creative North Star: "The Writing Canvas"**

The Markdown block should disappear into the post. Empty, it invites a sentence under the title. Selected, it is a borderless monospace well. Unselected with content, it reads as published prose. Chrome (Source / Preview / Insert image) lives in Gutenberg’s block toolbar, not as a card around the text.

This file locks that **block editor writing surface**. Product meaning (Jetpack replacement, iA Writer syntax, no-build JS, storage) stays in `PRODUCT.md`, `readme.txt`, and `docs/plugin-home.md`. Published output is themed by the site plus `assets/css/frontend.css`; this system does not restyle the front end.

**Key Characteristics:**

- Typography and whitespace carry the UI; field chrome is almost invisible until the block is selected
- Empty unselected copy inherits the editor/theme face (serif when the theme is serif)
- Source is the only forced mono surface
- Preview is canvas content, not an admin panel
- Gutenberg selection outline is the focus treatment; no second box

## Colors

There is **no plugin brand palette** on this surface. Text, links, and heading color inherit the editor. Surfaces are transparent.

Do not introduce Jetpack purple, FlexInvoice teal, Inter-on-gradient SaaS blues, or a forced white card (`#fff`) behind preview.

Error copy uses Gutenberg `Notice` (warning status). Do not wrap the whole block in an extra bordered shell when preview fails.

Placeholder and empty-preview copy use **opacity 0.62** on inherited color (Gutenberg RichText placeholder convention). That is the only “muted” treatment.

## Typography

Three roles, three faces:

| Role | Face | Where |
| --- | --- | --- |
| **Placeholder** | Inherit editor/theme (size, family, weight, leading) | Empty + unselected `<p class="bristlecone-markdown-placeholder">` |
| **Source** | `ui-monospace, SFMono-Regular, Menlo, Consolas, monospace` at 15px / 1.6 | Selected Source `PlainText` |
| **Preview / front-end** | Theme + `.bristlecone-markdown` in `frontend.css` | Preview tab and published posts |

**The Inherit Placeholder Rule.** Do not hardcode Georgia, Newsreader, or a plugin serif. Mahler’s title-serif theme should look like Jetpack’s empty state *because the theme serif shows through*, not because we shipped a display face.

**The Source Mono Rule.** Monospace is for Markdown source fidelity, not a “technical” costume on preview or placeholder.

**The No Second Brand Rule.** Do not load Newsreader, Source Sans 3, Geist, or Inter as plugin fonts. Do not copy FlexInvoice paper/ink/teal.

Placeholder string (i18n, textdomain `bristlecone-markdown`):

`Write your _Markdown_ **here**…`

Underscores and asterisks are literal teaching marks, not emphasis markup.

Selected empty Source is a blank field (no textarea `placeholder` attribute), matching the Jetpack focused-empty pattern.

Selected empty Preview may show `Nothing to preview yet.` at the same 0.62 opacity.

## Layout

The writing surface is the block itself. Padding on source and preview is **0**. Min-height on source is **1.8em** so a caret has a line; the field grows with content (PlainText autosize). Do **not** restore the old 280px boxed well or a native resize handle.

Unselected empty: one line of placeholder; no min-height box.

Unselected with content: preview only, height of the rendered HTML.

Selected: Gutenberg provides the block outline and toolbar. Do not add an inner border, drop shadow, or card radius.

## Elevation & Depth

**Flat.** No box-shadow, no 1px `#dcdcde` preview frame, no white fill that fights dark themes. Depth is WordPress’s selected-block outline.

**The No Card Rule.** If a surface needs emphasis, use type (source vs preview vs placeholder) — not a border.

## Shapes

No radii on the writing surface. Toolbar buttons keep Gutenberg’s native shape.

## Components

### Placeholder

A `<p>` when the block is unselected and the source is blank. `pointer-events: none` so clicks select the block. Opacity 0.62. Inherit type.

### Source (`PlainText`)

`wp.blockEditor.PlainText` with class `bristlecone-markdown-source`. Accessible name: “Markdown”. Transparent background, no border, no box-shadow, `resize: none`, transparent focus (`border-color: transparent; box-shadow: none`). Not `TextareaControl`.

### Preview

`.bristlecone-markdown-preview` plus `.bristlecone-markdown` so content rules (tables, mark, TOC, footnotes) match the front. Editor chrome (padding/border/min-height) stays off this element. Server HTML via REST; KaTeX `renderMath` when this surface is shown.

When the block is **unselected and has content**, show preview even if the last toolbar tab was Source (Jetpack pattern). Remember the last tab for the next time the block is selected.

### Toolbar

Gutenberg `BlockControls`: Markdown (source), Preview, Insert image. Keep Dashicon buttons with `isPressed`. Do not add Jetpack-only controls or a custom tab strip outside the block toolbar.

### Inspector

Stock `PanelBody`. Orientation copy, not a visual skin. Compatibility aliases keep their existing notice.

### Notice

Preview REST failure: Gutenberg `Notice`, warning, not dismissible. Margin above the notice only — do not re-box the writing surface.

## Do's and Don'ts

### Do:

- **Do** let the post title and theme type set the empty-state voice.
- **Do** keep Source / Preview / Insert image and server preview.
- **Do** persist `html` on the owned block; keep alias/compat edit paths.
- **Do** confine editor chrome to `assets/css/editor.css` and `.bristlecone-markdown-editor`.
- **Do** prefer quieter, distill, typeset, polish. Skip unconstrained bolder.

### Don't:

- **Don't** ship Jetpack logos, purple, or the Jetpack block name as branding.
- **Don't** import FlexInvoice paper / ink / teal / Newsreader, or Inter-gradient SaaS chrome.
- **Don't** put Component Library textarea chrome (label well, border, resize grip) on the writing surface.
- **Don't** change published `.bristlecone-markdown` look for this aesthetic pass.
- **Don't** introduce a JS bundler to achieve these styles.
- **Don't** restyle wp-admin outside this block.

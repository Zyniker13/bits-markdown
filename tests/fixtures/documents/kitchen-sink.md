---
title: Spec kitchen sink
slug: spec-kitchen-sink
excerpt: Exhaustive Markdown sample for BITS Markdown.
tags:
  - spec
  - markdown
categories:
  - Spec
Me: Bob Loblaw
---

Sincerely, [%me]

{{TOC}}

# Heading one

## Heading two [Sink Label]

### Heading three {#explicit-sink-three}

#### Heading four

##### Heading five

###### Heading six

Setext heading
==============

Paragraph with *em*, _also em_, **strong**, ~~deleted~~, ==highlighted==, and `inline code`.
A [link](https://bristleconeit.com) and an image ![alt text](https://example.com/x.png "title").
Autolink https://example.com/autolink and email-style text not a protocol.

Named footnote[^1] plus an inline one[^this is an inline note.] and a square of 100m^2 plus x~i~ and y^(a+b)^.

Inline math $a_b$ and currency $12.00$ stay distinct.

$$
E = mc^2
$$

[^1]: Kitchen named footnote.


| Left | Center | Right |
| :--- | :----: | ----: |
| a    |   b    |     c |
| d    |   e    |     f |

```php
echo "kitchen";
```

```
unlabeled fence with **bold**, [^1], {{TOC}}, // comment, +++ and [p. 1][#Doe:2006]
```

> A quoted paragraph with **strong**.
>
> > Nested quote.

- Tight item
- Nested:
  1. Ordered
  2. Still ordered
- `- [ ]` lookalike as plain text: task syntax below is intentional.

- [ ] not a task
- [x] also not a task

Term
: Description list definition

See [Heading two][] and [Sink Label][] and [Heading one][].

// this writer comment must not appear in HTML

+++

After the page break.

A claim[p. 23][#Doe:2006] with a second cite[#Doe:2006].

[#Doe:2006]: John Doe. Some Big Fancy Book. Vanity Press, 2006.

Safe HTML: <span class="ok">there</span>

Unsafe HTML: <script>alert(1)</script>

iA Writer Content Block left literal:

/includes/chapter.md

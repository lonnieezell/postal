# Postal

A CodeIgniter 4 mailer package (`Myth\Postal`) providing Laravel-style mail sending: `Mailable` classes, named `Mailer`s/transports, and a dev preview UI.

## Language

**Mailable**:
A class describing one email — its recipients, subject, and body — built lazily in `build()` and sent via `service('mailer')`.

**Markdown Mailable**:
A `Mailable` whose body is authored as CommonMark markdown via the `markdown()` helper rather than raw HTML/text strings. Rendered through the Layout and Mail Component pipeline into HTML (and a derived plain-text fallback) before being set on the underlying `Email`.

**`markdown()` helper**:
A global helper function, sibling to CodeIgniter's `view()`, that resolves a markdown view file plus data into a markdown string (`Stringable`).
_Avoid_: describing it as "just a view() wrapper" — it's a distinct authoring primitive with its own resolution pipeline (Layout, Mail Components, text-fallback generation).

**Layout**:
A first-party, single-slot wrapping template that composed HTML (markdown + Mail Components, already converted) is inserted into. Applied *after* markdown-to-HTML conversion, not before — distinct from CodeIgniter's own `extend()`/`section()` view composition, which operates on raw view content before any conversion. Set per-`Mailable` via `Mailable::layout()`, falling back to a configurable global default.
_Avoid_: "template" (too broad — Layout specifically means the outer wrapper, not a Mailable's markdown view itself).

**Mail Component**:
A reusable, pre-styled UI element (e.g. button, panel) invoked from within markdown source via a `mail-`-prefixed tag (e.g. `<mail-button url="...">Confirm</mail-button>`), resolved through a CommonMark parser/renderer extension into a `view()` call. Ships with a Default Theme; overridable per host application via `ViewPublisher`.
_Avoid_: "custom element" or bare "component" — always qualify as Mail Component, to distinguish from CI4 view partials generally and from browser custom elements/web components.

**Slot**:
The reserved `$slot` variable a Mail Component's view receives, containing the tag's inner content — itself already parsed as markdown and nested Mail Components before the parent component's view renders.

**Default Theme**:
The built-in, inline-styled (not `<style>`-block-only) visual design applied to the shipped Mail Components and default Layout, chosen so CSS inlining has real inline styles to normalize rather than relying solely on it.

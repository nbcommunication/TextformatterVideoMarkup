# TextformatterVideoMarkup - Agent API Reference

Machine-oriented reference for the `TextformatterVideoMarkup` module. For
human-oriented setup/usage docs and narrative examples, see `README.md` in
this same directory.

This file documents the *actual code-level API surface* an agent needs to
generate correct template/config code against this module: hooked methods,
property names, config option keys, defaults, return/array shapes, and known
gotchas.

## Module identity

- Class: `TextformatterVideoMarkup` (file `TextformatterVideoMarkup.module`), extends `Textformatter`, implements `ConfigurableModule`
- Config class: `TextformatterVideoMarkupConfig` (file `TextformatterVideoMarkupConfig.php`, extends `ModuleConfig`)
- `autoload`: not set (standard Textformatter - runs only when applied to a field's Text Formatters)
- Requires: ProcessWire >= 3.0.148, PHP >= 7.4
- Constant `TextformatterVideoMarkup::noCookie` = `'noCookie'` - used internally to identify/exclude the `yt_noCookie` config key from being passed through as a literal YouTube query parameter (it's applied via a domain swap instead, see below).

## Module config properties (`getDefaults()`)

Set via Modules > Configure > TextformatterVideoMarkup, or read directly off
the module instance, e.g. `$modules->get('TextformatterVideoMarkup')->maxWidth`.

| Property | Type | Default | Notes |
| --- | --- | --- | --- |
| `markupTpl` | string | `''` (empty) | The render template. If empty, falls back to `{debug}` when `$config->debug` is true and the endpoint returned debug data, else `{html}`. See "Placeholders" below. |
| `maxWidth` | int | `1280` | Sent as `maxwidth` to the oEmbed endpoint request. |
| `maxHeight` | int | `720` | Sent as `maxheight` to the oEmbed endpoint request (YouTube only sends `format=json` in addition). |
| `emptyValue` | string | `''` | Rendered (via `___render()`) when no oEmbed data is returned, e.g. private/deleted video. Supports `{url}` and `{link}` placeholders (see below). |

Additionally, every YouTube (`yt_*`) and Vimeo (`vm_*`) option key listed
below is itself a config property, e.g. `$module->yt_autoplay`,
`$module->vm_color`. These are only added to the outgoing request query
string if non-empty (`''` is treated as "not set") and only if the
corresponding key isn't **already** present in the video URL's own query
string (per-URL query params always take precedence over module config).

### YouTube option keys (`yt_` prefix)

`noCookie` (special-cased, see below), `autoplay`, `cc_lang_pref`, `cc_load_policy`,
`color` (`red`/`white`), `controls`, `disablekb`, `fs`, `hl`, `iv_load_policy`
(`1`/`3`), `modestbranding`, `playsinline`, `rel` (`0`/`1`).

- `yt_noCookie`: if truthy, rewrites the embed HTML's domain from
  `youtube.com` to `youtube-nocookie.com` (via `applyYoutubeNoCookie()`).
  This is **not** sent as a `yt_` query parameter - it's filtered out of
  `applyOptions()`'s query-building loop by name (`substr($key, 3) === 'noCookie'`).
- `yt_cc_lang_pref` and `yt_hl` both default to the current user's language
  `name` if multi-language support is active and the value isn't already
  set - see "Language defaults" below.

### Vimeo option keys (`vm_` prefix)

`autopause`, `autoplay`, `byline`, `color` (hex string, `#` stripped
automatically if present), `dnt`, `fun`, `loop`, `muted`, `playsinline`,
`portrait`, `responsive`, `texttrack`, `title`, `transparent`.

- `vm_texttrack` defaults to the current user's language `name` if
  multi-language support is active and the value isn't already set.

Most boolean-style options above are stored/rendered as a 3-state select
(`''`/`0`/`1`) rather than a true checkbox - `''` means "not set, don't send
this parameter at all" (distinct from explicitly sending `0`).

## Hookable methods

### `formatValue(Page $page, Field $field, &$value)`

Standard Textformatter entry point, called by the field's output formatting.
Not typically called directly by application code. Sets `$this->page`,
`$this->field`, and `$this->html` (true if the field is a rich text/CKEditor
field with `contentType >= 1`) for the duration of the call, then scans
`$value` for YouTube and Vimeo URLs and replaces each match in place.

`$this->isHtml` can be set to `true` before calling `formatValue()` (e.g. via
a hook) to force HTML-mode parsing/output regardless of the field's own
`contentType` - it is reset to `false` at the end of every `formatValue()` call.

### `___render($tpl, $data, $line, $emptyValue = null)`

Hookable. Called once per matched URL, either with real oEmbed `$data`
(array) or `$data = false` (no data available, e.g. private/deleted video).

- If `$data` is not an array: renders `$emptyValue` (defaults to the module's
  `emptyValue` config value if `null` passed), substituting `{link}` (the
  requested URL as an `<a>` tag, or wrapped in `<p>` if `$this->html`) and
  `{url}` (the raw requested URL) placeholders.
- If `$data` is an array: substitutes every non-array key in `$data` as a
  `{key}` placeholder into `$tpl` and returns the result. Keys whose value is
  itself an array (there are none by default, but a hook could add one) are
  skipped/left as literal `{key}` text in the output.
- `$tpl` for the real-data case defaults to `$this->markupTpl`, falling back
  to `{debug}` (if `$config->debug` and debug data present) or `{html}`.

Hook signature/argument order matches the README's documented example
exactly - `$event->arguments(0)` = tpl, `(1)` = data, `(2)` = url, `(3)` =
emptyValue. `$event->object->page`, `->field`, `->html` are readable from
the hook for per-page/per-field customisation.

## oEmbed `$data` array shape (as passed into `render()`)

Standard oEmbed fields returned by the provider (not all guaranteed present
on every response - especially Vimeo's provider-specific extras):
`type`, `version`, `title`, `author_name`, `author_url`, `provider_name`,
`provider_url`, `thumbnail_url`, `thumbnail_width`, `thumbnail_height`,
`html`, `width`, `height`.

Additional keys added by this module before `render()` is called:

| Key | Description |
| --- | --- |
| `url` | The originally requested URL (pre-option-munging, as found in the source text). |
| `embedUrl` | The `src` attribute value extracted from the oEmbed `html`'s `<iframe>`. |
| `width` / `height` | Falls back to module's `maxWidth`/`maxHeight` config if the endpoint didn't return them. |
| `class` | `yt` or `vm` - useful for styling YouTube/Vimeo markup differently. |
| `video_id` | The provider's video ID, extracted from the last path segment of `embedUrl` if not already present in the response. |
| `params` | The extra query string (beyond the video ID/URL) from the original requested URL, if any. |
| `lite` | Pre-rendered `<lite-youtube>`/`<lite-vimeo>` facade markup string (see README "Facades"). Built from `video_id`, `params`, and a translated "Play: {title}" label. |
| `debug` | HTML table of all `$data` key/value pairs (via `MarkupAdminDataTable`), only populated if `$config->debug` is true; otherwise `''`. |

Data is cached **permanently** (`WireCache::expireNever`) keyed by
`md5($endpoint)` (the full oEmbed request URL including maxwidth/maxheight
query params) - i.e. changing `maxWidth`/`maxHeight` config produces a new
cache entry rather than invalidating the old one. Manual clearing only, via
the "Clear Cache" button on the module config screen (deletes all cache
entries for this module in one action, not selectively).

## URL matching

- YouTube: matches `youtube.com/watch?v=`, `youtube.com/v/`,
  `youtube.com/shorts/`, and `youtu.be/` URLs, with or without a `www.`
  prefix (`www.` is always optional/stripped for the `youtube.com` forms;
  `youtu.be` never has a `www.` form in practice).
- Vimeo: matches `vimeo.com/...` URLs.
- In HTML mode (`$this->html` true), a URL is only matched if it is the sole
  content of its own `<p>...</p>` paragraph - this mirrors the documented
  "make sure each URL is on its own paragraph" requirement in the README for
  CKEditor/rich text fields. In plain-text mode there's no such constraint.

## Known gotchas for agents generating code against this module

1. **Config-screen "Empty Value" and the documented empty-value `render()` hook pattern (README "Example 2") require module version 106 or higher.** In versions prior to 106, `replace()` returned a hardcoded `''` for the no-data case and never called `render()` at all, silently ignoring both the `emptyValue` config field and any hook logic targeting it. This is fixed as of v106. If auditing an older install, check `$modules->get('TextformatterVideoMarkup')->getModuleInfo()['version']`.
2. **Vimeo URLs with a `www.` prefix** (e.g. `https://www.vimeo.com/12345`) are only recognised as of v106 - earlier versions matched only bare `vimeo.com/...` URLs.
3. **`{params}`, `{video_id}`, `{title}` are always populated (falling back to `''`)** in the `lite` facade markup as of v106, even for a video URL with no query string or a response missing those fields. Earlier versions could emit literal `Undefined array key` warnings in this specific path.
4. Per-URL query parameters in the source text **always** override the module's global `yt_*`/`vm_*` config defaults for the same key - config only fills in gaps, it never overwrites an explicit per-video value.
5. `yt_noCookie` does not behave like the other `yt_*` options - it never appears in the outgoing query string; it switches the embed domain instead. Don't expect `hl`-style merge/override semantics for it.
6. Multi-language default values (`yt_cc_lang_pref`, `yt_hl`, `vm_texttrack`) are only applied when ProcessWire's core `LanguageSupport` module is installed **and** a per-video/global value for that key isn't already set. On a patched (v106+) copy, the site's *default* language is correctly excluded from this behaviour (its conventional `name`, e.g. `default`, is not a valid ISO language code); on unpatched (v105) copies, visitors viewing the default language may have an invalid value like `hl=default` sent to the provider.
7. `render()` here is a **different hookable method** to `PageimageSource`'s/core `Pageimage`'s `render()` - hook onto `TextformatterVideoMarkup::render`, not a `Pageimage` hook, when customising this module's output.
8. `formatValue()` runs on `Textformatter` fields generally (Text, Textarea/CKEditor, URL, etc.) - it does not require or check for an `images`/image-type field the way `TextformatterPageimageSource` does.

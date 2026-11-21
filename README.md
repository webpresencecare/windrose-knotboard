# Windrose Knotboard - Pattern Library

A pattern library on a site, part of the Windrose Suite: visitors filter and search the site's library patterns, see them in a style of their choice, copy one, or build a selection (header, footer, content) and copy or download it for their own site. A designer who installs Knotboard offers their own patterns, written in the editor or imported as pattern library packages.

Requires WordPress 7.2, PHP 8.4 and **Windrose Livery 0.7** (it draws the previews and holds the ready styles; `Requires Plugins: windrose-livery`).

## Content (`Content`)

- Post type `windrose_knotboard` (admin menu "Pattern Library", items "Pattern": All Patterns, Add Pattern; 0.1 said "Library patterns"): title, content, excerpt (the card's description), revisions; published = offered. Apart from core's `wp_block` (the site's own patterns), so a library of thousands never fills the Site Editor or the inserter.
- Taxonomies, filtered through query variables of the same names (no addresses of their own): `windrose_knotboard_kind` (header, footer, section, page; exactly one, `section` when none; labels by slug), `windrose_knotboard_category`, `windrose_knotboard_tag`, `windrose_knotboard_language` (slug = language code, `fr`, `pt-br`; right to left for the codes in `Content::RTL`). The site's own language is created on its first request (named with PHP's intl when present).
- Addresses: `/<base>/` the library (24 a page; `windrose_knotboard_search` searches without becoming a search page), `/<base>/<name>/` a pattern, `/<base>/selection/` the selection (`windrose_knotboard_view=selection`). Base `patterns` (translatable) or the field "Pattern library base" on Settings, Permalinks (option `windrose_knotboard_base`; core's form and flush).
- Rights: capabilities of their own (`edit_windrose_knotboard_patterns` and the post type's others, the taxonomies' `edit_windrose_knotboard_patterns`), granted by `user_has_cap` to people with `edit_theme_options`; on a network only while "Site administrators manage their site's pattern library" is on (Network Settings, `windrose_knotboard_network`); network administrators always.
- Once per site and version (`wp_loaded`): the four kinds, the site's language, a rewrite flush.

## Templates (`Templates`) and blocks (`Blocks`, `blocks/`, `src/view`, `src/blocks`)

Core's `register_block_template()`: `archive-windrose_knotboard` (heading, text, Pattern Filters with search and the four filters as dropdowns, the style picker, a Query Loop that inherits the page's query with a grid of cards, pagination, no results, the selection bar), `single-windrose_knotboard` (back link, title, terms, excerpt, style, actions, large preview, tags, bar), `windrose-knotboard-selection` (back link, heading, text, style, the selection; used by the selection address through `archive_template_hierarchy`). All editable in the Site Editor; a theme's own template of the same name wins. Block pattern `windrose-knotboard/library-grid` ("Pattern Library grid", a Query Loop of library patterns for any page; its filters lead to the library).

| Block | Output |
|---|---|
| `windrose/pattern-preview` | `size` thumb (in a card) or full (Wide and Phone); filled by the browser |
| `windrose/pattern-actions` | Copy pattern (core's `wp-element-button`, the site's button style), Add to selection, View code; the markup to copy in a JSON script (`Copy::for_visitors()`) |
| `windrose/pattern-filters` | One GET form around its inner Pattern Search and Pattern Filter blocks, one Filter button, the active choices as chips that remove themselves, Clear filters (nothing changes before the button) |
| `windrose/pattern-filter` | `taxonomy` kind, category, tag or language, **several values at once: OR inside a filter, AND between filters** (core's taxonomy query variables take lists and commas). `display`: dropdown (default: a native disclosure `details`/`summary` showing the name and the number chosen, opening a fieldset of checkboxes with each value's number of patterns, a Find field over 8 values, one menu open at a time, Escape and a click outside close it; works without JavaScript), links (each adds or removes its value) or cloud. Only with two values or more |
| `windrose/pattern-search` | A field of Pattern Filters (its name as placeholder, a hidden label), or a small form of its own keeping the filters |
| `windrose/pattern-style` | A button showing "Preview style:", the style and its six colors, opening a list of Livery's ready styles (`Catalog::ready_recipes()`), radio buttons with color dots; Escape and a click outside close it |
| `windrose/pattern-selection-bar` | Sticky; count, See selection (opens in a new tab, `target="_blank"`, said in its text), Clear (two clicks) |
| `windrose/pattern-selection` | Filled by the browser: template parts (only those the library offers, `config.parts`) and content, ↑ ↓ ×, Copy header, Copy footer, Copy content with where to paste each, Download (core's pattern file), the selection as one page |
| `windrose/pattern-back` | Link to the library |
| `windrose/pattern-notes` | "Translation not reviewed yet" (meta `_windrose_knotboard_unreviewed`) and, with `credits` (default on; off on cards), the photo credits of an imported pattern (meta `_windrose_knotboard_credits`) |

The browser part (`src/view`, plain DOM, `wp-i18n` and `wp-a11y` only): one Livery request (`layout=list`) for all the thumbnails of a page, split into one document per pattern (`preview.js`: the page's styles, the pattern's top elements with its `lang` and `dir`), shown in frames without scripts (`sandbox="allow-same-origin"`), drawn 1200 px wide and shrunk to the card (short patterns centred in at least 96 px, long ones cut at 340 px), measured from the patterns' own bottom edge. The selection (`selection.js`, tested with `node --test`): `{header, footer, content[]}` of `{id, kind, title, lang, dir}` in local storage `windrose-knotboard-selection-<site>`, checked against `/copy` on the selection page (patterns no longer offered leave it), other tabs follow through `storage` events. Style: `windrose-knotboard-style`.

## Previews (`Render`) and copies (`Copy`)

Livery 0.7's filters: `windrose_livery_pattern_items` (source `knotboard`, keys = pattern IDs: visitors get published library patterns only, Livery's administrators any they can read, never other post types) and `windrose_livery_visitor_previews` (where the site offers a pattern). Copies (`Copy::for_visitors()`): `parse_blocks()`, then Image, Cover and Media & Text **keep their picture** at its full address but lose the attachment ID, the `wp-image-N` (and Media & Text's size) class and any link to this site, as core saves them without an attachment; `serialize_blocks()`. On the visitor's site WordPress treats them as outside pictures: the publish panel's "External media" Upload, the Image toolbar's "Upload to Media Library" (7.2: `POST /wp/v2/media` with `url`, the server downloads it), or Replace. Cards say so on Copy pattern (`data-pictures`), the selection page under each part (`/copy` gives `pictures`). Checked in the block editor (valid blocks, Upload to Media Library gives the Image an attachment). Ipsum's names (since core r65224 its colours carry Twenty Twenty-Five's slugs, so on both themes copies keep their colours); theme maps in 0.3 (font sizes: Ipsum `2-x-large`, Twenty Twenty-Five `xx-large`).

## REST (`windrose-knotboard/v1`, public, read only)

| Route | Answer |
|---|---|
| `GET /copy?ids=1,2` | Offered library patterns among them, in that order (at most 60): `id`, `title`, `kind`, `lang`, `dir`, `content` (pictures detached), `pictures`; 404 when none |
| `GET /styles` | Livery's ready styles: `slug`, `title`, `description` (cached an hour) |
| `GET /styles/<slug>` | One ready style with its `recipe` |

## Import (`Package`, `Import`, `Importer`, `Cli`; 0.2)

A **pattern library package** is a zip of one pack (a topic) in one language, made by any build that writes this format:

```
knotboard.json          format "windrose-knotboard-package", format_version 1, pack, release,
                        language {code, name, dir}, terms {category: {key: names}, tag: {key: names}},
                        images {name: {file, sha256, type, alt, credit {title, author, author_url, source, licence}}},
                        patterns [{key, kind, title, description, categories, tags, reviewed, file, sha256, images}]
patterns/<key>.html     block markup; pictures as {{url:<name>}} and {{id:<name>}}
images/<name>.<ext>     WebP, JPEG, PNG or AVIF
```

Term names are a string or an object of names by language code. `Package::read()` checks everything in memory before anything is written: only these files (no other path, no `..`), sizes (zip 64 MB, pattern 512 KB, picture 5 MB, 600 files, 128 MB unpacked), the format version, keys, kinds, terms and pictures named by each pattern, checksums, picture types read from their bytes, placeholders only for the pattern's own pictures.

`Import::plan()` (the dry run, nothing written): per pattern **new**, **changed**, **unchanged**, **edited on this site** since the last import (it will be replaced; the earlier version stays in the revisions), or **refused**; and the published patterns of the same pack and language that the package no longer holds (**no longer in its pack**). `Import::run( $packs, 'published' | 'draft' )` needs that choice when there are any. A pattern is refused when the site's content filter would change it (`wp_kses_post()`, block attributes included, compared token by token: 7.2's KSES rewrites tags), when it has content outside blocks, or when two chosen packages hold it.

Saved per library pattern (key + language = identity): title, excerpt (description), content (pictures' placeholders replaced), post name `<key>-<lang>` for new ones, kind, categories and tags by key (a new term named in the site's language when the package has it, else the package's, English or the first; an existing term keeps its name), the language term (code, own name). Meta: `_windrose_knotboard_key`, `_pack`, `_source` (checksum of what was imported), `_saved` (checksum of the stored content, to see edits), `_unreviewed`, `_credits`, `_removed` (moved to drafts by an import; back in the pack, published again). Pictures: `media_handle_sideload()` once per checksum (`_windrose_knotboard_image` on the attachment), alternative text from the package, credit as the attachment's description.

Where: **Tools, Import, "Pattern library package"** (core's `register_importer()`; also Pattern Library, Import): upload one or more zips (kept as core keeps import files: a private attachment marked as an import, only the uploader's, deleted after the import or by core's `importer_scheduled_cleanup` a day later), the dry run table with the required choice for patterns no longer in their pack, Import, the results. **WP-CLI**: `wp windrose-knotboard import <file>... [--run] [--removed=published|draft] [--format=table|json|csv] --url=<library site>` (dry run without `--run`). Rights: Knotboard's capability, `upload_files` and publishing library patterns on the site.

## Development

`npm install`, `npm run build` (build/ is committed, and so is `blocks/blocks-manifest.php`, the blocks' metadata that core registers them from, written by `wp-scripts build-blocks-manifest`; a PHPUnit test fails when it is older than a block.json), `npx wp-scripts lint-js src`, `npx wp-scripts lint-style "src/**/*.scss"`, `npm run test:js`.

PHP checks: `php -l`, PHPCS with the WordPress coding standards (`phpcs.xml.dist`) and PHPStan level 8 (`phpstan.neon.dist`). PHPUnit runs in multisite mode on a WordPress test library installed by `bin/install-wp-tests.sh` (`phpunit.xml.dist`; single site: `phpunit-single.xml.dist`). It loads Windrose Livery when its repository sits next to this one; on GitHub the tests that need it are skipped. PHPStan knows Livery's two classes from `stubs/windrose-livery.php`, WP-CLI's from `stubs/wp-cli.php`.

Playwright `tests/e2e/library.spec.mjs` runs against a WordPress multisite with WP-CLI, Windrose Livery network active and Windrose Prow for Ipsum installed (environment variables `WINDROSE_E2E_HOST`, the network's host, default `localhost`; `WINDROSE_E2E_USER`, a network administrator, default `admin`; `WINDROSE_PASS`, that user's password). It works on a throwaway site with library patterns from `tests/e2e/fixtures` (a header, a hero, a concert list, a footer, an Arabic version of the opening hours, one with pictures), a draft and one of the site's own patterns, and packages it makes for the import screen; see the file's header for what it checks. Everything is deleted at the end.

**Version bump**: the `Version` header and `WINDROSE_KNOTBOARD_VERSION` in `windrose-knotboard.php`, `Stable tag` and the changelog in `readme.txt`, `version` in `package.json` (and `package-lock.json`).

`common/` is Windrose Common (the update library), copied from Windrose Helm with `windrose-helm/bin/sync-common.sh`; never edit it here.

## License

GPL-2.0-or-later.

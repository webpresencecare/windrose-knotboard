=== Windrose Knotboard - Pattern Library ===
Contributors: patricia70
Tags: patterns, block themes, site editor, pattern library, multisite
Requires at least: 7.2
Tested up to: 7.2
Requires PHP: 8.4
Stable tag: 0.2.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A pattern library on your site: visitors filter your patterns, see them in a style, copy one, or build a selection and copy it.

== Description ==

Windrose Knotboard publishes a library of block patterns that visitors take to their own sites: a single pattern with one click, or a whole page (a header, sections, a footer) put together in the library.

* **The Pattern Library** has a menu of its own (Pattern Library: All Patterns, Add Pattern, Import), apart from your site's own patterns: they never show in your Site Editor's patterns or in your inserter. Write them in the block editor like posts, with a short description (the excerpt), a kind (header, footer, section or page), categories, tags and a language. Published patterns are in the library; drafts are not.
* **The library** (`/patterns/`, or another address you choose under Settings, Permalinks, for example `/designs/`): a search field and buttons for kind, category, tag and language in one row, each opening checkboxes so visitors choose several values at once (restaurant or architect), then a Filter button; the active choices show as chips that remove themselves. Each filter can also show as links or a cloud, and only appears when there is a choice to make, and a card per pattern with a preview, its category, title and description, and three buttons: **Copy pattern** (straight to the clipboard, to paste into a page), **Add to selection** and **View code**. 24 patterns a page.
* **A pattern's page**: a large preview, Wide or Phone, and the same buttons.
* **Previews in a style**: visitors choose one of Windrose Livery's ready styles, shown with their colors; every preview follows. Patterns in a right-to-left language (Arabic, Persian, Urdu, Hebrew...) show right to left.
* **The selection**: a bar shows how many patterns a visitor chose and **See selection**, which opens in a new tab so the library stays where it was. There they reorder or remove patterns, see them together as one page, and copy the **header**, the **footer** and the **content** separately, each with where to paste it, or download each as a pattern file for the Site Editor (Patterns, Import pattern from JSON). The selection stays in the visitor's browser.
* **Copies keep their pictures**, detached from your Media Library: on their own site, visitors click Upload under External media in WordPress's publish panel (or Upload to Media Library on a picture, or Replace), and WordPress copies the picture into their Media Library. The library tells them when they copy.
* **Import packages**: Tools, Import, "Pattern library package" (or Pattern Library, Import), or `wp windrose-knotboard import`. A package is a zip of patterns of one pack in one language, with their pictures, kinds, categories, tags and texts. A dry run shows what is new, changed, unchanged or edited on your site since the last import; patterns no longer in their pack stay published or move to drafts, as you choose. Pictures go into your Media Library once, with their alternative text and credit. The format is in the plugin's README on GitHub.
* **Pattern Notes**: "Translation not reviewed yet" on patterns imported with an unreviewed translation, and photo credits.
* **Change it in the Site Editor**: the library, a pattern's page and the selection are templates made of blocks (Query Loop, Post Title, Post Excerpt, Post Terms, and Knotboard's blocks: Pattern Preview, Pattern Actions, Pattern Filter, Pattern Search, Pattern Preview Style, Pattern Selection Bar, Pattern Selection, Pattern Library Link, Pattern Notes). Move them, remove a filter, change the texts. The block pattern "Pattern Library grid" puts a grid of the library's patterns on any page.
* **On a network**: each site's administrators manage their site's library, unless network administrators turn that off (Network Settings, Pattern library).

Copies use the color, size and spacing names of the Ipsum theme, so they fit Ipsum and its child themes, such as Windrose Prow for Ipsum. Names for other themes come in a later version.

Knotboard needs Windrose Livery 0.7 or later (it draws the previews and holds the ready styles).

Windrose Knotboard is part of the Windrose Suite, a free set of plugins and themes for WordPress networks: https://www.webpresencecare.com/windrose/ . Each Windrose plugin and theme works on its own.

Source code, issues and build steps: https://github.com/webpresencecare/windrose-knotboard

Security: please report vulnerabilities privately through GitHub: https://github.com/webpresencecare/windrose-knotboard/security/advisories/new . Never in a public support thread.

== Privacy ==

Windrose Knotboard sets no cookies and contacts no other service.

A visitor's selection and chosen style are kept in their own browser (local storage, not cookies) and never stored on this website. To show patterns in a style, their list and the chosen style are sent to this website, which draws the previews with sample text and stores nothing; Windrose Livery limits how many previews one connection gets (a keyed hash of the IP address and a counter, kept for one hour). Copying and downloading happen in the visitor's browser. Copied patterns keep their pictures at this website's address until the visitor uploads them to their own site, so until then their site loads them from here. The site's privacy policy guide (Settings, Privacy) has a paragraph about it.

== External services ==

* The bundled update library contacts the GitHub API (api.github.com) to check for and download updates of Windrose plugins: sent are the repository name and a "Windrose" User-Agent. GitHub terms: https://docs.github.com/en/site-policy/github-terms/github-terms-of-service, privacy: https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement

== Frequently Asked Questions ==

= What happens to the pictures of a copied pattern? =

They stay at the library's address until the visitor uploads them. WordPress shows "External media" with an Upload button in the publish panel before a page is first published, and "Upload to Media Library" on a selected Image block; Replace works everywhere (also for Cover and Media & Text in a page already published, and in headers and footers). Offer only pictures you may share: the Windrose Knots packs use CC0 or public domain pictures.

= Do visitors see my site's content in the previews? =

No. Previews use sample text, a sample menu and sample posts, made for the preview and never saved.

= Can I call the library something else? =

Yes: its address under Settings, Permalinks (Pattern library base), and its title and texts in the Site Editor (Templates, Pattern library).

= Is this plugin written with AI? =

Yes. I write the Windrose plugins and themes with Claude Code, Anthropic's AI coding assistant: I design each feature, test it on real sites and review every release, and every commit written with Claude says so. Independent reviews are welcome on GitHub.

== Uninstall ==

Uninstalling removes Knotboard's settings and the network switch. The library patterns stay in the database, as posts of any plugin do; reinstalling Knotboard shows them again.

== Changelog ==

= 0.2.4 =
* Windrose Common 1.3.0: updates come from the public GitHub repositories, without a token (WordPress 7.2 RC no longer has the Secrets API, where the token was kept).

= 0.2.3 =
* The menu icon is WordPress's own "symbol" SVG icon instead of a Dashicon.
* Copy and Download: HTML is changed with WordPress's HTML API instead of regular expressions; same output for WordPress's own markup.
* Blocks are registered from one manifest (WordPress's block metadata collection).
* Code: all WordPress packages on their newest releases.

= 0.2.2 =
* Password-protected patterns are no longer offered: not on the library page and its grids, not in the pattern blocks, not in the selection's copy route.
* Import: choosing the same pack twice no longer lists its patterns as "no longer in their pack" (which could move the whole pack to drafts); a pattern whose key belongs to another pack on this site is refused instead of taken over.
* The selection's copy route reads the patterns in one query.
* Windrose Common 1.2.0.

= 0.2.1 =
* Ipsum's new color names (WordPress 7.2-beta4): the color dots of the ready styles read base, contrast, accent-1, accent-3, accent-5 and accent-6, in Ipsum's order.

= 0.2.0 =
* Clearer names in the admin: the menu is Pattern Library, its items are Patterns (All Patterns, Add Pattern, Edit Pattern).
* Import pattern library packages (zip files, one pack in one language each): Tools, Import, "Pattern library package", or Pattern Library, Import; also `wp windrose-knotboard import`. A dry run shows what is new, changed, unchanged or edited on the site; patterns no longer in their pack stay published or move to drafts, as you choose. Pictures go into the Media Library once, with their alternative text and credit.
* Copies keep their pictures (no longer empty placeholders), detached from the library's Media Library, with a note on uploading them: WordPress offers Upload for outside pictures.
* Pattern Notes block: "Translation not reviewed yet" and photo credits, on cards and a pattern's page.

= 0.1.0 =
* Library patterns with kinds, categories, tags and languages, apart from the site's own patterns.
* The library, a pattern's page and the selection, as templates of blocks: filters, search, previews in a style, Copy pattern, Add to selection, View code; the selection in a new tab with Copy header, Copy footer, Copy content and downloads.
* Settings, Permalinks: the library's address. Network Settings: whether site administrators manage their library.

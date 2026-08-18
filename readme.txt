=== Make Block Editor Links Relative ===
Contributors: derpixler
Tags: relative urls, links, block editor, migration, staging
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Stores and renders your site's own URLs as root-relative paths, so the domain is never baked into content.

== Description ==

WordPress saves absolute URLs (`https://example.com/…`) into `post_content`. In the
block editor, the site's domain is written into every link, image, button and
embed, and re-written on every save.

When you change domains or move content between staging and production, those
hard-coded URLs keep pointing at the old domain. This plugin prevents that.

It converts the site's **own** URLs to root-relative paths (`/path/` instead of
`https://example.com/path/`) in two places:

* **On save** — `content_save_pre` and `rest_pre_insert_post` strip the site's base
  URL from `post_content` before it is written to the database.
* **On render** — `the_content`, `the_excerpt` and widget content strip the site's
  base URL from the output, so existing content is also neutralized without a
  database migration.

External links are left untouched. RSS feeds, canonical URLs, Open Graph tags and
sitemaps are generated from the site URL and remain absolute.

The domain itself is defined by the environment (`WP_HOME` / `WP_SITEURL`), not by
the stored content.

== Installation ==

1. Upload the plugin directory to `/wp-content/plugins/`, or install it via
   Composer (`derpixler/make-block-editor-links-relative`).
2. Activate the plugin.
3. Done — no further configuration.

To keep the site domain-independent, leave the plugin active on every environment
(local, staging, production).

== Frequently Asked Questions ==

= Do I still need to run search-replace on old content? =

No. The render layer neutralizes old absolute URLs on the fly. A one-time
search-replace is still fine if you want the database cleaned, but it is not
required.

= Does it break SEO? =

No. Canonical URLs, Open Graph tags, sitemaps and RSS feeds are generated from
the environment URL and remain absolute.

= What about external links? =

They are never modified.

= Does it change the database on activation? =

No. It only changes what is written from that point on.

== Screenshots ==

1. The rendered page — internal links and images resolve root-relative, external links stay absolute.
2. The block editor with link-generating blocks (heading, paragraph, button, image, list).
3. The code editor shows the content stored domain-free.

== Changelog ==

= 1.0.0 =

* Initial release.

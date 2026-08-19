# Make Block Editor Links Relative

![Make Block Editor Links Relative](assets/hero.jpg)

**Your WordPress site is quietly poisoning its own database — and one day it will cost you a migration.**

Every time someone hits *Update* in the block editor, WordPress takes your domain name and hard-wires it into the content. Not once. Thousands of times. Every image, every link, every button, every embed — the domain is burned into `post_content` forever.

## The problem you don't know you have

WordPress doesn't store links. It stores **absolute URLs** — `https://yourdomain.com/…` — inside the database.

The block editor is the worst offender. It serializes every block with the full domain baked into the block attributes:

```html
<!-- wp:image {"url":"https://yourdomain.com/wp-content/uploads/2026/08/x.jpg","id":42} -->
```

That `yourdomain.com` never leaves. It looks harmless. It isn't.

---

## Why this will hurt you

- **You move domains, and your site breaks.** Every internal link still points at the old domain. Images 404. Redirects fire. SEO collapses — over a string of characters you never meant to keep.
- **Staging leaks into production** — or production leaks into staging. One wrong push and your live site serves `staging.yourdomain.com` links to real customers.
- **Every future migration needs a risky `search-replace`.** And a search-replace over serialized data is exactly where databases get corrupted.
- **You are welded to one domain.** Previewing from another host, on mobile, or behind a VPN breaks your content.

---

## Why WordPress does this — and why it won't stop on its own

This isn't a block editor bug. It's 20-year-old architecture.

WordPress was built around one assumption: **one site, one fixed URL**, stored in the database. Every core function — `home_url()`, `get_permalink()`, the media library — returns absolute URLs, because content has to work *outside* a browser too: RSS feeds, email notifications, the REST API, the mobile apps, oEmbed, sitemaps, canonical and Open Graph tags. In all of those, a `href="/page/"` is meaningless; only `https://domain/page/` resolves.

So, historically, "moving a site" always meant exactly one thing: a risky `search-replace` across the entire database. The core team has declined to change this for years — it's the price of the one-site model, and you were never the target audience.

The block editor just made it worse: it stores your domain **twice** — in the visible HTML *and* inside the JSON of every single block — and re-bakes it on every save.

**This plugin breaks the cycle for good.**

Once it's active, your domain stops being a *storage* problem and becomes a *runtime* one: stripped before anything is written to the database, stripped again before anything is rendered. Move domains whenever you want. The content will never care.

---

## The fix: root-relative URLs

This plugin makes your **own** URLs root-relative — `href="/path/"` instead of `href="https://yourdomain.com/path/"`.

A root-relative URL resolves against whatever domain is **currently serving the site** — never against the domain that happened to be live when someone clicked "Save".

Your domain now lives in exactly one place: your environment (`WP_HOME` / `WP_SITEURL`). Not in your content. Not in your database.

- ✅ Store root-relative
- ✅ Render root-relative
- ✅ External links stay untouched
- ✅ No search-replace, ever again

---

## How it works

Two layers, one guarantee:

1. **On save** — `content_save_pre` and `rest_pre_insert_post` strip your own domain from `post_content` *before* it reaches the database. What you save is what you can safely move.
2. **On render** — `the_content`, `the_excerpt` and widget content strip your own domain from the final HTML. Legacy content that already contains absolute URLs is neutralized on the way out — no database migration required.

Both plain URLs (`https://host/path`) and JSON-escaped URLs (`https:\/\/host\/path`, as used inside block attributes) are handled. External URLs are never touched.

```php
// Input (block markup)
'<!-- wp:image {"url":"https://yourdomain.com/wp-content/uploads/x.jpg","id":42} -->'

// Stored / rendered
'<!-- wp:image {"url":"\/wp-content\/uploads\/x.jpg","id":42} -->'
```

---

## Install

**Composer:**

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/derpixler/make-block-editor-links-relative.git" }
  ],
  "require": {
    "derpixler/make-block-editor-links-relative": "^1.0"
  }
}
```

The package is `type: wordpress-plugin`, so with `composer/installers` it installs
into `wp-content/plugins/{name}/`. Activate it once (wp-admin → Plugins, or
`wp plugin activate make-block-editor-links-relative`) — the filters run
immediately, no further configuration.

**Manual:** copy the plugin directory into `wp-content/plugins/` and activate it.

---

## Configuration

The site's own base URLs are derived automatically from WordPress
(`home_url()`, `site_url()`, `content_url()`) — i.e. from `WP_HOME` / `WP_SITEURL`.

### `mbelr_enable_save_normalization`

Controls the **save layer** only. When `true` (default), the site's base URL is
stripped from `post_content` *before* it is written to the database — the domain
never gets baked in. The **render layer** is always active and is not affected by
this filter.

Disable it to run in **render-only mode**: the database keeps absolute URLs, but
the frontend output is still normalized on the fly.

```php
add_filter( 'mbelr_enable_save_normalization', '__return_false' );
```

**Use case:** You run the plugin on a site where another tool reads `post_content`
directly and expects absolute URLs (for example an external import pipeline or a
legacy plugin). Keep the database untouched and only fix what visitors actually
see:

```php
// Keep absolute URLs in the DB, normalize the output only.
add_filter( 'mbelr_enable_save_normalization', '__return_false' );
```

With this filter removed, the save layer is enabled and the database itself stays
domain-free — that's the recommended mode.

---

## Testing

```bash
# Unit tests (27 cases, no WordPress required)
composer install
vendor/bin/phpunit

# End-to-end (Playwright + wp-env, requires Docker)
cd tests/e2e
npm install
npx playwright install chromium
npx playwright test        # auto-starts WordPress via wp-env (ports 8888/8889)
npm run report             # opens the HTML report with traces, video & screenshots
```

The end-to-end suite covers all three layers:

| Test | What it proves |
| --- | --- |
| `make-links-relative.spec.js` | Render layer: legacy absolute URLs are neutralized on output, DB untouched |
| `gutenberg-blocks.spec.js` | Save layer: a landing page of link-generating blocks (heading, paragraph, button, image, list) is stored domain-free via the real block-editor REST endpoint |
| `block-editor.spec.js` | Backend UI: a link typed in the block editor is saved domain-free |

---

## FAQ

**Does this break SEO?** No. Canonical URLs, Open Graph tags, sitemaps and RSS feeds are generated from the environment URL, not from `the_content`, so they remain absolute.

**What about external links?** They stay exactly as they are.

**Do I still need to run search-replace on old content?** No. The render layer neutralizes old absolute URLs on the fly. A one-time search-replace is still fine if you want a spotless database, but you don't *need* it.

**Does it touch the database on install?** No. It only changes what gets written going forward.

**Do I have to keep it active?** Yes — keep it activated on every environment (local, staging, production). That's what guarantees the domain stays out of the content.

---

## License

GPL-2.0-or-later. See [LICENSE](./LICENSE).

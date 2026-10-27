# Hooli

The pre-launch page for Hooli, a mobile app that helps women protect their hormonal health: it scans everyday
products and medicines, and answers daily questions with calm, evidence-based advice. The app doesn't exist yet:
the site measures interest with a waitlist, and answers the questions people search for in a few guides. It lives at
https://gethooli.app, in English, French and Estonian.

Laravel 13, Blade, Tailwind 4, no JavaScript framework. No cookies: there are no sessions, and the waitlist form is
guarded by a honeypot and a rate limit instead of a CSRF token.

## The pages

The home page, `resources/views/landing.blade.php`, and the guides (`resources/views/guides/`), all in the frame of
`layouts/site.blade.php`: what search engines read, the header with its language menu, the footer. The phone in the
hero (`partials/phone-scan.blade.php`) plays its scan once, when it comes into view; the animation lives in
`resources/css/app.css` under `.phone`. The waitlist's situations are listed in `config/hooli.php`.

## Languages

Every page exists once per language: `/` and `/guides` in English, `/fr` and `/fr/guides` in French, `/et` and
`/et/juhendid` in Estonian (`config/hooli.php`, `locales`). Each page names its translations for search engines
(hreflang), and `/sitemap.xml` lists them all. A visitor whose browser prefers another of the three languages is
offered it in a bar at the top; nobody is redirected.

The words of the pages are English keys in `__()`, translated in `lang/fr.json` and `lang/et.json`. A test renders
every page in French and Estonian and fails on any word left untranslated. Use the `localized_route()` helper for
links: it keeps the visitor in their language.

## The guides

Long reads written for search: endocrine disruptors, progestins and meningioma, cosmetic ingredients. Each is a
Markdown file per language, `resources/guides/{locale}/{key}.md`; its front matter gives the address (`slug`), the
titles, the description search engines show and the key points shown first. The list and its order are in
`config/hooli.php` (`guides`). To add one, write it in all three languages and add its key there. Every guide ends with
its sources: keep them, and update `updated` when the facts change.

## Working on it

```sh
composer install && npm install
php artisan migrate
npm run build        # or npm run dev
php artisan test
```

Herd serves it at http://hooli.test.

## The icon and logo

A big serif H, cut from Newsreader like the wordmark, cream on a sage tile. `scripts/pictures.py` draws everything
from the font file: the favicons and app icons, the share picture in each language, the inline icon the page uses and the logo files
in `public/images/logo/` (icon, H alone, icon with the wordmark on light and dark, 1024 px PNGs). After changing the
icon or the headline, run it again (needs Pillow and fontTools):

```sh
python3 scripts/pictures.py
```

## The waitlist

```sh
php artisan waitlist:stats             # how many joined, their situations and campaigns
php artisan waitlist:export            # storage/app/waitlist.csv, for the launch email
php artisan waitlist:forget a@b.com    # delete someone who asks
```

Add `?utm_source=instagram&utm_campaign=...` to links you share: the campaign is stored with each address.

The situation people may give is health data under the GDPR. It is optional, and the form says what it's for.

## Deploying

The site's address is https://gethooli.app: set `APP_URL` to it in production (`.env.example` does), so canonical
links, hreflang and the sitemap point there. Mail goes to hello@gethooli.app (`HOOLI_CONTACT` changes it). Run the
migrations and `npm run build`. On Apache hosting whose document root is the project folder, the root `.htaccess`
serves everything from `public/`. The `www` host redirects to the bare domain. Once live, add the site to Google
Search Console and submit https://gethooli.app/sitemap.xml.

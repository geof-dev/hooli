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

## The app's API

The iPhone app's backend, under `/api/v1` (`routes/api.php`). It knows products, never people: the health profile and
the scoring stay on the phone, and the app sends no health data. Every answer is JSON, there is no cookie or account,
and each address is rate-limited: 120 lookups a minute, 30 labels read on the phone and 20 photos an hour.

The AI is off unless `HOOLI_AI=true`, and the app works without it: it reads labels on the phone and sends their text,
and most of what it scans is already here, imported every week from the Open Facts databases for Hooli's countries
(`HOOLI_COUNTRIES`, ISO codes separated by commas, `ee` unless set). With the AI on, the server also reads photos with
Claude.

- `GET /api/v1/products/{barcode}?country=ee`: a product by its barcode (8 to 14 digits). One we don't have is looked
  up in Open Beauty Facts, then Open Food Facts, then Open Products Facts, and kept when they list its ingredients. 404
  `not_found` when nobody knows it. The `country` (two letters, optional) is the one the app is used in: the product
  is then known to be sold there.
- `POST /api/v1/products`: a label the app read on the phone, as JSON: `barcode`, `name` (up to 200 characters),
  `brand` (or null), `kind`, `category`, `ingredients` (1 to 300 names of up to 200 characters, in label order, or a
  medicine's active substances) and `country` (optional). It adds the product with `source` `user` (201), confirms the
  one we have when the list is the same whatever the case and punctuation, or replaces a shorter list from Open Facts
  or the web; a list read on a label before, or a longer one, stays as it is (200). Either way the answer is the
  `product`. 422 with Laravel's usual `message` and `errors` when a field is missing or wrong.
- `POST /api/v1/labels`, with the AI on (503 `ai_off` otherwise, and nothing is sent to Claude): a photo of any side of
  the product (`image`: JPEG, PNG or HEIC, up to 8 MB), with the `barcode` if there is one. Claude says what the photo
  shows, and the answer, `product`, `medicine` and `found_by`, says where the ingredients came from:
  - an ingredient list (`label`): Claude reads it, with the name. With a barcode, the read adds the product, confirms
    the one we have, or replaces a shorter list from Open Facts or the web.
  - a medicine box (`medicine`): Claude reads its active substances, strength and form, which come back as
    `medicine`. The product's `ingredients` are its active substances, and with a barcode it is kept, so the next scan
    finds it without a photo.
  - the front (`database`, `open_facts` or `web`): Claude recognises the brand and name, and the list is looked up. In
    our products first, by the barcode or the name and brand, whatever their case, the most confirmed first. Then in
    the Open Facts databases' search, which must agree on the brand and name; what they know is kept under its own
    barcode. Then on the web, where Claude looks for the list on the brand's own site or a major retailer's product
    page. The web comes last because every search is billed on top of Claude's tokens ($10 per 1,000 searches, three
    at most per photo). What it finds is kept under the barcode with `source` `web` and no confirmations, until a
    label photo with a fuller list replaces it.

  422 `needs_ingredients` when nobody knows the list of a front, with the `product` Claude recognised (`name`, `brand`,
  `kind`, `category`), so the app can ask for a photo of its ingredients. 422 `unreadable` when the photo shows nothing
  to read and no product to recognise.
- `GET /api/v1/products?category=food&exclude={barcode}&country=ee&types=en:breads,en:white-breads`: better swaps, up
  to 40 products of the category with their ingredients, the most confirmed first and, of food, the richest in fibre.
  With a `country`, only those sold there. With the `types` of the product scanned, from the most general to the most
  specific, they are of the most specific type that gives at least five (white breads), or else of a more general one
  (breads), or else of the category. The phone scores them.
- `GET /api/v1/updates`: changes to EU rules the app checks the user's products against, in English, Estonian and
  Russian. They are listed in `config/hooli.php` (`updates`), with the kinds and categories a product can have.

A product comes back as `barcode`, `name`, `brand`, `kind`, `category`, `ingredients` (in label order, or a medicine's
active substances), `image_url`, `source` (`hooli` for a photo Claude read, `user` for a label the app read, the Open
Facts database, or `web`), `source_url` (for a list from the web, the page it was taken from) and `confirmations`,
with `updated_at`. What Open Facts knows of it comes too, null or empty when nobody knows:

- `types`: their categories of it, from the most general to the most specific (`["en:breads", "en:white-breads"]`).
- `countries`: where it is sold or was scanned, by ISO codes in lower case (`["ee"]`).
- `ingredient_ids`: their ids of the ingredients (`en:wheat-flour`, `en:e300`), one for each of `ingredients`, in the
  same order, null for one they don't know. Null for a list read on a label.
- `additives`: their ids of its additives (`["en:e300"]`).
- `nutrition`: per 100 g or ml, `energy_kcal`, `carbohydrates`, `sugars`, `fibre`, `fat`, `saturated_fat`, `proteins`
  and `salt`, each a number or null.
- `processing`: its NOVA group, 1 (unprocessed) to 4 (ultra-processed).

From Open Facts, the `ingredients` are their reading of the label, in its language, with a compound ingredient's own
in brackets (`taimeõli (rapsiõli)`); a product they haven't read that way gives its list in English if they have it,
without ids.

Reading photos needs the AI on (`HOOLI_AI=true`), a Claude API key in `ANTHROPIC_API_KEY`, and PHP's Imagick
extension, with HEIC support for iPhone photos. Looking a front up on the web needs web search allowed for the
organisation in the Claude Console, as it is unless an admin turns it off; without it, a front Claude can't find
elsewhere gets `needs_ingredients`. Photos are never kept: each is read in memory, sent to Claude without its
metadata, and dropped with the request. The web search sends only the product's brand, name, kind, category and
barcode. The models are set in `app/LabelReader.php` and `app/WebIngredients.php`.

### The weekly import

```sh
php artisan products:import                                   # every database, for HOOLI_COUNTRIES
php artisan products:import --country=ee --country=lv --source=beauty
php artisan products:import --source=food --file=openfoodfacts-products.jsonl.gz   # an export already downloaded
```

Every product Open Food Facts, Open Beauty Facts and Open Products Facts say is sold in the countries, with a name
and an ingredient list, is added or updated (`app/ProductImport.php`). Each database exports all its products every
night (https://world.openfoodfacts.org/data), a line of JSON each, gzipped: the import downloads the file to
`storage/app/imports/`, reads it a line at a time, so memory stays flat, and deletes it. A product whose list was read
on a label (`hooli`, `user`) or found on the web keeps its name and list: only its types, nutrition, processing,
additives and countries are filled in, when we didn't know them. One from Open Facts is refreshed. The countries it
was scanned in are kept, and no product is ever deleted. The counts go to the log.

It runs every Sunday at midnight UTC (`routes/console.php`), which needs the server's cron to run Laravel's scheduler
every minute: `* * * * * cd /path/to/hooli && php artisan schedule:run`. Open Food Facts' export is the big one: some
13 GB to download, half an hour at 60 Mbit/s, which the disk must have free while it runs, and some 90 GB of JSON to
read through, a few minutes, for the 6,800 or so products it says are sold in Estonia. Open Beauty Facts' is 100 MB,
Open Products Facts' 40 MB: the two take under half a minute. Their API is no way around it: its search allows ten
requests a minute, and its documentation sends anyone wanting more than a few hundred products to the exports.

## Deploying

The site's address is https://gethooli.app: set `APP_URL` to it in production (`.env.example` does), so canonical
links, hreflang and the sitemap point there. Mail goes to hello@gethooli.app (`HOOLI_CONTACT` changes it). Run the
migrations and `npm run build`. On Apache hosting whose document root is the project folder, the root `.htaccess`
serves everything from `public/`. The `www` host redirects to the bare domain. Once live, add the site to Google
Search Console and submit https://gethooli.app/sitemap.xml.

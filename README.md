# Hooli

The pre-launch page for Hooli, a mobile app that helps women protect their hormonal health: it scans everyday
products and medicines, and answers daily questions with calm, evidence-based advice. The app doesn't exist yet:
the page measures interest with a waitlist. English first; French and Estonian later.

Laravel 13, Blade, Tailwind 4, no JavaScript framework. No cookies: there are no sessions, and the waitlist form is
guarded by a honeypot and a rate limit instead of a CSRF token.

## The page

One page, `resources/views/landing.blade.php`, with its words in `__()` so `lang/fr.json` and `lang/et.json` can
translate them later. The phone in the hero (`partials/phone-scan.blade.php`) plays its scan once, when it comes into
view; the animation lives in `resources/css/app.css` under `.phone`. The waitlist's situations are listed in
`config/hooli.php`.

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
from the font file: the favicons and app icons, the share picture, the inline icon the page uses and the logo files
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

The domain isn't chosen yet: `APP_URL` in `.env.example` and the contact address in `config/hooli.php`
(`HOOLI_CONTACT`) use the placeholder `hooli.example`. Set both, run the migrations and `npm run build`. On Apache
hosting whose document root is the project folder, the root `.htaccess` serves everything from `public/`. The `www`
host redirects to the bare domain.

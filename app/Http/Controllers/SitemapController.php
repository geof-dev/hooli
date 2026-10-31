<?php

namespace App\Http\Controllers;

use App\Guide;
use Illuminate\Http\Response;

/** Every page in every language, each with its translations, for search engines. */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $locales = array_keys(config('hooli.locales'));
        $everyLanguage = fn (string $name) => collect($locales)->mapWithKeys(fn (string $locale) => [$locale => localized_route($name, locale: $locale)])->all();

        $pages = [
            $everyLanguage('home'),
            $everyLanguage('guides'),
            $everyLanguage('privacy'),
            ...Guide::all('en')->map(fn (Guide $guide) => $guide->translations()->map->url()->all()),
        ];

        return response()->view('sitemap', ['pages' => $pages])->header('Content-Type', 'application/xml');
    }
}

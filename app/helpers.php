<?php

/**
 * The URL of a page in the current language, or in the one given: localized_route('home') is / in English and /fr
 * in French. Every page has a route per language, named after it (en.home, fr.home).
 */
function localized_route(string $name, array $parameters = [], ?string $locale = null): string
{
    return route(($locale ?? app()->getLocale()).'.'.$name, $parameters);
}

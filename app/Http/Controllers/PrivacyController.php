<?php

namespace App\Http\Controllers;

use Illuminate\Support\Carbon;
use Illuminate\View\View;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\MarkdownConverter;

/**
 * The privacy policy, written once per language in resources/privacy/{locale}.md, its title, description and date in
 * the front matter. It says what the app and the site actually do: change it with them.
 */
class PrivacyController extends Controller
{
    public function __invoke(): View
    {
        $environment = new Environment;
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new FrontMatterExtension);
        $rendered = (new MarkdownConverter($environment))->convert(file_get_contents(resource_path('privacy/'.app()->getLocale().'.md')));
        $meta = $rendered->getFrontMatter();

        return view('privacy', [
            'title' => $meta['title'].' | Hooli',
            'heading' => $meta['title'],
            'description' => $meta['description'],
            'updated' => Carbon::parse($meta['updated']),
            'html' => $rendered->getContent(),
        ]);
    }
}

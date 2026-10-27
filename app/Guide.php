<?php

namespace App;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

/**
 * A guide: a long read on one question, written once per language in resources/guides/{locale}/{key}.md. Its front
 * matter gives the address, the titles, the description search engines show, and the key points; the same key in
 * another language is the same guide, translated. The order and the list live in config/hooli.php.
 */
final class Guide
{
    /** @var array<string, self> */
    private static array $read = [];

    /**
     * @param  list<string>  $points  the key points, shown before the text
     * @param  array<string, string>  $contents  the text's sections, by the id of their heading
     */
    public function __construct(
        public readonly string $key,
        public readonly string $locale,
        public readonly string $slug,
        public readonly string $title,
        public readonly string $short,
        public readonly string $description,
        public readonly array $points,
        public readonly Carbon $published,
        public readonly Carbon $updated,
        public readonly string $html,
        public readonly array $contents,
        public readonly int $minutes,
    ) {}

    /** @return Collection<int, self> */
    public static function all(string $locale): Collection
    {
        return collect(config('hooli.guides'))->map(fn (string $key) => self::get($key, $locale));
    }

    public static function find(string $locale, string $slug): ?self
    {
        return self::all($locale)->firstWhere('slug', $slug);
    }

    public static function get(string $key, string $locale): self
    {
        return self::$read["$locale/$key"] ??= self::read($key, $locale);
    }

    /** @return Collection<string, self> this guide in every language, by locale */
    public function translations(): Collection
    {
        return collect(config('hooli.locales'))->map(fn (array $language, string $locale) => self::get($this->key, $locale));
    }

    public function url(): string
    {
        return localized_route('guide', ['slug' => $this->slug], $this->locale);
    }

    private static function read(string $key, string $locale): self
    {
        $environment = new Environment;
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new TableExtension);
        $environment->addExtension(new FrontMatterExtension);
        $rendered = (new MarkdownConverter($environment))->convert(file_get_contents(resource_path("guides/$locale/$key.md")));
        $meta = $rendered->getFrontMatter();

        // Each section's heading gets an id, so the contents can link to it.
        $contents = [];
        $html = preg_replace_callback('/<h2>(.*?)<\/h2>/s', function (array $match) use (&$contents) {
            $heading = html_entity_decode(strip_tags($match[1]));
            $id = Str::slug($heading);
            $contents[$id] = $heading;

            return '<h2 id="'.$id.'">'.$match[1].'</h2>';
        }, $rendered->getContent());

        return new self(
            key: $key,
            locale: $locale,
            slug: $meta['slug'],
            title: $meta['title'],
            short: $meta['short'],
            description: $meta['description'],
            points: $meta['points'],
            published: Carbon::parse($meta['published']),
            updated: Carbon::parse($meta['updated']),
            html: $html,
            contents: $contents,
            // At about 200 words a minute, an unhurried reading pace.
            minutes: max(1, (int) round(count(preg_split('/\s+/u', strip_tags($html), flags: PREG_SPLIT_NO_EMPTY)) / 200)),
        );
    }
}

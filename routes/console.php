<?php

use App\Models\Subscriber;
use App\OpenFacts;
use App\ProductImport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

// How the waitlist is doing, and which situations to build for first.
Artisan::command('waitlist:stats', function () {
    $total = Subscriber::count();
    $this->info("$total on the waitlist");
    if (! $total) {
        return;
    }

    $this->table(['Last 7 days', 'Last 30 days', 'Told us their situation'], [[
        Subscriber::where('created_at', '>=', now()->subDays(7))->count(),
        Subscriber::where('created_at', '>=', now()->subDays(30))->count(),
        Subscriber::whereNotNull('situation')->count(),
    ]]);

    $breakdown = fn (string $column) => Subscriber::selectRaw("coalesce($column, '—') as value, count(*) as people")
        ->groupBy('value')->orderByDesc('people')->get()->map(fn ($row) => [$row->value, $row->people])->all();
    $this->table(['Situation', 'People'], array_map(
        fn (array $row) => [config("hooli.situations.$row[0]", $row[0]), $row[1]],
        $breakdown('situation'),
    ));
    $this->table(['Campaign', 'People'], $breakdown('utm_source'));
})->purpose('Show how many people joined the waitlist, and their situations');

// Everyone still on the list, for the email that announces the launch. Situations are health data: the file stays
// on this machine.
Artisan::command('waitlist:export {path=storage/app/waitlist.csv}', function (string $path) {
    $file = fopen(base_path($path), 'w');
    fputcsv($file, ['email', 'locale', 'joined', 'situation', 'utm_source', 'utm_campaign']);
    Subscriber::orderBy('id')->each(function (Subscriber $subscriber) use ($file) {
        fputcsv($file, [
            $subscriber->email,
            $subscriber->locale,
            $subscriber->created_at->toDateString(),
            $subscriber->situation,
            $subscriber->utm_source,
            $subscriber->utm_campaign,
        ]);
    });
    fclose($file);
    $this->info('Exported '.Subscriber::count()." people to $path");
})->purpose('Export the waitlist as CSV');

// The right to be forgotten, before there is a leave link: deletes the address and the situation with it.
Artisan::command('waitlist:forget {email}', function (string $email) {
    $deleted = Subscriber::where('email', mb_strtolower(trim($email)))->delete();
    $deleted ? $this->info("Deleted $email") : $this->warn("$email is not on the list");
})->purpose('Delete one person from the waitlist, at their request');

// Every product the open databases say is sold in our countries, with its name and ingredient list, from the exports
// they make each night: most scans then find their product here, without asking anyone. A list read on a label stays.
Artisan::command('products:import
    {--country=* : A country, by its ISO code (ee); those of config/hooli.php if none}
    {--source=* : food, beauty or products; all three if none}
    {--file= : An export already on this machine, of the one --source, instead of downloading it}', function () {
    $countries = array_map(fn (string $country) => strtolower(trim($country)), $this->option('country') ?: config('hooli.countries'));
    $databases = $this->option('source') ?: array_keys(ProductImport::DATABASES);
    $file = $this->option('file');
    if ($unknown = array_diff($countries, OpenFacts::COUNTRIES)) {
        $this->error('Unknown country: '.implode(', ', $unknown));

        return 1;
    }
    if (array_diff($databases, array_keys(ProductImport::DATABASES)) || ($file && count($databases) !== 1)) {
        $this->error('The sources are food, beauty and products, and a --file is of one of them');

        return 1;
    }

    foreach ($databases as $database) {
        $path = $file ?? ProductImport::path($database);
        try {
            if (! $file) {
                ProductImport::download($database);
            }
            $counts = ProductImport::import($database, $path, $countries);
        } finally {
            if (! $file) {
                File::delete($path);
            }
        }

        Log::info("products:import $database", ['countries' => $countries, ...$counts]);
        $this->info(sprintf(
            '%s: %d sold in %s with their ingredients, %d added, %d updated, %d unchanged',
            $database, $counts['sold'], implode(', ', $countries), $counts['added'], $counts['updated'], $counts['unchanged'],
        ));
    }
})->purpose('Import the products sold in our countries from the open databases');

Schedule::command('products:import')->weekly();

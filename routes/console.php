<?php

use App\Models\Subscriber;
use Illuminate\Support\Facades\Artisan;

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

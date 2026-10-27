<?php

namespace App\Http\Controllers;

use App\Guide;
use Illuminate\View\View;

/** The guides, in the language of the URL: the list, and each guide. */
class GuideController extends Controller
{
    public function index(): View
    {
        return view('guides.index', ['guides' => Guide::all(app()->getLocale())]);
    }

    public function show(string $slug): View
    {
        $guide = Guide::find(app()->getLocale(), $slug) ?? abort(404);

        return view('guides.show', [
            'guide' => $guide,
            'alternates' => $guide->translations()->map->url()->all(),
        ]);
    }
}

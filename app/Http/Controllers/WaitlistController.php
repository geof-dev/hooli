<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Joining the waitlist. Works without JavaScript (a redirect back to the form, which then says what happened)
 * and answers JSON when the page's script sends it, so the visitor stays where they are.
 */
class WaitlistController extends Controller
{
    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        // Bots fill every field; people never see this one. They get the same answer and nothing is kept.
        if ($request->filled('website')) {
            return $this->joined($request);
        }

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'max:254', 'email:rfc'],
            'situation' => ['nullable', Rule::in(array_keys(config('hooli.situations')))],
            'utm_source' => ['nullable', 'string', 'max:80'],
            'utm_campaign' => ['nullable', 'string', 'max:80'],
        ]);
        if ($validator->fails()) {
            $message = $validator->errors()->has('email')
                ? __('Enter an email address like name@example.com.')
                : __('Choose your situation from the list, or leave it empty.');
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $message], 422);
            }

            return redirect()->to(route('home').'?waitlist=invalid#waitlist');
        }

        $email = mb_strtolower(trim($request->input('email')));

        // Already on the list: the same thanks, and nothing changes, so no one can rewrite another person's answer
        // or find out whose address is on the list.
        Subscriber::firstOrCreate(['email' => $email], [
            'locale' => app()->getLocale(),
            'situation' => $request->input('situation') ?: null,
            'utm_source' => $request->input('utm_source') ?: null,
            'utm_campaign' => $request->input('utm_campaign') ?: null,
        ]);

        return $this->joined($request);
    }

    private function joined(Request $request): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['ok' => true])
            : redirect()->to(route('home').'?waitlist=joined#waitlist');
    }
}

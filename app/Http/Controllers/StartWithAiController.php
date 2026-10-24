<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/*
 | The landing page's "بتبيع إيه؟" box.
 |
 | What the visitor typed is kept in their session and carried through signup
 | to the AI store builder, which opens with it already filled in: a visitor
 | who has just described their shop should not be asked to describe it again
 | two screens later. Kept in the session, not the URL, so the description
 | does not end up in access logs or a Referer header.
 */
class StartWithAiController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business' => ['nullable', 'string', 'max:600'],
        ]);

        // Set even when the box was left empty: the visitor still chose the AI
        // route, and signup should still end on the builder.
        $request->session()->put('ai_start', [
            'business' => trim((string) ($validated['business'] ?? '')),
        ]);

        return $request->user()
            ? to_route('ai.store-builder')
            : to_route('register');
    }
}

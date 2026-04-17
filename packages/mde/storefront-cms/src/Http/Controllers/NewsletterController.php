<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Mde\StorefrontCms\Models\NewsletterSubscriber;

class NewsletterController
{
    public function subscribe(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email|max:190']);

        NewsletterSubscriber::firstOrCreate(
            ['email' => strtolower($data['email'])],
            ['ip' => $request->ip(), 'consent_at' => now()],
        );

        return back()->with('status', 'Merci ! Vous êtes inscrit·e à la newsletter MDE.');
    }
}

<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSite\StoreContactMessageRequest;
use App\Mail\PublicContactMessageMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ContactController extends Controller
{
    public function store(StoreContactMessageRequest $request): RedirectResponse
    {
        $key = 'public-contact:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => "Too many messages. Please try again in {$seconds} seconds.",
            ]);
        }

        RateLimiter::hit($key, 60);

        $payload = $request->validated();

        Log::info('Public contact message received', [
            'name' => $payload['name'],
            'email' => $payload['email'],
            'subject' => $payload['subject'],
            'message' => $payload['message'],
        ]);

        // Use the log mailer so receipt never depends on slow/unreachable SMTP.
        // Outbound delivery can be wired to a real mailer later without changing the public UX.
        Mail::mailer('log')
            ->to((string) config('mail.from.address', 'hello@example.com'))
            ->send(new PublicContactMessageMail($payload));

        return back()->with('contact_success', 'Your message has been received.');
    }
}

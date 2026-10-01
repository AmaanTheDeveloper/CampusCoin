<?php

namespace App\Http\Controllers\Bank;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\User;
use App\Services\Loans\BankRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class BankRegistrationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('bank/register', [
            'eyebrow' => 'For banks',
            'title' => 'Register your bank',
            'description' => 'Join Campus Coin and offer student loan products after platform verification.',
        ]);
    }

    public function store(Request $request, BankRegistrationService $registration): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'official_email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:80'],
            'registration_number' => ['nullable', 'string', 'max:120'],
            'authorized_representative' => ['required', 'string', 'max:255'],
            'representative_email' => ['required', 'email', 'max:255', 'unique:'.User::class.',email'],
            'representative_phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'documents' => ['nullable', 'array', 'max:10'],
            'documents.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'authorization_confirmed' => ['accepted'],
        ]);

        $documents = $request->file('documents', []);
        if (! is_array($documents)) {
            $documents = $documents ? [$documents] : [];
        }

        $bank = $registration->register($validated, array_values($documents));

        $request->session()->put('bank_registration_reference', $bank->reference_code);

        return redirect()
            ->route('bank.registration.status', ['ref' => $bank->reference_code])
            ->with('success', 'Registration request submitted.');
    }

    public function status(Request $request): Response|RedirectResponse
    {
        $ref = trim((string) $request->query('ref', $request->session()->get('bank_registration_reference', '')));

        if ($ref === '') {
            return redirect()->route('bank.register');
        }

        $bank = Bank::query()->where('reference_code', $ref)->first();

        if ($bank === null) {
            return redirect()
                ->route('bank.register')
                ->with('error', 'We could not find that registration request.');
        }

        return Inertia::render('bank/registration-status', [
            'bank' => [
                'name' => $bank->name,
                'reference_code' => $bank->reference_code,
                'status' => $bank->status->value,
                'status_label' => $bank->status->label(),
                'submitted_at' => $bank->created_at?->toIso8601String(),
                'rejection_reason' => $bank->status->value === 'rejected' ? $bank->rejection_reason : null,
            ],
        ]);
    }
}

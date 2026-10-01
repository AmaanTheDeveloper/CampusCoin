<?php

use App\Mail\PublicContactMessageMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('home route renders welcome component', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('welcome'));
});

test('features route renders public features page', function () {
    $this->get(route('features'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('public/features'));
});

test('features page is public for guests and authenticated users', function () {
    $this->get('/features')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/features')
            ->where('auth.user', null));

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/features')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/features')
            ->where('auth.user.id', $user->id));
});

test('features page registration and how-it-works destinations remain named routes', function () {
    expect(route('register', absolute: false))->toBe('/register');
    expect(route('how-it-works', absolute: false))->toBe('/how-it-works');
    expect(route('features', absolute: false))->toBe('/features');
});

test('how it works route renders public how-it-works page', function () {
    $this->get(route('how-it-works'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('public/how-it-works'));
});

test('how it works page is public for guests and authenticated users', function () {
    $this->get('/how-it-works')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/how-it-works')
            ->where('auth.user', null));

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/how-it-works')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/how-it-works')
            ->where('auth.user.id', $user->id));
});

test('how it works registration and features destinations remain named routes', function () {
    expect(route('register', absolute: false))->toBe('/register');
    expect(route('features', absolute: false))->toBe('/features');
    expect(route('how-it-works', absolute: false))->toBe('/how-it-works');
});

test('about route renders public about page', function () {
    $this->get(route('about'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('public/about'));
});

test('contact route renders public contact page', function () {
    $this->get(route('contact'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('public/contact'));
});

test('about and contact pages remain public for guests', function () {
    $this->get('/about')->assertOk()->assertInertia(fn ($page) => $page->component('public/about'));
    $this->get('/contact')->assertOk()->assertInertia(fn ($page) => $page->component('public/contact'));
});

test('contact form validates required fields', function () {
    $this->from(route('contact'))
        ->post(route('contact.store'), [])
        ->assertRedirect(route('contact'))
        ->assertSessionHasErrors(['name', 'email', 'subject', 'message']);
});

test('contact form validates email format', function () {
    $this->from(route('contact'))
        ->post(route('contact.store'), [
            'name' => 'Student',
            'email' => 'not-an-email',
            'subject' => 'Help',
            'message' => 'Need assistance with budgets.',
        ])
        ->assertRedirect(route('contact'))
        ->assertSessionHasErrors(['email']);
});

test('contact form accepts a valid submission', function () {
    Mail::fake();

    $this->from(route('contact'))
        ->post(route('contact.store'), [
            'name' => 'Student User',
            'email' => 'student@example.com',
            'subject' => 'Feedback',
            'message' => 'Campus Coin looks helpful for student budgeting.',
        ])
        ->assertRedirect(route('contact'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('contact_success', 'Your message has been received.');

    Mail::assertSent(PublicContactMessageMail::class);
});

test('guest public home shares null auth user for login and get started ctas', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome')
            ->where('auth.user', null));
});

test('authenticated public home shares user for dashboard cta', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome')
            ->where('auth.user.id', $user->id));
});

test('login register and dashboard routes remain available', function () {
    $this->get(route('login'))->assertOk();
    $this->get(route('register'))->assertOk();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('home page is publicly reachable with inertia welcome shell', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('welcome'));
});

test('home registration and feature destinations remain named routes', function () {
    expect(route('register', absolute: false))->toBe('/register');
    expect(route('features', absolute: false))->toBe('/features');
    expect(route('how-it-works', absolute: false))->toBe('/how-it-works');
});

test('student loans public page renders for guests', function () {
    $this->get(route('student-loans'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('public/loans/index'));
});

test('intelligence preview is public for guests and redirects authenticated users', function () {
    $this->get(route('intelligence.preview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('public/intelligence'));

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('intelligence.preview'))
        ->assertRedirect(route('intelligence.index'));
});

test('bank registration page is publicly reachable', function () {
    $this->get(route('bank.register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('bank/register'));
});

test('intelligence and bank register named routes remain stable', function () {
    expect(route('intelligence.preview', absolute: false))->toBe('/intelligence/preview');
    expect(route('bank.register', absolute: false))->toBe('/bank/register');
    expect(route('bank.registration.status', absolute: false))->toBe('/bank/registration/status');
    expect(route('intelligence.index', absolute: false))->toBe('/intelligence');
});

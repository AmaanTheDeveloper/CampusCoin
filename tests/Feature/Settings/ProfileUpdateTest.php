<?php

use App\Enums\AcademicYear;
use App\Enums\Role;
use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'academic_year' => AcademicYear::Year2->value,
            'monthly_allowance' => '500.50',
            'savings_goal' => '1000.00',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
    expect($user->academic_year)->toBe(AcademicYear::Year2);
    expect((string) $user->monthly_allowance)->toBe('500.50');
    expect((string) $user->savings_goal)->toBe('1000.00');
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('negative monthly allowance is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/settings/profile')
        ->patch('/settings/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'monthly_allowance' => '-10',
        ])
        ->assertSessionHasErrors('monthly_allowance')
        ->assertRedirect('/settings/profile');
});

test('negative savings goal is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/settings/profile')
        ->patch('/settings/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'savings_goal' => '-5.00',
        ])
        ->assertSessionHasErrors('savings_goal')
        ->assertRedirect('/settings/profile');
});

test('invalid academic year is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/settings/profile')
        ->patch('/settings/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'academic_year' => 'freshman-forever',
        ])
        ->assertSessionHasErrors('academic_year')
        ->assertRedirect('/settings/profile');
});

test('profile update cannot change role', function () {
    $user = User::factory()->create(['role' => Role::Student]);

    $this->actingAs($user)
        ->patch('/settings/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'admin',
            'is_admin' => true,
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->role)->toBe(Role::Student);
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/settings/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/settings/profile')
        ->delete('/settings/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/settings/profile');

    expect($user->fresh())->not->toBeNull();
});

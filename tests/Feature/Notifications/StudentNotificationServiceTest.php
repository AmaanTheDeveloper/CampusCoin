<?php

use App\Models\User;
use App\Notifications\AdminOperationalNotification;
use App\Services\Notifications\StudentNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('student notification service excludes admin audience notifications', function () {
    $student = User::factory()->create();

    // Student-facing row (no admin audience).
    $student->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\BudgetAlertNotification',
        'data' => [
            'type' => 'budget_near_limit',
            'message' => 'Near limit',
        ],
        'read_at' => null,
    ]);

    // Admin-audience row incorrectly present on student (must be excluded).
    $student->notify(new AdminOperationalNotification(
        eventType: 'admin_only',
        title: 'Admin',
        message: 'Secret',
        severity: 'important',
    ));

    $service = app(StudentNotificationService::class);

    expect($student->notifications()->count())->toBe(2);
    expect($service->queryFor($student)->count())->toBe(1);
    expect($service->unreadCount($student))->toBe(1);

    $only = $service->queryFor($student)->first();
    expect($only?->data['type'] ?? null)->toBe('budget_near_limit');
});

test('student notification controller remains user scoped after service extraction', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $userA->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\BudgetAlertNotification',
        'data' => [
            'type' => 'budget_exceeded',
            'message' => 'Exceeded',
            'budget_id' => 1,
        ],
        'read_at' => null,
    ]);

    $id = $userA->notifications()->firstOrFail()->id;

    $this->actingAs($userB)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('notifications', []));

    $this->actingAs($userB)
        ->post(route('notifications.read', $id))
        ->assertNotFound();

    $this->actingAs($userA)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('notifications', 1));
});

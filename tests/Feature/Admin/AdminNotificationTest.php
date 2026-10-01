<?php

use App\Enums\Role;
use App\Models\User;
use App\Notifications\AdminOperationalNotification;
use App\Services\Admin\AdminNotificationService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedDefaultCategories();
});

test('admin can open notifications page and preview shared props', function () {
    $admin = User::factory()->admin()->create();

    app(AdminNotificationService::class)->notifyAdmins(
        eventType: 'student.registered',
        title: 'New student registered',
        message: 'Test Student joined Campus Coin.',
        severity: 'info',
        actionUrl: '/admin/users',
        actionLabel: 'View users',
    );

    $this->actingAs($admin)
        ->get(route('admin.notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/notifications/index')
            ->has('notifications', 1)
            ->where('unread_count', 1)
            ->where('notifications.0.title', 'New student registered'));

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('admin_unread_notifications_count', 1)
            ->has('admin_notifications_preview', 1));
});

test('students cannot access admin notifications', function () {
    $student = User::factory()->create();

    $this->actingAs($student)
        ->get(route('admin.notifications.index'))
        ->assertForbidden();
});

test('admin can mark one and all notifications as read', function () {
    $admin = User::factory()->admin()->create();
    $service = app(AdminNotificationService::class);

    $service->notifyAdmins('student.registered', 'New student registered', 'A joined.', 'info', '/admin/users', 'View');
    $service->notifyAdmins('category.created', 'System category created', 'Food added.', 'success', '/admin/categories', 'View');

    $ids = $admin->fresh()->notifications()->pluck('id');
    expect($ids)->toHaveCount(2);
    expect($service->unreadAdminCount($admin->fresh()))->toBe(2);

    $this->actingAs($admin)
        ->post(route('admin.notifications.read', $ids[0]))
        ->assertRedirect();

    expect($service->unreadAdminCount($admin->fresh()))->toBe(1);

    $this->actingAs($admin)
        ->post(route('admin.notifications.read-all'))
        ->assertRedirect();

    expect($service->unreadAdminCount($admin->fresh()))->toBe(0);
});

test('admin can mark unread and delete notifications', function () {
    $admin = User::factory()->admin()->create();
    app(AdminNotificationService::class)->notifyAdmins(
        'student.disabled',
        'Student account disabled',
        'Account disabled.',
        'warning',
        '/admin/users',
        'View',
    );

    $id = $admin->fresh()->notifications()->firstOrFail()->id;

    $this->actingAs($admin)
        ->post(route('admin.notifications.read', $id))
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('admin.notifications.unread', $id))
        ->assertRedirect();

    expect($admin->fresh()->unreadNotifications()->count())->toBe(1);

    $this->actingAs($admin)
        ->delete(route('admin.notifications.destroy', $id))
        ->assertRedirect();

    expect($admin->fresh()->notifications()->count())->toBe(0);
});

test('admin notifications stay out of student notification inbox and badge', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->create();

    app(AdminNotificationService::class)->notifyAdmins(
        'student.registered',
        'New student registered',
        'Someone joined.',
        'info',
    );

    // Force an admin-scoped row onto the student (should never happen in product, but protects filtering).
    Notification::sendNow(
        $student,
        new AdminOperationalNotification(
            eventType: 'student.registered',
            title: 'Should stay hidden',
            message: 'Admin-only payload',
            severity: 'info',
        ),
    );

    $this->actingAs($student)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('notifications', []));

    $this->actingAs($student)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('unread_notifications_count', 0));

    expect($admin->role)->toBe(Role::Admin);
});

test('new student registration notifies admins', function () {
    $admin = User::factory()->admin()->create();

    $this->post(route('register'), [
        'name' => 'Fresh Student',
        'email' => 'fresh-student@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('verification.notice'));

    $notifications = $admin->fresh()->notifications()->get();
    expect($notifications->where(fn ($n) => ($n->data['type'] ?? null) === 'student.registered')->count())->toBeGreaterThanOrEqual(1);
    expect($notifications->first()->data['audience'] ?? null)->toBe('admin');
    expect($notifications->firstWhere(fn ($n) => ($n->data['type'] ?? null) === 'student.registered')->data['title'] ?? null)
        ->toBe('New student registered');
});

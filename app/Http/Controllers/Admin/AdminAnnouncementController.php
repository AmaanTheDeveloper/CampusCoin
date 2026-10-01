<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AnnouncementPriority;
use App\Enums\AnnouncementStatus;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\Admin\AdminActivityLogger;
use App\Services\Admin\AdminNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminAnnouncementController extends Controller
{
    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', 'all');

        $announcements = Announcement::query()
            ->with('creator:id,name')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Announcement $item) => $this->serialize($item));

        return Inertia::render('admin/announcements/index', [
            'announcements' => $announcements,
            'filters' => [
                'status' => in_array($status, ['all', ...AnnouncementStatus::values()], true) ? $status : 'all',
            ],
            'priority_options' => collect(AnnouncementPriority::cases())->map(fn ($p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ]),
            'status_options' => collect(AnnouncementStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    public function store(Request $request, AdminActivityLogger $logger, AdminNotificationService $notifications): RedirectResponse
    {
        $data = $this->validated($request);
        $announcement = new Announcement($data);
        $announcement->created_by = $request->user()->id;
        $announcement->syncLifecycleStatus();
        $announcement->save();

        $logger->log($request->user(), 'announcement.create', "Created announcement {$announcement->title}", $announcement);

        $notifications->notifyAdmins(
            eventType: 'announcement.created',
            title: 'Announcement created',
            message: "“{$announcement->title}” is ready for campus-wide delivery.",
            severity: 'success',
            actionUrl: '/admin/announcements',
            actionLabel: 'View announcements',
        );

        return back()->with('success', 'Announcement created.');
    }

    public function update(Request $request, Announcement $announcement, AdminActivityLogger $logger): RedirectResponse
    {
        $data = $this->validated($request);
        $announcement->fill($data);
        $announcement->syncLifecycleStatus();
        $announcement->save();

        $logger->log($request->user(), 'announcement.update', "Updated announcement {$announcement->title}", $announcement);

        return back()->with('success', 'Announcement updated.');
    }

    public function destroy(Request $request, Announcement $announcement, AdminActivityLogger $logger, AdminNotificationService $notifications): RedirectResponse
    {
        $title = $announcement->title;
        $announcement->delete();

        $logger->log($request->user(), 'announcement.delete', "Deleted announcement {$title}");

        $notifications->notifyAdmins(
            eventType: 'announcement.deleted',
            title: 'Announcement deleted',
            message: "“{$title}” was removed from campus announcements.",
            severity: 'warning',
            actionUrl: '/admin/announcements',
            actionLabel: 'View announcements',
        );

        return back()->with('success', 'Announcement deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(AnnouncementPriority::values())],
            'status' => ['required', Rule::in(AnnouncementStatus::values())],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Announcement $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'message' => $item->message,
            'priority' => $item->priority->value,
            'status' => $item->status->value,
            'starts_at' => $item->starts_at?->toIso8601String(),
            'ends_at' => $item->ends_at?->toIso8601String(),
            'updated_at' => $item->updated_at?->toIso8601String(),
            'creator' => $item->creator?->name,
        ];
    }
}

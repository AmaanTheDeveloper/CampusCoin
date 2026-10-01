<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TipImpactLevel;
use App\Http\Controllers\Controller;
use App\Models\TipTemplate;
use App\Services\Admin\AdminActivityLogger;
use App\Services\Admin\AdminNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminTipTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', 'all');

        $tips = TipTemplate::query()
            ->with('creator:id,name')
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (TipTemplate $tip) => [
                'id' => $tip->id,
                'title' => $tip->title,
                'body' => $tip->body,
                'category_label' => $tip->category_label,
                'impact_level' => $tip->impact_level->value,
                'is_active' => $tip->is_active,
                'updated_at' => $tip->updated_at?->toIso8601String(),
                'creator' => $tip->creator?->name,
            ]);

        return Inertia::render('admin/saving-tips/index', [
            'tips' => $tips,
            'filters' => [
                'status' => in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all',
            ],
            'impact_options' => collect(TipImpactLevel::cases())->map(fn ($level) => [
                'value' => $level->value,
                'label' => $level->label(),
            ]),
        ]);
    }

    public function store(Request $request, AdminActivityLogger $logger, AdminNotificationService $notifications): RedirectResponse
    {
        $data = $this->validated($request);
        $tip = TipTemplate::query()->create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        $logger->log($request->user(), 'tip.create', "Created saving tip {$tip->title}", $tip);

        $notifications->notifyAdmins(
            eventType: 'tip.created',
            title: 'Saving tip created',
            message: "“{$tip->title}” was added to student insights templates.",
            severity: 'success',
            actionUrl: '/admin/saving-tips',
            actionLabel: 'View saving tips',
        );

        return back()->with('success', 'Saving tip template created.');
    }

    public function update(Request $request, TipTemplate $tipTemplate, AdminActivityLogger $logger): RedirectResponse
    {
        $tipTemplate->update($this->validated($request));

        $logger->log($request->user(), 'tip.update', "Updated saving tip {$tipTemplate->title}", $tipTemplate);

        return back()->with('success', 'Saving tip template updated.');
    }

    public function destroy(Request $request, TipTemplate $tipTemplate, AdminActivityLogger $logger, AdminNotificationService $notifications): RedirectResponse
    {
        $title = $tipTemplate->title;
        $tipTemplate->delete();

        $logger->log($request->user(), 'tip.delete', "Deleted saving tip {$title}");

        $notifications->notifyAdmins(
            eventType: 'tip.deleted',
            title: 'Saving tip deleted',
            message: "“{$title}” was removed from tip templates.",
            severity: 'warning',
            actionUrl: '/admin/saving-tips',
            actionLabel: 'View saving tips',
        );

        return back()->with('success', 'Saving tip template deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
            'category_label' => ['nullable', 'string', 'max:80'],
            'impact_level' => ['required', Rule::in(TipImpactLevel::values())],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}

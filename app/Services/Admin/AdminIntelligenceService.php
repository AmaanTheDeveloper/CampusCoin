<?php

namespace App\Services\Admin;

use App\Enums\TransactionSignalStatus;
use App\Enums\TransactionSignalType;
use App\Models\AiCategoryFeedback;
use App\Models\Category;
use App\Models\TransactionSignal;
use Illuminate\Support\Facades\DB;

class AdminIntelligenceService
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $total = AiCategoryFeedback::query()->count();
        $accepted = AiCategoryFeedback::query()->where('was_accepted', true)->count();
        $corrected = AiCategoryFeedback::query()->where('was_accepted', false)->count();

        return [
            'categorization' => [
                'has_data' => $total > 0,
                'suggestions' => $total,
                'accepted' => $accepted,
                'corrected' => $corrected,
                'acceptance_rate' => $total > 0 ? round(($accepted / $total) * 100, 1) : null,
                'disclaimer' => 'Based on student acceptance and correction activity for AI category suggestions.',
            ],
            'confusion_pairs' => $this->confusionPairs(8),
            'signals' => [
                'open_duplicates' => TransactionSignal::query()
                    ->where('signal_type', TransactionSignalType::DuplicateTransaction)
                    ->where('status', TransactionSignalStatus::Open)
                    ->count(),
                'open_unusual' => TransactionSignal::query()
                    ->where('signal_type', TransactionSignalType::LargeTransaction)
                    ->where('status', TransactionSignalStatus::Open)
                    ->count(),
                'resolved_duplicates' => TransactionSignal::query()
                    ->where('signal_type', TransactionSignalType::DuplicateTransaction)
                    ->whereIn('status', [
                        TransactionSignalStatus::KeptBoth,
                        TransactionSignalStatus::RemovedDuplicate,
                        TransactionSignalStatus::Reviewed,
                        TransactionSignalStatus::Dismissed,
                    ])
                    ->count(),
            ],
            'recent_feedback' => AiCategoryFeedback::query()
                ->with(['suggestedCategory:id,name', 'selectedCategory:id,name'])
                ->latest('created_at')
                ->limit(10)
                ->get()
                ->map(fn (AiCategoryFeedback $row) => [
                    'id' => $row->id,
                    'accepted' => $row->was_accepted,
                    'suggested' => $row->suggestedCategory?->name,
                    'selected' => $row->selectedCategory?->name,
                    'created_at' => $row->created_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }

    /**
     * @return list<array{from: string, to: string, count: int}>
     */
    public function confusionPairs(int $limit = 8): array
    {
        $rows = AiCategoryFeedback::query()
            ->select('suggested_category_id', 'selected_category_id', DB::raw('COUNT(*) as aggregate'))
            ->where('was_accepted', false)
            ->whereColumn('suggested_category_id', '!=', 'selected_category_id')
            ->groupBy('suggested_category_id', 'selected_category_id')
            ->orderByDesc('aggregate')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $ids = $rows->pluck('suggested_category_id')
            ->merge($rows->pluck('selected_category_id'))
            ->unique()
            ->all();
        $names = Category::query()->whereIn('id', $ids)->pluck('name', 'id');

        return $rows->map(fn ($row) => [
            'from' => $names[$row->suggested_category_id] ?? 'Unknown',
            'to' => $names[$row->selected_category_id] ?? 'Unknown',
            'count' => (int) $row->aggregate,
        ])->values()->all();
    }
}

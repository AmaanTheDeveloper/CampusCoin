<?php

namespace App\Services\Bookmarks;

use App\Models\AiMonthlyInsight;
use App\Models\Bookmark;
use App\Models\SavingTip;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class BookmarkService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(User $user): array
    {
        return Bookmark::query()
            ->where('user_id', $user->id)
            ->with('bookmarkable')
            ->latest()
            ->limit(50)
            ->get()
            ->map(function (Bookmark $bookmark) {
                $target = $bookmark->bookmarkable;
                if ($target === null) {
                    return null;
                }

                return [
                    'id' => $bookmark->id,
                    'type' => $this->typeLabel($target),
                    'bookmarkable_type' => $bookmark->bookmarkable_type,
                    'bookmarkable_id' => $bookmark->bookmarkable_id,
                    'title' => $this->title($target),
                    'summary' => $this->summary($target),
                    'period' => $target->period ?? null,
                    'created_at' => $bookmark->created_at?->toIso8601String(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function toggle(User $user, string $type, int $id): array
    {
        $model = $this->resolveOwned($user, $type, $id);

        $existing = Bookmark::query()
            ->where('user_id', $user->id)
            ->where('bookmarkable_type', $model::class)
            ->where('bookmarkable_id', $model->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return ['bookmarked' => false];
        }

        Bookmark::query()->create([
            'user_id' => $user->id,
            'bookmarkable_type' => $model::class,
            'bookmarkable_id' => $model->id,
        ]);

        return ['bookmarked' => true];
    }

    public function remove(User $user, Bookmark $bookmark): void
    {
        abort_unless($bookmark->user_id === $user->id, 404);
        $bookmark->delete();
    }

    private function resolveOwned(User $user, string $type, int $id): Model
    {
        $class = match ($type) {
            'insight' => AiMonthlyInsight::class,
            'tip' => SavingTip::class,
            default => throw new InvalidArgumentException('Invalid bookmark type.'),
        };

        $model = $class::query()->whereKey($id)->firstOrFail();

        abort_unless((int) $model->user_id === (int) $user->id, 404);

        return $model;
    }

    private function typeLabel(Model $target): string
    {
        return match ($target::class) {
            AiMonthlyInsight::class => 'insight',
            SavingTip::class => 'tip',
            default => 'item',
        };
    }

    private function title(Model $target): string
    {
        return match ($target::class) {
            AiMonthlyInsight::class => (string) $target->headline,
            SavingTip::class => (string) $target->title,
            default => 'Bookmark',
        };
    }

    private function summary(Model $target): string
    {
        return match ($target::class) {
            AiMonthlyInsight::class => (string) $target->summary,
            SavingTip::class => (string) $target->body,
            default => '',
        };
    }
}

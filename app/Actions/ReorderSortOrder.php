<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReorderSortOrder
{
    /**
     * @param  Builder<Model>  $query
     * @param  list<int>  $orderedIds
     */
    public function execute(Builder $query, array $orderedIds, int $maxSortOrder = 255): void
    {
        DB::transaction(function () use ($query, $orderedIds, $maxSortOrder): void {
            $models = (clone $query)
                ->orderBy('sort_order')
                ->lockForUpdate()
                ->get();

            $existingIds = $models
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();
            $sortedExisting = $existingIds;
            sort($sortedExisting);
            $sortedIncoming = $orderedIds;
            sort($sortedIncoming);

            if ($sortedExisting !== $sortedIncoming) {
                throw ValidationException::withMessages([
                    'ids' => ['The given ids do not match the current list.'],
                ]);
            }

            if ($existingIds === $orderedIds) {
                return;
            }

            $sortValues = $models
                ->pluck('sort_order')
                ->map(fn (mixed $value): int => (int) $value)
                ->values()
                ->all();

            $usedSortOrders = (clone $query)
                ->withTrashed()
                ->pluck('sort_order')
                ->map(fn (mixed $value): int => (int) $value)
                ->all();

            $temporaryOrders = $this->temporarySortOrders(
                $usedSortOrders,
                count($orderedIds),
                $maxSortOrder,
            );

            $modelClass = $query->getModel()::class;

            foreach ($orderedIds as $index => $id) {
                $modelClass::query()->whereKey($id)->update([
                    'sort_order' => $temporaryOrders[$index],
                ]);
            }

            foreach ($orderedIds as $index => $id) {
                $modelClass::query()->whereKey($id)->update([
                    'sort_order' => $sortValues[$index],
                ]);
            }
        });
    }

    /**
     * @param  list<int>  $usedSortOrders
     * @return list<int>
     */
    private function temporarySortOrders(array $usedSortOrders, int $needed, int $maxSortOrder): array
    {
        $used = array_flip($usedSortOrders);
        $temps = [];

        for ($value = 1; $value <= $maxSortOrder && count($temps) < $needed; $value++) {
            if (! isset($used[$value])) {
                $temps[] = $value;
            }
        }

        if (count($temps) < $needed) {
            throw ValidationException::withMessages([
                'ids' => ['Not enough sort order values available to reorder.'],
            ]);
        }

        return $temps;
    }
}

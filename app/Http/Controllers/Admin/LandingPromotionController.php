<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLandingPromotionRequest;
use App\Http\Requests\UpdateLandingPromotionRequest;
use App\Models\LandingPromotion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class LandingPromotionController extends Controller
{
    private const string IMAGE_DIRECTORY = 'landing/promotions';

    /**
     * List every promotion with its status today (academy timezone).
     */
    public function index(): Response
    {
        $today = Carbon::now(config('cubania.timezone'));

        $promotions = LandingPromotion::query()
            ->latest('updated_at')
            ->latest('id')
            ->get()
            ->map(fn (LandingPromotion $promotion): array => [
                'id' => $promotion->id,
                'title' => $promotion->title,
                'image_url' => $promotion->image_url,
                'cta_label' => $promotion->cta_label,
                'cta_url' => $promotion->cta_url,
                'display_mode' => $promotion->display_mode->value,
                'modal_orientation' => $promotion->modal_orientation->value,
                'is_active' => $promotion->is_active,
                'starts_on' => $promotion->starts_on?->toDateString(),
                'ends_on' => $promotion->ends_on?->toDateString(),
                'status' => $promotion->statusOn($today),
            ])
            ->values()
            ->all();

        return Inertia::render('admin/promotions/index', [
            'promotions' => $promotions,
        ]);
    }

    /**
     * Create a promotion and store its image on the public disk.
     */
    public function store(StoreLandingPromotionRequest $request): RedirectResponse
    {
        $imagePath = $request->file('image')->store(self::IMAGE_DIRECTORY, 'public');

        try {
            LandingPromotion::query()->create([
                ...Arr::except($request->validated(), ['image']),
                'image_path' => $imagePath,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($imagePath);

            throw $exception;
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Promoción creada.',
        ]);

        return redirect()->route('admin.promotions.index');
    }

    /**
     * Update a promotion. A new image replaces the old one, which is deleted once the row points to the new file.
     */
    public function update(UpdateLandingPromotionRequest $request, LandingPromotion $promotion): RedirectResponse
    {
        $attributes = Arr::except($request->validated(), ['image']);
        $previousImagePath = $promotion->image_path;
        $newImagePath = $request->hasFile('image')
            ? $request->file('image')->store(self::IMAGE_DIRECTORY, 'public')
            : null;

        if ($newImagePath !== null) {
            $attributes['image_path'] = $newImagePath;
        }

        try {
            $promotion->update($attributes);
        } catch (Throwable $exception) {
            if ($newImagePath !== null) {
                Storage::disk('public')->delete($newImagePath);
            }

            throw $exception;
        }

        if ($newImagePath !== null) {
            Storage::disk('public')->delete($previousImagePath);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Promoción actualizada.',
        ]);

        return redirect()->route('admin.promotions.index');
    }

    /**
     * Delete a promotion and its image.
     */
    public function destroy(LandingPromotion $promotion): RedirectResponse
    {
        $imagePath = $promotion->image_path;

        $promotion->delete();
        Storage::disk('public')->delete($imagePath);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Promoción eliminada.',
        ]);

        return redirect()->route('admin.promotions.index');
    }
}

<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateProductDraft;
use App\Models\AiGeneration;
use App\Services\AI\AiException;
use App\Services\AI\AiFeature;
use App\Services\AI\AiUsageMeter;
use App\Services\AI\VisionImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The product form's "write it for me" button.
 *
 * `store` takes the photos and notes, queues the work and answers at once;
 * `show` is what the form polls until the draft is ready. Nothing here writes
 * to a product — the draft goes back to the form, and the merchant saves it
 * through the normal product endpoints like anything they typed.
 */
class AiProductDraftController extends Controller
{
    /** Claude reads up to 4 photos well; more mostly adds cost. */
    private const MAX_IMAGES = 4;

    public function store(Request $request, AiUsageMeter $meter): JsonResponse
    {
        $store = $request->user()->currentStore();

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
            'images' => ['nullable', 'array', 'max:' . self::MAX_IMAGES],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'notes.max' => 'الكلام كتير شوية — اكتب أهم حاجات عن المنتج في سطرين.',
            'images.max' => 'كفاية ' . self::MAX_IMAGES . ' صور، اختار أوضحهم.',
            'images.*.image' => 'الملف لازم يكون صورة.',
            'images.*.mimes' => 'الصورة لازم تكون JPG أو PNG أو WEBP.',
            'images.*.max' => 'أقصى حجم للصورة ٥ ميجا.',
        ]);

        $notes = trim((string) ($data['notes'] ?? ''));
        $files = $request->file('images', []);

        if ($notes === '' && $files === []) {
            throw ValidationException::withMessages([
                'images' => 'ارفع صورة للمنتج أو اكتب عنه كلمتين الأول.',
            ]);
        }

        try {
            $meter->ensureAllowed($store, AiFeature::ProductWriter);
        } catch (AiException $e) {
            return response()->json(['message' => $e->getMessage()], 429);
        }

        /*
         | One at a time per store. The button is disabled while a draft is
         | being written, so a second one only comes from a double-click or a
         | second tab — and each would be a paid call for the same product.
         */
        $busy = AiGeneration::where('store_id', $store->id)
            ->where('feature', AiFeature::ProductWriter->value)
            ->whereIn('status', [AiGeneration::STATUS_PENDING, AiGeneration::STATUS_RUNNING])
            ->where('created_at', '>=', now()->subMinutes(AiGeneration::EXPIRES_AFTER_MINUTES))
            ->exists();

        if ($busy) {
            return response()->json(['message' => 'لسه بنكتب المنتج اللي قبله. استنى ثواني.'], 429);
        }

        $images = $this->prepareImages($files);

        if ($notes === '' && $images === []) {
            throw ValidationException::withMessages([
                'images' => 'مقدرناش نفتح الصور دي. جرّب صور تانية.',
            ]);
        }

        $generation = AiGeneration::create([
            'store_id' => $store->id,
            'user_id' => $request->user()->id,
            'feature' => AiFeature::ProductWriter->value,
            'status' => AiGeneration::STATUS_PENDING,
        ]);

        $generation->update(['input' => [
            'notes' => $notes,
            'images' => $this->stash($generation, $images),
        ]]);

        GenerateProductDraft::dispatch($generation->id);

        return response()->json($this->present($generation->refresh()), 202);
    }

    public function show(Request $request, AiGeneration $generation): JsonResponse
    {
        // 404, not 403: whether somebody else's draft exists is none of the
        // asker's business.
        abort_unless(
            (int) $generation->store_id === (int) $request->user()->currentStore()->id
                && $generation->feature === AiFeature::ProductWriter->value,
            404,
        );

        if ($generation->hasExpired()) {
            $generation->markFailed('الموضوع أخد وقت أكتر من اللازم. جرّب تاني.');
            $generation->deleteInputs();
        }

        return response()->json($this->present($generation));
    }

    /**
     * Shrink every photo to what the model needs.
     *
     * A photo that passed validation but GD cannot decode (a truncated
     * upload, an exotic colour profile) is skipped: one unreadable photo
     * should not cost the merchant the other three.
     *
     * @param  array<int,UploadedFile>  $files
     * @return array<int,array{bytes:string,media_type:string}>
     */
    private function prepareImages(array $files): array
    {
        return collect($files)
            ->map(fn (UploadedFile $file) => VisionImage::prepare($file->getRealPath()))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Put the photos where the job can read them.
     *
     * @param  array<int,array{bytes:string,media_type:string}>  $images
     * @return array<int,array{path:string,media_type:string}>
     */
    private function stash(AiGeneration $generation, array $images): array
    {
        $stored = [];

        foreach ($images as $index => $image) {
            $path = $generation->inputDirectory() . "/{$index}";
            Storage::disk('local')->put($path, $image['bytes']);

            $stored[] = ['path' => $path, 'media_type' => $image['media_type']];
        }

        return $stored;
    }

    /** @return array<string,mixed> */
    private function present(AiGeneration $generation): array
    {
        return [
            'id' => $generation->id,
            'status' => $generation->status,
            'draft' => $generation->status === AiGeneration::STATUS_DONE ? $generation->result : null,
            'error' => $generation->status === AiGeneration::STATUS_FAILED ? $generation->error : null,
        ];
    }
}

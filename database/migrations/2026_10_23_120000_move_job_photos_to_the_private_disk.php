<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Shopmade\Jobs\Services\JobPhotoStore;

/**
 * Photos used to live on the public disk at a guessable URL, with their EXIF (GPS included). Re-encode them onto the
 * private disk, where JobPhotoFileController serves them after an authorization check. A public file is deleted only
 * once its private copy is confirmed; anything that fails is logged and left in place, so rerunning is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');
        $store = app(JobPhotoStore::class);

        DB::table('job_photos')->orderBy('id')->chunkById(200, function ($photos) use ($public, $private, $store): void {
            foreach ($photos as $photo) {
                foreach (array_filter([$photo->path, $photo->thumb_path]) as $path) {
                    if (! $public->exists($path)) {
                        continue;
                    }

                    if (! $private->exists($path)) {
                        $contents = $public->get($path);

                        if ($contents === null) {
                            Log::warning('Job photo could not be read from the public disk; left in place.', ['photo' => $photo->id, 'path' => $path]);

                            continue;
                        }

                        $contents = $this->clean($store, $contents, $path, $photo->id);

                        if (! $private->put($path, $contents) || $private->size($path) !== strlen($contents)) {
                            Log::error('Job photo could not be written to the private disk; left on the public disk.', ['photo' => $photo->id, 'path' => $path]);
                            $private->delete($path);

                            continue;
                        }

                        if ($path === $photo->path) {
                            DB::table('job_photos')->where('id', $photo->id)->update(['size' => strlen($contents)]);
                        }
                    }

                    $public->delete($path);
                }
            }
        });
    }

    /**
     * One-way: the photos stay on the private disk, since moving them back would publish them again.
     */
    public function down(): void
    {
        //
    }

    /**
     * Re-encoded bytes without EXIF, or the original bytes (with a warning) when GD can't handle the file.
     */
    private function clean(JobPhotoStore $store, string $contents, string $path, int $photoId): string
    {
        $format = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'png',
            'webp' => 'webp',
            default => 'jpg',
        };

        try {
            return $store->reencode($contents, $format);
        } catch (ValidationException $exception) {
            Log::warning('Job photo could not be re-encoded and was moved with its metadata.', [
                'photo' => $photoId,
                'path' => $path,
                'reason' => $exception->getMessage(),
            ]);

            return $contents;
        }
    }
};

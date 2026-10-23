<?php

namespace Shopmade\Jobs\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Shopmade\Jobs\Enums\JobPhotoKind;
use Shopmade\Jobs\Models\Job;
use Shopmade\Jobs\Models\JobPhoto;

/**
 * Writes job photos to the public disk with a GD thumbnail beside the original.
 */
class JobPhotoStore
{
    public const int THUMB_WIDTH = 640;

    public function store(Job $job, UploadedFile $file, ?User $uploader, JobPhotoKind $kind, ?string $caption = null): JobPhoto
    {
        $directory = 'job-photos/'.$job->id;
        $name = Str::random(40);
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');

        $path = $file->storeAs($directory, $name.'.'.$extension, JobPhoto::DISK);
        $thumbPath = $this->writeThumbnail($path, $directory.'/'.$name.'-thumb.jpg');

        return $job->photos()->create([
            'user_id' => $uploader?->id,
            'path' => $path,
            'thumb_path' => $thumbPath,
            'original_name' => Str::limit($file->getClientOriginalName(), 200, ''),
            'mime' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize() ?: 0,
            'kind' => $kind,
            'caption' => $caption,
            'client_visible' => true,
        ]);
    }

    public function delete(JobPhoto $photo): void
    {
        $disk = Storage::disk(JobPhoto::DISK);

        $disk->delete(array_filter([$photo->path, $photo->thumb_path]));
        $photo->delete();
    }

    /**
     * Returns the stored thumbnail path, or null when GD cannot decode the image.
     */
    private function writeThumbnail(string $sourcePath, string $thumbPath): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $disk = Storage::disk(JobPhoto::DISK);

        try {
            $contents = $disk->get($sourcePath);
            $image = $contents ? @imagecreatefromstring($contents) : false;

            if ($image === false) {
                return null;
            }

            $image = $this->applyExifOrientation($image, $contents);

            $width = imagesx($image);
            $height = imagesy($image);

            if ($width > self::THUMB_WIDTH) {
                $scaled = imagescale($image, self::THUMB_WIDTH, (int) round($height * self::THUMB_WIDTH / $width));
                imagedestroy($image);
                $image = $scaled === false ? null : $scaled;
            }

            if ($image === null) {
                return null;
            }

            ob_start();
            imagejpeg($image, null, 80);
            $jpeg = (string) ob_get_clean();
            imagedestroy($image);

            $disk->put($thumbPath, $jpeg);

            return $thumbPath;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private function applyExifOrientation(\GdImage $image, string $contents): \GdImage
    {
        if (! function_exists('exif_read_data') || ! str_starts_with($contents, "\xFF\xD8")) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($contents));
        $orientation = (int) ($exif['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        if ($rotated === null || $rotated === false) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }
}

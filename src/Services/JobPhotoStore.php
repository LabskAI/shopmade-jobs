<?php

namespace Shopmade\Jobs\Services;

use App\Models\User;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Shopmade\Jobs\Enums\JobPhotoKind;
use Shopmade\Jobs\Models\Job;
use Shopmade\Jobs\Models\JobPhoto;

/**
 * Writes job photos to the private disk. Originals are re-encoded with GD, which drops EXIF (GPS included) after
 * applying its orientation, and a thumbnail is written beside each one. Files are served by JobPhotoFileController.
 */
class JobPhotoStore
{
    public const int THUMB_WIDTH = 640;

    /**
     * Largest image GD is asked to decode, in pixels (about 40 megapixels).
     */
    public const int MAX_PIXELS = 40_000_000;

    /**
     * @throws ValidationException
     */
    public function store(Job $job, UploadedFile $file, ?User $uploader, JobPhotoKind $kind, ?string $caption = null, bool $clientVisible = true): JobPhoto
    {
        $contents = (string) file_get_contents($file->getRealPath());
        $image = $this->decode($contents);
        $format = match ($file->getMimeType()) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $directory = 'job-photos/'.$job->id;
        $name = Str::random(40);
        $disk = Storage::disk(JobPhoto::DISK);

        $path = $directory.'/'.$name.'.'.$format;
        $disk->put($path, $this->encode($image, $format));

        $thumbPath = $directory.'/'.$name.'-thumb.jpg';
        $disk->put($thumbPath, $this->encode($this->thumbnail($image), 'jpg', 80));

        imagedestroy($image);

        return $job->photos()->create([
            'user_id' => $uploader?->id,
            'path' => $path,
            'thumb_path' => $thumbPath,
            'original_name' => Str::limit($file->getClientOriginalName(), 200, ''),
            'mime' => match ($format) {
                'png' => 'image/png',
                'webp' => 'image/webp',
                default => 'image/jpeg',
            },
            'size' => $disk->size($path),
            'kind' => $kind,
            'caption' => $caption,
            'client_visible' => $clientVisible,
        ]);
    }

    public function delete(JobPhoto $photo): void
    {
        $this->deleteFiles($photo);
        $photo->delete();
    }

    public function deleteFiles(JobPhoto $photo): void
    {
        Storage::disk(JobPhoto::DISK)->delete(array_filter([$photo->path, $photo->thumb_path]));
    }

    /**
     * @throws ValidationException
     */
    private function decode(string $contents): GdImage
    {
        if (! function_exists('imagecreatefromstring')) {
            throw ValidationException::withMessages(['photos' => 'Photo uploads need the PHP GD extension on the server.']);
        }

        $size = @getimagesizefromstring($contents);

        if ($size === false || $size[0] * $size[1] > self::MAX_PIXELS) {
            throw ValidationException::withMessages(['photos' => 'Each photo must be an image of at most 40 megapixels.']);
        }

        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            throw ValidationException::withMessages(['photos' => 'That photo could not be read.']);
        }

        return $this->applyExifOrientation($image, $contents);
    }

    private function thumbnail(GdImage $image): GdImage
    {
        $width = imagesx($image);

        if ($width <= self::THUMB_WIDTH) {
            return $image;
        }

        $scaled = imagescale($image, self::THUMB_WIDTH, (int) round(imagesy($image) * self::THUMB_WIDTH / $width));

        return $scaled === false ? $image : $scaled;
    }

    private function encode(GdImage $image, string $format, int $quality = 85): string
    {
        ob_start();

        match ($format) {
            'png' => imagepng($image),
            'webp' => imagewebp($image, null, $quality),
            default => imagejpeg($image, null, $quality),
        };

        return (string) ob_get_clean();
    }

    private function applyExifOrientation(GdImage $image, string $contents): GdImage
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

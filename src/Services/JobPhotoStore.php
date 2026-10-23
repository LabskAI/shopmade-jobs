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
 * applying its orientation; PNG and WebP keep their transparency. A JPEG thumbnail is written beside each one, with
 * transparent areas flattened onto white. Files are served by JobPhotoFileController.
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
        $format = match ($file->getMimeType()) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $image = $this->decode($contents);

        $directory = 'job-photos/'.$job->id;
        $name = Str::random(40);
        $disk = Storage::disk(JobPhoto::DISK);

        $path = $directory.'/'.$name.'.'.$format;
        $original = $this->encode($image, $format);
        $thumb = $this->encode($this->flatten($this->thumbnail($image)), 'jpg', 80);
        imagedestroy($image);

        $thumbPath = $directory.'/'.$name.'-thumb.jpg';

        if (! $disk->put($path, $original) || ! $disk->put($thumbPath, $thumb)) {
            $disk->delete([$path, $thumbPath]);

            throw ValidationException::withMessages(['photos' => 'The photo could not be saved. Try again.']);
        }

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

    /**
     * Re-encodes stored image bytes in the given format, dropping their metadata. Used to clean photos stored before
     * uploads were re-encoded.
     *
     * @throws ValidationException when GD can't decode or encode the image
     */
    public function reencode(string $contents, string $format): string
    {
        $image = $this->decode($contents);
        $encoded = $this->encode($format === 'jpg' ? $this->flatten($image) : $image, $format);
        imagedestroy($image);

        return $encoded;
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

        if (! $this->fitsInMemory($size[0] * $size[1])) {
            throw ValidationException::withMessages(['photos' => 'That photo is too large for this server to process. Try a smaller one.']);
        }

        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            throw ValidationException::withMessages(['photos' => 'That photo could not be read.']);
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $this->applyExifOrientation($image, $contents);
    }

    /**
     * GD holds about 5 bytes per pixel, and a rotation or thumbnail briefly needs a second copy.
     */
    private function fitsInMemory(int $pixels): bool
    {
        $limit = $this->bytes((string) ini_get('memory_limit'));

        return $limit <= 0 || memory_get_usage() + $pixels * 5 * 2 < $limit;
    }

    private function bytes(string $value): int
    {
        $number = (int) $value;

        return match (strtolower(substr(trim($value), -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /**
     * Copies the image onto white, so transparent areas don't turn black in a JPEG.
     */
    private function flatten(GdImage $image): GdImage
    {
        $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($flat, 0, 0, (int) imagecolorallocate($flat, 255, 255, 255));
        imagealphablending($flat, true);
        imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        return $flat;
    }

    private function thumbnail(GdImage $image): GdImage
    {
        $width = imagesx($image);

        if ($width <= self::THUMB_WIDTH) {
            return $image;
        }

        $scaled = imagescale($image, self::THUMB_WIDTH, (int) round(imagesy($image) * self::THUMB_WIDTH / $width));

        if ($scaled === false) {
            return $image;
        }

        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);

        return $scaled;
    }

    /**
     * @throws ValidationException
     */
    private function encode(GdImage $image, string $format, int $quality = 85): string
    {
        $encoder = match ($format) {
            'png' => 'imagepng',
            'webp' => 'imagewebp',
            default => 'imagejpeg',
        };

        if (! function_exists($encoder)) {
            throw ValidationException::withMessages(['photos' => 'This server cannot save '.strtoupper($format).' photos. Upload a JPEG instead.']);
        }

        ob_start();
        $written = $format === 'png' ? imagepng($image) : $encoder($image, null, $quality);
        $bytes = (string) ob_get_clean();

        if (! $written || $bytes === '') {
            throw ValidationException::withMessages(['photos' => 'That photo could not be saved.']);
        }

        return $bytes;
    }

    private function applyExifOrientation(GdImage $image, string $contents): GdImage
    {
        if (! function_exists('exif_read_data') || ! str_starts_with($contents, "\xFF\xD8")) {
            return $image;
        }

        $stream = fopen('php://memory', 'r+b');

        if ($stream === false) {
            return $image;
        }

        fwrite($stream, $contents);
        rewind($stream);
        $exif = @exif_read_data($stream);
        fclose($stream);

        $orientation = (int) ($exif['Orientation'] ?? 1);

        if ($orientation < 2 || $orientation > 8) {
            return $image;
        }

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, $orientation === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
        }

        $angle = match ($orientation) {
            3 => 180,
            6, 7 => -90,
            5, 8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        if ($rotated === false) {
            return $image;
        }

        imagedestroy($image);
        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);

        return $rotated;
    }
}

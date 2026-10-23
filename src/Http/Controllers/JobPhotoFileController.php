<?php

namespace Shopmade\Jobs\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Shopmade\Jobs\Models\JobPhoto;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a job photo from the private disk to staff, or to a client of the job's customer while it is visible.
 */
class JobPhotoFileController extends Controller
{
    public function __invoke(Request $request, JobPhoto $photo, string $variant): StreamedResponse
    {
        $this->authorize('viewPhoto', [$photo->job, $photo]);

        $path = $variant === 'thumb' && $photo->thumb_path ? $photo->thumb_path : $photo->path;
        $disk = Storage::disk(JobPhoto::DISK);

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

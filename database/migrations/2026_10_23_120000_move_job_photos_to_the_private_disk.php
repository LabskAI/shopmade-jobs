<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Photos used to live on the public disk at a guessable URL. Move them to the private disk, where
 * JobPhotoFileController serves them after an authorization check.
 */
return new class extends Migration
{
    public function up(): void
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');

        DB::table('job_photos')->orderBy('id')->each(function (object $photo) use ($public, $private): void {
            foreach (array_filter([$photo->path, $photo->thumb_path]) as $path) {
                if ($public->exists($path) && ! $private->exists($path)) {
                    $private->writeStream($path, $public->readStream($path));
                    $public->delete($path);
                }
            }
        });
    }

    public function down(): void
    {
        //
    }
};

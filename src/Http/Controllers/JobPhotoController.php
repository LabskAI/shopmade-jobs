<?php

namespace Shopmade\Jobs\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Shopmade\Jobs\Enums\JobPhotoKind;
use Shopmade\Jobs\Models\Job;
use Shopmade\Jobs\Models\JobPhoto;
use Shopmade\Jobs\Services\JobPhotoStore;

class JobPhotoController extends Controller
{
    public const int MAX_KILOBYTES = 8192;

    public function __construct(private readonly JobPhotoStore $store) {}

    public function store(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('managePhotos', $job);

        $data = $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_KILOBYTES, 'dimensions:max_width=10000,max_height=10000'],
            'kind' => ['nullable', Rule::enum(JobPhotoKind::class)],
            'caption' => ['nullable', 'string', 'max:255'],
            'client_visible' => ['sometimes', 'boolean'],
        ]);

        $kind = isset($data['kind']) ? JobPhotoKind::from($data['kind']) : JobPhotoKind::Other;

        foreach ($request->file('photos', []) as $file) {
            $this->store->store($job, $file, $request->user(), $kind, $data['caption'] ?? null, $request->boolean('client_visible', true));
        }

        $count = count($request->file('photos', []));

        return redirect()->route('jobs.show', $job)->with('status', $count === 1 ? 'Photo added.' : $count.' photos added.');
    }

    public function update(Request $request, Job $job, JobPhoto $photo): RedirectResponse
    {
        $this->authorize('managePhotos', $job);

        $data = $request->validate([
            'kind' => ['sometimes', Rule::enum(JobPhotoKind::class)],
            'caption' => ['sometimes', 'nullable', 'string', 'max:255'],
            'client_visible' => ['sometimes', 'boolean'],
        ]);

        $photo->update($data);

        return redirect()->route('jobs.show', $job)->with('status', 'Photo updated.');
    }

    public function destroy(Job $job, JobPhoto $photo): RedirectResponse
    {
        $this->authorize('managePhotos', $job);

        $this->store->delete($photo);

        return redirect()->route('jobs.show', $job)->with('status', 'Photo removed.');
    }
}

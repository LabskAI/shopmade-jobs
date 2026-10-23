<?php

use Illuminate\Support\Facades\Route;
use Shopmade\Jobs\Http\Controllers\JobCommentController;
use Shopmade\Jobs\Http\Controllers\JobController;
use Shopmade\Jobs\Http\Controllers\JobPhotoController;
use Shopmade\Jobs\Http\Controllers\JobPhotoFileController;
use Shopmade\Jobs\Http\Controllers\JobScheduleController;
use Shopmade\Jobs\Http\Controllers\JobStatusController;
use Shopmade\Jobs\Http\Controllers\Portal\PortalJobController;
use Shopmade\Jobs\Http\Controllers\ScheduleController;

Route::middleware(['web', 'auth', 'can:staff.access'])->group(function () {
    Route::get('schedule', ScheduleController::class)->name('schedule.index');
    Route::resource('jobs', JobController::class);
    Route::post('jobs/{job}/status', JobStatusController::class)->name('jobs.status.store');
    Route::post('jobs/{job}/schedule', JobScheduleController::class)->name('jobs.schedule.store');
    Route::post('jobs/{job}/comments', JobCommentController::class)->name('jobs.comments.store');
    Route::post('jobs/{job}/photos', [JobPhotoController::class, 'store'])->name('jobs.photos.store');
    Route::patch('jobs/{job}/photos/{photo}', [JobPhotoController::class, 'update'])->name('jobs.photos.update')->scopeBindings();
    Route::delete('jobs/{job}/photos/{photo}', [JobPhotoController::class, 'destroy'])->name('jobs.photos.destroy')->scopeBindings();
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('job-photos/{photo}/{variant}', JobPhotoFileController::class)->whereIn('variant', ['original', 'thumb'])->name('jobs.photos.file');
});

Route::middleware(['web', 'auth', 'can:portal.access'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('jobs', [PortalJobController::class, 'index'])->name('jobs.index');
    Route::get('jobs/{job}', [PortalJobController::class, 'show'])->name('jobs.show');
    Route::post('jobs/{job}/comments', [PortalJobController::class, 'comment'])->middleware('throttle:20,1')->name('jobs.comments.store');
});

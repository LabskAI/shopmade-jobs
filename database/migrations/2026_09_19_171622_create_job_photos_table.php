<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path');
            $table->string('thumb_path')->nullable();
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->string('kind', 20)->default('other');
            $table->string('caption')->nullable();
            $table->boolean('client_visible')->default(true);
            $table->timestamps();

            $table->index(['job_id', 'client_visible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_photos');
    }
};

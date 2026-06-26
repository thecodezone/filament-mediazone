<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(app(config('media.model'))->getTable(), function (Blueprint $table) {
            $table->id();
            $table->string('disk')->default('media');
            $table->string('directory')->default('');
            $table->string('visibility')->default('public');
            $table->string('name');
            $table->string('slug')->nullable()->unique();
            $table->string('path');
            $table->string('file')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('type')->default('image');
            $table->string('ext')->nullable();
            $table->string('alt')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('caption')->nullable();
            $table->json('exif')->nullable();
            $table->json('crops')->nullable();
            $table->string('url')->nullable();
            $table->timestamps();

            $table->index('disk');
            $table->index('directory');
            $table->index('ext');
            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(app(config('media.model'))->getTable());
    }
};

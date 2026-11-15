<?php

namespace App\Providers\Sandbox;

use App\Contracts\MediaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class LaravelMediaStorage implements MediaStorage
{
    public function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'public');
    }

    public function put(string $path, string $contents): string
    {
        Storage::disk('public')->put($path, $contents);
        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) Storage::disk('public')->delete($path);
    }

    public function url(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}

<?php

namespace App\Providers\Live;

use App\Contracts\MediaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DiskMediaStorage implements MediaStorage
{
    public function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, $this->disk());
    }

    public function put(string $path, string $contents): string
    {
        Storage::disk($this->disk())->put($path, $contents);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) Storage::disk($this->disk())->delete($path);
    }

    public function url(?string $path): ?string
    {
        return $path ? Storage::disk($this->disk())->url($path) : null;
    }

    private function disk(): string
    {
        return (string) config('services.rawijli.media_disk', 'public');
    }
}

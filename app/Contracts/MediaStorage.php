<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface MediaStorage
{
    public function store(UploadedFile $file, string $directory): string;
    public function put(string $path, string $contents): string;
    public function delete(?string $path): void;
    public function url(?string $path): ?string;
}

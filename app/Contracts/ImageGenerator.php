<?php

namespace App\Contracts;

interface ImageGenerator
{
    public function generate(string $workspaceId, string $headline, string $aspectRatio = '4:5'): array;
}

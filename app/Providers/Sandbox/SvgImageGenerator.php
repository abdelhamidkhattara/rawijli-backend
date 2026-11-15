<?php

namespace App\Providers\Sandbox;

use App\Contracts\ImageGenerator;
use App\Contracts\MediaStorage;
use Illuminate\Support\Str;

class SvgImageGenerator implements ImageGenerator
{
    public function __construct(private MediaStorage $storage) {}

    public function generate(string $workspaceId, string $headline, string $aspectRatio = '4:5'): array
    {
        $hue = 250 + (crc32($headline) % 90);
        $safe = htmlspecialchars($headline, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $height = $aspectRatio === '9:16' ? 960 : ($aspectRatio === '1:1' ? 540 : 675);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="540" height="'.$height.'" viewBox="0 0 540 '.$height.'"><defs><linearGradient id="g" x2="1" y2="1"><stop stop-color="hsl('.$hue.' 80% 22%)"/><stop offset="1" stop-color="hsl('.($hue + 40).' 85% 62%)"/></linearGradient></defs><rect width="100%" height="100%" fill="url(#g)"/><circle cx="440" cy="130" r="150" fill="white" opacity=".09"/><text x="42" y="'.($height - 90).'" fill="white" font-family="Arial" font-size="30" font-weight="700">'.Str::limit($safe, 42).'</text><text x="42" y="'.($height - 52).'" fill="white" opacity=".8" font-family="Arial" font-size="15">RAWIJLI · SANDBOX GENERATED</text></svg>';
        $path = $this->storage->put('generated/'.$workspaceId.'/'.Str::uuid().'.svg', $svg);
        return ['path' => $path, 'hue' => $hue, 'provider' => 'sandbox-svg'];
    }
}

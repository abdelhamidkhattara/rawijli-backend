<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class ApiMapper
{
    public static function camel(mixed $value): mixed
    {
        if ($value instanceof CarbonInterface) {
            return $value->toISOString();
        }
        if (! is_array($value)) {
            return $value;
        }
        $result = [];
        foreach ($value as $key => $item) {
            $result[is_string($key) && str_contains($key, '_') ? Str::camel($key) : $key] = self::camel($item);
        }
        return $result;
    }
}

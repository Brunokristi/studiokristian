<?php

namespace App\Services;

use InvalidArgumentException;

class ProjectUploadPathService
{
    public function segments(string $path): array
    {
        if ($path === '' || str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('/^[A-Za-z]:/', $path)) throw new InvalidArgumentException('Invalid upload path.');
        $segments = preg_split('#[\\/]#', $path);
        if (count($segments) > 12) throw new InvalidArgumentException('Upload path exceeds the maximum depth.');
        foreach ($segments as $segment) {
            if ($segment === '' || in_array($segment, ['.', '..'], true) || mb_strlen($segment) > 150 || preg_match('/[\x00-\x1F<>:"|?*]/u', $segment)) throw new InvalidArgumentException('Upload path contains an invalid segment.');
        }
        return $segments;
    }
}
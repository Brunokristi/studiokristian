<?php

namespace App\Services;

class TextDiffService
{
    public function lines(string $old, string $new): array
    {
        $oldLines = preg_split('/\R/', $old) ?: [];
        $newLines = preg_split('/\R/', $new) ?: [];
        $matrix = array_fill(0, count($oldLines) + 1, array_fill(0, count($newLines) + 1, 0));

        for ($oldIndex = count($oldLines) - 1; $oldIndex >= 0; $oldIndex--) {
            for ($newIndex = count($newLines) - 1; $newIndex >= 0; $newIndex--) {
                $matrix[$oldIndex][$newIndex] = $oldLines[$oldIndex] === $newLines[$newIndex]
                    ? 1 + $matrix[$oldIndex + 1][$newIndex + 1]
                    : max($matrix[$oldIndex + 1][$newIndex], $matrix[$oldIndex][$newIndex + 1]);
            }
        }

        $result = [];
        $oldIndex = $newIndex = 0;
        while ($oldIndex < count($oldLines) && $newIndex < count($newLines)) {
            if ($oldLines[$oldIndex] === $newLines[$newIndex]) {
                $result[] = ['type' => 'same', 'text' => $oldLines[$oldIndex]];
                $oldIndex++; $newIndex++;
            } elseif ($matrix[$oldIndex + 1][$newIndex] >= $matrix[$oldIndex][$newIndex + 1]) {
                $result[] = ['type' => 'removed', 'text' => $oldLines[$oldIndex++]];
            } else {
                $result[] = ['type' => 'added', 'text' => $newLines[$newIndex++]];
            }
        }
        while ($oldIndex < count($oldLines)) { $result[] = ['type' => 'removed', 'text' => $oldLines[$oldIndex++]]; }
        while ($newIndex < count($newLines)) { $result[] = ['type' => 'added', 'text' => $newLines[$newIndex++]]; }

        return $result;
    }
}
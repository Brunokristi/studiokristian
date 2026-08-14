<?php

namespace App\Services;

use InvalidArgumentException;

class ContractBlockDocumentService
{
    private const TYPES = ['heading', 'paragraph', 'clause', 'bullet_list', 'numbered_list', 'table', 'information', 'conditional', 'page_break'];

    public function validate(array $document): array
    {
        if (! isset($document['blocks']) || ! is_array($document['blocks'])) throw new InvalidArgumentException('Contract document requires a blocks array.');

        return [
            'version' => (int) ($document['version'] ?? 1),
            'blocks' => array_values(array_map(fn ($block) => $this->validateBlock($block), $document['blocks'])),
        ];
    }

    public function render(array $document, array $values): string
    {
        $document = $this->validate($document);
        return implode('', array_map(fn (array $block) => $this->renderBlock($block, $values), $document['blocks']));
    }

    private function validateBlock(mixed $block): array
    {
        if (! is_array($block) || ! in_array($block['type'] ?? null, self::TYPES, true)) throw new InvalidArgumentException('Unsupported contract block type.');

        $allowed = ['id', 'type', 'content', 'level', 'items', 'rows', 'conditions', 'mode', 'blocks', 'key', 'data', 'meta'];
        if (array_diff(array_keys($block), $allowed)) throw new InvalidArgumentException('Unsupported contract block attribute.');

        if (isset($block['id']) && (! is_string($block['id']) || trim($block['id']) === '')) throw new InvalidArgumentException('Contract block id must be a non-empty string.');

        if (in_array($block['type'], ['bullet_list', 'numbered_list'], true) && empty($block['items']) && isset($block['content']) && is_string($block['content'])) {
            $block['items'] = array_values(array_filter(array_map('trim', preg_split('/\r?\n+/', $block['content']) ?: []), fn ($item) => $item !== ''));
        }

        if (isset($block['conditions'])) {
            foreach ($block['conditions'] as $condition) {
                if (! is_array($condition) || ! preg_match('/^[a-z][a-z0-9_]*$/', $condition['field'] ?? '') || ! in_array($condition['operator'] ?? null, ['equals', 'not_equals', 'checked', 'unchecked'], true)) {
                    throw new InvalidArgumentException('Invalid contract block condition.');
                }
            }
        }

        $block['blocks'] = array_values(array_map(fn ($child) => $this->validateBlock($child), $block['blocks'] ?? []));

        return array_merge($block, [
            'blocks' => $block['blocks'],
        ]);
    }

    private function renderBlock(array $block, array $values): string
    {
        if (! $this->conditionsPass($block, $values)) return '';
        $content = e((string) ($block['content'] ?? ''));
        return match ($block['type']) {
            'heading' => '<h'.min(max((int) ($block['level'] ?? 2), 1), 4).'>'.$content.'</h'.min(max((int) ($block['level'] ?? 2), 1), 4).'>',
            'paragraph', 'clause' => '<p>'.$content.'</p>',
            'information' => '<aside>'.$content.'</aside>',
            'bullet_list', 'numbered_list' => $this->renderList($block),
            'table' => $this->renderTable($block),
            'conditional' => implode('', array_map(fn ($child) => $this->renderBlock($child, $values), $block['blocks'] ?? [])),
            'page_break' => '<div class="page-break"></div>',
        };
    }

    private function conditionsPass(array $block, array $values): bool
    {
        $results = array_map(function ($condition) use ($values) {
            $actual = $values[$condition['field']] ?? null;
            return match ($condition['operator']) {
                'equals' => $actual == ($condition['value'] ?? null),
                'not_equals' => $actual != ($condition['value'] ?? null),
                'checked' => $actual === true || $actual === 1 || $actual === '1',
                'unchecked' => ! ($actual === true || $actual === 1 || $actual === '1'),
            };
        }, $block['conditions'] ?? []);
        return ($block['mode'] ?? 'all') === 'any' ? in_array(true, $results, true) : ! in_array(false, $results, true);
    }

    private function renderList(array $block): string
    {
        $tag = $block['type'] === 'bullet_list' ? 'ul' : 'ol';
        $items = $block['items'] ?? [];
        if (! is_array($items)) {
            $items = array_values(array_filter(array_map('trim', preg_split('/\r?\n+/', (string) ($block['content'] ?? '')) ?: []), fn ($item) => $item !== ''));
        }

        return '<'.$tag.'>'.implode('', array_map(fn ($item) => '<li>'.e((string) $item).'</li>', $items)).'</'.$tag.'>';
    }

    private function renderTable(array $block): string
    {
        return '<table>'.implode('', array_map(fn ($row) => '<tr>'.implode('', array_map(fn ($cell) => '<td>'.e((string) $cell).'</td>', $row)).'</tr>', $block['rows'] ?? [])).'</table>';
    }
}
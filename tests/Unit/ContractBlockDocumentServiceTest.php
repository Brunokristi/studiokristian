<?php

namespace Tests\Unit;

use App\Services\ContractBlockDocumentService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ContractBlockDocumentServiceTest extends TestCase
{
    public function test_validate_preserves_block_ids_and_normalizes_list_items(): void
    {
        $service = new ContractBlockDocumentService();

        $document = $service->validate([
            'version' => '2',
            'blocks' => [
                [
                    'id' => 'intro',
                    'type' => 'bullet_list',
                    'content' => "First item\nSecond item",
                ],
                [
                    'type' => 'conditional',
                    'conditions' => [
                        ['field' => 'approved', 'operator' => 'checked'],
                    ],
                    'blocks' => [
                        [
                            'id' => 'nested-1',
                            'type' => 'paragraph',
                            'content' => 'Nested text',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame(2, $document['version']);
        $this->assertSame('intro', $document['blocks'][0]['id']);
        $this->assertSame(['First item', 'Second item'], $document['blocks'][0]['items']);
        $this->assertSame('nested-1', $document['blocks'][1]['blocks'][0]['id']);
    }

    public function test_validate_rejects_unknown_block_attributes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ContractBlockDocumentService())->validate([
            'blocks' => [
                [
                    'type' => 'paragraph',
                    'content' => 'Hello',
                    'unexpected' => true,
                ],
            ],
        ]);
    }
}
<?php

namespace Tests\Feature\Client;

use Illuminate\Support\Facades\View;
use Tests\TestCase;

class ContractPdfRenderingTest extends TestCase
{
    public function test_contract_pdf_view_renders_semantic_html_body(): void
    {
        $contract = (object) [
            'title' => 'Dokument',
            'version' => '1.0',
            'number' => 'SK-001',
            'rendered_content' => '<h2>Section</h2><ul><li>One</li><li>Two</li></ul>',
        ];

        $html = View::make('pdf.contract', ['contract' => $contract])->render();

        $this->assertStringContainsString('<main><h2>Section</h2><ul><li>One</li><li>Two</li></ul></main>', $html);
        $this->assertStringNotContainsString('&lt;h2&gt;', $html);
    }

    public function test_accepted_contract_pdf_view_renders_semantic_html_body(): void
    {
        $contract = (object) [
            'title' => 'Dokument',
            'version' => '1.0',
            'number' => 'SK-001',
            'rendered_content' => '<p>Body</p>',
            'project' => (object) [
                'company' => (object) ['name' => 'Studio Kristian'],
            ],
            'content_hash' => 'hash',
        ];

        $contact = (object) [
            'name' => 'Anna Kovacova',
            'position' => 'Konateľka',
            'email' => 'anna@example.test',
        ];

        $html = View::make('pdf.accepted-contract', [
            'contract' => $contract,
            'contact' => $contact,
            'acceptedAt' => now('UTC'),
        ])->render();

        $this->assertStringContainsString('<main><p>Body</p></main>', $html);
        $this->assertStringNotContainsString('&lt;p&gt;Body&lt;/p&gt;', $html);
    }
}
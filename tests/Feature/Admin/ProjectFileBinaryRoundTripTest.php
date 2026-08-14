<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class ProjectFileBinaryRoundTripTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_pdf_lifecycle_is_byte_preserving_and_metadata_points_to_real_storage(): void
    {
        [$project, $admin] = $this->projectAndAdmin();

        $pdfBytes = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Count 1 /Kids [3 0 R] >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
        $originalSize = strlen($pdfBytes);
        $originalHash = hash('sha256', $pdfBytes);

        $upload = UploadedFile::fake()->createWithContent('test.pdf', $pdfBytes);

        $response = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post("/admin/client-portal/api/projects/{$project->id}/files", [
                'client_visible' => true,
                'files' => [$upload],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('uploaded_count', 1);
        $response->assertJsonPath('failed_count', 0);

        $file = ProjectFile::query()->where('project_id', $project->id)->firstOrFail();

        $this->assertSame($project->id, $file->project_id);
        $this->assertSame('test.pdf', $file->original_filename);
        $this->assertSame('test.pdf', $file->display_name);
        $this->assertSame('pdf', $file->extension);
        $this->assertNotEmpty($file->mime_type);
        $this->assertSame('local', $file->disk);
        $this->assertNotEmpty($file->storage_path);
        $this->assertSame($originalSize, (int) $file->size);

        $this->assertTrue(Storage::disk($file->disk)->exists($file->storage_path));
        $this->assertSame($originalSize, Storage::disk($file->disk)->size($file->storage_path));

        $storedBytes = Storage::disk($file->disk)->get($file->storage_path);
        $this->assertTrue(str_starts_with($storedBytes, '%PDF-'));
        $this->assertSame($originalHash, hash('sha256', $storedBytes));

        $open = $this->actingAs($admin)
            ->get("/admin/client-portal/api/projects/{$project->id}/files/{$file->id}/open");

        $open->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $open->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', (string) $open->headers->get('Content-Disposition'));
        $openBytes = $open->streamedContent();
        $this->assertSame($originalSize, strlen($openBytes));
        $this->assertSame($originalHash, hash('sha256', $openBytes));

        $download = $this->actingAs($admin)
            ->get("/admin/client-portal/api/projects/{$project->id}/files/{$file->id}/download");

        $download->assertOk();
        $this->assertStringContainsString('attachment', (string) $download->headers->get('Content-Disposition'));
        $downloadBytes = $download->streamedContent();
        $this->assertSame($originalSize, strlen($downloadBytes));
        $this->assertSame($originalHash, hash('sha256', $downloadBytes));
    }

    public function test_binary_round_trip_preserves_bytes_for_common_file_types(): void
    {
        [$project, $admin] = $this->projectAndAdmin();

        $fixtures = $this->fixtures();
        $uploads = [];

        foreach ($fixtures as $name => $bytes) {
            $uploads[] = UploadedFile::fake()->createWithContent($name, $bytes);
        }

        $upload = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post("/admin/client-portal/api/projects/{$project->id}/files", [
                'client_visible' => true,
                'files' => $uploads,
            ]);

        $upload->assertCreated();
        $upload->assertJsonPath('uploaded_count', count($fixtures));
        $upload->assertJsonPath('failed_count', 0);

        $storedFiles = ProjectFile::query()
            ->where('project_id', $project->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(count($fixtures), $storedFiles);

        foreach ($storedFiles as $file) {
            $this->assertArrayHasKey($file->original_filename, $fixtures);

            $original = $fixtures[$file->original_filename];
            $originalHash = hash('sha256', $original);
            $originalSize = strlen($original);

            $storedBytes = Storage::disk($file->disk ?: 'local')->get($file->storage_path);
            $storedHash = hash('sha256', $storedBytes);

            $this->assertSame($originalSize, strlen($storedBytes), "Stored size mismatch for {$file->original_filename}");
            $this->assertSame($originalHash, $storedHash, "Stored hash mismatch for {$file->original_filename}");
            $this->assertSame($originalSize, (int) $file->size, "DB size mismatch for {$file->original_filename}");

            $download = $this->actingAs($admin)
                ->get("/admin/client-portal/api/projects/{$project->id}/files/{$file->id}/download");

            $download->assertOk();
            $this->assertStringContainsString('attachment', (string) $download->headers->get('Content-Disposition'));
            $downloadedBytes = $download->streamedContent();
            $this->assertSame($originalSize, strlen($downloadedBytes), "Downloaded size mismatch for {$file->original_filename}");
            $this->assertSame($originalHash, hash('sha256', $downloadedBytes), "Downloaded hash mismatch for {$file->original_filename}");

            $open = $this->actingAs($admin)
                ->get("/admin/client-portal/api/projects/{$project->id}/files/{$file->id}/open");

            $open->assertOk();
            $this->assertNotEmpty((string) $open->headers->get('Content-Type'));
            $openedBytes = $open->streamedContent();
            $this->assertSame($originalSize, strlen($openedBytes), "Open size mismatch for {$file->original_filename}");
            $this->assertSame($originalHash, hash('sha256', $openedBytes), "Open hash mismatch for {$file->original_filename}");
        }

        $logo = $storedFiles->firstWhere('original_filename', 'logo.svg');
        $this->assertNotNull($logo);

        $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->patchJson("/admin/client-portal/api/projects/{$project->id}/files/{$logo->id}", [
                'name' => 'logo-final',
            ])
            ->assertOk();

        $renamed = ProjectFile::query()->findOrFail($logo->id);
        $this->assertSame('logo-final.svg', $renamed->original_filename);

        $renamedDownload = $this->actingAs($admin)
            ->get("/admin/client-portal/api/projects/{$project->id}/files/{$renamed->id}/download");

        $renamedDownload->assertOk();
        $this->assertSame(
            hash('sha256', $fixtures['logo.svg']),
            hash('sha256', $renamedDownload->streamedContent())
        );

        $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->deleteJson("/admin/client-portal/api/projects/{$project->id}/files/{$renamed->id}")
            ->assertOk();

        $this->assertDatabaseMissing('project_files', ['id' => $renamed->id]);
        Storage::disk($renamed->disk ?: 'local')->assertMissing($renamed->storage_path);

        Storage::disk('local')->deleteDirectory('client-portal/projects/' . $project->id . '/files');
    }

    private function fixtures(): array
    {
        $svg = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 20 20\"><rect width=\"20\" height=\"20\" fill=\"#f97316\"/></svg>";

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9YvKkAAAAASUVORK5CYII=',
            true
        );

        $jpg = base64_decode(
            '/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxAQEBAQEA8QDw8QEA8QDw8QEA8QFREWFhURFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMtNygtLisBCgoKDg0OGxAQGzclHyU3LS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAAQABAMBIgACEQEDEQH/xAAXAAEBAQEAAAAAAAAAAAAAAAAAAQID/8QAFhEBAQEAAAAAAAAAAAAAAAAAAAER/9oADAMBAAIQAxAAAAGVwZ3/xAAVEAEBAAAAAAAAAAAAAAAAAAAAEf/aAAgBAQABBQJf/8QAFREBAQAAAAAAAAAAAAAAAAAAABH/2gAIAQMBAT8BX//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8BX//Z',
            true
        );

        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Count 1 /Kids [3 0 R] >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

        $txt = "Hello file system\n";
        $csv = "name,value\nlogo,1\n";

        $docx = $this->zipBytes([
            '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>',
            'word/document.xml' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"></w:document>',
        ]);

        $xlsx = $this->zipBytes([
            '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>',
            'xl/workbook.xml' => '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"></workbook>',
        ]);

        $zip = $this->zipBytes([
            'readme.txt' => 'archive payload',
        ]);

        return [
            'logo.svg' => $svg,
            'photo.png' => $png ?: '',
            'photo.jpg' => $jpg ?: '',
            'document.pdf' => $pdf,
            'notes.txt' => $txt,
            'data.csv' => $csv,
            'document.docx' => $docx,
            'sheet.xlsx' => $xlsx,
            'archive.zip' => $zip,
        ];
    }

    private function zipBytes(array $entries): string
    {
        $path = storage_path('app/private/testing-' . Str::uuid() . '.zip');

        $zip = new ZipArchive();
        $opened = $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $this->assertTrue($opened === true, 'Unable to create temporary zip fixture.');

        foreach ($entries as $entry => $content) {
            $zip->addFromString($entry, $content);
        }

        $zip->close();

        $bytes = (string) file_get_contents($path);
        @unlink($path);

        return $bytes;
    }

    private function projectAndAdmin(): array
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $company = Company::query()->create(['name' => 'Client Company']);
        $project = Project::query()->create([
            'company_id' => $company->id,
            'name' => 'Website',
            'url' => 'website-' . Str::lower(Str::random(8)),
        ]);

        return [$project, $admin];
    }
}

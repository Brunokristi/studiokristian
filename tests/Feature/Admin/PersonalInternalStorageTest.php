<?php

namespace Tests\Feature\Admin;

use App\Models\CompanyStorageFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PersonalInternalStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_coworker_can_open_and_use_personal_internal_storage(): void
    {
        Storage::fake('local');
        $coworker = User::factory()->create(['role' => 'coworker']);

        $this->actingAs($coworker)
            ->get('/admin/client-portal/internal-storage')
            ->assertOk();

        $response = $this->actingAs($coworker)
            ->post('/admin/client-portal/coworker-api/internal-storage/files', [
                'file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.0.user_id', $coworker->id);

        $this->assertStringContainsString('/coworker-api/internal-storage/files/', $response->json('data.0.download_url'));

        $file = CompanyStorageFolder::query()->findOrFail($response->json('data.0.id'));
        $this->assertStringStartsWith("client-portal/internal-storage/{$coworker->id}/files/", $file->storage_path);

        $this->actingAs($coworker)
            ->get("/admin/client-portal/coworker-api/internal-storage/files/{$file->id}/download")
            ->assertOk();

        $this->actingAs($coworker)
            ->getJson('/admin/client-portal/coworker-api/internal-storage')
            ->assertOk()
            ->assertJsonCount(1, 'folders');
    }

    public function test_users_only_see_and_change_their_own_storage(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true, 'role' => 'admin']);
        $otherAdmin = User::factory()->create(['is_admin' => true, 'role' => 'admin']);
        $coworker = User::factory()->create(['role' => 'coworker']);

        $adminFile = $this->actingAs($admin)
            ->post('/admin/client-portal/api/internal-storage/files', [
                'file' => UploadedFile::fake()->create('admin.pdf', 10, 'application/pdf'),
            ])
            ->assertCreated()
            ->json('data.0.id');

        $this->actingAs($admin)
            ->getJson('/admin/client-portal/api/internal-storage')
            ->assertJsonCount(1, 'folders');

        $this->actingAs($otherAdmin)
            ->getJson('/admin/client-portal/api/internal-storage')
            ->assertOk()
            ->assertJsonCount(0, 'folders');

        $this->actingAs($coworker)
            ->getJson('/admin/client-portal/coworker-api/internal-storage')
            ->assertOk()
            ->assertJsonCount(0, 'folders');

        $this->actingAs($coworker)
            ->get("/admin/client-portal/coworker-api/internal-storage/files/{$adminFile}/download")
            ->assertNotFound();

        $this->actingAs($otherAdmin)
            ->get("/admin/client-portal/api/internal-storage/files/{$adminFile}/open")
            ->assertNotFound();

        // Saving an empty tree must not delete another user's items.
        $this->actingAs($otherAdmin)
            ->putJson('/admin/client-portal/api/internal-storage/structure', ['folders' => []])
            ->assertOk();

        $this->assertDatabaseHas('company_storage_folders', ['id' => $adminFile, 'deleted_at' => null]);

        // Uploading into someone else's folder is rejected.
        $folderId = CompanyStorageFolder::query()->create([
            'user_id' => $admin->id,
            'type' => 'folder',
            'name' => 'Private',
        ])->id;

        $this->actingAs($coworker)
            ->postJson('/admin/client-portal/coworker-api/internal-storage/files', [
                'folder_id' => $folderId,
                'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
            ])
            ->assertUnprocessable();
    }
}

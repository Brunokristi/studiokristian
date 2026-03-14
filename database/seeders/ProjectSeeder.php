<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $projects = [
            [
                'name' => 'Parkfive',
                'url' => 'parkfive',
                'summary' => 'Web solutions that perform, convert and scale.',
                'hex_color' => '#133EB4',
                'logo_path' => '/assets/logo_white.svg',
                'images' => [
                    ['path' => '/assets/img1.jpg', 'description' => 'Project preview one', 'sort_order' => 1],
                    ['path' => '/assets/card_front.svg', 'description' => 'Project preview two', 'sort_order' => 2],
                    ['path' => '/assets/card_front.svg', 'description' => 'Project preview three', 'sort_order' => 3],
                ],
                'features' => [
                    ['title' => 'What services do you offer?', 'description' => 'I create visual identities, websites and digital experiences.', 'sort_order' => 1],
                    ['title' => 'Do you work internationally?', 'description' => 'Yes, I work with clients from all around the world.', 'sort_order' => 2],
                    ['title' => 'What is your design process?', 'description' => 'My design process is collaborative and iterative, ensuring the best results for my clients.', 'sort_order' => 3],
                ],
            ],
            [
                'name' => 'Kaq',
                'url' => 'kaq',
                'summary' => 'Brand-first digital experience with measurable outcomes.',
                'hex_color' => '#4E6F3D',
                'logo_path' => '/assets/kaq_logo.svg',
                'images' => [
                    ['path' => '/assets/kaq_logo.svg', 'description' => 'Brand mark', 'sort_order' => 1],
                    ['path' => '/assets/card_front.svg', 'description' => 'Landing page', 'sort_order' => 2],
                ],
                'features' => [
                    ['title' => 'Visual direction', 'description' => 'Developed a clear visual system and conversion-focused layout.', 'sort_order' => 1],
                    ['title' => 'Performance', 'description' => 'Improved loading behavior and mobile usability.', 'sort_order' => 2],
                ],
            ],
        ];

        foreach ($projects as $projectData) {
            $project = Project::query()->updateOrCreate([
                'url' => $projectData['url'],
            ], [
                'name' => $projectData['name'],
                'summary' => $projectData['summary'],
                'hex_color' => $projectData['hex_color'] ?? null,
                'logo_path' => $projectData['logo_path'] ?? null,
            ]);

            $project->images()->delete();
            $project->features()->delete();
            $project->images()->createMany($projectData['images']);
            $project->features()->createMany($projectData['features']);
        }
    }
}

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
                'name_translations' => [
                    'en' => 'Parkfive',
                    'sk' => 'Parkfive',
                ],
                'url' => 'parkfive',
                'summary' => 'Web solutions that perform, convert and scale.',
                'summary_translations' => [
                    'en' => 'Web solutions that perform, convert and scale.',
                    'sk' => 'Webove riesenia, ktore prinasaju vykon, konverzie a rast.',
                ],
                'hex_color' => '#133EB4',
                'logo_path' => '/assets/logo_white.svg',
                'images' => [
                    [
                        'path' => '/assets/img1.jpg',
                        'description' => 'Project preview one',
                        'description_translations' => [
                            'en' => 'Project preview one',
                            'sk' => 'Ukazka projektu jedna',
                        ],
                        'sort_order' => 1,
                    ],
                    [
                        'path' => '/assets/card_front.svg',
                        'description' => 'Project preview two',
                        'description_translations' => [
                            'en' => 'Project preview two',
                            'sk' => 'Ukazka projektu dva',
                        ],
                        'sort_order' => 2,
                    ],
                    [
                        'path' => '/assets/card_front.svg',
                        'description' => 'Project preview three',
                        'description_translations' => [
                            'en' => 'Project preview three',
                            'sk' => 'Ukazka projektu tri',
                        ],
                        'sort_order' => 3,
                    ],
                ],
                'features' => [
                    [
                        'title' => 'What services do you offer?',
                        'title_translations' => [
                            'en' => 'What services do you offer?',
                            'sk' => 'Ake sluzby ponukujete?',
                        ],
                        'description' => 'I create visual identities, websites and digital experiences.',
                        'description_translations' => [
                            'en' => 'I create visual identities, websites and digital experiences.',
                            'sk' => 'Tvorim vizualne identity, webove stranky a digitalne zazitky.',
                        ],
                        'sort_order' => 1,
                    ],
                    [
                        'title' => 'Do you work internationally?',
                        'title_translations' => [
                            'en' => 'Do you work internationally?',
                            'sk' => 'Pracujete aj medzinarodne?',
                        ],
                        'description' => 'Yes, I work with clients from all around the world.',
                        'description_translations' => [
                            'en' => 'Yes, I work with clients from all around the world.',
                            'sk' => 'Ano, spolupracujem s klientmi z celeho sveta.',
                        ],
                        'sort_order' => 2,
                    ],
                    [
                        'title' => 'What is your design process?',
                        'title_translations' => [
                            'en' => 'What is your design process?',
                            'sk' => 'Aky je vas dizajnovy proces?',
                        ],
                        'description' => 'My design process is collaborative and iterative, ensuring the best results for my clients.',
                        'description_translations' => [
                            'en' => 'My design process is collaborative and iterative, ensuring the best results for my clients.',
                            'sk' => 'Moj proces je kolaborativny a iterativny, aby priniesol najlepsie vysledky.',
                        ],
                        'sort_order' => 3,
                    ],
                ],
            ],
            [
                'name' => 'Kaq',
                'name_translations' => [
                    'en' => 'Kaq',
                    'sk' => 'Kaq',
                ],
                'url' => 'kaq',
                'summary' => 'Brand-first digital experience with measurable outcomes.',
                'summary_translations' => [
                    'en' => 'Brand-first digital experience with measurable outcomes.',
                    'sk' => 'Digitalny zazitok zamerany na znacku s meratelnymi vysledkami.',
                ],
                'hex_color' => '#4E6F3D',
                'logo_path' => '/assets/kaq_logo.svg',
                'images' => [
                    [
                        'path' => '/assets/kaq_logo.svg',
                        'description' => 'Brand mark',
                        'description_translations' => [
                            'en' => 'Brand mark',
                            'sk' => 'Logo znacky',
                        ],
                        'sort_order' => 1,
                    ],
                    [
                        'path' => '/assets/card_front.svg',
                        'description' => 'Landing page',
                        'description_translations' => [
                            'en' => 'Landing page',
                            'sk' => 'Vstupna stranka',
                        ],
                        'sort_order' => 2,
                    ],
                ],
                'features' => [
                    [
                        'title' => 'Visual direction',
                        'title_translations' => [
                            'en' => 'Visual direction',
                            'sk' => 'Vizualny smer',
                        ],
                        'description' => 'Developed a clear visual system and conversion-focused layout.',
                        'description_translations' => [
                            'en' => 'Developed a clear visual system and conversion-focused layout.',
                            'sk' => 'Vytvorili sme jasny vizualny system a konverzne orientovane rozlozenie.',
                        ],
                        'sort_order' => 1,
                    ],
                    [
                        'title' => 'Performance',
                        'title_translations' => [
                            'en' => 'Performance',
                            'sk' => 'Vykon',
                        ],
                        'description' => 'Improved loading behavior and mobile usability.',
                        'description_translations' => [
                            'en' => 'Improved loading behavior and mobile usability.',
                            'sk' => 'Zlepsili sme nacitavanie a pouzitelnost na mobilnych zariadeniach.',
                        ],
                        'sort_order' => 2,
                    ],
                ],
            ],
        ];

        foreach ($projects as $projectData) {
            $project = Project::query()->updateOrCreate([
                'url' => $projectData['url'],
            ], [
                'name' => $projectData['name'],
                'name_translations' => $projectData['name_translations'] ?? null,
                'summary' => $projectData['summary'],
                'summary_translations' => $projectData['summary_translations'] ?? null,
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

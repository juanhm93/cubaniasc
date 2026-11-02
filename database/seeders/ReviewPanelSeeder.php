<?php

namespace Database\Seeders;

use App\Enums\QuizItemType;
use App\Models\Level;
use App\Models\LevelContent;
use App\Models\QuizItem;
use App\Models\QuizOption;
use App\Models\RecommendedSong;
use Illuminate\Database\Seeder;

class ReviewPanelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $basico1 = Level::query()->where('slug', 'basico_1')->first();
        $basico2 = Level::query()->where('slug', 'basico_2')->first();

        if ($basico1 === null) {
            return;
        }

        $basico1->update(['review_duration_seconds' => 1800]);

        if ($basico2 !== null) {
            $basico2->update(['review_duration_seconds' => 1800]);
        }

        $songs = [
            [
                'title' => 'Temba, tumba, timba',
                'artist' => 'Los Van Van',
                'audio_file_path' => 'musica-salsa-casino/Los Van Van - Temba, tumba, timba.mp3',
                'levels' => ['basico_1', 'basico_2'],
            ],
            [
                'title' => 'Dulce Habanera',
                'artist' => 'Sierra Maestra',
                'audio_file_path' => 'musica-salsa-casino/Sierra Maestra - Dulce Habanera.mp3',
                'levels' => ['basico_1'],
            ],
            [
                'title' => 'Lamento Yoruba',
                'artist' => 'Havana D\'Primera',
                'audio_file_path' => 'musica-salsa-casino/Havana D_Primera - Lamento Yoruba.mp3',
                'levels' => ['basico_2'],
            ],
            [
                'title' => 'Somos Cubanos',
                'artist' => 'Los Van Van',
                'audio_file_path' => 'musica-salsa-casino/Los Van van - Somos Cubanos.mp3',
                'levels' => ['basico_1', 'basico_2'],
            ],
        ];

        foreach ($songs as $songData) {
            $levelSlugs = $songData['levels'];
            unset($songData['levels']);

            $song = RecommendedSong::query()->updateOrCreate(
                [
                    'title' => $songData['title'],
                    'artist' => $songData['artist'],
                ],
                ['is_active' => true],
            );

            $song->forceFill([
                'audio_file_path' => $songData['audio_file_path'],
                'audio_file_version' => 1,
            ])->save();

            $levelIds = Level::query()
                ->whereIn('slug', $levelSlugs)
                ->pluck('id');

            $song->levels()->syncWithoutDetaching($levelIds);
        }

        $figures = LevelContent::query()
            ->where('level_id', $basico1->id)
            ->orderBy('sort_order')
            ->get();

        foreach ($figures as $figure) {
            $quizItem = QuizItem::query()->updateOrCreate(
                [
                    'type' => QuizItemType::Figure,
                    'prompt' => $figure->name,
                    'level_id' => $basico1->id,
                ],
                ['is_active' => true]
            );

            $this->seedQuizOptions($quizItem, $figure->description ?? 'Figura de salsa casino.');
        }

        $funFacts = [
            [
                'prompt' => '¿De dónde proviene la salsa casino?',
                'correct' => 'Cuba, especialmente La Habana.',
                'incorrect' => [
                    'Puerto Rico, en los años 60.',
                    'Nueva York, en los años 40.',
                ],
            ],
            [
                'prompt' => '¿Qué significa "casino" en salsa casino?',
                'correct' => 'El nombre del club social donde se popularizó el estilo.',
                'incorrect' => [
                    'Una variante que se baila solo en casinos.',
                    'Un término para las figuras en rueda.',
                ],
            ],
        ];

        foreach ($funFacts as $funFact) {
            $quizItem = QuizItem::query()->updateOrCreate(
                [
                    'type' => QuizItemType::FunFact,
                    'prompt' => $funFact['prompt'],
                    'level_id' => null,
                ],
                ['is_active' => true]
            );

            QuizOption::query()
                ->where('quiz_item_id', $quizItem->id)
                ->delete();

            QuizOption::query()->create([
                'quiz_item_id' => $quizItem->id,
                'description' => $funFact['correct'],
                'is_correct' => true,
            ]);

            foreach ($funFact['incorrect'] as $description) {
                QuizOption::query()->create([
                    'quiz_item_id' => $quizItem->id,
                    'description' => $description,
                    'is_correct' => false,
                ]);
            }
        }
    }

    private function seedQuizOptions(QuizItem $quizItem, string $correctDescription): void
    {
        QuizOption::query()
            ->where('quiz_item_id', $quizItem->id)
            ->delete();

        QuizOption::query()->create([
            'quiz_item_id' => $quizItem->id,
            'description' => $correctDescription,
            'is_correct' => true,
        ]);

        $distractors = [
            'Movimiento en línea con cambio de pareja.',
            'Giro doble con pausa en el centro.',
        ];

        foreach ($distractors as $description) {
            QuizOption::query()->create([
                'quiz_item_id' => $quizItem->id,
                'description' => $description,
                'is_correct' => false,
            ]);
        }
    }
}

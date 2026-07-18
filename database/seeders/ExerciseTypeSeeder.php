<?php

namespace Database\Seeders;

use App\Enums\ExerciseIcon;
use App\Enums\MuscleGroup;
use App\Models\ExerciseType;
use Illuminate\Database\Seeder;

class ExerciseTypeSeeder extends Seeder
{
    /**
     * The global movement catalogue — every row here has a null user_id, so it
     * is visible to everyone and owned by no one.
     *
     * Colour is not listed per row: it comes from the muscle group, so the
     * dashboard breakdown reads as groups rather than confetti. Same reasoning
     * as CategorySeeder colouring by theme, but enforced in code here because
     * MuscleGroup::color() already holds the mapping.
     *
     * Khmer is filled in for all of them — the app ships bilingual, and a row
     * with no Khmer name silently falls back to English mid-list.
     *
     * @return array<int, array{0: string, 1: string, 2: MuscleGroup, 3: ExerciseIcon}>
     */
    private function types(): array
    {
        return [
            // --- Chest --------------------------------------------------------
            ['Bench Press', 'លើកទម្ងន់លើកាំជណ្តើរ', MuscleGroup::Chest, ExerciseIcon::Dumbbell],
            ['Incline Press', 'លើកទម្ងន់ជម្រាល', MuscleGroup::Chest, ExerciseIcon::ArrowUp],
            ['Push-up', 'ដកដង្ហើមរុញ', MuscleGroup::Chest, ExerciseIcon::Accessibility],
            ['Chest Fly', 'ចលនាបើកទ្រូង', MuscleGroup::Chest, ExerciseIcon::MoveHorizontal],

            // --- Back ---------------------------------------------------------
            ['Deadlift', 'លើកទម្ងន់ពីដី', MuscleGroup::Back, ExerciseIcon::Weight],
            ['Pull-up', 'ទាញខ្លួនឡើង', MuscleGroup::Back, ExerciseIcon::ArrowUp],
            ['Barbell Row', 'ទាញដងទម្ងន់', MuscleGroup::Back, ExerciseIcon::MoveHorizontal],
            ['Lat Pulldown', 'ទាញចុះក្រោម', MuscleGroup::Back, ExerciseIcon::ArrowDown],

            // --- Legs ---------------------------------------------------------
            ['Squat', 'អង្គុយចុះឡើង', MuscleGroup::Legs, ExerciseIcon::Weight],
            ['Leg Press', 'រុញទម្ងន់ដោយជើង', MuscleGroup::Legs, ExerciseIcon::MoveVertical],
            ['Lunge', 'ជំហានវែង', MuscleGroup::Legs, ExerciseIcon::Footprints],
            ['Calf Raise', 'លើកកែងជើង', MuscleGroup::Legs, ExerciseIcon::ArrowUp],

            // --- Shoulders ----------------------------------------------------
            ['Overhead Press', 'រុញទម្ងន់លើក្បាល', MuscleGroup::Shoulders, ExerciseIcon::ArrowUp],
            ['Lateral Raise', 'លើកដៃទៅចំហៀង', MuscleGroup::Shoulders, ExerciseIcon::MoveHorizontal],
            ['Face Pull', 'ទាញឆ្ពោះមុខ', MuscleGroup::Shoulders, ExerciseIcon::Target],

            // --- Arms ---------------------------------------------------------
            ['Bicep Curl', 'កោងដៃ', MuscleGroup::Arms, ExerciseIcon::Dumbbell],
            ['Tricep Extension', 'លាតដៃក្រោយ', MuscleGroup::Arms, ExerciseIcon::MoveVertical],
            ['Hammer Curl', 'កោងដៃបែបញញួរ', MuscleGroup::Arms, ExerciseIcon::Dumbbell],

            // --- Core ---------------------------------------------------------
            ['Plank', 'ទ្រទ្រង់ខ្លួន', MuscleGroup::Core, ExerciseIcon::Timer],
            ['Crunch', 'កោងពោះ', MuscleGroup::Core, ExerciseIcon::Activity],
            ['Leg Raise', 'លើកជើង', MuscleGroup::Core, ExerciseIcon::ArrowUp],

            // --- Full body ----------------------------------------------------
            ['Burpee', 'បឺពី', MuscleGroup::FullBody, ExerciseIcon::Flame],
            ['Kettlebell Swing', 'យោលទម្ងន់', MuscleGroup::FullBody, ExerciseIcon::Zap],
            ['Clean and Press', 'លើកនិងរុញ', MuscleGroup::FullBody, ExerciseIcon::Weight],

            // --- Cardio -------------------------------------------------------
            ['Running', 'រត់', MuscleGroup::Cardio, ExerciseIcon::Footprints],
            ['Walking', 'ដើរ', MuscleGroup::Cardio, ExerciseIcon::Footprints],
            ['Cycling', 'ជិះកង់', MuscleGroup::Cardio, ExerciseIcon::Bike],
            ['Swimming', 'ហែលទឹក', MuscleGroup::Cardio, ExerciseIcon::Waves],
            ['Rowing', 'អុំទូក', MuscleGroup::Cardio, ExerciseIcon::Anchor],
            ['Hiking', 'ដើរឡើងភ្នំ', MuscleGroup::Cardio, ExerciseIcon::Mountain],
            ['Jump Rope', 'លោតខ្សែ', MuscleGroup::Cardio, ExerciseIcon::HeartPulse],

            // Kept last: the fallback for anything that fits nowhere above.
            ['Other', 'ផ្សេងៗ', MuscleGroup::FullBody, ExerciseIcon::CircleDashed],
        ];
    }

    public function run(): void
    {
        foreach ($this->types() as [$en, $km, $group, $icon]) {
            /*
             * Matched on the English name *and* a null user_id: the English name
             * is the stable identifier across re-seeds (a JSON column cannot be
             * matched on as a whole), and the null scope stops a re-seed from
             * reaching into a user's own "Running" and overwriting it.
             *
             * Upsert rather than insert, so running this twice does not
             * duplicate the catalogue.
             */
            $type = ExerciseType::query()
                ->whereNull('user_id')
                ->whereJsonContains('name->en', $en)
                ->first() ?? new ExerciseType;

            $type->setTranslations('name', ['en' => $en, 'km' => $km]);
            $type->muscle_group = $group;
            $type->is_cardio = $group === MuscleGroup::Cardio;
            $type->color = $group->color();
            $type->icon = $icon;
            $type->user_id = null;
            $type->save();
        }
    }
}

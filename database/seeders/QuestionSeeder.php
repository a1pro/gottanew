<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Goal\Goal;
use App\Models\Question\Question;
use App\Models\Question\QuestionOption;

class QuestionSeeder extends Seeder
{
    public function run(): void
    {
        $goal = Goal::where('goal_id','health-fitness')->first();

        /*
        |--------------------------------------------------------------------------
        | Question 1
        |--------------------------------------------------------------------------
        */

        $q1 = Question::create([
            'goal_id' => $goal->id,
            'question' => 'How would you rate your current fitness level?',
            'type' => 'multiple-choice',
            'order' => 1
        ]);

        QuestionOption::insert([
            [
                'question_id' => $q1->id,
                'option_text' => 'Beginner - Just starting my fitness journey'
            ],
            [
                'question_id' => $q1->id,
                'option_text' => 'Intermediate - I exercise regularly but want to improve'
            ],
            [
                'question_id' => $q1->id,
                'option_text' => 'Advanced - I have a solid fitness routine'
            ]
        ]);

        /*
        |--------------------------------------------------------------------------
        | Question 2
        |--------------------------------------------------------------------------
        */

        Question::create([
            'goal_id' => $goal->id,
            'question' => 'What is your main fitness goal?',
            'type' => 'open-ended',
            'placeholder' => 'Lose weight, build muscle, run a marathon...',
            'order' => 2
        ]);

        /*
        |--------------------------------------------------------------------------
        | Question 3
        |--------------------------------------------------------------------------
        */

        Question::create([
            'goal_id' => $goal->id,
            'question' => 'Do you have any health conditions or injuries?',
            'type' => 'open-ended',
            'placeholder' => 'Describe any health considerations...',
            'order' => 3
        ]);

        /*
        |--------------------------------------------------------------------------
        | Question 4
        |--------------------------------------------------------------------------
        */

        Question::create([
            'goal_id' => $goal->id,
            'question' => 'What motivates you most to stay healthy?',
            'type' => 'open-ended',
            'placeholder' => 'Share what drives you...',
            'order' => 4
        ]);
    }
}
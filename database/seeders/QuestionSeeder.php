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
        $goals = Goal::where('is_active', 1)->get();

        foreach ($goals as $goal) {

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
                    'option_text' => 'Beginner - Just starting my journey'
                ],
                [
                    'question_id' => $q1->id,
                    'option_text' => 'Intermediate - I am progressing steadily'
                ],
                [
                    'question_id' => $q1->id,
                    'option_text' => 'Advanced - I already have strong experience'
                ]
            ]);

            /*
            |--------------------------------------------------------------------------
            | Question 2
            |--------------------------------------------------------------------------
            */

            Question::create([
                'goal_id' => $goal->id,
                'question' => 'What is your main goal in this area?',
                'type' => 'open-ended',
                'placeholder' => 'Describe what you want to achieve...',
                'order' => 2
            ]);

            /*
            |--------------------------------------------------------------------------
            | Question 3
            |--------------------------------------------------------------------------
            */

            Question::create([
                'goal_id' => $goal->id,
                'question' => 'What challenges are you currently facing?',
                'type' => 'open-ended',
                'placeholder' => 'Share any obstacles...',
                'order' => 3
            ]);

            /*
            |--------------------------------------------------------------------------
            | Question 4
            |--------------------------------------------------------------------------
            */

            Question::create([
                'goal_id' => $goal->id,
                'question' => 'What motivates you to improve in this area?',
                'type' => 'open-ended',
                'placeholder' => 'Share your motivation...',
                'order' => 4
            ]);
        }
    }
}
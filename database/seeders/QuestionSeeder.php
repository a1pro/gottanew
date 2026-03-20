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
            $q1 = Question::updateOrCreate(
                [
                    'goal_id' => $goal->id,
                    'order' => 1,
                ],
                [
                    'question' => 'How would you rate your current fitness level?',
                    'type' => 'multiple-choice',
                    'placeholder' => null,
                    'is_active' => 1,
                ]
            );

            QuestionOption::updateOrCreate(
                [
                    'question_id' => $q1->id,
                    'option_text' => 'Beginner - Just starting my journey',
                ],
                []
            );

            QuestionOption::updateOrCreate(
                [
                    'question_id' => $q1->id,
                    'option_text' => 'Intermediate - I am progressing steadily',
                ],
                []
            );

            QuestionOption::updateOrCreate(
                [
                    'question_id' => $q1->id,
                    'option_text' => 'Advanced - I already have strong experience',
                ],
                []
            );

            /*
            |--------------------------------------------------------------------------
            | Question 2
            |--------------------------------------------------------------------------
            */
            Question::updateOrCreate(
                [
                    'goal_id' => $goal->id,
                    'order' => 2,
                ],
                [
                    'question' => 'What is your main goal in this area?',
                    'type' => 'open-ended',
                    'placeholder' => 'Describe what you want to achieve...',
                    'is_active' => 1,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Question 3
            |--------------------------------------------------------------------------
            */
            Question::updateOrCreate(
                [
                    'goal_id' => $goal->id,
                    'order' => 3,
                ],
                [
                    'question' => 'What challenges are you currently facing?',
                    'type' => 'open-ended',
                    'placeholder' => 'Share any obstacles...',
                    'is_active' => 1,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Question 4
            |--------------------------------------------------------------------------
            */
            Question::updateOrCreate(
                [
                    'goal_id' => $goal->id,
                    'order' => 4,
                ],
                [
                    'question' => 'What motivates you to improve in this area?',
                    'type' => 'open-ended',
                    'placeholder' => 'Share your motivation...',
                    'is_active' => 1,
                ]
            );
        }
    }
}
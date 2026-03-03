<?php
namespace App\Models\Analytics;

use Illuminate\Database\Eloquent\Model;

class ConversationTheme extends Model
{
    protected $fillable = [
        'user_id','theme_name','theme_description',
        'first_mentioned_at','last_mentioned_at',
        'mention_count','sentiment_trend',
        'importance_score','related_goals','session_ids'
    ];

    protected $casts = [
        'sentiment_trend' => 'array',
        'related_goals' => 'array',
        'session_ids' => 'array'
    ];
}
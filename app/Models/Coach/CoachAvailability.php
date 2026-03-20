<?php
namespace App\Models\Coach;

use Illuminate\Database\Eloquent\Model;

class CoachAvailability extends Model
{

    protected $table = 'coach_availability';
    protected $fillable = [
        'coach_id','day_of_week','start_time',
        'end_time','timezone','is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function coach()
    {
        return $this->belongsTo(Coach::class);
    }
}
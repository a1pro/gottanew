<?php
namespace App\Models\Coach;

use Illuminate\Database\Eloquent\Model;

class CoachingPackage extends Model
{
    protected $fillable = [
        'coach_id','name','description',
        'duration_minutes','price_amount',
        'price_currency','coin_cost',
        'package_type','features','is_active'
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean'
    ];

    public function coach()
    {
        return $this->belongsTo(Coach::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Thinker extends Model
{
    use HasFactory;

    protected $table = 'thinkers';

    protected $fillable = [
        'user_id',
        'national_id',
        'first_name',
        'last_name',
        'father_name',
        'date_of_birth',
        'phone',
        'metadata',
        'fyda_verified_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'fyda_verified_at' => 'datetime',
        'date_of_birth' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

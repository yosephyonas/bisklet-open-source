<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'national_id',
        'first_name',
        'last_name',
        'father_name',
        'email',
        'phone',
        'date_of_birth',
        'role',
        'password',
        'fyda_verified_at',
        'last_login_at',
        'metadata',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string|\UnitEnum>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'fyda_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'date_of_birth' => 'date',
            'password' => 'hashed',
            'metadata' => 'array',
            'role' => UserRole::class,
        ];
    }

    public function rides()
    {
        return $this->hasMany(Ride::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function fydaVerifications()
    {
        return $this->hasMany(FydaVerification::class);
    }

    public function notifications()
    {
        return $this->hasMany(UserNotification::class);
    }
}

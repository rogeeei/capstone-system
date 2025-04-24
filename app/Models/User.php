<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // Set the primary key to 'user_id'
    protected $primaryKey = 'user_id';

    // Set the incrementing property to false because 'user_id' is not auto-incrementing
    public $incrementing = false;

    // Set the key type to 'string' since 'user_id' is a string
    protected $keyType = 'string';


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'firstname',
        'middle_name',
        'lastname',
        'suffix',
        'email',
        'phone_number',
        'birthdate',
        'brgy',
        'purok',
        'municipality',
        'province',
        'role',
        'image_path',
        'approved',
        'username',
        'password',
        'confirmed_password',

        
    ];

 public function reports()
{
    return $this->hasMany(Report::class, 'user_id', 'user_id');
}

public function equipment()
{
    return $this->hasMany(Equipment::class, 'user_id', 'user_id');
}

public function medicines()
{
    return $this->hasMany(Medicine::class, 'user_id', 'user_id');
}
 public function services()
    {
        return $this->belongsToMany(Service::class, 'barangay_services', 'brgy', 'service_id', 'brgy', 'id');
    }


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        // 'password' => 'hashed',
        'approved' => 'boolean',
    ];
}

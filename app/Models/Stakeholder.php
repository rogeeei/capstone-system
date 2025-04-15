<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Stakeholder extends Authenticatable
{
    use HasFactory, HasApiTokens;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stakeholders';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'agency_name', 
        'barangay', 
        'municipality', 
        'province', 
        'is_approved',
        'username',
        'password',
        'confirmed_password',

    ];

  

    
}

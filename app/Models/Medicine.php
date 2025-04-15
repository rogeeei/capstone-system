<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'medicine';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'medicine_id';

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
        'medicine_id',
        'name',
        'usage_description',
        'unit',
        'quantity',
        'expiration_date',
        'medicine_status',
        'date_acquired',
        'user_id',
    ];
// Medicine.php
public function citizens()
{
    return $this->belongsToMany(CitizenDetails::class, 'citizen_medicine', 'medicine_id', 'citizen_id')
                ->withPivot('quantity')  
                ->withTimestamps();
}
public function transactions()
{
    return $this->hasMany(Transaction::class, 'medicine_id');
}

public function user()
{
    return $this->belongsTo(User::class, 'user_id', 'user_id'); // Ensure correct foreign key
}

    public function medicines()
    {
        return $this->hasMany(Medicine::class, 'user_id', 'user_id'); 
    }
  
}

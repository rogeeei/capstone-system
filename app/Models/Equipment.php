<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'equipment';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'equipment_id';
    
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
        'name', 'description', 'quantity', 'location', 'condition', 'date_acquired', 'equipment_id', 'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}

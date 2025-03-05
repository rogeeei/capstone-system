<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CitizenHistory extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'citizen_history';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'citizen_history_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
    'citizen_id',
    'firstname',
    'middle_name',
    'lastname',
    'purok', 
    'barangay', 
    'municipality', 
    'province', 
    'date_of_birth',
    'gender',
    'blood_type',
    'height',
    'weight',
    'allergies',
    'medication',
    'emergency_contact_name',
    'emergency_contact_no',
];


    /**
     * Get the citizen that owns the history.
     */
    public function citizen()
    {
        return $this->belongsTo(CitizenDetails::class, 'citizen_id', 'citizen_id');
    }

     
public function services()
{
    return $this->belongsToMany(Services::class, 'citizen_history_service', 'citizen_history_id', 'service_id');
}

}

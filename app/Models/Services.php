<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; 

class Services extends Model
{
    use HasFactory;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'services';


    protected $fillable = [
        'name',
        'icon',
        'description'
    ];

    public function citizens()
    {
        return $this->belongsToMany(CitizenDetails::class, 'citizen_service', 'service_id', 'citizen_id');
    }
    public function citizenHistories()
{
    return $this->belongsToMany(CitizenHistory::class, 'citizen_history_service', 'service_id', 'citizen_history_id');
}
public function services()
{
    return $this->belongsToMany(Services::class, 'citizen_service', 'citizen_id', 'services_id');
}
public function transactions()
{
    return $this->hasMany(Transaction::class, 'service_id');
}
 public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id'); // 🔥 Ensure correct column
    }

     public function barangays()
    {
        return $this->belongsToMany(User::class, 'barangay_services', 'service_id', 'brgy', 'id', 'brgy');
    }

}

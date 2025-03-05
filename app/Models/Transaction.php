<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = ['citizen_id', 'service_id', 'transaction_date'];
public function service()
{
    return $this->belongsTo(Services::class, 'service_id');
}

public function medicines()
{
    return $this->belongsToMany(Medicine::class, 'citizen_medicine', 'transaction_id', 'medicine_id')
        ->withPivot('quantity', 'unit');
}

public function citizen()
{
    return $this->belongsTo(CitizenDetails::class, 'citizen_id', 'citizen_id');
}

}

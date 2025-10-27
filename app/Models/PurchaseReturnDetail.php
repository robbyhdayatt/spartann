<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturnDetail extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     * Use $guarded = [] or explicitly list fillable fields.
     * Since you added 'receiving_detail_id', ensure it's fillable.
     * Using $guarded = ['id'] already makes all other fields fillable.
     */
    protected $guarded = ['id'];

    /**
     * Get the part associated with the return detail.
     */
    public function part()
    {
        return $this->belongsTo(Part::class);
    }

    /**
     * Get the purchase return document associated with this detail.
     */
    public function purchaseReturn()
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    /**
     * Get the receiving detail from which this return originated.
     */
    public function receivingDetail()
    {
        // Assumes you added the foreign key 'receiving_detail_id'
        return $this->belongsTo(ReceivingDetail::class, 'receiving_detail_id');
    }
}
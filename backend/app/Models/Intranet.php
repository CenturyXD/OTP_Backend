<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Intranet extends Model
{
    //
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'intranet';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ip_address',
        'customer',
        'contact',
        'phone',
        'remark',
        'status',
        'updated_by',
    ];

    /**
     * Get the user who last updated the record.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by')->select('id', 'name');
    }

}

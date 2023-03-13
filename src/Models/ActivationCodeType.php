<?php

namespace Omconnect\Pay\Models;

use Illuminate\Database\Eloquent\Model;

class ActivationCodeType extends Model
{
    protected $fillable = [
        'code',
        'description',
        'max_count',
    ];

    protected $casts = [
        'max_count' => 'integer',
    ];

    public function activationCodes()
    {
        return $this->hasMany(ActivationCode::class);
    }
}
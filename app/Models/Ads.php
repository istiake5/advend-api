<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ads extends Model
{
    use HasFactory;
    protected $fillable = [
        'link',
        'image',
        'title',
        'description',
        'country',
        'region',
        'appearance_type',
        'user_id'
    ];

}

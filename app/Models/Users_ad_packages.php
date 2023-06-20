<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Users_ad_packages extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'package_id'
    ];
}

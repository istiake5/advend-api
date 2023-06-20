<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Owed_amounts;

class OwedAmountsController extends Controller
{
    public static function owed_amount($user_id){
        return Owed_amounts::where('user_id', $user_id)->sum('amount');
    }
}

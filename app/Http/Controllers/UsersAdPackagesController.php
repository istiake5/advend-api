<?php

namespace App\Http\Controllers;
use App\Models\Users_ad_packages;
use Illuminate\Http\Request;

class UsersAdPackagesController extends Controller
{
    public static function add_user_package($user_id,$package){
        $user_package = new Users_ad_packages();
        $user_package->user_id = $user_id;
        $user_package->package_id = $package;
        $user_package->save();
    }
}

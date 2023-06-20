<?php

namespace App\Http\Controllers;
use App\Models\Ads_packages;
use App\Models\Users_ad_packages;
use Illuminate\Http\Request;

class AdsPackagesController extends Controller
{
    public static function get_user_package(){
        $user_id = auth()->user()->id;
        $package = Users_ad_packages::where('user_id', $user_id)->first(['package_id'])->package_id;
        $allowed = Ads_packages::where('id', $package)->first(['allowed_ads'])->allowed_ads;
        return [
            'id' => $package,
            'allowed_ads' => $allowed,
        ];
    }
    public static function get_packages_data(){
        $packages = Ads_packages::all();
        return $packages;
    }
}

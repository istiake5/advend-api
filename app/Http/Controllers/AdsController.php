<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ads;
use App\Models\Domains;
use Illuminate\Support\Facades\DB;

class AdsController extends Controller
{
    public static function ads_data()
    {
        $user_id = auth()->user()->id;
        $ads = Ads::where('user_id', $user_id)->get();
        foreach ($ads as $ad) {
            $ad->views = DB::table('shown_ads')->where('ad_id', $ad->id)->count();
        }
        return $ads;
    }
    public static function add_ad(Request $request)
    {
        //check if user is paused
        if (auth()->user()->paused == 1) {
            return response()->json([
                'valid' => 0,
                'message' => 'Your account is paused.'
            ], 200);
        }
        $request->validate([
            'link' => 'required|string',
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'title' => 'required|string',
            'description' => 'required|string'
        ]);

        //check if user allowed to add ad according to packages
        $user_id = auth()->user()->id;
        $user_package = DB::table('users_ad_packages')->where('user_id', $user_id)->first(['package_id']);
        $package = DB::table('ads_packages')->where('id', $user_package->package_id)->first(['allowed_ads']);
        $ads_count = DB::table('ads')->where('user_id', $user_id)->count();
        if ($ads_count >= $package->allowed_ads) {
            return response()->json([
                'valid' => 0,
                'message' => 'You have reached your ads limit.'
            ], 200);
        }
        //check if ad's link in domain's registered
        $domain = parse_url($request->link, PHP_URL_HOST);
        if (!Domains::where('name', $domain)->first(['id']) || Domains::where('name', $domain)->first(['verified'])->verified == 0 ) {
            return response()->json([
                'valid' => 0,
                'message' => 'Ad\'s link is not in your registered domains'
            ], 200);
        }

        // add ad
        $image = $request->file('file');
        //check if image extension is allowed
        $allowedfileExtension = ['jpg', 'png', 'jpeg'];
        $extension = $image->getClientOriginalExtension();
        $check = in_array($extension, $allowedfileExtension);
        if (!$check) {
            return response()->json([
                'valid' => 0,
                'message' => 'Image extension is not allowed'
            ], 200);
        }

        $new_name = rand() . '.' . $image->getClientOriginalExtension();
        $image->move(public_path('ads_images'), $new_name);
        $ad = new Ads([
            'title' => $request->title,
            'description' => $request->description,
            'link' => $request->link,
            'image' => $new_name,
            'user_id' => auth()->user()->id,
            'country' => $request->country,
            'region' => $request->region,
            'appearence_type' => $request->appearence_type
        ]);
        $ad->save();
        $ad->views = 0;
        return response()->json([
            'valid' => 1,
            'data' => ['ad' => $ad]
        ], 200);
    }
    public static function update_ad(Request $request, $id)
    {
        //check if user is paused
        if (auth()->user()->paused == 1) {
            return response()->json([
                'valid' => 0,
                'message' => 'Your account is paused.'
            ], 200);
        }
        $request->validate([
            'link' => 'required|string',
            'title' => 'required|string',
            'description' => 'required|string'
        ]);

        $ad = Ads::find($id);
        $ad->title = $request->title;
        $ad->description = $request->description;
        $ad->link = $request->link;
        $ad->country = $request->country;
        $ad->region = $request->region;
        $ad->appearence_type = $request->appearence_type;
        $ad->save();
        return response()->json([
            'valid' => 1
        ], 200);
    }
    public static function delete_ad(Request $request)
    {
        //check if user is paused
        if (auth()->user()->paused == 1) {
            return response()->json([
                'valid' => 0,
                'message' => 'Your account is paused.'
            ], 200);
        }
        Ads::where('id', $request->id)->where('user_id', auth()->user()->id)->delete();
        //remove one ad from switch_packages_paused_ads if exists
        //get id of ad in switch_packages_paused_ads
        $user_id = auth()->user()->id;
        $ad_id = DB::table('switch_packages_paused_ads')->where('user_id', $user_id)->where('ad_id', $request->id)->first(['id']);
        if ($ad_id) {
            DB::table('switch_packages_paused_ads')->where('id', $ad_id->id)->delete();
            //unpause ad
            Ads::where('id', $request->id)->update(['paused' => 0]);
        }

        return response()->json([
            'valid' => 1
        ], 200);
    }

    public static function pause_user_ads($user_id)
    {
        Ads::where('user_id', $user_id)->update(['paused' => 1]);
    }
    public static function unpause_user_ads($user_id)
    {
        Ads::where('user_id', $user_id)->update(['paused' => 0]);
    }
    public static function pause_ad_of_switch($id)
    {
        Ads::where('id', $id)->update(['paused' => 1]);
        //add to switch_packages_paused_ads
        $user_id = auth()->user()->id;
        DB::table('switch_packages_paused_ads')->insert([
            'user_id' => $user_id,
            'ad_id' => $id
        ]);
    }
    public static function unpause_ad_of_switch($id)
    {
        Ads::where('id', $id)->update(['paused' => 0]);
        //remove from switch_packages_paused_ads
        $user_id = auth()->user()->id;
        DB::table('switch_packages_paused_ads')->where('user_id', $user_id)->where('ad_id', $id)->delete();
    }
}

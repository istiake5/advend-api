<?php

namespace App\Http\Controllers;

use App\Models\referral_system;
use Illuminate\Http\Request;

class ReferralSystemController extends Controller
{
    public static function get_referral_data($user_id){
        return referral_system::where('user_id',$user_id)->first(['code','credits','number_of_uses']);
    }
    public static function add_referral_code($user_id,$referral_code){
        $referral_code = new  referral_system([
            'code' => $referral_code,
            'user_id' => $user_id
        ]);
        $referral_code->save();
    }
    public static function apply_referral_code($user_id,$referral_code){
        //check if referral code exists
        $referral_code_exists = referral_system::where('code',$referral_code)->first(['user_id']);
        if($referral_code_exists){
            //update credits of user
            referral_system::where('user_id',$referral_code_exists->user_id)->increment('credits',10);
            referral_system::where('user_id',$referral_code_exists->user_id)->increment('number_of_uses',1);
            return true;
        }
        return false;
    }
    public static function update_referral_credits($user_id,$credits){
        referral_system::where('user_id',$user_id)->update(['credits' => $credits]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Domains;
use App\Models\User;
use App\Models\Payments;
use App\Models\Owed_amounts;
use App\Models\Deserved_amounts;
use App\Models\Ads;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class SnippetsController extends Controller
{
    public static function verify_key($key)
    {
        //check if key exists
        $domain = Domains::where('key', $key)->first(['id']);
        if ($domain) {
            // Verify if domain is new
            $domain_id = $domain->id;
            if (Domains::where('id', $domain_id)->first(['verified'])->verified == 0) {
                Domains::where('id', $domain_id)->update(['verified' => 1]);
            }
            return true;
        }
        return false;
    }
    public function verify_domain(Request $request)
    {
        $key = $request->key;
        $domain = $request->domain;
        //check if key exists
        $domain_data = Domains::where([
            ['key', $key],
            ['name', $domain]
        ])->first(['user_id']);
        if ($domain_data) {
            $domain_owner = $domain_data->user_id;
            //check if paused or not
            $paused = User::where('id', $domain_owner)->first(['paused'])->paused;
            if ($paused == 0) {
                return ['valid' => 1];
            }
            return ['valid' => 0, 'paused' => 1];
        }
        return ['valid' => 0, 'paused' => 0];
    }
    public function click(Request $request)
    {
        $key = $request->key;
        $from_key = $request->from_key;
        $domain_id = Domains::where('key', $key)->first(['id'])->id;
        $from_domain_id = Domains::where('key', $from_key)->first(['id']);
        //Check if domain exists
        if ($from_domain_id) {
            $from_domain_id = $from_domain_id->id;
        } else {
            return false;
        }
        //Check if click exists from that ip in last 24 hours
        if (DB::table('clicks')->where([
            ['domain_id', $domain_id],
            ['ip', $request->ip()],
            ['created_at', '>=', date('Y-m-d H:i:s', strtotime('-24 hours'))]
        ])->exists()) {
            return false;
        }
        // Insert to total clicks table
        if (DB::table('total_clicks')->where([
            ['domain_id', $domain_id]
        ])->exists()) { // If domain exists
            DB::table('total_clicks')->where([
                ['domain_id', $domain_id],
            ])->increment('clicks', 1);
        } else { // If domain doesn't exist
            DB::table('total_clicks')->insert([
                'domain_id' => $domain_id,
                'clicks' => 1
            ]);
        }
        // Insert to clicks
        DB::table('clicks')->insert([
            'domain_id' => $domain_id,
            'from_domain_id' => $from_domain_id,
            'ip' => $request->ip(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function check_if_clicked(Request $request)
    {
        $key = $request->key;
        $domain_id = Domains::where('key', $key)->first(['id'])->id;
        //Check if click exists from that ip in last 24 hours
        if (DB::table('clicks')->where([
            ['domain_id', $domain_id],
            ['ip', $request->ip()],
            ['created_at', '>=', date('Y-m-d H:i:s', strtotime('-24 hours'))]
        ])->exists()) {
            $clicked_from_key = Domains::where('id', DB::table('clicks')->where([
                ['domain_id', $domain_id],
                ['ip', $request->ip()],
                ['created_at', '>=', date('Y-m-d H:i:s', strtotime('-24 hours'))]
            ])->first(['from_domain_id'])->from_domain_id)->first(['key'])->key;
            return ['clicked' => 1, 'clicked_from' => $clicked_from_key];
        }
        return ['clicked' => 0];
    }

    public function save_payment(Request $request)
    {
        $clicked_from_key = $request->clicked_from_key;
        $key = $request->key;
        $price = $request->price;
        $domain_id = Domains::where('key', $key)->first(['id'])->id;
        $clicked_from_domain_id = Domains::where('key', $clicked_from_key)->first(['id'])->id;
        $domain_owner = Domains::where('id', $domain_id)->first(['user_id'])->user_id;
        $clicked_from_domain_owner = Domains::where('id', $clicked_from_domain_id)->first(['user_id'])->user_id;
        if ($domain_owner == $clicked_from_domain_owner) {
            return false;
        }
        // Insert to payments
        $payment = new Payments;
        $payment->domain_id = $domain_id;
        $payment->from_domain_id = $clicked_from_domain_id;
        $payment->amount = $price;
        $payment->save();
        // Owed & Deserved amounts
        $domain_owner = Domains::where('id', $domain_id)->first(['user_id'])->user_id;
        $owed_amount = new Owed_amounts;
        $owed_amount->user_id = $domain_owner;
        // check if user has credits
        $user_credits = ReferralSystemController::get_referral_data($domain_owner)->credits;
        if ($user_credits > 0) {
            $owed_amount->owed_to_advend_amount = $price * (3 / 100) - $user_credits;
            if ( $owed_amount->owed_to_advend_amount < 0) {
                $owed_amount->owed_to_advend_amount = 0;
            }
            // Update user credits
            $remaining_credits = $user_credits - $price * (3 / 100);
            if ($remaining_credits < 0) {
                $remaining_credits = 0;
            }
            ReferralSystemController::update_referral_credits($domain_owner, $remaining_credits);
        }else{
            $owed_amount->owed_to_advend_amount = $price * (3 / 100);
        }
        $owed_amount->amount = $price * (7 / 100);
        $owed_amount->owed_to_id = $clicked_from_domain_owner;
        $owed_amount->save();
    }

    public function ads(Request $request)
    {
        $key = $request->key;
        $domain_id = Domains::where('key', $key)->first(['id'])->id;
        $domain_owner = Domains::where('id', $domain_id)->first(['user_id'])->user_id;
        $ads = Ads::where('user_id', '!=', $domain_owner);
        if (count($ads->get()) > 3) {
            $ads = $ads->get()->random(3);
        } else {
            $ads = $ads->all();
        }
        foreach ($ads as $ad) {
            DB::table('shown_ads')->insert([
                'domain_id' => $domain_id,
                'ad_id' => $ad->id
            ]);
        }
        return $ads;
    }
}

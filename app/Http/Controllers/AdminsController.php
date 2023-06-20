<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminsController extends Controller
{
    public function check_login_token(Request $request)
    {
        $token = $request->token;
        if (DB::table('admin_login_tokens')->where('token', $token)->exists()) {
            return ['valid' => 1];
        }
        return ['valid' => 0];
    }
    public function login(Request $request)
    {
        $Admin = DB::table('admins')->where('username', $request->username)->first();
        if ($Admin) {
            if (password_verify($request->password, $Admin->password)) {
                $token = bin2hex(random_bytes(64));
                if (DB::table('admin_tokens')->where('admin_id', $Admin->id)->exists()) {
                    DB::table('admin_tokens')->where('admin_id', $Admin->id)->update(['token' => $token]);
                } else {
                    DB::table('admin_tokens')->insert([
                        'admin_id' => $Admin->id,
                        'token' => $token
                    ]);
                }
                return ['valid' => 1, 'token' => $token];
            }
            return ['valid' => 0];
        }
        return ['valid' => 0];
    }
    public static function verify_token($token)
    {
        if (DB::table('admin_tokens')->where('token', $token)->exists()) {
            return true;
        }
        return false;
    }
    public function users()
    {
        $users =  DB::table('users')->get(['id', 'username', 'email', 'created_at', 'paused', 'email_verified_at']);
        foreach($users as $user){
            $package_id  = DB::table('users_ad_packages')->where('user_id', $user->id)->first(['package_id'])->package_id;
            $user->package_type = DB::table('ads_packages')->where('id', $package_id)->first(['name'])->name;
            $credits = DB::table('referral_systems')->where('user_id', $user->id)->first(['credits'])->credits ?? 0;
            $user->credits = $credits;
        }
        return $users;
    }
    public function domains()
    {
        $domains = DB::table('domains')->get(['id', 'name', 'key', 'user_id', 'created_at', 'verified']);
        foreach ($domains as $domain) {
            $domain->username = DB::table('users')->where('id', $domain->user_id)->first(['username'])->username;
            $domain->incoming = DB::table('clicks')->where('domain_id', $domain->id)->count();
            $domain->outgoing = DB::table('clicks')->where('from_domain_id', $domain->id)->count();
            $domain->payments = DB::table('payments')->where('domain_id', $domain->id)->sum('amount');
        }
        return $domains;
    }
    public function ads()
    {
        $ads = DB::table('ads')->get(['id', 'title', 'description', 'link', 'image', 'country', 'region', 'created_at', 'user_id']);
        foreach ($ads as $ad) {
            $ad->username = DB::table('users')->where('id', $ad->user_id)->first(['username'])->username;
        }
        return $ads;
    }
    public function payments()
    {
        $payments = DB::table('payments')->get(['id', 'domain_id', 'amount', 'from_domain_id', 'created_at']);
        foreach ($payments as $payment) {
            $payment->username = DB::table('users')->where('id', DB::table('domains')->where('id', $payment->domain_id)->first(['user_id'])->user_id)->first(['username'])->username;
        }
        $total_payments = 0;
        $owed_to_advend = 0;
        $paid = 0;
        $total_payments += DB::table('payments')->sum('amount');
        $owed_to_advend += DB::table('owed_amounts')->sum('amount');
        $paid += DB::table('transactions')->where('type', 1)->sum('amount');

        return [
            'total_payments' => $total_payments,
            'owed_to_advend' => $owed_to_advend,
            'paid' => $paid,
            'payments_data' => $payments
        ];
    }
    public function transactions()
    {
        $transactions = DB::table('transactions')->get(['id', 'amount', 'type', 'created_at','user_id']);
        foreach ($transactions as $transaction) {
           $transaction->username  = Db::table('users')->where('id', $transaction->user_id)->first(['username'])->username;
        }
        return $transactions;
    }
    public function delete_user(){
        $id = request()->id;
        $username = DB::table('users')->where('id', $id)->first(['username'])->username;
        // Delete all domains
        $domains = DB::table('domains')->where('user_id', $id)->get(['id']);
        foreach($domains as $domain){
            $this->delete_domain($domain->id);
        }
        // Delete all ads
        $ads = DB::table('ads')->where('user_id', $id)->get(['id']);
        foreach($ads as $ad){
            $this->delete_ad($ad->id);
        }
        DB::table('users_ad_packages')->where('user_id', $id)->delete();
        DB::table('owed_amounts')->where('user_id', $id)->delete();
        DB::table('deserved_amounts')->where('user_id', $id)->delete();
        DB::table('referral_systems')->where('user_id', $id)->delete();
        DB::table('subscribtion_time')->where('user_id', $id)->delete();
        DB::table('users')->where('id', $id)->delete();
        return ['username' => $username];
    }
    public function pause_user(){
        $id = request()->id;
        $paused = request()->paused;
        DB::table('users')->where('id', $id)->update(['paused' => $paused]);
    }
    public function delete_domain($id=null){
        if(!$id) $id = request()->id;
        DB::table('clicks')->where('domain_id', $id)->delete();
        DB::table('clicks')->where('from_domain_id', $id)->delete();
        DB::table('total_clicks')->where('domain_id', $id)->delete();
        DB::table('payments')->where('domain_id', $id)->delete();
        DB::table('payments')->where('from_domain_id', $id)->delete();
        DB::table('payments')->where('domain_id', $id)->delete();
        DB::table('shown_ads')->where('domain_id', $id)->delete();
        DB::table('domains')->where('id', $id)->delete();

    }
    public function delete_ad($id=null){
        if(!$id) $id = request()->id;
        DB::table('shown_ads')->where('ad_id', $id)->delete();
        DB::table('ads')->where('id', $id)->delete();
    }
    public function give_credits(){
        $id = request()->id;
        $credits = request()->credits;
        //add credits
        DB::table('referral_systems')->where('user_id', $id)->increment('credits', $credits);
        return json_encode(['valid' => 1]);
    }

}

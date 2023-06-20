<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User as UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\DomainsController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\AdsPackagesController;
use App\Http\Controllers\ReferralSystemController;
use App\Models\Domains;
use App\Models\Ads;
use App\Models\Payments;

use Illuminate\Support\Facades\Mail;
use App\Mail\reset_password;
use App\Mail\confirm_email;
use App\Mail\login_token;
use App\Models\Owed_amounts;

class User extends Controller
{
    /**
     * Register a new user.
     *
     * @param  Request  $request
     * @return Response
     */
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string',
                'username' => 'required|string|unique:users',
                'email' => 'required|string|email|unique:users',
                'password' => 'required|string|confirmed',
            ]);
            $user = new UserModel([
                'name' => $request->name,
                'email' => $request->email,
                'username' => $request->username,
                'password' => password_hash($request->password, PASSWORD_DEFAULT)
            ]);
            $user->save();
            $user_id = $user->id;
            //Add to basic plan
            UsersAdPackagesController::add_user_package($user_id, 1);
            //Add to subscribtion_time table
            $date = date('Y-m-d');
            DB::table('subscribtion_time')->insert(['user_id' => $user_id, 'renewal_date' => $date, 'package_id' => 1]);
            //Generate a referral code
            $referral_code =  "advend-" . $this->random_6_digit_number();
            ReferralSystemController::add_referral_code($user_id, $referral_code);
            //Check if registered by referral code
            if ($request->referral_code != null && $request->referral_code != "") {
                $referral_code_used = $request->referral_code;
                $referral =  ReferralSystemController::apply_referral_code($user_id, $referral_code_used);
                //if referral code is set & update the user who referred this user
                if ($referral) {
                    //update the referall_code_used of current user
                    $user->referral_code_used = $referral_code_used;
                    $user->save();
                }
            }
            //return
            return response()->json([
                'valid' => 'Successfully created user!'

            ], 201);
        } catch (ValidationException $e) {
            //return first error
            return response()->json([
                'error' => $e->errors()[array_key_first($e->errors())][0]
            ]);
        }
    }
    public function email_confirmed()
    {
        if (auth()->user()->email_verified_at != null) {
            return response()->json([
                'valid' => 1
            ]);
        } else {
            return response()->json([
                'valid' => 0
            ]);
        }
    }
    public function login(Request $request)
    {
        //login
        $username = $request->username;
        $password = $request->password;
        $user = UserModel::where('username', $username)->first();
        if ($user) {
            if (password_verify($password, $user->password)) {
                //2 step verification
                $token =  bin2hex(random_bytes(80));
                DB::table('two_step_verification_codes')->where('user_id', $user->id)->delete();
                $code =  $this->random_6_digit_number();
                DB::table('two_step_verification_codes')->insert(['user_id' => $user->id, 'code' => $code, 'token' => $token, 'created_at' => now(), 'updated_at' => now()]);
                Mail::to($user->email)->send(new login_token($user->username, $code));

                return response()->json([
                    'token' => $token
                ]);
            } else {
                return response()->json([
                    'error' => 'Invalid data!'
                ]);
            }
        } else {
            return response()->json([
                'error' => 'Invalid data!'
            ]);
        }
    }
    public function verify_login(Request $request)
    {
        $token = $request->token;
        $code = $request->code;
        $user = DB::table('two_step_verification_codes')->where('token', $token)->first();
        if ($user) {
            if ($user->code == $code) {
                $user = UserModel::find($user->user_id);
                DB::table('two_step_verification_codes')->where('user_id', $user->id)->delete();
                return response()->json([
                    'valid' => 'Successfully logged in!',
                    'data' => ['id' => $user->id, 'email' => $user->email, 'token' => $user->createToken('authToken')->plainTextToken]
                ], 201);
            } else {
                return response()->json([
                    'error' => 'Invalid data!'
                ]);
            }
        } else {
            return response()->json([
                'error' => 'Invalid data!'
            ]);
        }
    }

    public function confirm_email(Request $request)
    {
        $email = $request->email;
        $user = UserModel::where('email', $email)->first();
        $token =  bin2hex(random_bytes(16));
        $confirm_code =  bin2hex(random_bytes(16));
        DB::table('email_confirm_tokens')->where('user_id', $user->id)->delete();
        DB::table('email_confirm_tokens')->insert(['user_id' => $user->id, 'token' => $token, 'code' => $confirm_code, 'created_at' => now(), 'updated_at' => now()]);
        Mail::to($request->email)->send(new confirm_email($user->username, $confirm_code));
        return response()->json([
            'token' => $token
        ]);
    }

    public function verify_confirm_email(Request $request)
    {
        $code = $request->code;
        $token = $request->token;
        $user = DB::table('email_confirm_tokens')->where('token', $token)->first();
        if ($user) {
            if ($user->code == $code) {
                $user = UserModel::find($user->user_id);
                $user->email_verified_at = now();
                $user->save();
                DB::table('email_confirm_tokens')->where('user_id', $user->id)->delete();
                return response()->json([
                    'valid' => 1
                ], 201);
            } else {
                return response()->json([
                    'error' => 'Invalid data!'
                ]);
            }
        }
    }


    private function random_6_digit_number()
    {
        return rand(100000, 999999);
    }

    public function pause_account(Request $request)
    {
        $domain_key = $request->key;
        $paused = $request->paused;
        $user_id = Domains::where('key', $domain_key)->first(['user_id']);
        $user = UserModel::find($user_id->user_id);
        if ($user->paused != $paused) {
            $user->paused = $paused;
            $user->save();
        }
    }

    public function reset_password(Request $request)
    {
        $token =  bin2hex(random_bytes(16));
        $confirm_code =  bin2hex(random_bytes(16));
        if (!UserModel::where('email', $request->email)->first()) {
            return response()->json([
                'error' => 'Invalid data!'
            ]);
        }
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        DB::table('password_reset_tokens')->insert(['email' => $request->email, 'token' => $token, 'code' => $confirm_code, 'created_at' => now()]);
        $username = UserModel::where('email', $request->email)->first(['username'])->username;
        Mail::to($request->email)->send(new reset_password($username, $confirm_code));
        return response()->json([
            'token' => $token
        ]);
    }

    public function verify_reset_password(Request $request)
    {
        $code = $request->code;
        $token = $request->token;
        $user = DB::table('password_reset_tokens')->where('token', $token)->first();
        if ($user) {
            if ($user->code == $code) {
                //insert token 2 and return it
                $token2 =  bin2hex(random_bytes(16));
                DB::table('password_reset_tokens')->where('email', $user->email)->update(['token2' => $token2]);
                return response()->json([
                    'token2' => $token2
                ], 201);
            } else {
                return response()->json([
                    'error' => 'Invalid data!'
                ]);
            }
        }
    }

    public function change_password(Request $request)
    {
        $token = $request->token;
        $token2 = $request->token2;
        $password = $request->password;
        $user = DB::table('password_reset_tokens')->where(['token' => $token, 'token2' => $token2])->first();
        if ($user) {
            $user = UserModel::where('email', $user->email)->first();
            $user->password = password_hash($password, PASSWORD_DEFAULT);
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            return response()->json([
                'valid' => 1
            ], 201);
        }
    }

    public function init_data()
    {
        $user_id = auth()->user()->id;
        $user = UserModel::find($user_id);
        $paused = $user->paused;
        $payment_method = DB::table('customer_ids')->where('user_id', $user_id)->count() == 0 ? 0 : 1;
        $package_data = AdsPackagesController::get_user_package();
        $package_id = $package_data['id'];
        $email = $user->email;
        $domains_count = Domains::where('user_id', $user_id)->count();
        $ads_count = Ads::where('user_id', $user_id)->count();
        $total_payments = Payments::join('domains', 'payments.domain_id', '=', 'domains.id')
            ->where('domains.user_id', '=', $user_id)
            ->sum('amount');
        $profits = Owed_amounts::where('owed_to_id', $user_id)->where('paid', '=', 1)->sum('amount') * (70 / 100);
        $referral_data = ReferralSystemController::get_referral_data($user_id);
        return [
            'paused' => $paused,
            'added_payment_method' => $payment_method,
            'package' => $package_id,
            'email' => $email,
            'confirmed' => $user->email_verified_at != null ? 1 : 0,
            'domains' => $domains_count,
            'ads' => $ads_count,
            'payments' => $total_payments,
            'profits' => $profits,
            'referral_code' => $referral_data->code,
            'referral_credits' => $referral_data->credits,
            'referral_uses' => $referral_data->number_of_uses,
        ];
    }
}

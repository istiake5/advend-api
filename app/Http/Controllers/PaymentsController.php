<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payments;
use App\Http\Controllers\OwedAmountsController;
use App\Models\Owed_amounts;
use App\Http\Controllers\DeservedAmountsController;
use App\Http\Controllers\DomainsController;
use App\Http\Controllers\AdsController;
use App\Models\Users_ad_packages;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\payment_faild;
use App\Mail\subscribtion_failed;
use App\Models\Deserved_amounts;
use Exception;




class PaymentsController extends Controller
{
    public static function get_payments_data()
    {
        $user_id = auth()->user()->id;
        $owed = OwedAmountsController::owed_amount($user_id);
        $deserved = DeservedAmountsController::deserved_amount($user_id);
        $domains = DomainsController::get_user_domains($user_id);
        //get amount of payments done
        $total_payments_done = Payments::whereIn('domain_id', $domains)->sum('amount');
        return [
            'costs' => $owed,
            'profits' => $deserved,
            'total_payments' => $total_payments_done
        ];
    }
    public function monthly_collect_payments()
    {
        \Stripe\Stripe::setApiKey('sk_test_4eC39HqLyjWDarjtT1zdp7dc');
        $stripe = new \Stripe\StripeClient('sk_test_51Mc4tzC0OeMEv9hdGxo8CjEtyZ3zD3DZOnHakqPxGAMgm2w6mxrf4PTe5xUlLbFeJf5Nvl0a7Th3VzOXqRcJcB1A00hGSqbfWM');
        // Get owed amounts that are not paid
        $owed_amounts = Owed_amounts::where('paid', 0)->get();
        foreach ($owed_amounts as $owed_amount) {
            $user_id = $owed_amount->user_id;
            //Payment data of user
            $data = DB::table('customer_ids')->where('user_id', $user_id)->get(['customer_id', 'payment_method_id'])->first();
            if (!$data) {
                continue;
            }
            $customer_id = $data->customer_id;
            $payment_method_id = $data->payment_method_id;
            //try to charge 
            try {
                $charge = $stripe->paymentIntents->create(
                    ['amount' => $owed_amount->amount, "payment_method" => $payment_method_id, 'currency' => 'usd', 'customer' => $customer_id, "description" => "Owed Amounts"]
                );
                if ($charge->status != 'succeeded') {
                    $charge->confirm();
                }
                //get created date and convert from timestamp to date
                $created = date('Y-m-d H:i:s', $charge->created);
                //update owed amount to paid
                $owed_amount->paid = 1;
                $owed_amount->save();
                //add to transactions

                DB::table('transactions')->insert(['user_id' => $user_id, 'amount' => $owed_amount->amount, 'type' => 1, 'session_id' => $charge->id, 'created_at' => $created, 'updated_at' => $created]);
                //get transaction id
                $transaction_id = DB::table('transactions')->where('session_id', $charge->id)->first()->id;
                //add to paid owed amounts
                DB::table('paid_owed_amounts')->insert(['owed_amount_id' => $owed_amount->id, 'transactions' => $transaction_id, 'created_at' => $created, 'updated_at' => $created]);
            } catch (\Exception $e) {
                echo $e->getMessage();
                //send email to user
                $user = DB::table('users')->where('id', $user_id)->first();
                $email = $user->email;
                $name = $user->name;
                Mail::to($email)->send(new payment_faild($name));
                // Insert to alerts_sent
                DB::table('alerts_sent')->insert(['user_id' => $user_id, 'type' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                //Pause Ads
                AdsController::pause_user_ads($user_id);
                // Pause user
                DB::table('users')->where('id', $user_id)->update(['paused' => 1]);
            }
        }
    }

    public function switch_package(Request $request)
    {
        $user_id = auth()->user()->id;
        $pckg_id = $request->pckg_id;
        $current_pckg_id = DB::table('users_ad_packages')->where('user_id', $user_id)->first()->package_id;
        //Downgrade
        if ($pckg_id < $current_pckg_id) {
            return $this->downgrade($user_id, $pckg_id);
        } else {
            return $this->upgrade($user_id, $pckg_id);
        }
        /*
        \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
        try {
            if ($pckg_id == 2) {
                $price = 10;
            } else if ($pckg_id == 3) {
                $price = 15;
            }
            // insert to downgrades_subscritions table
            if($pckg_id < $current_pckg_id){
                DB::table('downgrades_subscritions')->insert(['user_id' => $user_id, 'package_id' => $pckg_id]);
            }
            if ($pckg_id != 1) {
                //create payment
                \Stripe\Stripe::setApiKey('sk_test_4eC39HqLyjWDarjtT1zdp7dc');
                $stripe = new \Stripe\StripeClient('sk_test_51Mc4tzC0OeMEv9hdGxo8CjEtyZ3zD3DZOnHakqPxGAMgm2w6mxrf4PTe5xUlLbFeJf5Nvl0a7Th3VzOXqRcJcB1A00hGSqbfWM');
                $data = DB::table('customer_ids')->where('user_id', $user_id)->get(['customer_id', 'payment_method_id'])->first();
                if (!$data) {
                    return;
                }
                $customer_id = $data->customer_id;
                $payment_method_id = $data->payment_method_id;
                $charge = $stripe->paymentIntents->create(
                    ['amount' => $price * 1000, 'currency' => 'usd', 'customer' => $customer_id, "payment_method" => $payment_method_id, "description" => "Switch package"]
                );
                if ($charge->status != 'succeeded') {
                    $charge->confirm();
                }
                if ($charge->status != 'succeeded') {
                    return ['error' => 'Payment failed'];
                }
                //insert to subscribtion_time or update if found
                $renew_date = date('Y-m-d');
                if (DB::table('subscribtion_time')->where('user_id', $user_id)->first()) {
                    DB::table('subscribtion_time')->where('user_id', $user_id)->update(['package_id' => $pckg_id, 'renewal_date' => $renew_date]);
                } else {
                    DB::table('subscribtion_time')->insert(['user_id' => $user_id, 'package_id' => $pckg_id, 'renewal_date' => $renew_date]);
                }
                // insert to transactions
                $created = date('Y-m-d H:i:s');
                DB::table('transactions')->insert(['user_id' => $user_id, 'amount' => $price, 'type' => 2, 'session_id' => $charge->id, 'created_at' => $created, 'updated_at' => $created]);
            }

            //update user package
            Users_ad_packages::where('user_id', auth()->user()->id)->update(['package_id' => $pckg_id]);
            //check if user has exceded ads than the new plan
            $user_ads_count = DB::table('ads')->where('user_id', $user_id)->where('paused', 0)->count();
            $package_ads_count = DB::table('ads_packages')->where('id', $pckg_id)->first()->allowed_ads;
            if ($user_ads_count > $package_ads_count) {
                //keep only the allowed ads and pause the rest
                $ads = DB::table('ads')->where('user_id', $user_id)->orderBy('id', 'desc')->get(['id'])->toArray();
                $ads = array_slice($ads, $package_ads_count);
                foreach ($ads as $ad) {
                    AdsController::pause_ad_of_switch($ad->id);
                }
                return ['valid' => 1, 'warning' => 1];
            } else {
                //check if user has paused ads of switching plans
                $ads = DB::table('switch_packages_paused_ads')->where('user_id', $user_id)->get(['ad_id'])->toArray();
                if ($ads) {
                    //unpause remaining spots
                    $to_unpause_ads = array_slice($ads, 0, $package_ads_count - $user_ads_count);
                    foreach ($to_unpause_ads as $ad) {
                        AdsController::unpause_ad_of_switch($ad->ad_id);
                    }
                }
            }

            return ['valid' => 1, 'warning' => 0];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
        */
    }

    private function downgrade($user_id, $pckg_id)
    {
        // Update subscribtion_time table
        $renew_date = date('Y-m-d');
        DB::table('subscribtion_time')->where('user_id', $user_id)->update(['package_id' => $pckg_id, 'renewal_date' => $renew_date]);
        // insert to downgrades_subscritions table
        DB::table('downgrades_subscritions')->insert(['user_id' => $user_id, 'package_id' => $pckg_id]);
        return ['valid' => 1, 'warning' => 0];
    }

    private function upgrade($user_id, $pckg_id)
    {
        //update check subscribtion_time
        $renew_date = date('Y-m-d');
        DB::table('subscribtion_time')->where('user_id', $user_id)->update(['package_id' => $pckg_id, 'renewal_date' => $renew_date]);
        try {
            \Stripe\Stripe::setApiKey('sk_test_4eC39HqLyjWDarjtT1zdp7dc');
            $stripe = new \Stripe\StripeClient('sk_test_51Mc4tzC0OeMEv9hdGxo8CjEtyZ3zD3DZOnHakqPxGAMgm2w6mxrf4PTe5xUlLbFeJf5Nvl0a7Th3VzOXqRcJcB1A00hGSqbfWM');
            $data = DB::table('customer_ids')->where('user_id', $user_id)->get(['customer_id', 'payment_method_id'])->first();
            if (!$data) {
                return;
            }
            $customer_id = $data->customer_id;
            $payment_method_id = $data->payment_method_id;
            $price = DB::table('ads_packages')->where('id', $pckg_id)->first()->price;
            $charge = $stripe->paymentIntents->create(
                ['amount' => $price * 100, 'currency' => 'usd', 'customer' => $customer_id, "payment_method" => $payment_method_id, "description" => "Switch package"]
            );
            if ($charge->status != 'succeeded') {
                $charge->confirm();
            }
            if ($charge->status != 'succeeded') {
                return ['error' => 'Payment failed'];
            }
            //update subscribtion_time
            $renew_date = date('Y-m-d');
            DB::table('subscribtion_time')->where('user_id', $user_id)->update(['package_id' => $pckg_id, 'renewal_date' => $renew_date]);
            //remove from downgrades_subscritions table if exists
            DB::table('downgrades_subscritions')->where('user_id', $user_id)->delete();
            // insert to transactions
            $created = date('Y-m-d H:i:s');
            DB::table('transactions')->insert(['user_id' => $user_id, 'amount' => $price, 'type' => 2, 'session_id' => $charge->id, 'created_at' => $created, 'updated_at' => $created]);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
        //update user package
        Users_ad_packages::where('user_id', auth()->user()->id)->update(['package_id' => $pckg_id]);
        return ['valid' => 1, 'warning' => 0];
    }

    public function send_alerts()
    {
        // Get all users that have owed_amount from last month and not paid
        $users = DB::table('owed_amounts')
            ->where('owed_amounts.created_at', '<', Carbon::now()->startOfMonth())
            ->where('paid', 0)
            ->join('users', 'owed_amounts.user_id', '=', 'users.id')
            ->select(['users.email', 'users.name', 'users.id'])
            ->distinct()
            ->get();

        foreach ($users as $user) {
            $email = $user->email;
            $name = $user->name;
            //check if not sent 5 emails before
            $count = DB::table('alerts_sent')->where('user_id', $user->id)->where('type', 1)->count();
            if ($count >= 7) {

                if ($count >= 9) {
                    continue;
                }
                //check last email sent time and send email after 5 days
                $last_email_sent_time = DB::table('alerts_sent')->where('user_id', $user->id)->where('type', 1)->orderBy('id', 'desc')->first()->created_at;
                if (Carbon::now()->diffInDays($last_email_sent_time) < 5) {
                    //check for alerts sent count  
                    continue;
                }
            }
            Mail::to($email)->send(new payment_faild($name));
            // Insert to alerts_sent
            DB::table('alerts_sent')->insert(['user_id' => $user->id, 'type' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }
    public function delayed_amounts()
    {
        $user_id = auth()->user()->id;
        //get owed amounts of last month and previous month
        $data = DB::table('owed_amounts')
            ->where('owed_amounts.created_at', '<', Carbon::now()->startOfMonth())
            ->where('paid', 0)
            ->where('user_id', $user_id)
            ->sum('amount');

        return $data;
    }
    public function pay_delayed_amounts()
    {
        $user_id = auth()->user()->id;
        try {
            $user_customer_id = DB::table('customer_ids')->where('user_id', $user_id)->first()->customer_id;
            $user_payment_method_id = DB::table('customer_ids')->where('user_id', $user_id)->first()->payment_method_id;
            $amount = $this->delayed_amounts();
            \Stripe\Stripe::setApiKey('sk_test_4eC39HqLyjWDarjtT1zdp7dc');
            $stripe = new \Stripe\StripeClient('sk_test_51Mc4tzC0OeMEv9hdGxo8CjEtyZ3zD3DZOnHakqPxGAMgm2w6mxrf4PTe5xUlLbFeJf5Nvl0a7Th3VzOXqRcJcB1A00hGSqbfWM');
            $charge = $stripe->paymentIntents->create(
                ['amount' => $amount * 100, 'currency' => 'usd', 'payment_method' => $user_payment_method_id, 'customer' => $user_customer_id, "description" => "Delayed Owed Amounts"]
            );
            if ($charge->status != 'succeeded') {
                $charge->confirm();
            }
            if ($charge->status == 'succeeded') {
                //unpause user
                DB::table('users')->where('id', $user_id)->update(['paused' => 0]);
                //unpause ads
                AdsController::unpause_user_ads($user_id);
                //update owed amount to paid
                DB::table('owed_amounts')
                    ->where('owed_amounts.created_at', '<', Carbon::now()->startOfMonth())
                    ->where('user_id', $user_id)
                    ->update(['paid' => 1]);
                //get time
                $created = date('Y-m-d H:i:s', $charge->created);
                //remove alerts sent
                DB::table('alerts_sent')->where('user_id', $user_id)->where('type', 1)->delete();
                DB::table('transactions')->insert(['user_id' => $user_id, 'amount' => $amount, 'type' => 1, 'session_id' => $charge->id, 'created_at' => $created, 'updated_at' => $created]);
                //get transaction id
                $transaction_id = DB::table('transactions')->where('session_id', $charge->id)->first()->id;
                //add to paid owed amounts
                $owed_amounts =  DB::table('owed_amounts')
                    ->where('owed_amounts.created_at', '<', Carbon::now()->startOfMonth())
                    ->where('user_id', $user_id)->get(['id']);
                foreach ($owed_amounts as $owed_amount) {
                    DB::table('paid_owed_amounts')->insert(['owed_amount_id' => $owed_amount->id, 'transactions' => $transaction_id, 'created_at' => $created, 'updated_at' => $created]);
                }
                return ['valid' => 1, 'message' => 'Payment Succeeded'];
            }
        } catch (\Exception $th) {
            return response()->json(['valid' =>  0, 'message' => $th->getMessage()]);
        }
    }
    public function pay_subscriptions()
    {
        $subscribtions = DB::table('subscribtion_time')->get();
        foreach ($subscribtions as $subscribtion) {
            $renewal_date = $subscribtion->renewal_date;
            $added_month_date = date('Y-m-d', strtotime($renewal_date . ' + 1 month'));
            $user_id = $subscribtion->user_id;
            if ($added_month_date == date('Y-m-d')) {
                $data = DB::table('customer_ids')->where('user_id', $user_id)->first(['customer_id', 'payment_method_id']);
                if (!$data) {
                    //send failed email and switch to basic
                    $user_data = DB::table('users')->where('id', $user_id)->first(['email', 'name']);
                    $email = $user_data->email;
                    $name = $user_data->name;
                    Mail::to($email)->send(new payment_faild($name));
                    //switch to basic
                    DB::table('subscribtion_time')->where('user_id', $user_id)->update(['package_id' => 1]);
                    //delete from downgrades_subscritions
                    DB::table('downgrades_subscritions')->where('user_id', $user_id)->delete();
                    //check if user has exceded ads than the new plan
                    $user_ads_count = DB::table('ads')->where('user_id', $user_id)->where('paused', 0)->count();
                    $package_ads_count = DB::table('ads_packages')->where('id', 1)->first()->allowed_ads;
                    if ($user_ads_count > $package_ads_count) {
                        //keep only the allowed ads and pause the rest
                        $ads = DB::table('ads')->where('user_id', $user_id)->orderBy('id', 'desc')->get(['id'])->toArray();
                        $ads = array_slice($ads, $package_ads_count);
                        foreach ($ads as $ad) {
                            AdsController::pause_ad_of_switch($ad->id);
                        }
                    }
                } else {
                    $user_customer_id = DB::table('customer_ids')->where('user_id', $user_id)->first()->customer_id;
                    $user_payment_method_id = DB::table('customer_ids')->where('user_id', $user_id)->first()->payment_method_id;
                    //check if downgrade subscription exists
                    $downgrade_subscription = DB::table('downgrades_subscritions')->where('user_id', $user_id)->first();
                    if ($downgrade_subscription) {
                        $package_id = $downgrade_subscription->package_id;
                    } else {
                        $package_id = $subscribtion->package_id;
                    }
                    $amount = DB::table('ads_packages')->where('id', $package_id)->first()->price;
                    \Stripe\Stripe::setApiKey('sk_test_4eC39HqLyjWDarjtT1zdp7dc');
                    $stripe = new \Stripe\StripeClient('sk_test_51Mc4tzC0OeMEv9hdGxo8CjEtyZ3zD3DZOnHakqPxGAMgm2w6mxrf4PTe5xUlLbFeJf5Nvl0a7Th3VzOXqRcJcB1A00hGSqbfWM');
                    try {
                        $charge = $stripe->paymentIntents->create(
                            ['amount' => $amount * 100, 'currency' => 'usd', 'payment_method' => $user_payment_method_id, 'customer' => $user_customer_id, "description" => "Subscription Renewal"]
                        );
                        if ($charge->status != 'succeeded') {
                            $charge->confirm();
                        }
                        if ($charge->status == 'succeeded') {
                            //update renewal date
                            DB::table('subscribtion_time')->where('user_id', $user_id)->update(['renewal_date' => date('Y-m-d')]);
                            //remove from downgrades_subscritions if exists
                            DB::table('downgrades_subscritions')->where('user_id', $user_id)->delete();
                            //insert transaction
                            $created = date('Y-m-d H:i:s');
                            DB::table('transactions')->insert(['user_id' => $user_id, 'amount' => $amount, 'type' => 2, 'session_id' => $charge->id, 'created_at' => $created, 'updated_at' => $created]);
                            //update user package if downgraded
                            if ($downgrade_subscription) {
                                DB::table('subscribtion_time')->where('user_id', $user_id)->update(['package_id' => $package_id]);
                                DB::table("users_ad_packages")->where("user_id", $user_id)->update(["package_id" => $package_id]);
                                //check if user has exceded ads than the new plan
                                $user_ads_count = DB::table('ads')->where('user_id', $user_id)->where('paused', 0)->count();
                                $package_ads_count = DB::table('ads_packages')->where('id', $package_id)->first()->allowed_ads;
                                if ($user_ads_count > $package_ads_count) {
                                    //keep only the allowed ads and pause the rest
                                    $ads = DB::table('ads')->where('user_id', $user_id)->orderBy('id', 'desc')->get(['id'])->toArray();
                                    $ads = array_slice($ads, $package_ads_count);
                                    foreach ($ads as $ad) {
                                        AdsController::pause_ad_of_switch($ad->id);
                                    }
                                }
                            }
                        }
                    } catch (\Exception $th) {
                        //send failed email and switch to basic
                        $user_data = DB::table('users')->where('id', $user_id)->first(['email', 'name']);
                        $email = $user_data->email;
                        $name = $user_data->name;
                        Mail::to($email)->send(new subscribtion_failed($name));
                        //switch to basic
                        DB::table('subscribtion_time')->where('user_id', $user_id)->update(['package_id' => 1]);
                        DB::table("users_ad_packages")->where("user_id", $user_id)->update(["package_id" => 1]);
                        //delete from downgrades_subscritions
                        DB::table('downgrades_subscritions')->where('user_id', $user_id)->delete();
                        //check if user has exceded ads than the new plan
                        $user_ads_count = DB::table('ads')->where('user_id', $user_id)->where('paused', 0)->count();
                        $package_ads_count = DB::table('ads_packages')->where('id', 1)->first()->allowed_ads;
                        if ($user_ads_count > $package_ads_count) {
                            //keep only the allowed ads and pause the rest
                            $ads = DB::table('ads')->where('user_id', $user_id)->orderBy('id', 'desc')->get(['id'])->toArray();
                            $ads = array_slice($ads, $package_ads_count);
                            foreach ($ads as $ad) {
                                AdsController::pause_ad_of_switch($ad->id);
                            }
                        }
                    }
                }
            }
        }
    }
    public function send_deserved(){
        //send deserved amount from stripe to user by customer id
        $user_id = auth()->user()->id;
        $deserved_amounts = new DeservedAmountsController();
        $deserved = $deserved_amounts->get_deserved_info();
        $amount = $deserved['amount'] * 100;
        $owed_amount_ids_array = $deserved['owed_amount_ids'];
        $data = DB::table('customer_ids')->where('user_id', $user_id)->first();
        if(!$data){
            return response()->json(['valid' => 0, 'error' => 'No customer id']);
        }
        $customer_id = $data->customer_id;
        $payment_method_id = $data->payment_method_id;
        \Stripe\Stripe::setApiKey('sk_test_4eC39HqLyjWDarjtT1zdp7dc');
        $stripe = new \Stripe\StripeClient('sk_test_51Mc4tzC0OeMEv9hdGxo8CjEtyZ3zD3DZOnHakqPxGAMgm2w6mxrf4PTe5xUlLbFeJf5Nvl0a7Th3VzOXqRcJcB1A00hGSqbfWM');
        try {
            $paymentIntent = $stripe->paymentIntents->create([
                'amount' => $amount,
                'currency' => 'usd',
                'customer' => $customer_id,
                'payment_method' => $payment_method_id
            ]);
            $paymentIntent->confirm();
            //insert to withdrawns json encoded list of owed amounts paid
            DB::table('withdrawns')->insert(['user_id' => $user_id, 'amount' => $amount / 100 ,'owed_amount_ids' => $owed_amount_ids_array, 'created_at' => date('Y-m-d H:i:s')]);
            return response()->json(['valid' => 1]);
        } catch(\Exception $e) {
            return response()->json(['valid' => 0, 'error' => $e->getMessage()]);
        }
        
        
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    public function setup_payment_method(Request $request)
    {
        try {
        $request->validate([
            'card_number' => 'required|int',
            'expiry_month' => 'required|int',
            'expiry_year' => 'required|int',
            'cvc' => 'required|int',
            'first_name' => 'required|string',
        ]);
        \Stripe\Stripe::setApiKey('sk_test_4eC39HqLyjWDarjtT1zdp7dc');
        $stripe = new \Stripe\StripeClient('sk_test_51Mc4tzC0OeMEv9hdGxo8CjEtyZ3zD3DZOnHakqPxGAMgm2w6mxrf4PTe5xUlLbFeJf5Nvl0a7Th3VzOXqRcJcB1A00hGSqbfWM');
        //check if user has customer id
        $customer_id = DB::table('customer_ids')->where('user_id', auth()->user()->id)->first(['customer_id']);
        if($customer_id) return ['valid' => 0, 'error' => 'You already have a payment method setup'];
        
            // Create a PaymentMethod:
            $payment_method = $stripe->paymentMethods->create([
                'type' => 'card',
                'card' => [
                    'number' => $request->card_number,
                    'exp_month' => $request->expiry_month,
                    'exp_year' => $request->expiry_year,
                    'cvc' => $request->cvc
                ],
            ]);
            $payment_method_id = $payment_method->id;
            // Create a Customer:
            $customer = $stripe->customers->create([
                'name' => $request->first_name,
                'payment_method' => $payment_method_id,
                'invoice_settings' => [
                    'default_payment_method' => $payment_method_id,
                ],
            ]);
            // Charge the Customer instead of the card:
            $customer_id = $customer->id;
            //Save Customer Id to DB
            DB::table('customer_ids')->insert([
                'customer_id' => $customer_id,
                'payment_method_id' => $payment_method_id,
                'user_id' => auth()->user()->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            return [
                'valid' => 1
            ];
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'valid' => 0
            ]);
        }
    }
}

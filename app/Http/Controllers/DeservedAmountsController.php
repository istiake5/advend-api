<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Owed_amounts;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
class DeservedAmountsController extends Controller
{
    public static function deserved_amount($user_id){
        return Owed_amounts::where('owed_to_id', $user_id)->sum('amount') * (70/100);
    }
    public function get_deserved_info(){
        $user_id = auth()->user()->id;
        //get total amount from owed which was before day 15 of previous month
       // $deserved = Owed_amounts::where('owed_to_id', $user_id)->where('created_at', '<', date('Y-m-d', strtotime('first day of previous month')));
        $previousMonth = Carbon::now()->subMonth()->startOfMonth()->subDay();
   
        //get withdrawn ids from withdrawns table
        $withdrawn_ids = DB::table('withdrawns')->where('user_id', $user_id)->get(['owed_amount_ids']);
        $total_ids = [];
        foreach($withdrawn_ids as $withdrawn_id){
            $ids = json_decode($withdrawn_id->owed_amount_ids);
            foreach($ids as $id){
                $total_ids[] = $id->id;
            }
        }
        $deserved = Owed_amounts::where('owed_to_id', $user_id)
                      ->where('created_at', '<', $previousMonth->addDays(15))->where('paid',1)->whereNotIn('id', $total_ids);
        $owed_amount_ids = json_encode($deserved->get(['id']));
        $deserved = $deserved->sum('amount') * (70/100);
        return [
            'amount' => $deserved,
            'owed_amount_ids' => $owed_amount_ids
        ];
    }
   
}

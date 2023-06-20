<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Domains;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DomainsController extends Controller
{
    public static function get_domains_data()
    {
        $user_id = auth()->user()->id;
        $domains = Domains::where('user_id', $user_id)->get();
        $clicks = 0;
        $outgoing = 0;
        $incoming = 0;
        foreach ($domains as $domain) {
            if ($domain['verified'] == 1) {
                $incoming_of_domain = DB::table('clicks')->where('domain_id', $domain['id'])->count();
                $outgoing_of_domain = DB::table('clicks')->where('from_domain_id', $domain['id'])->count();
                $domain['incoming'] = $incoming_of_domain;
                $domain['outgoing'] = $outgoing_of_domain;
                $clicks += $incoming_of_domain + $outgoing_of_domain;
                $incoming += $incoming_of_domain;
                $outgoing += $outgoing_of_domain;
            } else {
                $domain['incoming'] = 0;
                $domain['outgoing'] = 0;
            }
        }
        return [
            'domains' => $domains,
            'total_domains' => count($domains),
            'clicks' => $clicks,
            'outgoing' => $outgoing,
            'incoming' => $incoming
        ];
    }

    public static function get_user_domains($user_id)
    {
        $domains =  Domains::where('user_id', $user_id)->get();
        $domain_ids = $domains->pluck('id')->toArray();
        return $domain_ids;
    }

    public static function add_domain(request $request)
    {
         //check if user is paused
         if(auth()->user()->paused == 1){
            return response()->json([
                'valid' => 0,
                'message' => 'Your account is paused.'
            ], 200);
        }
        try {
            $request->validate([
                'name' => 'required|string|unique:domains'
            ]);
            $domain_key = md5(uniqid(rand(), true));

            // if contains http or https remove it
            $domain_name = $request->name;
            if (strpos($domain_name, 'http://') !== false) {
                $domain_name = str_replace('http://', '', $domain_name);
            } else if (strpos($domain_name, 'https://') !== false) {
                $domain_name = str_replace('https://', '', $domain_name);
            }
            $domain = new Domains([
                'name' => $domain_name,
                'user_id' => auth()->user()->id,
                'key' => $domain_key
            ]);
            $domain->save();
            return response()->json([
                'valid' => 1,
                'data' => ['id' => $domain->id, 'key' => $domain->key, 'name' => $domain_name, 'user_id' => $domain->user_id, 'outgoing' => 0, 'incoming' => 0, 'verified' => 0]
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 1
            ]);
        }
    }
    public static function delete_domain(Request $request)
    {
         //check if user is paused
         if(auth()->user()->paused == 1){
            return response()->json([
                'valid' => 0,
                'message' => 'Your account is paused.'
            ], 200);
        }
        Domains::where('id', $request->id)->where('user_id', auth()->user()->id)->delete();
        return response()->json([
            'valid' => 1
        ], 200);
    }
}

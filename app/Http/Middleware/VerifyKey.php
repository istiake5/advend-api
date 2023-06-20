<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
//snippets controller
use App\Http\Controllers\SnippetsController;

class VerifyKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next)
    {

        $key = $request->route('key');
        if ($this->isValidKey($key)) {
            return $next($request);
        }

        return response()->json(['error' => 'Invalid key'], 401);
    }

    private function isValidKey($key)
    {
        return SnippetsController::verify_key($key);
    }
}

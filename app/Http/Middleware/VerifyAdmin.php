<?php

namespace App\Http\Middleware;

use App\Http\Controllers\AdminsController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next)
    {

        $token = $request->header('Admin-Token');
        if ($this->isValidKey($token)) {
            return $next($request);
        }

        return response()->json(['error' => 'Invalid token'], 401);
    }

    private function isValidKey($token)
    {
        return AdminsController::verify_token($token);
    }
}

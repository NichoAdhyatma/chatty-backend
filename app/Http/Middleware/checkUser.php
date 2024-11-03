<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class checkUser
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $Authorization = $request->header('Authorization');
        if (empty($Authorization)) {
            return response()->json([
                'code' => 0,
                'data' => null,
                'message' => 'Authorization is required'
            ], 401);
        }

        $access_token = trim(str_replace('Bearer', '', $Authorization));
        $user = DB::table('users')
            ->select('id', 'name', 'token', 'type', 'expire_date', 'avatar', 'access_token')
            ->where('access_token', $access_token)
            ->first();

        if (empty($user)) {
            return response()->json([
                'code' => 0,
                'data' => null,
                'message' => 'User not found'
            ], 401);
        }

        $expire_date = $user->expire_date;

        if (empty($expire_date)) {
            return response()->json([
                'code' => 0,
                'data' => null,
                'message' => 'Token not valid'
            ], 401);
        }

        if ($expire_date < Carbon::now()) {
            return response()->json([
                'code' => 0,
                'data' => null,
                'message' => 'Token expired'
            ], 401);
        }

        $add_time = Carbon::now()->addDays(5);

        if ($expire_date < $add_time) {
            $add_expire_date = Carbon::now()->addDays(30);
            DB::table('users')
                ->where('access_token', $access_token)
                ->update(['expire_date' => $add_expire_date]);
        }

        $request->user = $user;

        return $next($request);
    }
}

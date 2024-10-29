<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use Exception;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:50',
            'avatar' => 'required',
            'type' => 'required',
            'name' => 'required',
            'open_id' => 'required',
            'phone' => 'max:30',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 0,
                'data' => null,
                'message' => $validator->errors()
            ]);
        }

        try {
            $validated = $validator->validated();

            $map = [];

            $map['type'] = $validated['type'];
            $map['open_id'] = $validated['open_id'];

            $result = DB::table('users')
                ->select(
                    'avatar',
                    'name',
                    'description',
                    'type',
                    'token',
                    'access_token',
                    'online')
                ->where($map)
                ->first();

            if (empty($result)) {
                $validated['token'] = md5(uniqid() . rand(1000, 9999));
                $validated['access_token'] = md5(uniqid() . rand(1000, 9999));
                $validated['created_at'] = Carbon::now();
                $validated['expire_date'] = Carbon::now()->addDays(30);
                $user_id = DB::table('users')->insertGetId($validated);
                $user = DB::table('users')
                    ->select(
                        'avatar',
                        'name',
                        'description',
                        'type',
                        'token',
                        'access_token',
                        'online')
                    ->where('id', $user_id)
                    ->first();

                return [
                    'code' => 1,
                    'data' => $user,
                    'message' => 'User created successfully'
                ];
            }

            $access_token = md5(uniqid() . rand(1000, 9999));
            $expire_date = Carbon::now()->addDays(30);

            DB::table('users')
                ->where($map)
                ->update([
                    'access_token' => $access_token,
                    'expire_date' => $expire_date,
                    'avatar' => $validated['avatar'],
                    'updated_at' => Carbon::now()
                ]);

            $result->access_token = $access_token;

            return response()->json([
                'code' => 1,
                'message' => 'User information updated',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'code' => 0,
                'data' => null,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function contact(Request $request)
    {
        $token = $request->user->token;

        $result = DB::table('users')
            ->select('token', 'avatar', 'description', 'online', 'name')
            ->where('token', '!=', $token)
            ->get();

        return response()->json([
            'code' => 1,
            'data' => $result,
            'message' => 'Successfully get contact data'
        ]);
    }

}

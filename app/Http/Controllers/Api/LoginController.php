<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use Exception;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Contract\Messaging;

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
            ->select('token',
                'avatar',
                'description',
                'online',
                'name')
            ->where('token', '!=', $token)
            ->get();

        return response()->json([
            'code' => 1,
            'data' => $result,
            'message' => 'Successfully get contact data'
        ]);
    }

    public function sendNotice(Request $request)
    {
        try {
            $user_token = $request->user->token;
            $user_avatar = $request->user->avatar;
            $user_name = $request->user->name;

            $to_token = $request->input('to_token');
            $to_avatar = $request->input('to_avatar');
            $to_name = $request->input('to_name');

            $call_type = $request->input('call_type');
            $doc_id = $request->input('doc_id');

            if (empty($doc_id)) {
                $doc_id = "";
            }

            $result = DB::table('users')
                ->select('token',
                    'avatar',
                    'fcm_token',
                    'online',
                    'name')
                ->where('token', '=', $to_token)
                ->first();

            $device_token = $result->fcm_token;

            try {
                if (!empty($device_token)) {
                    $messaging = app('firebase.messaging');
                    if ($call_type == 'cancel') {
                        $message = CloudMessage::fromArray([
                            'token' => $device_token,
                            'notification' => [
                                'title' => $user_name,
                                'body' => 'Call from ' . $user_name,
                            ],
                            'data' => [
                                'avatar' => $user_avatar,
                                'name' => $user_name,
                                'call_type' => $call_type,
                                'doc_id' => $doc_id,
                                'token' => $user_token
                            ]
                        ]);

                        $messaging->send($message);
                    } else if ($call_type == "voice") {
                        $message = CloudMessage::fromArray([
                            'token' => $device_token,
                            'data' => [
                                'avatar' => $user_avatar,
                                'name' => $user_name,
                                'call_type' => $call_type,
                                'doc_id' => $doc_id,
                                'token' => $user_token
                            ],
                            'android' => [
                                'priority' => 'high',
                                'notification' => [
                                    'channel_id' => 'xxxx',
                                    'title' => 'Voice call made by ' . $user_name,
                                    'body' => 'Please click to answer the voice call',
                                ],
                            ],
                        ]);

                        $messaging->send($message);

                    } else if ($call_type == "video") {
                        $message = CloudMessage::fromArray([
                            'token' => $device_token,
                            'data' => [
                                'avatar' => $user_avatar,
                                'name' => $user_name,
                                'call_type' => $call_type,
                                'doc_id' => $doc_id,
                                'token' => $user_token
                            ],
                            'android' => [
                                'priority' => 'high',
                                'notification' => [
                                    'channel_id' => 'xxxx',
                                    'title' => 'Video call made by ' . $user_name,
                                    'body' => 'Please click to answer the video call',
                                ],
                            ],
                        ]);

                        $messaging->send($message);
                    }
                }
            } catch (Exception $e) {
                return response()->json([
                    'code' => 0,
                    'data' => null,
                    'message' => $e->getMessage()
                ]);
            }
            return response()->json([
                'code' => 1,
                'data' => "{ 'token': $user_token, 'device_token': $device_token }",
                'message' => 'Successfully send notification'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'code' => 0,
                'data' => $e->getLine(),
                'message' => $e->getMessage()
            ]);
        }
    }

    public function bindFcmToken(Request $request)
    {
        $token = $request->user->token;
        $fcmtoken = $request->input('fcmtoken');

        if (empty($fcmtoken)) {
            return response()->json([
                'code' => 0,
                'data' => null,
                'message' => 'Invalid fcm token'
            ]);
        }

        try {
            $result = DB::table('users')
                ->where('token', '=', $token)
                ->update([
                    'fcm_token' => $fcmtoken,
                ]);
        } catch (Exception $e) {
            return response()->json([
                'code' => 0,
                'data' => null,
                'message' => $e->getMessage()
            ]);
        }

        return response()->json([
            'code' => 1,
            'data' => "{ 'token': $token, 'fcmtoken': $fcmtoken, 'result': $result }",
            'message' => 'Successfully bind fcm token'
        ]);
    }

    public function uploadImage(Request $request)
    {
        $file = $request->file('file');
        try {
            $extension = $file->getClientOriginalExtension();
            $fullNameFile = uniqid() . '.' . $extension;
            $timeDir = date("Ymd");
            $file->storeAs($timeDir, $fullNameFile, 'public');
            $url = config('app.ngrok_url') . '/storage/' . $timeDir . '/' . $fullNameFile;

            return response()->json([
                'code' => 1,
                'data' => $url,
                'message' => 'Successfully upload image'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'code' => 0,
                'data' => null,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function updateProfile(Request $request) {
        $user = $request->user;

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'description' => 'required',
            'avatar' => 'required',
            'online' => 'required',
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

                $result = DB::table('users')
                    ->where('token', '=', $user->token)
                    ->update([
                        'name' => $validated['name'],
                        'description' => $validated['description'],
                        'avatar' => $validated['avatar'],
                        'online' => $validated['online'],
                        'updated_at' => Carbon::now()
                    ]);

                if(!empty($result)) {
                    return response()->json([
                        'code' => 1,
                        'data' => $validated,
                        'message' => 'Successfully update profile'
                    ]);
                } else {
                    return response()->json([
                        'code' => 0,
                        'data' => null,
                        'message' => 'Failed to update profile'
                    ]);
                }
        } catch (Exception $e) {
            return response()->json([
                'code' => 0,
                'data' => null,
                'message' => $e->getMessage()
            ]);
        }
    }

}

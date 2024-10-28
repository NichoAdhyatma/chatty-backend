<?php

namespace App\Http\Controllers;

class TestController extends Controller
{
    public function index()
    {
        return [
            'message' => 'Successfully fetched data!',
            'data' => [
                'name' => 'John Doe',
                'email' => 'johndoe@gmail.com',
            ]
        ];
    }

}

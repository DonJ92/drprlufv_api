<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator;
use App\Notification;

class NotificationController extends Controller
{

    /**
     * Create a new ProductController instance.
     *
     * @return void
     */
    public function __construct() {
        
    }

    /**
     * Display a listing of the comment.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->respondValidateError($validator->errors()->first());
        }
        
        $rows = Notification::where('user_id', '=', $request->user_id)->orWhere('user_id', 0)->orderBy('created_at', 'desc')->get();


        return $this->respondSuccess($rows);        
    }
}

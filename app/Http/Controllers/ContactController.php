<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\UserRequest;
use Validator;
use App\Contact;
use App\User;

class ContactController extends Controller
{
    /**
     * Create a new UserController instance.
     *
     * @return void
     */
    public function __construct() {
        
    }

    /**
     * Display a listing of the user.
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
        
        $rows = Contact::where('sender', '=', $request->user_id)->orWhere('receiver', $request->user_id)->get();

        $contacts = array();

        foreach ($rows as $row) {
            if ($row->sender == $request->user_id) {
                $user = User::where('id', '=', $row->receiver)->first();
                $user->contact_id = $row->id;
                array_push($contacts, $user);
            } else {
                $user = User::where('id', '=', $row->sender)->first();
                $user->contact_id = $row->id;
                array_push($contacts, $user);
            }
        }
        return $this->respondSuccess($contacts);
    }

    /**
     * Store new contact.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request) 
    {
        $validator = Validator::make($request->all(), [
            'sender' => 'int',
            'receiver' => 'int',
        ]);

        if ($validator->fails()) {
            return $this->respondValidateError($validator->errors()->first());
        }

        $params = $validator->validated();

        $contact = Contact::where(function ($query) use ($request) {
            $query->where('sender', '=', $request->sender)
                  ->where('receiver', '=', $request->receiver);
        })->orWhere(function ($query) use ($request) {
            $query->where('sender', '=', $request->receiver)
                  ->where('receiver', '=', $request->sender);
        })->first();

        if ($contact == null) {            
            $contact = Contact::create($params);    
        }

        $user = User::where('id', '=', $request->receiver)->first();
        $user->contact_id = $contact->id;

        return $this->respondSuccess($user);
    }
}

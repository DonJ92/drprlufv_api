<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator;
use App\Comment;
use App\User;

class CommentController extends Controller
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
            'product_id' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->respondValidateError($validator->errors()->first());
        }
        
        $rows = Comment::select('comments.*', 'users.name as user_name', 'users.image as user_icon')
                        ->leftJoin('users', 'users.id', '=', 'comments.user_id')
                        ->where('product_id', '=', $request->product_id)
                        ->orderBy('created_at', 'desc')->get();
                        
        return $this->respondSuccess($rows);        
    }

    /**
     * Store new comment.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request) 
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required',
            'text' => 'required|string',
            'user_id' => 'nullable',
            'parent_id' => 'nullable'
        ]);

        if ($validator->fails()) {
            return $this->respondValidateError($validator->errors()->first());
        }

        $params = $validator->validated();

        $comment = Comment::create($params);

        $user = User::where('id', $comment->user_id)->first();

        if ($user) {
            $comment->user_name = $user->name;
            $comment->user_icon = $user->image;
        } else {
            $comment->user_name = '';
            $comment->user_icon = '';
        }

		return $this->respondSuccess($comment);
    }
}

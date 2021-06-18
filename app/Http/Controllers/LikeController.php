<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator;
use App\ProductLike;
use App\Product;
use App\User;
use App\Services\FCMPush;

class LikeController extends Controller
{

    /**
     * Create a new ProductController instance.
     *
     * @return void
     */
    public function __construct() {
        
    }

    /**
     * like product.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request) 
    {

        $validator = Validator::make($request->all(), [
            'product_id' => 'required',
            'user_id' => 'nullable',
        ]);

        if ($validator->fails()) {
            return $this->respondValidateError($validator->errors()->first());
        }

        $params = $validator->validated();

        $like = ProductLike::create($params);

        // Send notification
        $product = Product::where('id', '=', $request->product_id)->first();
        $product->update(array('likes' => $product->likes + 1));

        $user = User::where('id', '=', $product->user_id)->first();

        $fcm = new FCMPush();
        $fcm->send($product->user_id, 'Product Like', $user->name . ' like your product', $product->ref_id);

		return $this->respondSuccess($like);
    }
}

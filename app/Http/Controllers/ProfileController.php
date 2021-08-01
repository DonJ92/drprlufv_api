<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\UserRequest;
use Validator;
use JWTAuth;
use App\User;
use App\Product;

class ProfileController extends Controller
{
    /**
     * Create a new ProfileController instance.
     *
     * @return void
     */
    public function __construct() {
        
    }

    /**
     * Get Profile
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

        $query = User::where('id', '=' , $request->user_id);
        
        $row = $query->first();

        if ($row) {
        	$products = Product::leftjoin('delivery', 'products.id', '=', 'delivery.product_id')
                ->where('products.user_id', '=', $request->user_id)
                ->where('products.visible', 1)
                ->where('delivery.status', '<', 3)
                ->select('products.*', 'delivery.status')
                ->get();

        	foreach ($products as $product) {            
	            $filenames = [];

	            $images = DB::table('images')->where('product_id', '=', $product->id)->get();
	            foreach ($images as $image) {
	                array_push($filenames, $image->filename);
	            }

	            $product->filenames = $filenames;
	            $product->likes = DB::table('product_likes')->where('product_id', '=', $product->id)->count();
            	$product->comments = DB::table('comments')->where('product_id', '=', $product->id)->count();
	        }

	        $row->products = $products;	        
        }

        return $this->respondSuccess($row);        
    }

    /**
     * Update Profile
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request){
       
        $user = auth()->user();
        
        $validator = Validator::make($request->all(), [
            'email' => 'email|unique:users,email,'.$user->id,            
            'name' => 'string',
            'role' => 'string',
            'password' => 'nullable|string|min:6|max:10',
            'image' => 'image|mimes:jpeg,png,jpg,gif,svg',
        ]);

        if ($validator->fails()) {
            return $this->respondValidateError($validator->errors()->first());
        }

        $params = $validator->validated();

        if ($request->has('password') && !empty($request->password)) {
            $params['password'] = bcrypt($request->password);
        } else {
            unset($params['password']);
        }

        if($request->hasfile('image'))
        {
            // File Upload
            $imageName = time().'.'.$request->image->extension();     
            $request->image->move(public_path('images'), $imageName);
            $params['image'] = $imageName;
        }

        $user->update($params);

        return $this->respondSuccess($user);
    }
}

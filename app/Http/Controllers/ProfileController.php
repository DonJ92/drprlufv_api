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
                ->select('products.*', 'delivery.id as delivery_id', 'delivery.status', 'delivery.updated_at as delivery_updated_at')
                ->orderby('delivery.updated_at', 'desc')
                ->get()->toArray();

            $product_list = array();

        	foreach ($products as $product) {
                if (in_array($product['id'], array_column($product_list, 'id')))
                    continue;

	            $filenames = [];

	            $images = DB::table('images')->where('product_id', '=', $product['id'])->get();
	            foreach ($images as $image) {
	                array_push($filenames, $image->filename);
	            }

                $product['delivery_id'] = is_null($product['delivery_id']) ? 0 : (int)$product['delivery_id'];
                $product['status'] = is_null($product['status']) ? -1 : (int)$product['status'];
	            $product['filenames'] = $filenames;
	            $product['likes'] = DB::table('product_likes')->where('product_id', '=', $product['id'])->count();
            	$product['comments'] = DB::table('comments')->where('product_id', '=', $product['id'])->count();

                $product_list[] = $product;
	        }

	        $row->products = $product_list;	        
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

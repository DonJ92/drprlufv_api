<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
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

    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'uuid' => 'string|nullable',
            'latitude' => 'nullable',
            'longitude' => 'nullable',
            'count' => 'nullable',
        ]);

        if ($validator->fails()) {
            return $this->respondValidateError($validator->errors()->first());
        }

        $data = $request->all();
        $user_id = $request->user_id;
        $latitude = $request->latitude;
        $longitude = $request->longitude;
        $count = $request->count;
        if (empty($count))
            $count = 3;

        try {
            if (is_double($latitude) && is_double($longitude)) {
                $distanceField = "111.111 * DEGREES(ACOS(LEAST(1.0, COS(RADIANS(latitude)) * COS(RADIANS({$latitude})) * COS(RADIANS(longitude - ${longitude}))" . " + SIN(RADIANS(latitude)) * SIN(RADIANS({$latitude}))))) AS distance";

                $query = ProductLike::leftjoin('products', 'products.id', '=', 'product_likes.product_id')
                    ->leftjoin('users', 'users.id', '=', 'product_likes.user_id')
                    ->where('product_likes.user_id', $user_id)
                    ->select('products.*', 'users.image as user_icon', DB::raw($distanceField))
                    ->orderBy('products.distance', 'ASC')
                    ->orderBy('products.likes', 'DESC')
                    ->orderBy('products.created_at', 'DESC')
                    ->orderBy(DB::raw('RAND()'));

                $rows = $query->get();
            } else {
                $query = ProductLike::leftjoin('products', 'products.id', '=', 'product_likes.product_id')
                    ->leftjoin('users', 'users.id', '=', 'product_likes.user_id')
                    ->where('product_likes.user_id', $user_id)
                    ->select('products.*', 'users.image as user_icon')
                    ->orderBy('products.likes', 'DESC')
                    ->orderBy('products.created_at', 'DESC')
                    ->orderBy(DB::raw('RAND()'));

                $rows = $query->get();
            }

            $max = count($rows) - 1;
/*
            $result = array();

            while (count($result) < $count && count($result) != count($rows)) {
                $index = $this->random_select(0, $max * 2, $max / 3 * 2);
                $index = abs($max - $index);
                $row = $rows[$index];
                if (in_array($row, $result))
                    continue;
                array_push($result, $row);
            };
*/
            foreach ($rows as $row) {
                $filenames = [];

                $images = DB::table('images')->where('product_id', '=', $row->id)->get();
                foreach ($images as $image) {
                    array_push($filenames, $image->filename);
                }

                $row->filenames = $filenames;

                $row->comments = DB::table('comments')->where('product_id', '=', $row->id)->count();

            }
        } catch(QueryException $e) {
            print_r($e->getMessage());
            die();
        }

        return $this->respondSuccess($rows);
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

    function random_select($min, $max, $std_deviation, $step=1) {
        $rand1 = (float)mt_rand()/(float)mt_getrandmax();
        $rand2 = (float)mt_rand()/(float)mt_getrandmax();
        $gaussian_number = sqrt(-2 * log($rand1)) * cos(2 * M_PI * $rand2);
        $mean = ($max + $min) / 2;
        $random_number = ($gaussian_number * $std_deviation) + $mean;
        $random_number = round($random_number / $step) * $step;
        if($random_number < $min || $random_number > $max) {
            $random_number = $this->random_select($min, $max, $std_deviation);
        }
        return $random_number;
    }
}

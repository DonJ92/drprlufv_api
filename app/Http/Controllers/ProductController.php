<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\ProductRequest;
use Validator;
use App\Product;
use App\Image;

class ProductController extends Controller
{

    /**
     * Create a new ProductController instance.
     *
     * @return void
     */
    public function __construct() {
        
    }

    /**
     * Display a listing of the product.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable',
            'uuid' => 'string|nullable',
            'latitude' => 'nullable',
            'longitude' => 'nullable',
            'count' => 'nullable',
			'keyword' => 'string|nullable'
        ]);

        if ($validator->fails()) {
            return $this->respondValidateError($validator->errors()->first());
        }
        
        $latitude = $request->latitude;
        $longitude = $request->longitude;
		$keyword = $request->keyword;
        $count = $request->count;
        if (empty($count)) 
            $count = 3;

        if (is_double($latitude) && is_double($longitude)) {	        
			$distanceField = "111.111 * DEGREES(ACOS(LEAST(1.0, COS(RADIANS(latitude)) * COS(RADIANS({$latitude})) * COS(RADIANS(longitude - ${longitude}))" . " + SIN(RADIANS(latitude)) * SIN(RADIANS({$latitude}))))) AS distance"; 

	        $query = Product::select('products.*', 'users.image as user_icon', DB::raw($distanceField))
	                        ->leftjoin('users', 'users.id', '=', 'products.user_id')
	                        ->orderBy('distance', 'ASC')
	                        ->orderBy('likes', 'DESC')
	                        ->orderBy('created_at', 'DESC')
	                        ->orderBy(DB::raw('RAND()'));

			if (!empty($keyword)) {
				$query = $query->where(function($q) use($keyword) {
					$q->where('products.title', 'like', '%'.$keyword.'%')
						->orWhere('products.description', 'like', '%'.$keyword.'%');
				});
			}
			$rows = $query->get();
        } else {
        	$query = Product::select('products.*', 'users.image as user_icon')
	                        ->leftjoin('users', 'users.id', '=', 'products.user_id')
	                        ->orderBy('likes', 'DESC')
	                        ->orderBy('created_at', 'DESC')
	                        ->orderBy(DB::raw('RAND()'));

			if (!empty($keyword)) {
				$query = $query->where(function($q) use($keyword) {
					$q->where('products.title', 'like', '%'.$keyword.'%')
						->orWhere('products.description', 'like', '%'.$keyword.'%');
				});
			}
			$rows = $query->get();
	
        }
        
        $max = count($rows) - 1;

        $result = array();

        while(count($result) < $count) {
            $index = $this->random_select(0, $max * 2, $max / 3 * 2);
            $index = abs($max - $index);
            $row = $rows[$index];
            if (in_array($row, $result))
                continue;
            array_push($result, $row);
        };        
        
        foreach ($result as $row) {            
            $filenames = [];

            $images = DB::table('images')->where('product_id', '=', $row->id)->get();
            foreach ($images as $image) {
                array_push($filenames, $image->filename);
            }

            $row->filenames = $filenames;

            $row->comments = DB::table('comments')->where('product_id', '=', $row->id)->count();

        }
        
        return $this->respondSuccess($result);        
    }

    /**
     * Store new product.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(ProductRequest $request) 
    {
        $params = $request->validated();

        $product = Product::create($params);

        if($request->hasfile('images'))
        {
            $time = time();
            $index = 1;

            foreach($request->file('images') as $file)
            {
                $name = $time . '_' . $index .'.'.$file->extension();
                $file->move(public_path().'/images/', $name);  
                Image::create(array('product_id' => $product->id, 'filename' => $name));  
                $index++;
            }
        }

		return $this->respondSuccess($product);
    }

    /**
     * Remove the specified product.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete($id) {
        $product = Product::find($id);
        if (!$product) {
            return $this->respondNotFoundError('Could not find the apartment');
        }
        
        if ($product->delete()){ // physical delete
            return $this->respondSuccess(['message' => 'Successfull']);
        } else {
            return $this->response->respondServerError('Could not delete product');
        }
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

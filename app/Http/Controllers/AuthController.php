<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Stripe\StripeClient;
use Validator;
use App\User;
use App\Services\SocialAccountsService;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller {

    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct() {
        
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request) {
    	$validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
        	return $this->respondValidateError($validator->errors()->first());
        }

        if (! $token = auth()->attempt($validator->validated())) {
        	return $this->respondTokenError('Unauthorized');
        }

        $user = User::where('email', '=', $request->email)->first();

        $user->update(array('device_token' => $request->device_token));

        return $this->createNewToken($token);
    }

    /**
     * Social login
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function token(Request $request) {
        $validator = Validator::make($request->all(), [
            'provider' => 'required',
            'access_token' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->respondValidateError($validator->errors()->first());
        }

        try {
            $providerUser = Socialite::driver($request->provider)->stateless()->userFromToken($request->access_token);
        } catch (Exception $exception) {
            
        }
        
        if (!$providerUser) {
            return $this->respondTokenError('Unauthorized');            
        }

        $user = (new SocialAccountsService())->findOrCreate($providerUser, $request->provider);

        $token = auth()->login($user);

        return $this->createNewToken($token);
    }


    /**
     * Register a User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request) {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100|unique:users',
            'password' => 'required|string|min:6',
        ]);

        if($validator->fails()){
        	return $this->respondValidateError($validator->errors()->first());
        }

        $stripe_key = getenv('STRIPE_KEY');

        $stripe = new \Stripe\StripeClient(
            $stripe_key
        );
        $customer = $stripe->customers->create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
        ]);

        $user = User::create(array_merge(
                    $validator->validated(),
                    ['password' => bcrypt($request->password)],
                    ['stripe_customer_id' => $customer['id']]
                ));

        return $this->login($request);
    }

    /**
     * Set Firebase uid
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function firebase(Request $request) {
        $validator = Validator::make($request->all(), [
            'uid' => 'required'
        ]);

        if($validator->fails()){
            return $this->respondValidateError($validator->errors()->first());
        }

        $user = auth()->user();

        $params = $validator->validated();

        $user->update($params);

        return $this->respondSuccess($user);
    }


    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout() {
        auth()->logout();

        return response()->json(['message' => 'User successfully signed out']);
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function createNewToken($token){
    	return $this->respondSuccess(['token' => $token, 'user' => auth()->user()]);
    }

}
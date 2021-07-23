<?php

namespace App\Http\Controllers;

use App\Delivery;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Http\Requests\UserRequest;
use Validator;
use JWTAuth;
use App\User;

class UserController extends Controller
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
    public function index()
    {
        $user = auth()->user();

        $query = User::orderBy('name')->where('id', '!=' , $user->id);
        
        $query->where('role', '!=', config('constants.role.admin'));

        $rows = $query->get();

        return $this->respondSuccess($rows);        
    }

    /**
     * Store new user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(UserRequest $request ){
        // Password Validate
        $user = User::create(array_merge(
                    $request->validated(),
                    ['password' => bcrypt($request->password)]
                ));

		return $this->respondSuccess($user);
    }

    /**
     * Update existing user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id){
       
        $user = User::find($id);

        if (!$user) {
            return $this->respondNotFoundError('Could not find the user');
        }

        $validator = Validator::make($request->all(), [
            'email' => 'email|unique:users,email,'.$user->id,
            'name' => 'string',
            'role' => 'string',
            'prefer_work_hours' => 'numeric|min:0',
            'password' => 'nullable|string|min:6|max:10',
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

        $user->update($params);

        return $this->respondSuccess($user);
    }

    /**
     * Remove the specified user
     *
     * @return \Illuminate\Http\JsonResponse
     */
     public function delete($id) {
        $user = User::find($id);
        if (!$user) {
            return $this->respondNotFoundError('Could not find the user');
        }
        
        if ($user->delete()){ // physical delete
            return $this->respondSuccess(['message' => 'Successfull']);
        } else {
            return $this->response->respondServerError('Could not delete user');
        }
    }

    public function activateWallet(Request $request) {
        $data = $request->all();
        $validator = Validator::make($data, [
            'user_id' => 'required|string',
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->respondValidateError($validator->errors()->first());
        }

        try {
            $user_info = User::where('id', $data['user_id'])->first();
            if (is_null($user_info))
                return $this->respondNotFoundError('Could not find the user');
        } catch (QueryException $e) {
            return $this->respondNotFoundError('Could not find the user');
        }

        $stripe_key = getenv('STRIPE_KEY');

        $stripe = new \Stripe\StripeClient(
            $stripe_key
        );

        $account = $stripe->accounts->create([
            'type' => 'express',
            'email' => $user_info->email,
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
        ]);

        if (!is_array($account))
            return $this->respondServerError('Account Create API Failed');

        $user_info->stripe_acct_id = $account['id'];
        $user_info->save();

        $external_account = $stripe->accounts->createExternalAccount(
            $account['id'],
            ['external_account' => $data['token']]
        );
        if (!is_array($external_account))
            return $this->respondServerError('External Account Create API Failed');

        return $this->respondSuccess($user_info);
    }

    public function addPaymentMethod(Request $request) {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'card_number' => 'required|string',
            'exp_date' => 'required|string',
            'cvc' => 'required|integer',
        ]);

        if ($validator->fails()) {
            $message = $validator->errors()->first();
            return $this->respondValidateError(message);
        }

        $expDate = $request->exp_date;
        if (strpos($expDate, '/') == false) {
            return $this->respondValidateError('Invalid date');
        }

        $expMonth = explode('/', $expDate)[0];
        $expYear = explode('/', $expDate)[1];

        $user = User::where('id', $request->user_id)->get()->first();

        $stripe_key = getenv('STRIPE_KEY');

        $stripe = new \Stripe\StripeClient(
            $stripe_key
        );

        try {
            $paymentMethod = $stripe->paymentMethods->create([
                'type' => 'card',
                'card' => [
                    'number' => $request->card_number,
                    'exp_month' => $expMonth,
                    'exp_year' => $expYear,
                    'cvc' => $request->cvc,
                ],
            ]);

            if ($paymentMethod == null)
                return $this->respondServerError('Payment Method API Failed');

            $stripe->paymentMethods->attach(
                $paymentMethod->id,
                [
                    'customer' => $user->stripe_customer_id
                ]
            );
        } catch (\Exception $ex) {
            return $this->respondServerError($ex->getMessage());
        }   

        return $this->respondSuccess(array('status' => 1));
    }

    public function verifyPayment(Request $request) {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'name' => 'required|string',
            'email' => 'required|string',
            'phone' => 'required|string',
            'birthday' => 'required',
            'city' => 'required|string',
            'line1' => 'required|string',
            'line2' => 'nullable|string',
            'postal_code' => 'required|string',
            'state' => 'required|string',
            'ssn' => 'required|string',
            'ssn_last_4' => 'required|numeric',
            'card_number' => 'required|string',
            'exp_date' => 'required|string',
            'cvc' => 'required|integer',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg'
        ]);

        if ($validator->fails()) {
            $message = $validator->errors()->first();
            return $this->respondValidateError($message);
        }

        $expDate = $request->exp_date;
        if (strpos($expDate, '/') == false) {
            return $this->respondValidateError('Invalid date');
        }

        $expMonth = explode('/', $expDate)[0];
        $expYear = explode('/', $expDate)[1];

        $customer = User::where('id', $request->id)->get()->first();

        $stripe_key = getenv('STRIPE_KEY');
        $stripe = new \Stripe\StripeClient(
            $stripe_key
        );

        $name = trim($request->name);
        $last_name = (strpos($name, ' ') === false) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $name);
        $first_name = trim( preg_replace('#'.preg_quote($last_name,'#').'#', '', $name ) );

        // set document
        if($request->hasfile('image'))
        {
            // File Upload
            $imageName = time().'.'.$request->image->extension();     
            $request->image->move(public_path('identify'), $imageName);
            $document = $imageName;
        }

        if (empty($document)) {
            return $this->respondValidateError('Please upload verification image.');
        }

        User::where('id' , '=', $request->id)->update([
            'name' => $request->name,
            'email' => $request->email,
            //'phone' => $request->phone,
            // 'id_verification_image' => $document,
        ]);

        try {

            $birthday = explode('-', $request->birthday);

            $stripe_account = $stripe->accounts->retrieve($customer->stripe_acct_id);

            $params = [
                'business_type' => 'individual',
                'individual' => [
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $request->email,
                    'address' => [
                        'city' => $request->city,
                        'country' => 'US',
                        'line1' => $request->line1,
                        'line2' => $request->line2,
                        'postal_code' => $request->postal_code,
                        'state' => $request->state,
                    ],
                    'dob' => [
                        'day' => $birthday[2],
                        'month' => $birthday[1],
                        'year' => $birthday[0],
                    ],
                    'phone' => $request->phone,
                    'id_number' => $request->ssn,
                    'ssn_last_4' => $request->ssn_last_4,
                    'verification' => [
                        'document' => [
                            // 'back' => $document_back->id,
                            // 'front' => $document_front->id,
                        ]
                    ]
                ],
                'tos_acceptance' => [
                    'date' => time(),
                    'ip' => $_SERVER['REMOTE_ADDR'], // Assumes you're not using a proxy
                ],
                'business_profile' => [
                    'url' => 'http://dripn.com/',
                    'mcc' => '1520'
                ]
            ];

            if (empty($stripe_account->individual->verification->document->front)) {
                $document_front = $stripe->files->create([
                    'purpose' => 'identity_document',
                    'file' => fopen(public_path('identify') . '/' . $document, 'r'),
                ]);

                $params['individual']['verification']['document']['front'] = $document_front->id;
            }

            $stripe->accounts->update($customer->stripe_acct_id, $params);

            $token = $stripe->tokens->create([
                'card' => [
                    'number' => $request->card_number,
                    'exp_month' => $expMonth,
                    'exp_year' => $expYear,
                    'cvc' => $request->cvc,
                    'currency' => 'USD'
                ],
            ]);

            $stripe->accounts->createExternalAccount(
                $customer->stripe_acct_id,
                ['external_account' => $token]
            );

        } catch (\Exception $ex) {
            return $this->respondServerError($ex->getMessage());
        }

        return $this->respondSuccess(array('status' => 1));
    }

    public function getPaymentInfo(Request $request) {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            $message = $validator->errors()->first();
            return $this->respondValidateError($message);
        }

        $stripe_key = getenv('STRIPE_KEY');
        $stripe = new \Stripe\StripeClient(
            $stripe_key
        );

        $customer = User::where('id', $request->user_id)->get()->first();
        $stripeAccount = $stripe->accounts->retrieve($customer->stripe_acct_id);

        $result = array(
            'paymentMethods' => [],
            'verified' => 0,
        );

        if (empty($stripeAccount->capabilities)) {
            $result['verified'] = 0;
        } else if ($stripeAccount->capabilities->transfers == 'active') {
            $result['verified'] = 2;
        } else if ($stripeAccount->capabilities->transfers == 'pending') {
            $result['verified'] = 1;
        }

        $paymentMethods = $stripe->paymentMethods->all([
            'customer' => $customer->stripe_customer_id,
            'type' => 'card',
        ]);

        foreach ($paymentMethods as $paymentMethod) {
            array_push($result['paymentMethods'], [
                'brand' => $paymentMethod->card->brand,
                'last4' => $paymentMethod->card->last4,
                'expYear' => $paymentMethod->card->exp_year,
                'expMonth' => date("M", mktime(0, 0, 0, $paymentMethod->card->exp_month, 10)),
            ]);
        }

        return $this->respondSuccess($result);
    }
}

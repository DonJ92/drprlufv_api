<?php

namespace App\Http\Controllers;


use App\Delivery;
use App\User;
use App\Withdraw;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BalanceController extends Controller
{
    public function charge(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'token' => 'required|string',
            'amount' => 'required',
            'shipping_address' => 'required|string',
            'product_id' => 'required|exists:products,id',
            'buyer_id' => 'required|exists:users,id',
            'seller_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->respondValidateError($validator->errors()->first());
        }

        $stripe_key = getenv('STRIPE_KEY');

        $stripe = new \Stripe\StripeClient(
            $stripe_key
        );

        try {
            $charge = $stripe->charges->create([
                'amount' => $data['amount'] * 100,
                'currency' => 'usd',
                'source' => $data['token'],
                'description' => 'My First Test Charge (created for API docs)',
            ]);

            if (!is_array($charge))
                $this->respondServerError('Charge API Failed');
        } catch (\Exception $e) {
            return $this->respondServerError($e->getMessage());
        }

        try {
            $delivery_info = Delivery::create([
                'seller_id' => $data['seller_id'],
                'buyer_id' => $data['buyer_id'],
                'amount' => $data['amount'] * 100,
                'product_id' => $data['product_id'],
                'shipping_address' => $data['shipping_address'],
                'status' => 0,
            ]);
        } catch (QueryException $e) {
            return $this->respondServerError($e->getMessage());
        }

        return $this->respondSuccess($delivery_info);
    }

    public function purchase(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->respondValidateError($validator->errors()->first());
        }

        try {
            $result = Delivery::leftjoin('products', 'products.id', '=', 'delivery.product_id')
                ->select('products.*', 'delivery.seller_id', 'delivery.buyer_id', 'delivery.id as delivery_id')
                ->where('delivery.buyer_id', $data['user_id'])
                ->where('delivery.status', '0')
                ->get();
        } catch (QueryException $e) {
            return $this->respondServerError($e->getMessage());
        }

        foreach ($result as $row) {
            $filenames = [];

            $images = DB::table('images')->where('product_id', '=', $row->id)->get();
            foreach ($images as $image) {
                array_push($filenames, $image->filename);
            }

            $row->filenames = $filenames;
            $row->price = (double)$row->price;
            $row->latitude = (double)$row->latitude;
            $row->longitude = (double)$row->longitude;
            $row->likes = (double)$row->likes;

            $row->comments = DB::table('comments')->where('product_id', '=', $row->id)->count();
        }

        return $this->respondSuccess($result);
    }

    public function confirmPurchase(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'order_id' => 'required|exists:delivery,id',
        ]);

        if ($validator->fails()) {
            return $this->respondValidateError($validator->errors()->first());
        }

        try {
            $delivery_info = Delivery::where('id', $data['order_id'])->first();

            if (is_null($delivery_info))
                return $this->respondNotFoundError('No delivery info');

            $delivery_info->status = '1';
            $delivery_info->save();
        } catch (QueryException $e) {
            return $this->respondServerError($e->getMessage());
        }

        return $this->respondSuccess($delivery_info);
    }

    public function withdraw(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return $this->respondValidateError($validator->errors()->first());
        }

        try {
            $user_info = User::where('id', $data['user_id'])->first();
            if (is_null($user_info))
                return $this->respondNotFoundError('No User Info');

            $stripe_acct_id = $user_info->stripe_acct_id;
        } catch (QueryException $e) {
            return $this->respondNotFoundError($e->getMessage());
        }

        $stripe_key = getenv('STRIPE_KEY');

        $stripe = new \Stripe\StripeClient(
            $stripe_key
        );

        $transfer_info = $stripe->transfers->create([
            'amount' => $data['amount'] * 100,
            'currency' => 'usd',
            'destination' => $stripe_acct_id,
        ]);

        if (!is_array($transfer_info))
            $this->respondServerError('Transfer API Failed');

		\Stripe\Stripe::setApiKey($stripe_key);
		$balance = \Stripe\Balance::retrieve(
		  ['stripe_account' => $user_info->stripe_acct_id]
		);
		$amount = $balance->available[0]->amount;

		if ($amount < $request->amount) {
			return json_encode(array('status' => 0, 'message' => 'Insufficient money'));
		}

		$payout = \Stripe\Payout::create([
		  'amount' => $request->amount * 100,
		  'currency' => 'usd',
		], [
		  'stripe_account' => $user_info->stripe_acct_id,
		]);

        try {
            $withdraw_info = Withdraw::create([
                'user_id' => $data['user_id'],
                'withdraw_amount' => $data['amount']
            ]);
        } catch (QueryException $e) {
            return $this->respondServerError($e->getMessage());
        }

        return $this->respondSuccess($withdraw_info);
    }

    public function availableBalance(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->respondValidateError($validator->errors()->first());
        }

        try {
            $withdraw_info = Withdraw::where('user_id', $data['user_id'])
                ->orderby('updated_at', 'desc')
                ->first();

            if (is_null($withdraw_info))
                $withdraw_time = '';
            else
                $withdraw_time = $withdraw_info->updated_at;

            $balance = Delivery::leftjoin('products', 'products.id', '=', 'delivery.product_id')
                ->where('delivery.seller_id', $data['user_id'])
                ->where('delivery.updated_at', '>', $withdraw_time)
                ->where('delivery.status', '1')
                ->sum('products.price');

            $result = array('balance' => (double)$balance);
        } catch (QueryException $e) {
            return $this->respondServerError($e->getMessage());
        }

        return $this->respondSuccess($result);
    }
}
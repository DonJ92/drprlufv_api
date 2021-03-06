<?php

namespace App\Http\Controllers;


use App\Delivery;
use App\Product;
use App\User;
use App\Withdraw;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PayPalCheckoutSdk\Payments\CapturesRefundRequest;
use PaypalPayoutsSDK\Payouts\PayoutsPostRequest;
use Sample\PayPalClient;

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
                'charge_id' => $charge['id'],
                'shipping_address' => $data['shipping_address'],
                'status' => 0,
            ]);

            $product_info = Product::where('id', $data['product_id'])->first();
            if (is_null($product_info))
                $this->respondNotFoundError('There is no product info.');

            $product_info->visible = 2;
            $product_info->save();

        } catch (QueryException $e) {
            return $this->respondServerError($e->getMessage());
        }

        return $this->respondSuccess($delivery_info);
    }

	public function checkOut(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'transaction_id' => 'required|string',
            'amount' => 'required',
            'shipping_address' => 'required|string',
            'product_id' => 'required|exists:products,id',
            'buyer_id' => 'required|exists:users,id',
            'seller_id' => 'required|exists:users,id',
            'capture_id' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->respondValidateError($validator->errors()->first());
        }

        try {
            $delivery_info = Delivery::create([
                'seller_id' => $data['seller_id'],
                'buyer_id' => $data['buyer_id'],
                'amount' => $data['amount'],
                'product_id' => $data['product_id'],
                'charge_id' => 0,
                'capture_id' => $data['capture_id'],
                'transaction_id' => $data['transaction_id'],
                'shipping_address' => $data['shipping_address'],
                'status' => 0,
            ]);

            $product_info = Product::where('id', $data['product_id'])->first();
            if (is_null($product_info))
                $this->respondNotFoundError('There is no product info.');

            $product_info->visible = 2;
            $product_info->save();

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
                ->select('products.*', 'delivery.seller_id', 'delivery.buyer_id', 'delivery.id as delivery_id', 'delivery.charge_id', 'delivery.status')
                ->where('delivery.buyer_id', $data['user_id'])
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
            $row->user_id = (int)$row->user_id;
            $row->price = (double)$row->price;
            $row->latitude = (double)$row->latitude;
            $row->longitude = (double)$row->longitude;
            $row->likes = (double)$row->likes;
            $row->delivery_id = (int)$row->delivery_id;
            $row->status = (int)$row->status;

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
            'email' => 'required|email',
            'amount' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return $this->respondValidateError($validator->errors()->first());
        }

        /*
        try {
            $user_info = User::where('id', $data['user_id'])->first();
            if (is_null($user_info))
                return $this->respondNotFoundError('No User Info');

            $stripe_acct_id = $user_info->stripe_acct_id;
        } catch (QueryException $e) {
            return $this->respondNotFoundError($e->getMessage());
        }
        */

        $request = new PayoutsPostRequest();
        $body= json_decode(
            '{
                "sender_batch_header":
                {
                  "email_subject": "SDK payouts test txn"
                },
                "items": [
                {
                  "recipient_type": "EMAIL",
                  "receiver": "'.$data['email'].'",
                  "note": "Your '.$data['amount'].'$ payout",
                  "sender_item_id": "Test_txn_12",
                  "amount":
                  {
                    "currency": "USD",
                    "value": "'.$data['amount'].'"
                  }
                }]
              }',
            true);
        $request->body = $body;
        $client = PayPalClient::client();
        $response = $client->execute($request);

// Disable Stripe withdraw
/*
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
*/
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

    public function refund(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'user_id' => 'required|exists:users,id',
            'delivery_id' => 'required|exists:delivery,id',
        ]);

        if ($validator->fails()) {
            return $this->respondValidateError($validator->errors()->first());
        }

        try {
            $delivery_info = Delivery::where('id', $data['delivery_id'])
                ->where('buyer_id', $data['user_id'])
                ->first();

            if (is_null($delivery_info)) {
                $delivery_info = Delivery::where('id', $data['delivery_id'])
                    ->where('seller_id', $data['user_id'])
                    ->first();
                if (is_null($delivery_info))
                    return $this->respondNotFoundError('No delivery info');

                $request = new CapturesRefundRequest($delivery_info->capture_id);

                $request->body = array(
                    'amount' =>
                        array(
                            'value' => $delivery_info->amount,
                            'currency_code' => 'USD'
                        )
                );
                $client = PayPalClient::client();
                $response = $client->execute($request);

/*                $stripe_key = getenv('STRIPE_KEY');

                $stripe = new \Stripe\StripeClient(
                    $stripe_key
                );

                $refund = $stripe->refunds->create([
                    'charge' => $delivery_info->charge_id
                ]);

                if (!is_array($refund))
                    $this->respondServerError('Refunds API Failed');
                */

                $delivery_info->status = '3';
                $delivery_info->save();

                $product_info = Product::where('id', $delivery_info->product_id)->first();

                if (is_null($product_info))
                    return $this->respondNotFoundError('No product info');

                $product_info->visible = 1;
                $product_info->save();
            } else {
                $delivery_info->status = '2';
                $delivery_info->save();
            }
        } catch (QueryException $e) {
            return $this->respondServerError($e->getMessage());
        }

        return $this->respondSuccess(array('status' => 1));
    }
}
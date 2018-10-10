<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Stripe;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Mail;
use App\Mail\SuccessAddedBalance;
use App\Models\CreditPackage;
use App\Http\Requests\StoreStripeRequest;
use App\Http\Requests\StorePaymentRequest;
use App\Services\Paypal;
use App\Http\Requests\StoreBankWireRequest;
use App\Services\PaymentGateway\PaymentGatewayHandler;
use Srmklive\PayPal\Services\ExpressCheckout;
use App\User;
use Auth;

class PaymentController extends Controller {

    protected $user;

    public function __construct(Stripe $stripe) {
        $this->stripe = $stripe;

        $this->middleware(function ($request, $next) {
            $this->user = jwt_user();
            return $next($request);
        });
        $this->vat = config('settings.vat');
        $this->provider = new ExpressCheckout;
    }

    public function stripePayment(StoreStripeRequest $request) {
        if (!$request->amount) {
            throw new \Exception('Please Enter a Valid Amount');
        }

        $smsSendLink = url('/') . '/app#/sms/send';

        $package = CreditPackage::find($request->package_id);

        // Charge using stripe
        $vatAmount = $request->amount * $this->vat / 100;
        $request->total = $request->amount + $vatAmount;
        $charge = $this->stripe->stripeCharge($request);

        $paymentMethodId = PaymentMethod::where('slug', 'credit_card')->first();

        if ($charge['status'] == 'success') {

            $payment = new Payment;
            $payment->user_id = $this->user->id;
            $payment->payment_method_id = $paymentMethodId->id;
            $payment->amount = $request->amount;
            $payment->vat = $vatAmount;
            $payment->save();

            auth()->user()->cash_balance += $request->amount;
            auth()->user()->credit_balance += $package['credits'];
            auth()->user()->save();
            $data = [
                'name' => auth()->user()->name,
                'balance' => auth()->user()->cash_balance,
                'status' => $charge['status'],
                'credits' => $package['credits'],
                'amount' => $package['price']
            ];
            // Send Mail
            Mail::to($this->user)->send(new SuccessAddedBalance($data, $smsSendLink));

            return response()->json($charge);
        }
    }

    public function bankWirePayment(StoreBankWireRequest $request) {

        $vatAmount = $request->amount * $this->vat / 100;
        $paymentMethod = PaymentMethod::where('slug', 'bank_wire')->first();

        $payment = Payment::create([
            'user_id' => $this->user->id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => $request->amount,
            'vat' => $vatAmount,
            'is_paid' => 0,
            'reference_number' => $request->reference_number,
            'details' => $request->description
        ]);
        return response()->json(['created' => 'true']);
    }

    public function purchase(StorePaymentRequest $request, $gateway) {
        $vatAmount = $request->amount * $this->vat / 100;
        $total = $request->amount + $vatAmount;

        $paymentMethodId = PaymentMethod::where('slug', $gateway)->first();

        $payment = Payment::create([
                    'user_id' => Auth::id(),
                    'payment_method_id' => $paymentMethodId->id,
                    'amount' => $request->amount,
                    'vat' => $vatAmount,
                    'is_paid' => 0,
        ]);

        $data = [
            'amount' => $total,
            'invoice_id' => $payment->id,
            'request' => $request->all()
        ];

        $payment = new PaymentGatewayHandler($gateway);
        $response = $payment->gateway->purchase($data);

        if ($response['status'] == 'success' && !isset($response['offSiteGWredirectLink'])) {
            //Onsite Payment Fateways Li8ke Stripe 
            $response = PaymentGatewayHandler::successPayment($response);
        }

        //For Offiste Gateways, Redirect Link will return to frontend , and user will redirected to gateway site to enter payment details
        return response()->json($response);
    }

    public function callback(Request $request, $gateway) {

        $payment = new PaymentGatewayHandler($gateway);
        $response = $payment->gateway->completePurchase($request);
        if (!$response['success']) {
            return redirect(route('app.dashboard').'#/add-funds/paypal/failed')->with(['status' => 'falied', 'message' => 'Error processing payment']);
        }

        $response = PaymentGatewayHandler::successPayment($response);
        return redirect(route('app.dashboard').'#/add-funds/paypal/success')->with(['status' => 'success', 'message' => 'Payment successful']);
        
    }

}

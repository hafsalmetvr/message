<?php

namespace App\Http\Requests;

// use Illuminate\Foundation\Http\FormRequest;

use Dingo\Api\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest {
    // Whether you want to use other status code other than 422.

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     * 
     * @return array
     */
    public function rules() {


        /*
         * Dynamic Rules Based on Gateways
         */
        $gateway = $this->route('gateway');

        $rules = [
            'paypal' => ['amount' => 'required'],
            'braintree' => [],
            'stripe' => []
        ];

        return $rules[$gateway];
    }

    public function withValidator($validator) {

        $validator->after(function ($validator) {
            if (!$this->input('amount'))
                $validator->errors()->add('amount', 'Amount should be greater than zero!');
        });
    }

}

<?php

namespace App\Http\Requests;

// use Illuminate\Foundation\Http\FormRequest;

use Dingo\Api\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBankWireRequest extends FormRequest {
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
        return [
             'amount' => 'required',
             'is_transferred' => ['required', 'integer']
        ];
    }
    public function messages()
    {
        return [
            'is_transferred.required' => "Please confirm, you have transferred the amount to our Bank account."
        ];
    }    
    public function withValidator($validator) {

        $validator->after(function ($validator) {
            if(!$this->input('amount'))
                $validator->errors()->add('amount', 'Amount should be greater than zero!');
        });
    }

}

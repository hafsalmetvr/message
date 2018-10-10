<?php

namespace App\Http\Requests;

//use Illuminate\Foundation\Http\FormRequest;
use Dingo\Api\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Contact;

class StoreContactRequest extends FormRequest {

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
            'phone' =>
            [
                'required',               
                'smsto_valid_phone',
            ],
            'lists' => ['required']
        ];
    }

    public function messages(){
        return [
            'lists.required' => "Please select a list"
        ];
    }
    public function withValidator($validator) {

        $validator->after(function ($validator) {
            $this->checkPhoneUniqueWithList($validator);
        });
    }

    private function checkPhoneUniqueWithList($validator) {

        $count = Contact::join('lists_contacts', 'lists_contacts.contact_id', '=', 'contacts.id')
                           ->where('contacts.phone',$this->input('phone')) 
                           ->where('lists_contacts.list_id', $this->input('lists'))
                           ->where('contacts.user_id', auth()->user()->id)
                           ->whereNull('contacts.deleted_at')->whereNull('lists_contacts.deleted_at')
                           ->count();

        if($count){
            $validator->errors()->add('phone', 'The number is already in your contacts');
        }
    }

}

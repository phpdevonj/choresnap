<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ProviderDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'document_id'              => 'required',
            'provider_document'        => 'mimes:jpg,jpeg,png,pdf,docx|max:10240'  // max:10240 KB = 10 MB
        ];
    }
    public function messages()
    {
        return [
            'provider_document.max'   => 'The provider document must not be greater than 10 MB.',
            'provider_document.mimes' => 'The provider document must be a file of type: jpg, jpeg, png, pdf, docx.',
        ];
    }
    

    protected function failedValidation(Validator $validator)
    {
        if ( request()->is('api*')){
            $data = [
                'status' => 'false',
                'message' => $validator->errors()->first(),
                'all_message' =>  $validator->errors()
            ];

            throw new HttpResponseException(response()->json($data,422));
        }

        throw new HttpResponseException(redirect()->back()->withInput()->with('errors', $validator->errors()));
    }
}

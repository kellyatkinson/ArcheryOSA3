<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateSchool extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (Auth::check() && Auth::user()->roleid <= 2) {
            return true;
        }
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'label' => 'required|max:155|unique:schools,label,'. $this->schoolid.',schoolid',
            'description' => 'nullable',
            'phone' => 'nullable',
            'contactname' => 'nullable',
            'address' => 'nullable',
            'suburb' => 'nullable',
            'city' => 'nullable',
            'country' => 'nullable',
            'visible' => 'nullable',
            'url'   => 'nullable|url',
            'email' => 'nullable|email'
        ];
    }

    /**
     * @return array
     */
    public function messages()
    {
        return [
            'label.required' => 'School name is required',
        ];
    }
}

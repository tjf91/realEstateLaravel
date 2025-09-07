<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRealEstateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => ['required','string','min:1','max:128'],
            'real_state_type' => ['required', Rule::in(['house','department','land','commercial_ground'])],

            'street'          => ['required','string','min:1','max:128'],
            'external_number' => ['required','string','max:12','regex:/^[A-Za-z0-9-]+$/'],
            'internal_number' => ['nullable','string','max:12','regex:/^[A-Za-z0-9\- ]+$/'],

            'neighborhood'    => ['required','string','min:1','max:128'],
            'city'            => ['required','string','min:1','max:64'],
            'country'         => ['required','string','size:2','regex:/^[A-Za-z]{2}$/'],

            'rooms'           => ['required','integer','min:0'],
            'bathrooms'       => ['required','numeric','min:0','max:99.9'],

            'comments'        => ['nullable','string','max:128'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $type = $this->input('real_state_type');
            $bath = (float) ($this->request->all()['bathrooms'] ?? 0);
            $internal = $this->input('internal_number');

            // bathrooms == 0 only for land / commercial_ground
            if ($bath === 0.0 && !in_array($type, ['land','commercial_ground'], true)) {
                $v->errors()->add('bathrooms', 'bathrooms can be zero only for land or commercial_ground.');
            }

            // internal_number required for department / commercial_ground
            if (in_array($type, ['department','commercial_ground'], true)
                && ($internal === null || $internal === '')) {
                $v->errors()->add('internal_number', 'internal_number is required for this real_state_type.');
            }
        });
    }
}

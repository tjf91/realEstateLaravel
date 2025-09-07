<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRealEstateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => ['sometimes','string','min:1','max:128'],
            'real_state_type' => ['sometimes', Rule::in(['house','department','land','commercial_ground'])],

            'street'          => ['sometimes','string','min:1','max:128'],
            'external_number' => ['sometimes','string','max:12','regex:/^[A-Za-z0-9-]+$/'],
            'internal_number' => ['sometimes','nullable','string','max:12','regex:/^[A-Za-z0-9\- ]+$/'],

            'neighborhood'    => ['sometimes','string','min:1','max:128'],
            'city'            => ['sometimes','string','min:1','max:64'],
            'country'         => ['sometimes','string','size:2','regex:/^[A-Za-z]{2}$/'],

            'rooms'           => ['sometimes','integer','min:0'],
            'bathrooms'       => ['sometimes','numeric','min:0','max:99.9'],

            'comments'        => ['sometimes','nullable','string','max:128'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            // Route-model bound RealEstate (because controller uses RealEstate $property)
            $property = $this->route('property');

            // Effective type after this update (payload wins, else current model)
            $effectiveType = $this->input('real_state_type', $property?->real_state_type);

            // bathrooms == 0 only for land/commercial_ground
            $payload = $this->request->all();
            $bathroomsInput = $payload['bathrooms'] ?? null;  // detect presence even when 0
            if ($bathroomsInput !== null) {
                $bath = (float) $bathroomsInput;
                if ($bath === 0.0 && !in_array($effectiveType, ['land','commercial_ground'], true)) {
                    $v->errors()->add('bathrooms', 'bathrooms can be zero only for land or commercial_ground.');
                }
            }

            // internal_number required for department/commercial_ground
            $effectiveInternal = $this->has('internal_number')
                ? $this->input('internal_number')
                : ($property?->internal_number);

            if (in_array($effectiveType, ['department','commercial_ground'], true)
                && ($effectiveInternal === null || $effectiveInternal === '')) {
                $v->errors()->add('internal_number', 'internal_number is required for this real_state_type.');
            }
        });
    }
}

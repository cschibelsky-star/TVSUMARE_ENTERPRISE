<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SiteFactoryIntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $configuredToken = config('site_factory.token');

        if (! $configuredToken) {
            return false;
        }

        return hash_equals((string) $configuredToken, (string) $this->header('X-Site-Factory-Token'));
    }

    public function rules(): array
    {
        return [
            'product' => ['required', 'string', 'max:150'],
            'client' => ['required', 'string', 'max:150'],
            'plan' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:180'],
            'domain' => ['nullable', 'string', 'max:180'],
            'phone' => ['nullable', 'string', 'max:80'],
            'source' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'plan' => $this->input('plan') ?: config('site_factory.default_plan', 'start'),
            'source' => $this->input('source') ?: 'site',
        ]);
    }
}

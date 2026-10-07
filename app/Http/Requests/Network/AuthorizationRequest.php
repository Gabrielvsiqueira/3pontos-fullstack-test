<?php

declare(strict_types=1);

namespace App\Http\Requests\Network;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class AuthorizationRequest extends FormRequest
{
    public const string OCCURRED_AT_FORMAT = 'Y-m-d\TH:i:s\Z';

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'max:64'],
            'card_token' => ['required', 'string'],
            'amount_cents' => ['required', 'integer:strict', 'between:1,100000000'],
            'currency' => ['required', 'string', 'in:BRL'],
            'mcc' => ['required', 'string', 'regex:/^\d{4}$/'],
            'merchant' => ['required', 'array'],
            'merchant.name' => ['required', 'string'],
            'merchant.city' => ['required', 'string'],
            'merchant.country' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'occurred_at' => ['required', 'string', 'date_format:'.self::OCCURRED_AT_FORMAT],
        ];
    }
}

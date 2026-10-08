<?php

declare(strict_types=1);

namespace Passa\Network\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Passa\Authorization\DTOs\AuthorizationMessage;

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

    public function message(): AuthorizationMessage
    {
        return new AuthorizationMessage(
            id: $this->string('id')->toString(),
            cardToken: $this->string('card_token')->toString(),
            amountCents: $this->integer('amount_cents'),
            currency: $this->string('currency')->toString(),
            mcc: $this->string('mcc')->toString(),
            merchantName: $this->string('merchant.name')->toString(),
            merchantCity: $this->string('merchant.city')->toString(),
            merchantCountry: $this->string('merchant.country')->toString(),
            occurredAt: CarbonImmutable::createFromFormat(self::OCCURRED_AT_FORMAT, $this->string('occurred_at')->toString(), 'UTC'),
            payload: $this->json()->all(),
        );
    }
}

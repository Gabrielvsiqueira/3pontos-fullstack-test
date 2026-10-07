<?php

declare(strict_types=1);

namespace App\Http\Requests\Network;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class EventRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'max:64'],
            'type' => ['required', 'string', 'in:capture,cancellation'],
            'occurred_at' => ['required', 'string', 'date_format:'.AuthorizationRequest::OCCURRED_AT_FORMAT],
            'authorization_id' => ['required', 'string'],
            'amount_cents' => ['exclude_unless:type,capture', 'required', 'integer:strict', 'between:1,100000000'],
            'currency' => ['exclude_unless:type,capture', 'required', 'string', 'in:BRL'],
            'sequence' => ['exclude_unless:type,capture', 'required', 'integer:strict', 'min:1'],
            'final' => ['exclude_unless:type,capture', 'required', 'boolean:strict'],
        ];
    }
}

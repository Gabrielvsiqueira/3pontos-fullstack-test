<?php

declare(strict_types=1);

namespace Passa\Network\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Passa\Ledger\BillingMonth;

final class StatementRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'month' => ['sometimes', 'filled', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ];
    }

    public function month(): string
    {
        return $this->has('month')
            ? $this->string('month')->toString()
            : BillingMonth::of(now());
    }
}

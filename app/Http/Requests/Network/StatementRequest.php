<?php

declare(strict_types=1);

namespace App\Http\Requests\Network;

use App\Ledger\BillingMonth;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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

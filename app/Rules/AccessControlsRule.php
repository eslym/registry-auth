<?php

namespace App\Rules;

use App\Enums\AccessLevel;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AccessControlsRule implements ValidationRule, ValidatorAwareRule
{
    protected Validator $validator;

    public function __construct()
    {
    }

    /**
     * Run the validation rule.
     *
     * @param Closure(string, ?string=): PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value)) {
            return;
        }

        /** @var Validator $sub */
        $sub = validator($value, [
            '*' => ['array'],
            '*.id' => ['nullable', 'integer'],
            '*.rule' => ['required', 'string'],
            '*.access_level' => ['required', Rule::enum(AccessLevel::class)]
        ]);

        if ($sub->fails()) {
            foreach ($sub->errors()->toArray() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->validator->errors()->add($key, $message);
                }
                return;
            }
        }

        $rules = [];
        $order = 0;
        foreach ($value as $index => &$control) {
            if (!isset($rules[$control['rule']])) {
                $rules[$control['rule']] = $index;
            } else {
                $d = $rules[$control['rule']];
                $this->validator->errors()->add(
                    "{$attribute}.{$d}.rule",
                    'Duplicated rule'
                );
                $this->validator->errors()->add(
                    "{$attribute}.{$index}.rule",
                    'Duplicated rule'
                );
            }
            $control['sort_order'] = $order;
            $order++;
        }

        $this->validator->setValue($attribute, $value);
    }

    public function setValidator(Validator $validator): void
    {
        $this->validator = $validator;
    }
}

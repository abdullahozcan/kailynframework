<?php

namespace Kailyn\Validation;

use RuntimeException;

class Validator
{
    private array $data;
    private array $rules;
    private array $messages;
    private array $errors = [];
    private array $customMessages = [];
    private array $ruleDefaults = [
        'required' => 'The :field field is required.',
        'email' => 'The :field must be a valid email address.',
        'min' => 'The :field must be at least :param characters.',
        'max' => 'The :field must not exceed :param characters.',
        'numeric' => 'The :field must be a number.',
        'integer' => 'The :field must be an integer.',
        'string' => 'The :field must be a string.',
        'url' => 'The :field must be a valid URL.',
        'confirmed' => 'The :field confirmation does not match.',
        'alpha' => 'The :field may only contain letters.',
        'alpha_num' => 'The :field may only contain letters and numbers.',
        'alpha_dash' => 'The :field may only contain letters, numbers, dashes, and underscores.',
        'array' => 'The :field must be an array.',
        'boolean' => 'The :field must be true or false.',
        'date' => 'The :field must be a valid date.',
    ];

    public function __construct(array $data, array $rules, array $messages = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->customMessages = $messages;
    }

    public static function make(array $data, array $rules, array $messages = []): static
    {
        $instance = new static($data, $rules, $messages);
        $instance->validate();
        return $instance;
    }

    public function validate(): void
    {
        $this->errors = [];

        foreach ($this->rules as $field => $rules) {
            $rules = is_string($rules) ? explode('|', $rules) : $rules;
            $value = $this->getValue($field);

            foreach ($rules as $rule) {
                $this->validateRule($field, $value, $rule);
            }
        }
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function errorsFor(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    public function firstError(?string $field = null): ?string
    {
        if ($field !== null) {
            return $this->errors[$field][0] ?? null;
        }

        foreach ($this->errors as $fieldErrors) {
            if (!empty($fieldErrors)) {
                return $fieldErrors[0];
            }
        }

        return null;
    }

    private function validateRule(string $field, mixed $value, string $rule): void
    {
        $params = [];

        if (str_contains($rule, ':')) {
            [$rule, $paramStr] = explode(':', $rule, 2);
            $params = explode(',', $paramStr);
        }

        $method = 'validate' . ucfirst($rule);

        if (!method_exists($this, $method)) {
            throw new RuntimeException("Validation rule [{$rule}] not found");
        }

        $message = $this->customMessages[$field . '.' . $rule]
            ?? $this->customMessages[$field] ?? $this->ruleDefaults[$rule] ?? '';

        $valid = $this->$method($field, $value, $params);

        if (!$valid) {
            $error = str_replace(
                [':field', ':param', ':params'],
                [$field, $params[0] ?? '', implode(', ', $params)],
                $message
            );
            $this->errors[$field][] = $error;
        }
    }

    private function getValue(string $field): mixed
    {
        $keys = explode('.', $field);
        $value = $this->data;

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    protected function validateRequired(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (is_array($value) && empty($value)) {
            return false;
        }

        return true;
    }

    protected function validateEmail(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    protected function validateMin(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $param = (int) ($params[0] ?? 0);

        if (is_string($value)) {
            return mb_strlen($value) >= $param;
        }

        if (is_numeric($value)) {
            return $value >= $param;
        }

        if (is_array($value)) {
            return count($value) >= $param;
        }

        return true;
    }

    protected function validateMax(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $param = (int) ($params[0] ?? 0);

        if (is_string($value)) {
            return mb_strlen($value) <= $param;
        }

        if (is_numeric($value)) {
            return $value <= $param;
        }

        if (is_array($value)) {
            return count($value) <= $param;
        }

        return true;
    }

    protected function validateNumeric(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return is_numeric($value);
    }

    protected function validateInteger(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    protected function validateString(string $field, mixed $value, array $params): bool
    {
        return is_string($value);
    }

    protected function validateUrl(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    protected function validateConfirmed(string $field, mixed $value, array $params): bool
    {
        $confirmation = $this->getValue($field . '_confirmation');
        return $value === $confirmation;
    }

    protected function validateAlpha(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return ctype_alpha($value);
    }

    protected function validateAlphaNum(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return ctype_alnum($value);
    }

    protected function validateAlphaDash(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return preg_match('/^[a-zA-Z0-9_-]+$/', $value) === 1;
    }

    protected function validateArray(string $field, mixed $value, array $params): bool
    {
        return is_array($value);
    }

    protected function validateBoolean(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false'], true);
    }

    protected function validateDate(string $field, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $format = $params[0] ?? 'Y-m-d';
        $date = \DateTime::createFromFormat($format, $value);

        return $date && $date->format($format) === $value;
    }

    public function validated(): array
    {
        if ($this->fails()) {
            throw new RuntimeException('Validation failed');
        }

        $validated = [];

        foreach ($this->rules as $field => $rules) {
            $rules = is_string($rules) ? explode('|', $rules) : $rules;
            if (!in_array('required', $rules) && $this->getValue($field) === null) {
                continue;
            }
            $validated[$field] = $this->getValue($field);
        }

        return $validated;
    }
}

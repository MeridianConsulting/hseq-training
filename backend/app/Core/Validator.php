<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\ValidationException;

class Validator
{
    private array $errors = [];
    private array $data;
    private array $rules;
    private array $messages;

    public function __construct(array $data, array $rules, array $messages = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->messages = $messages;
    }

    public static function make(array $data, array $rules, array $messages = []): self
    {
        $validator = new self($data, $rules, $messages);
        $validator->validate();
        return $validator;
    }

    public function validate(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;
            $value = $this->data[$field] ?? null;

            if (in_array('nullable', $rules, true) && ($value === null || $value === '')) {
                continue;
            }

            foreach ($rules as $rule) {
                if ($rule === 'nullable') {
                    continue;
                }

                $params = [];
                if (str_contains($rule, ':')) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                $methodName = 'validate' . ucfirst($rule);
                if (method_exists($this, $methodName)) {
                    $this->$methodName($field, $value, $params);
                }
            }
        }

        return empty($this->errors);
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors);
        }

        $validated = array_intersect_key($this->data, $this->rules);

        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;
            if (
                in_array('nullable', $rules, true)
                && array_key_exists($field, $validated)
                && $validated[$field] === ''
            ) {
                $validated[$field] = null;
            }
        }

        return $validated;
    }

    private function addError(string $field, string $rule, string $defaultMessage): void
    {
        $this->errors[$field][] = $this->messages["{$field}.{$rule}"] ?? $defaultMessage;
    }

    private function validateRequired(string $field, mixed $value, array $params): void
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            $this->addError($field, 'required', "El campo {$field} es obligatorio.");
        }
    }

    private function validateString(string $field, mixed $value, array $params): void
    {
        if ($value !== null && !is_string($value)) {
            $this->addError($field, 'string', "El campo {$field} debe ser texto.");
        }
    }

    private function validateNumeric(string $field, mixed $value, array $params): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!is_numeric($value)) {
            $this->addError($field, 'numeric', "El campo {$field} debe ser numérico.");
        }
    }

    private function validateEmail(string $field, mixed $value, array $params): void
    {
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'email', "El campo {$field} debe ser un correo válido.");
        }
    }

    private function validateMin(string $field, mixed $value, array $params): void
    {
        $min = (int)($params[0] ?? 0);
        if (is_string($value) && strlen($value) < $min) {
            $this->addError($field, 'min', "El campo {$field} debe tener al menos {$min} caracteres.");
        } elseif (!is_string($value) && is_numeric($value) && $value < $min) {
            $this->addError($field, 'min', "El campo {$field} debe ser al menos {$min}.");
        }
    }

    private function validateMax(string $field, mixed $value, array $params): void
    {
        $max = (int)($params[0] ?? 0);
        if (is_string($value) && strlen($value) > $max) {
            $this->addError($field, 'max', "El campo {$field} no debe exceder {$max} caracteres.");
        } elseif (!is_string($value) && is_numeric($value) && $value > $max) {
            $this->addError($field, 'max', "El campo {$field} no debe exceder {$max}.");
        }
    }

    private function validateGt(string $field, mixed $value, array $params): void
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return;
        }

        $minimo = (float)($params[0] ?? 0);
        if ((float)$value <= $minimo) {
            $this->addError($field, 'gt', "El campo {$field} debe ser mayor que {$minimo}.");
        }
    }

    private function validateIn(string $field, mixed $value, array $params): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!in_array($value, $params, true)) {
            $allowed = implode(', ', $params);
            $this->addError($field, 'in', "El campo {$field} debe ser uno de: {$allowed}.");
        }
    }

    private function validateDate(string $field, mixed $value, array $params): void
    {
        if ($value !== null && !strtotime((string)$value)) {
            $this->addError($field, 'date', "El campo {$field} debe ser una fecha válida.");
        }
    }

    private function validateInteger(string $field, mixed $value, array $params): void
    {
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_INT) && $value !== 0 && $value !== '0') {
            $this->addError($field, 'integer', "El campo {$field} debe ser un entero.");
        }
    }

    private function validateNullable(string $field, mixed $value, array $params): void
    {
    }

    private function validateArray(string $field, mixed $value, array $params): void
    {
        if ($value !== null && !is_array($value)) {
            $this->addError($field, 'array', "El campo {$field} debe ser un arreglo.");
        }
    }
}

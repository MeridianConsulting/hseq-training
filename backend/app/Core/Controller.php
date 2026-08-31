<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function validate(Request $request, array $rules, array $messages = []): array
    {
        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        return $validator->validated();
    }

    protected function validateArray(array $data, array $rules, array $messages = []): array
    {
        $validator = Validator::make($data, $rules, $messages);

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        return $validator->validated();
    }

    protected function success(mixed $data = null, string $message = 'Operación exitosa', int $status = 200): void
    {
        Response::success($data, $message, $status);
    }

    protected function created(mixed $data = null, string $message = 'Recurso creado exitosamente'): void
    {
        Response::created($data, $message);
    }

    protected function error(string $message = 'Error', int $status = 500): void
    {
        Response::error($message, $status);
    }

    protected function paginate(array $items, int $total, int $page, int $perPage): void
    {
        Response::success([
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => max(1, (int)ceil($total / max(1, $perPage))),
            ],
        ]);
    }
}

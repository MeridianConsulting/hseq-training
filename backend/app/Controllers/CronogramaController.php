<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\CronogramaService;

class CronogramaController extends Controller
{
    private CronogramaService $servicio;

    public function __construct()
    {
        $this->servicio = new CronogramaService();
    }

    public function show(Request $request): void
    {
        $query = $request->allQuery();
        $filtros = $this->validateArray([
            'tipo' => $query['tipo'] ?? 'mensual',
            'anio' => $this->enteroONulo($query['anio'] ?? date('Y')),
            'mes' => $this->enteroONulo($query['mes'] ?? null),
            'trimestre' => $this->enteroONulo($query['trimestre'] ?? null),
            'proceso_id' => $this->enteroONulo($query['proceso_id'] ?? null),
        ], [
            'tipo' => 'required|in:mensual,trimestral,anual',
            'anio' => 'required|integer|min:2000|max:2100',
            'mes' => 'nullable|integer|min:1|max:12',
            'trimestre' => 'nullable|integer|min:1|max:4',
            'proceso_id' => 'nullable|integer|min:1',
        ]);

        $this->success($this->servicio->tablero($filtros), 'Cronograma del programa');
    }

    private function enteroONulo(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return is_numeric($valor) ? (int)$valor : null;
    }
}

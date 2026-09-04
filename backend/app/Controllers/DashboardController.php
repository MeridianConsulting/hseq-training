<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\DashboardService;

class DashboardController extends Controller
{
    private DashboardService $indicadores;

    public function __construct()
    {
        $this->indicadores = new DashboardService();
    }

    public function show(Request $request): void
    {
        $query = $request->allQuery();
        $filtros = $this->validateArray([
            'tipo' => $query['tipo'] ?? 'mensual',
            'anio' => $this->enteroONulo($query['anio'] ?? date('Y')),
            'mes' => $this->enteroONulo($query['mes'] ?? null),
            'trimestre' => $this->enteroONulo($query['trimestre'] ?? null),
            'semestre' => $this->enteroONulo($query['semestre'] ?? null),
            'proceso' => $this->textoONulo($query['proceso'] ?? 'todos') ?? 'todos',
            'proyecto' => $this->textoONulo($query['proyecto'] ?? null),
        ], [
            'tipo' => 'required|in:mensual,trimestral,semestral,anual',
            'anio' => 'required|integer|min:2000|max:2100',
            'mes' => 'nullable|integer|min:1|max:12',
            'trimestre' => 'nullable|integer|min:1|max:4',
            'semestre' => 'nullable|integer|min:1|max:2',
            'proceso' => 'required|string|max:120',
            'proyecto' => 'nullable|string|max:120',
        ]);

        $kpis = $this->indicadores->indicadores($filtros);

        $this->success($kpis, 'Indicadores del programa');
    }

    private function enteroONulo(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return is_numeric($valor) ? (int)$valor : null;
    }

    private function textoONulo(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim((string)$valor);

        return $texto === '' ? null : $texto;
    }
}

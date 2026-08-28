<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\DashboardService;
use App\Services\VencimientoService;

class DashboardController extends Controller
{
    private DashboardService $indicadores;
    private VencimientoService $vencimientos;

    public function __construct()
    {
        $this->indicadores = new DashboardService();
        $this->vencimientos = new VencimientoService();
    }

    public function show(Request $request): void
    {
        $query = $request->allQuery();
        $filtros = $this->validateArray([
            'tipo' => $query['tipo'] ?? 'mensual',
            'anio' => $this->enteroONulo($query['anio'] ?? date('Y')),
            'mes' => $this->enteroONulo($query['mes'] ?? null),
            'trimestre' => $this->enteroONulo($query['trimestre'] ?? null),
        ], [
            'tipo' => 'required|in:mensual,trimestral,anual',
            'anio' => 'required|integer|min:2000|max:2100',
            'mes' => 'nullable|integer|min:1|max:12',
            'trimestre' => 'nullable|integer|min:1|max:4',
        ]);

        $kpis = $this->indicadores->indicadores($filtros);
        $vencimientos = $this->vencimientos->dashboard();

        $this->success(array_merge($kpis, $vencimientos), 'Indicadores del programa');
    }

    private function enteroONulo(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return is_numeric($valor) ? (int)$valor : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

class ReporteService
{
    private CumplimientoService $cumplimientos;

    public function __construct()
    {
        $this->cumplimientos = new CumplimientoService();
    }

    /**
     * @param array{buscar?:?string} $filtros
     * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int}
     */
    public function evidenciasFaltantes(int $pagina, int $porPagina, array $filtros): array
    {
        $resultado = $this->cumplimientos->listar($pagina, $porPagina, [
            'buscar' => $filtros['buscar'] ?? null,
            'evidencia_faltante' => 1,
        ]);

        $items = [];
        foreach ($resultado['items'] as $fila) {
            $items[] = $this->filaEvidenciaFaltante($fila);
        }

        return [
            'items' => $items,
            'total' => $resultado['total'],
            'page' => $resultado['page'],
            'per_page' => $resultado['per_page'],
        ];
    }

    /**
     * @param array<string,mixed> $fila
     * @return array<string,mixed>
     */
    private function filaEvidenciaFaltante(array $fila): array
    {
        $codigo = $fila['capacitacion_codigo'] ?? null;
        $nombre = $fila['capacitacion_nombre'] ?? null;
        $capacitacion = $codigo && $nombre ? "{$codigo} — {$nombre}" : ($nombre ?? $codigo);

        return [
            'cumplimiento_id' => (int)$fila['cumplimiento_id'],
            'asignacion_id' => (int)$fila['asignacion_id'],
            'persona_id_ext' => $fila['persona_id_ext'] !== null ? (int)$fila['persona_id_ext'] : null,
            'trabajador' => $fila['persona_nombre'] ?? null,
            'documento' => $fila['numero_documento'] ?? null,
            'capacitacion' => $capacitacion,
            'fecha_realizacion' => $fila['fecha_realizacion'] ?? null,
            'estado' => ($fila['resultado'] ?? '') === CumplimientoService::RESULTADO_APROBADO
                ? 'Completado'
                : 'Pendiente',
            'requiere_certificado' => true,
            'soportes_count' => (int)($fila['soportes_count'] ?? 0),
        ];
    }
}

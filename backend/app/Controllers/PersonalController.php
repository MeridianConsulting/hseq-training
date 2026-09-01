<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditoriaService;
use App\Services\PersonalImportacionService;
use App\Services\PersonalService;

class PersonalController extends Controller
{
    private PersonalService $service;
    private PersonalImportacionService $importacion;
    private AuditoriaService $auditoria;

    public function __construct()
    {
        $this->service = new PersonalService();
        $this->importacion = new PersonalImportacionService();
        $this->auditoria = new AuditoriaService();
    }

    public function index(Request $request): void
    {
        $pagina = (int)$request->query('page', 1);
        $porPagina = (int)$request->query('per_page', 20);
        $buscar = nullable_trimmed_string($request->query('buscar'));
        $estado = nullable_trimmed_string($request->query('estado'));
        $cargoRaw = $request->query('cargo_id');
        $cargoId = ($cargoRaw !== null && $cargoRaw !== '') ? (int)$cargoRaw : null;

        $resultado = $this->service->listar($pagina, $porPagina, $buscar, $estado, $cargoId);

        $this->paginate($resultado['items'], $resultado['total'], $resultado['page'], $resultado['per_page']);
    }

    public function cargos(Request $request): void
    {
        $this->success($this->service->cargos(), 'Cargos corporativos');
    }

    public function tiposDocumento(Request $request): void
    {
        $this->success($this->service->tiposDocumento(), 'Tipos de documento');
    }

    public function show(Request $request, string $id): void
    {
        $this->success($this->service->ver((int)$id));
    }

    public function store(Request $request): void
    {
        $datos = $this->validate($request, $this->reglas(), $this->mensajes());
        $creado = $this->service->crear($datos);

        $this->auditoria->dePeticion(
            $request,
            'crear',
            'personal',
            (int)$creado['persona_id'],
            $creado
        );

        $this->created($creado, $this->mensajeSincronizacion('Trabajador registrado', $creado));
    }

    public function update(Request $request, string $id): void
    {
        $datos = $this->validate($request, $this->reglas(true), $this->mensajes());
        $actualizado = $this->service->editar((int)$id, $datos);

        $this->auditoria->dePeticion(
            $request,
            'actualizar',
            'personal',
            (int)$id,
            $actualizado
        );

        $this->success($actualizado, $this->mensajeSincronizacion('Trabajador actualizado', $actualizado));
    }

    public function plantilla(Request $request): void
    {
        $contenido = $this->importacion->generarPlantilla();
        Response::download(
            $contenido,
            'plantilla_trabajadores.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    public function importar(Request $request): void
    {
        $archivo = $request->file('archivo');

        if ($archivo === null) {
            $this->error('Debe seleccionar un archivo Excel o CSV.', 422);
            return;
        }

        $resultado = $this->importacion->importar($archivo);

        $this->auditoria->dePeticion(
            $request,
            'importar',
            'personal',
            null,
            [
                'total_procesados' => $resultado['total_procesados'],
                'total_importados' => $resultado['total_importados'],
                'total_rechazados' => $resultado['total_rechazados'],
                'archivo' => $archivo['name'] ?? null,
            ]
        );

        $mensaje = $this->mensajeImportacion($resultado);
        $this->success($resultado, $mensaje);
    }

    /** @return array<string, string> */
    private function reglas(bool $esActualizacion = false): array
    {
        if ($esActualizacion) {
            return [
                'correo' => 'nullable|email|max:100',
                'correo_corporativo' => 'nullable|email|max:100',
                'cargo_id' => 'required|integer',
                'proyecto' => 'nullable|string|max:120',
            ];
        }

        return [
            'numero_documento' => 'required|string|max:15',
            'nombre_completo' => 'required|string|max:210',
            'correo' => 'nullable|email|max:100',
            'correo_corporativo' => 'nullable|email|max:100',
            'cargo_id' => 'required|integer',
            'proyecto' => 'nullable|string|max:120',
            'fecha_ingreso' => 'required|date',
            'tipo_documento_id' => 'nullable|integer',
        ];
    }

    /** @return array<string, string> */
    private function mensajes(): array
    {
        return [
            'numero_documento.required' => 'El documento es obligatorio.',
            'nombre_completo.required' => 'El nombre es obligatorio.',
            'cargo_id.required' => 'El cargo es obligatorio.',
            'fecha_ingreso.required' => 'La fecha de ingreso es obligatoria.',
            'fecha_ingreso.date' => 'La fecha de ingreso no es válida.',
            'correo.email' => 'El correo no tiene un formato válido.',
        ];
    }

    /** @param array{total_procesados:int, total_importados:int, total_rechazados:int} $resultado */
    private function mensajeImportacion(array $resultado): string
    {
        if ($resultado['total_procesados'] === 0) {
            return 'No hubo registros para procesar.';
        }

        if ($resultado['total_rechazados'] === 0) {
            return 'Carga finalizada. Todos los registros fueron importados.';
        }

        if ($resultado['total_importados'] === 0) {
            return 'Carga finalizada. No hubo registros válidos.';
        }

        return 'Carga finalizada parcialmente. Se importaron los registros válidos y se rechazaron los inválidos.';
    }

    /** @param array<string,mixed> $persona */
    private function mensajeSincronizacion(string $base, array $persona): string
    {
        $sync = $persona['sincronizacion'] ?? null;
        if (!is_array($sync)) {
            return $base;
        }

        if (!empty($sync['error'])) {
            $verbo = str_contains($base, 'actualizado') ? 'actualizado' : 'registrado';

            return "El trabajador fue {$verbo}, pero ocurrió un problema al generar sus asignaciones de capacitación. Consulte el historial o contacte al administrador.";
        }

        $especiales = [];
        foreach ($sync['creadas_especiales'] ?? [] as $nombre) {
            if (is_string($nombre) && trim($nombre) !== '') {
                $especiales[] = trim($nombre);
            }
        }

        $partes = [$base];
        if ($especiales !== []) {
            $partes[] = 'Se asignó automáticamente: ' . implode(', ', $especiales);
        }

        $creadas = (int)($sync['creadas'] ?? 0);
        $deMatriz = $creadas - count($especiales);
        if ($deMatriz > 0) {
            $partes[] = $deMatriz === $creadas
                ? "{$creadas} asignaciones automáticas creadas."
                : "{$deMatriz} asignaciones de matriz creadas.";
        }

        if (count($partes) === 1) {
            return $base . '. No se encontraron capacitaciones aplicables nuevas para este trabajador.';
        }

        return implode('. ', $partes);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Core\Logger;
use App\Repositories\PersonalRepository;
use PDOException;
use Throwable;

class PersonalService
{
    public const TIPO_DOCUMENTO_CC = 1;
    public const FECHA_NACIMIENTO_TECNICA = '01011900';
    public const MAX_DOCUMENTO = 15;

    private PersonalRepository $repo;
    private ?MotorAsignacionService $motor = null;

    public function __construct()
    {
        $this->repo = new PersonalRepository();
    }

    private function motorAsignacion(): MotorAsignacionService
    {
        return $this->motor ??= new MotorAsignacionService();
    }

    public function listar(int $pagina, int $porPagina, ?string $buscar, ?string $estado, ?int $cargoId): array
    {
        $pagina = max(1, $pagina);
        $porPagina = min(100, max(1, $porPagina));
        $offset = ($pagina - 1) * $porPagina;

        try {
            $items = array_map([$this, 'normalizar'], $this->repo->listar(
                $porPagina,
                $offset,
                $buscar,
                $estado,
                $cargoId
            ));

            return [
                'items' => $items,
                'total' => $this->repo->contar($buscar, $estado, $cargoId),
                'page' => $pagina,
                'per_page' => $porPagina,
            ];
        } catch (Throwable $e) {
            $this->falloPersonal($e);
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarActivosParaMotor(): array
    {
        try {
            return $this->repo->listarActivosParaMotor();
        } catch (Throwable $e) {
            $this->falloPersonal($e);
        }
    }

    public function ver(int $personaId): array
    {
        try {
            $fila = $this->repo->buscarPorId($personaId);
        } catch (Throwable $e) {
            $this->falloPersonal($e);
        }

        if ($fila === null) {
            throw new HttpException('La persona no existe en el maestro de personal corporativo', 404);
        }

        return $this->normalizar($fila);
    }

    public function cargos(): array
    {
        try {
            return array_map(static function (array $fila): array {
                return [
                    'cargo_id' => (int)$fila['cargo_id'],
                    'nombre_cargo' => (string)$fila['nombre_cargo'],
                ];
            }, $this->repo->cargos());
        } catch (Throwable $e) {
            $this->falloPersonal($e);
        }
    }

    public function tiposDocumento(): array
    {
        try {
            return array_map(static function (array $fila): array {
                return [
                    'tipo_documento_id' => (int)$fila['tipo_documento_id'],
                    'descripcion' => (string)$fila['descripcion'],
                    'abreviatura' => (string)$fila['abreviatura'],
                ];
            }, $this->repo->tiposDocumento());
        } catch (Throwable $e) {
            $this->falloPersonal($e);
        }
    }

    public function cargoExiste(int $cargoId): bool
    {
        try {
            return $this->repo->cargoExiste($cargoId);
        } catch (Throwable $e) {
            $this->falloPersonal($e);
        }
    }

    /** @return array<int,string> */
    public function nombresCargosPorIds(array $ids): array
    {
        try {
            return $this->repo->nombresCargosPorIds($ids);
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $entrada
     */
    public function crear(array $entrada): array
    {
        $preparado = $this->prepararEntrada($entrada, null);

        if (!$preparado['ok']) {
            throw new HttpException((string)$preparado['motivo'], $this->codigoHttp((string)$preparado['motivo']));
        }

        $personaId = $this->persistirAlta($preparado['datos']);
        $persona = $this->ver($personaId);
        $persona['sincronizacion'] = $this->sincronizarAsignaciones($persona);

        return $persona;
    }

    /**
     * @param array<string, mixed> $entrada
     */
    public function editar(int $personaId, array $entrada): array
    {
        $actual = $this->ver($personaId);

        $correo = $this->normalizarTexto($entrada['correo'] ?? $entrada['correo_corporativo'] ?? '');
        if ($correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            throw new HttpException('El correo no tiene un formato válido.', 422);
        }

        $cargoId = $this->resolverCargoId($entrada, null);
        if ($cargoId === null) {
            $cargoBruto = $this->normalizarTexto($entrada['cargo'] ?? $entrada['cargo_id'] ?? '');
            throw new HttpException(
                $cargoBruto === '' ? 'El cargo es obligatorio.' : 'El cargo no existe en el catálogo.',
                422
            );
        }

        $proyecto = $this->normalizarTexto($entrada['proyecto'] ?? '');
        $proyecto = $proyecto === '' ? null : $proyecto;

        try {
            $this->repo->transaccion(function () use ($personaId, $actual, $correo, $cargoId, $proyecto): int {
                $this->repo->actualizarPersona($personaId, [
                    'correo_corporativo' => $correo !== '' ? $correo : null,
                    'cargo_id' => $cargoId,
                ]);

                $contratoId = $actual['contrato_id'];
                if ($contratoId) {
                    $this->repo->actualizarContrato($contratoId, [
                        'proyecto' => $proyecto,
                    ]);
                } else {
                    $fechaInicio = is_string($actual['contrato_fecha_inicio'] ?? null)
                        && $actual['contrato_fecha_inicio'] !== ''
                        ? $actual['contrato_fecha_inicio']
                        : date('Y-m-d');
                    $this->repo->insertarContrato([
                        'persona_id' => $personaId,
                        'fecha_inicio' => $fechaInicio,
                        'proyecto' => $proyecto,
                    ]);
                }

                return $personaId;
            });
        } catch (PDOException $e) {
            $this->interpretarEscritura($e);
        } catch (Throwable $e) {
            $this->falloPersonal($e, 'No fue posible actualizar el trabajador');
        }

        $actualizado = $this->ver($personaId);
        $cargoCambio = (int)($actual['cargo_id'] ?? 0) !== $cargoId;
        $proyectoAntes = $this->normalizarTexto($actual['proyecto'] ?? '');
        $proyectoAhora = $proyecto ?? '';
        if ($cargoCambio || strcasecmp($proyectoAntes, $proyectoAhora) !== 0) {
            $actualizado['sincronizacion'] = $this->sincronizarAsignaciones($actualizado);
        }

        return $actualizado;
    }

    /**
     * Validación de negocio reutilizada por el formulario y la carga masiva.
     *
     * @param array<string, mixed> $entrada
     * @param array<string, true>|null $documentosEnBd
     * @param array{por_nombre?: array<string,int>, por_id?: array<int,string>}|null $mapaCargos
     * @return array{ok:bool, motivo:?string, datos:?array}
     */
    public function prepararEntrada(
        array $entrada,
        ?int $exceptoPersonaId,
        ?array $documentosEnBd = null,
        bool $consultarDocumentoEnBd = true,
        ?array $mapaCargos = null
    ): array {
        $documento = $this->normalizarDocumento($entrada['numero_documento'] ?? $entrada['documento'] ?? '');

        if ($documento === '') {
            return $this->rechazo('El documento es obligatorio.');
        }

        if (strlen($documento) > self::MAX_DOCUMENTO) {
            return $this->rechazo('El documento no debe exceder 15 caracteres.');
        }

        $nombreCompleto = $this->normalizarTexto($entrada['nombre_completo'] ?? $entrada['nombre'] ?? '');

        if ($nombreCompleto === '') {
            return $this->rechazo('El nombre es obligatorio.');
        }

        $partes = $this->partirNombre($nombreCompleto);

        if ($partes === null) {
            return $this->rechazo('El nombre debe incluir al menos un nombre y un apellido.');
        }

        $correo = $this->normalizarTexto($entrada['correo'] ?? $entrada['correo_corporativo'] ?? '');

        if ($correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            return $this->rechazo('El correo no tiene un formato válido.');
        }

        $cargoId = $this->resolverCargoId($entrada, $mapaCargos);

        if ($cargoId === null) {
            $cargoBruto = $this->normalizarTexto($entrada['cargo'] ?? $entrada['cargo_id'] ?? '');

            return $this->rechazo($cargoBruto === '' ? 'El cargo es obligatorio.' : 'El cargo no existe en el catálogo.');
        }

        $fecha = $this->parsearFecha($entrada['fecha_ingreso'] ?? $entrada['contrato_fecha_inicio'] ?? '');

        if ($fecha === null) {
            $fechaBruta = $this->normalizarTexto($entrada['fecha_ingreso'] ?? $entrada['contrato_fecha_inicio'] ?? '');

            return $this->rechazo($fechaBruta === '' ? 'La fecha de ingreso es obligatoria.' : 'La fecha de ingreso no es válida.');
        }

        $tipoDocumentoId = (int)($entrada['tipo_documento_id'] ?? self::TIPO_DOCUMENTO_CC);

        if ($tipoDocumentoId <= 0) {
            $tipoDocumentoId = self::TIPO_DOCUMENTO_CC;
        }

        try {
            if (!$this->repo->tipoDocumentoExiste($tipoDocumentoId)) {
                return $this->rechazo('El tipo de documento no es válido.');
            }
        } catch (Throwable $e) {
            $this->falloPersonal($e);
        }

        $duplicado = false;

        if ($documentosEnBd !== null) {
            $duplicado = isset($documentosEnBd[$documento]);
            if ($exceptoPersonaId !== null && $duplicado) {
                try {
                    $duplicado = $this->repo->existeDocumento($documento, $exceptoPersonaId);
                } catch (Throwable $e) {
                    $this->falloPersonal($e);
                }
            }
        } elseif ($consultarDocumentoEnBd) {
            try {
                $duplicado = $this->repo->existeDocumento($documento, $exceptoPersonaId);
            } catch (Throwable $e) {
                $this->falloPersonal($e);
            }
        }

        if ($duplicado) {
            return $this->rechazo('El documento ya se encuentra registrado.');
        }

        $proyecto = $this->normalizarTexto($entrada['proyecto'] ?? '');

        return [
            'ok' => true,
            'motivo' => null,
            'datos' => [
                'numero_documento' => $documento,
                'tipo_documento_id' => $tipoDocumentoId,
                'nombre_completo' => $nombreCompleto,
                'primer_nombre' => $partes['primer_nombre'],
                'segundo_nombre' => $partes['segundo_nombre'],
                'primer_apellido' => $partes['primer_apellido'],
                'segundo_apellido' => $partes['segundo_apellido'],
                'correo_corporativo' => $correo !== '' ? $correo : null,
                'cargo_id' => $cargoId,
                'proyecto' => $proyecto !== '' ? $proyecto : null,
                'fecha_ingreso' => $fecha,
                'fecha_nacimiento_texto' => self::FECHA_NACIMIENTO_TECNICA,
                'estado' => 'Activo',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $datos
     */
    public function persistirAlta(array $datos): int
    {
        try {
            return $this->repo->transaccion(function () use ($datos): int {
                $personaId = $this->repo->insertarPersona([
                    'numero_documento' => $datos['numero_documento'],
                    'tipo_documento_id' => $datos['tipo_documento_id'],
                    'primer_nombre' => $datos['primer_nombre'],
                    'segundo_nombre' => $datos['segundo_nombre'],
                    'primer_apellido' => $datos['primer_apellido'],
                    'segundo_apellido' => $datos['segundo_apellido'],
                    'fecha_nacimiento_texto' => $datos['fecha_nacimiento_texto'],
                    'correo_corporativo' => $datos['correo_corporativo'],
                    'cargo_id' => $datos['cargo_id'],
                    'estado' => $datos['estado'],
                ]);

                $this->repo->insertarContrato([
                    'persona_id' => $personaId,
                    'fecha_inicio' => $datos['fecha_ingreso'],
                    'proyecto' => $datos['proyecto'],
                ]);

                return $personaId;
            });
        } catch (PDOException $e) {
            $this->interpretarEscritura($e);
        } catch (Throwable $e) {
            $this->falloPersonal($e, 'No fue posible registrar el trabajador');
        }

        throw new HttpException('No fue posible registrar el trabajador', 500);
    }

    /**
     * @param array<string,mixed> $persona
     * @return array{creadas:int, omitidas:int, creadas_especiales:list<string>, error:?string}
     */
    public function sincronizarAsignaciones(array $persona): array
    {
        try {
            $resultado = $this->motorAsignacion()->sincronizarPersona($persona, null);
            $especiales = [];
            foreach ($resultado['creadas_especiales'] ?? [] as $nombre) {
                if (is_string($nombre) && $nombre !== '') {
                    $especiales[] = $nombre;
                }
            }

            return [
                'creadas' => (int)$resultado['creadas'],
                'omitidas' => (int)$resultado['omitidas'],
                'creadas_especiales' => $especiales,
                'error' => null,
            ];
        } catch (Throwable $e) {
            Logger::error('RF-008 no pudo sincronizar al trabajador', [
                'persona_id' => $persona['persona_id'] ?? null,
                'cargo_id' => $persona['cargo_id'] ?? null,
                'proyecto' => $persona['proyecto'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'creadas' => 0,
                'omitidas' => 0,
                'creadas_especiales' => [],
                'error' => 'El trabajador fue registrado, pero ocurrió un problema al generar sus asignaciones de capacitación. Consulte el historial o contacte al administrador.',
            ];
        }
    }

    public function repositorio(): PersonalRepository
    {
        return $this->repo;
    }

    public function normalizarDocumento(mixed $valor): string
    {
        $texto = $this->normalizarTexto($valor);
        $texto = str_replace(["\u{00A0}", ' '], '', $texto);

        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $texto) === 1) {
            $texto = str_replace('.', '', $texto);
        }

        return $texto;
    }

    public function normalizarTexto(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }

        if (is_float($valor) || is_int($valor)) {
            if ((float)$valor == floor((float)$valor)) {
                return sprintf('%.0f', $valor);
            }

            return trim((string)$valor);
        }

        $texto = trim((string)$valor);
        $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;

        return trim($texto);
    }

    /**
     * @return array{primer_nombre:string, segundo_nombre:?string, primer_apellido:string, segundo_apellido:?string}|null
     */
    public function partirNombre(string $nombreCompleto): ?array
    {
        $tokens = preg_split('/\s+/u', trim($nombreCompleto)) ?: [];
        $tokens = array_values(array_filter($tokens, static fn ($t) => $t !== ''));

        if (count($tokens) < 2) {
            return null;
        }

        $limite = 50;
        $cortar = static function (string $valor) use ($limite): string {
            if (function_exists('mb_substr')) {
                return mb_substr($valor, 0, $limite, 'UTF-8');
            }

            return substr($valor, 0, $limite);
        };

        $primerNombre = $cortar($tokens[0]);
        $segundoNombre = null;
        $primerApellido = null;
        $segundoApellido = null;

        if (count($tokens) === 2) {
            $primerApellido = $cortar($tokens[1]);
        } elseif (count($tokens) === 3) {
            $segundoNombre = $cortar($tokens[1]);
            $primerApellido = $cortar($tokens[2]);
        } else {
            $segundoApellido = $cortar($tokens[count($tokens) - 1]);
            $primerApellido = $cortar($tokens[count($tokens) - 2]);
            $medio = array_slice($tokens, 1, count($tokens) - 3);
            $segundoNombre = $cortar(implode(' ', $medio));
        }

        return [
            'primer_nombre' => $primerNombre,
            'segundo_nombre' => $segundoNombre !== '' ? $segundoNombre : null,
            'primer_apellido' => (string)$primerApellido,
            'segundo_apellido' => $segundoApellido !== '' && $segundoApellido !== null ? $segundoApellido : null,
        ];
    }

    public function parsearFecha(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }

        if (is_int($valor) || (is_float($valor) && (float)$valor == floor((float)$valor))) {
            return $this->fechaDesdeSerialExcel((int)$valor);
        }

        $texto = $this->normalizarTexto($valor);

        if ($texto === '') {
            return null;
        }

        if (is_numeric($texto) && strpos($texto, '.') === false && (int)$texto > 20000 && (int)$texto < 80000) {
            return $this->fechaDesdeSerialExcel((int)$texto);
        }

        $formatos = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'd/m/y', 'd-m-y'];

        foreach ($formatos as $formato) {
            $fecha = \DateTimeImmutable::createFromFormat('!' . $formato, $texto);
            $errores = \DateTimeImmutable::getLastErrors();

            if ($fecha instanceof \DateTimeImmutable && $errores && $errores['warning_count'] === 0 && $errores['error_count'] === 0) {
                return $fecha->format('Y-m-d');
            }

            if ($fecha instanceof \DateTimeImmutable && $errores === false) {
                return $fecha->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $entrada
     */
    public function resolverCargoId(array $entrada, ?array $mapaCargos = null): ?int
    {
        if (isset($entrada['cargo_id']) && $entrada['cargo_id'] !== '' && $entrada['cargo_id'] !== null) {
            $id = (int)$entrada['cargo_id'];

            if ($id <= 0) {
                return null;
            }

            if ($mapaCargos !== null) {
                return isset($mapaCargos['por_id'][$id]) ? $id : null;
            }

            try {
                return $this->repo->cargoExiste($id) ? $id : null;
            } catch (Throwable $e) {
                $this->falloPersonal($e);
            }
        }

        $cargoTexto = $this->normalizarTexto($entrada['cargo'] ?? '');

        if ($cargoTexto === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $cargoTexto) === 1) {
            $id = (int)$cargoTexto;

            if ($mapaCargos !== null) {
                return isset($mapaCargos['por_id'][$id]) ? $id : null;
            }

            try {
                return $this->repo->cargoExiste($id) ? $id : null;
            } catch (Throwable $e) {
                $this->falloPersonal($e);
            }
        }

        $clave = $this->repo->claveCargo($cargoTexto);

        if ($mapaCargos !== null) {
            return $mapaCargos['por_nombre'][$clave] ?? null;
        }

        try {
            $mapa = $this->repo->mapaCargos();
        } catch (Throwable $e) {
            $this->falloPersonal($e);
        }

        return $mapa['por_nombre'][$clave] ?? null;
    }

    private function fechaDesdeSerialExcel(int $serial): ?string
    {
        if ($serial < 1) {
            return null;
        }

        $unix = ($serial - 25569) * 86400;
        $fecha = gmdate('Y-m-d', $unix);

        return $fecha !== false ? $fecha : null;
    }

    /** @return array{ok:bool, motivo:string, datos:null} */
    private function rechazo(string $motivo): array
    {
        return ['ok' => false, 'motivo' => $motivo, 'datos' => null];
    }

    private function codigoHttp(string $motivo): int
    {
        if ($motivo === 'El documento ya se encuentra registrado.') {
            return 409;
        }

        return 422;
    }

    private function interpretarEscritura(PDOException $e): void
    {
        if (PersonalRepository::esConflictoUnico($e)) {
            throw new HttpException('El documento ya se encuentra registrado.', 409);
        }

        Logger::error('Error al escribir personal corporativo: ' . $e->getMessage());
        throw new HttpException('No fue posible guardar el trabajador', 500);
    }

    private function falloPersonal(Throwable $e, string $mensaje = 'No fue posible consultar el maestro de personal corporativo'): void
    {
        if ($e instanceof HttpException) {
            throw $e;
        }

        Logger::error($mensaje . ': ' . $e->getMessage());

        throw new HttpException(
            $mensaje,
            $e instanceof PDOException ? 503 : 500
        );
    }

    private function normalizar(array $fila): array
    {
        return [
            'persona_id' => (int)$fila['persona_id'],
            'numero_documento' => $fila['numero_documento'],
            'tipo_documento_id' => isset($fila['tipo_documento_id']) ? (int)$fila['tipo_documento_id'] : null,
            'nombre_completo' => $fila['nombre_completo'],
            'estado' => $fila['estado'],
            'cargo_id' => $fila['cargo_id'] !== null ? (int)$fila['cargo_id'] : null,
            'cargo' => $fila['cargo'],
            'correo_corporativo' => $fila['correo_corporativo'],
            'correo_personal' => $fila['correo_personal'],
            'celular' => $fila['celular'],
            'contrato_id' => $fila['contrato_id'] !== null ? (int)$fila['contrato_id'] : null,
            'numero_contrato' => $fila['numero_contrato'],
            'proyecto' => $fila['proyecto'],
            'contrato_fecha_inicio' => $fila['contrato_fecha_inicio'],
            'contrato_fecha_terminacion' => $fila['contrato_fecha_terminacion'],
        ];
    }
}

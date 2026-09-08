<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Repositories\AuditoriaRepository;
use RuntimeException;

/**
 * Escribe en meridian_capacitaciones.auditoria.
 * El esquema en uso tiene valor_anterior / valor_nuevo (no detalle_json).
 * Usuario y marca de tiempo salen del backend; nunca del body del cliente.
 */
class AuditoriaService
{
    public const ORIGEN_USUARIO = 'usuario';
    public const ORIGEN_SISTEMA = 'sistema';
    public const ACTOR_SISTEMA = 'Motor de asignaciones';

    /** @var bool Solo pruebas: el siguiente registrar() lanza. */
    public static bool $fallarRegistro = false;

    /** @var array<string,string> */
    public const ETIQUETAS_ACCION = [
        'crear' => 'Crear',
        'actualizar' => 'Editar',
        'eliminar' => 'Eliminar',
        'inactivar' => 'Inactivar',
        'reactivar' => 'Reactivar',
        'cargar' => 'Cargar soporte',
        'descargar' => 'Descargar soporte',
        'asignar_masivo' => 'Asignación masiva',
        'generar_automaticas' => 'Asignación automática',
        'sincronizar' => 'Guardado masivo de matriz',
        'asociar_masivo' => 'Asociación masiva de matriz',
        'aprobar' => 'Aprobar',
        'devolver' => 'Devolver',
        'enviar_revision' => 'Enviar a revisión',
        'reprogramar' => 'Reprogramar',
        'cancelar' => 'Cancelar',
        'iniciar' => 'Iniciar ejecución',
        'asistencia' => 'Registrar asistencia',
        'finalizar' => 'Finalizar',
        'convocar' => 'Convocar',
        'retirar_convocado' => 'Retirar convocado',
        'registrar_evaluaciones' => 'Registrar evaluación',
        'registrar_masivo' => 'Registro masivo de cumplimientos',
        'crear_actividad' => 'Agregar actividad',
        'editar_actividad' => 'Editar actividad',
        'eliminar_actividad' => 'Retirar actividad',
        'incluir_asignaciones' => 'Incluir asignaciones',
        'quitar_asignacion' => 'Quitar asignación del plan',
        'mover_asignacion' => 'Mover asignación',
        'migracion_inicial' => 'Carga inicial Excel',
        'exportar' => 'Exportar reporte',
        'importar' => 'Importar personal',
    ];

    /** @var array<string,string> */
    public const ETIQUETAS_ENTIDAD = [
        'capacitaciones' => 'Capacitaciones',
        'matriz_aplicabilidad' => 'Matriz de aplicabilidad',
        'planes_anuales' => 'Plan anual',
        'plan_anual_detalle' => 'Plan anual',
        'asignaciones_capacitacion' => 'Asignaciones',
        'sesiones_capacitacion' => 'Tablero de Cronograma',
        'cumplimientos_capacitacion' => 'Cumplimientos',
        'soportes_cumplimiento' => 'Evidencias',
        'personal' => 'Personal',
        'migraciones' => 'Carga inicial Excel',
        'reportes' => 'Reportes',
    ];

    /** @var array<string,string> */
    public const RUTAS_ENTIDAD = [
        'capacitaciones' => '/capacitaciones',
        'matriz_aplicabilidad' => '/matriz',
        'planes_anuales' => '/plan-anual',
        'plan_anual_detalle' => '/plan-anual',
        'asignaciones_capacitacion' => '/asignaciones',
        'sesiones_capacitacion' => '/cronograma',
        'cumplimientos_capacitacion' => '/cumplimientos',
        'soportes_cumplimiento' => '/cumplimientos',
        'personal' => '/personal',
        'migraciones' => '/migracion',
        'reportes' => '/reportes',
    ];

    /** @var list<string> */
    private const ACCIONES_PLAN_DETALLE = [
        'crear_actividad',
        'editar_actividad',
        'eliminar_actividad',
    ];

    /** @var list<string> */
    private const ACCIONES_CRONOGRAMA_DETALLE = [
        'reprogramar',
        'cancelar',
        'iniciar',
    ];

    private AuditoriaRepository $repo;

    public function __construct()
    {
        $this->repo = new AuditoriaRepository();
    }

    /**
     * @return list<array{valor:string,etiqueta:string}>
     */
    public static function opcionesModulo(): array
    {
        return [
            ['valor' => 'capacitaciones', 'etiqueta' => 'Capacitaciones'],
            ['valor' => 'matriz', 'etiqueta' => 'Matriz de aplicabilidad'],
            ['valor' => 'plan-anual', 'etiqueta' => 'Plan anual'],
            ['valor' => 'cronograma', 'etiqueta' => 'Tablero de Cronograma'],
            ['valor' => 'asignaciones', 'etiqueta' => 'Asignaciones'],
            ['valor' => 'cumplimientos', 'etiqueta' => 'Cumplimientos'],
            ['valor' => 'catalogos', 'etiqueta' => 'Catálogos'],
            ['valor' => 'personal', 'etiqueta' => 'Personal'],
            ['valor' => 'migracion', 'etiqueta' => 'Carga inicial Excel'],
            ['valor' => 'reportes', 'etiqueta' => 'Reportes'],
        ];
    }

    /** @return list<string> */
    public static function tablasCatalogo(): array
    {
        $tablas = [];
        foreach (config('catalogs', []) as $def) {
            if (is_array($def) && isset($def['tabla']) && is_string($def['tabla']) && $def['tabla'] !== '') {
                $tablas[] = $def['tabla'];
            }
        }

        return array_values(array_unique($tablas));
    }

    /**
     * @return array{usuario_id:?int,nombre:?string,ip:?string}
     */
    public static function actorDe(Request $request): array
    {
        $usuario = $request->user() ?? [];
        $nombre = isset($usuario['nombre_usuario']) ? trim((string)$usuario['nombre_usuario']) : '';

        return [
            'usuario_id' => $request->userId() ?: null,
            'nombre' => $nombre !== '' ? $nombre : null,
            'ip' => $request->ip(),
        ];
    }

    /**
     * @param array{usuario_id:?int,nombre:?string,ip:?string} $actor
     */
    public function deActor(
        array $actor,
        string $accion,
        ?string $entidad,
        ?int $entidadId,
        mixed $detalle = null,
        mixed $anterior = null
    ): void {
        $this->registrar(
            isset($actor['usuario_id']) ? (int)$actor['usuario_id'] : null,
            $accion,
            $entidad,
            $entidadId,
            $detalle,
            $actor['ip'] ?? null,
            isset($actor['nombre']) && is_string($actor['nombre']) ? $actor['nombre'] : null,
            $anterior
        );
    }

    public function dePeticion(
        Request $request,
        string $accion,
        ?string $entidad,
        ?int $entidadId,
        mixed $detalle = null,
        mixed $anterior = null
    ): void {
        $this->deActor(self::actorDe($request), $accion, $entidad, $entidadId, $detalle, $anterior);
    }

    public function registrarSistema(
        string $accion,
        ?string $entidad,
        ?int $entidadId,
        mixed $detalle = null,
        mixed $anterior = null
    ): void {
        $this->registrar(
            null,
            $accion,
            $entidad,
            $entidadId,
            $detalle,
            null,
            self::ACTOR_SISTEMA,
            $anterior
        );
    }

    public function registrar(
        ?int $usuarioId,
        string $accion,
        ?string $entidad = null,
        ?int $entidadId = null,
        mixed $detalle = null,
        ?string $ip = null,
        ?string $usuarioNombre = null,
        mixed $anterior = null
    ): void {
        if (self::$fallarRegistro) {
            self::$fallarRegistro = false;
            throw new RuntimeException('No fue posible registrar la auditoría.');
        }

        $this->repo->registrar(
            $usuarioId !== null && $usuarioId > 0 ? $usuarioId : null,
            $usuarioNombre,
            $accion,
            $entidad,
            $entidadId,
            $this->aJson($anterior),
            $this->aJson($detalle),
            $ip
        );
    }

    /**
     * @param array<string,mixed> $antes
     * @param array<string,mixed> $despues
     * @param array<string,string> $campos mapa campo => etiqueta
     * @return list<array{campo:string,etiqueta:string,anterior:mixed,nuevo:mixed}>
     */
    public function diff(array $antes, array $despues, array $campos): array
    {
        $cambios = [];
        foreach ($campos as $campo => $etiqueta) {
            $a = $this->normalizarValor($antes[$campo] ?? null);
            $b = $this->normalizarValor($despues[$campo] ?? null);
            if ($a === $b) {
                continue;
            }
            $cambios[] = [
                'campo' => $campo,
                'etiqueta' => $etiqueta,
                'anterior' => $a,
                'nuevo' => $b,
            ];
        }

        return $cambios;
    }

    /**
     * @param list<array<string,mixed>> $antes
     * @param list<array<string,mixed>> $despues
     * @return list<array{campo:string,etiqueta:string,anterior:mixed,nuevo:mixed,persona_id_ext:?int,numero_documento:?string,persona_nombre:?string}>
     */
    public function diffAsistencia(array $antes, array $despues): array
    {
        $porAsig = [];
        foreach ($antes as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $id = (int)($fila['asignacion_id'] ?? 0);
            if ($id > 0) {
                $porAsig[$id] = $fila;
            }
        }

        $cambios = [];
        foreach ($despues as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $id = (int)($fila['asignacion_id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $prev = $porAsig[$id] ?? [];
            $a = $this->normalizarValor($prev['estado_asistencia'] ?? null);
            $b = $this->normalizarValor($fila['estado_asistencia'] ?? null);
            if ($a === $b) {
                continue;
            }
            $cambios[] = [
                'campo' => 'estado_asistencia',
                'etiqueta' => 'Asistencia',
                'anterior' => $a,
                'nuevo' => $b,
                'persona_id_ext' => isset($fila['persona_id_ext']) ? (int)$fila['persona_id_ext'] : null,
                'numero_documento' => isset($fila['numero_documento']) ? (string)$fila['numero_documento'] : null,
                'persona_nombre' => isset($fila['persona_nombre']) ? (string)$fila['persona_nombre'] : null,
            ];
        }

        return $cambios;
    }

    /**
     * @param list<array{campo:string,etiqueta:string,anterior:mixed,nuevo:mixed}> $cambios
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    public function payloadNuevo(array $cambios, string $origen, array $extra = []): array
    {
        return array_merge($extra, [
            'cambios' => $cambios,
            'origen' => $origen,
        ]);
    }

    /**
     * Recorte de alta de personal (sin PII innecesaria).
     *
     * @param array<string,mixed> $persona
     * @return array<string,mixed>
     */
    public function recortePersonal(array $persona): array
    {
        return [
            'persona_id' => $persona['persona_id'] ?? null,
            'numero_documento' => $persona['numero_documento'] ?? null,
            'correo_corporativo' => $persona['correo_corporativo'] ?? null,
            'cargo_id' => $persona['cargo_id'] ?? null,
            'cargo' => $persona['cargo'] ?? null,
            'proyecto' => $persona['proyecto'] ?? null,
        ];
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int}
     */
    public function listar(int $pagina, int $porPagina, array $filtros = []): array
    {
        $pagina = max(1, $pagina);
        $porPagina = min(100, max(1, $porPagina));
        $offset = ($pagina - 1) * $porPagina;
        $limpios = $this->normalizarFiltros($filtros);

        $items = $this->repo->listar($porPagina, $offset, $limpios);
        foreach ($items as &$item) {
            $item['auditoria_id'] = (int)$item['auditoria_id'];
            $item['usuario_id_ext'] = $item['usuario_id_ext'] !== null ? (int)$item['usuario_id_ext'] : null;
            $item['entidad_id'] = $item['entidad_id'] !== null ? (int)$item['entidad_id'] : null;
            $item['nombre_usuario'] = $item['usuario_nombre'] ?: ($item['nombre_usuario'] ?? null);
            $nuevo = $this->desdeJson($item['valor_nuevo'] ?? null);
            $anterior = $this->desdeJson($item['valor_anterior'] ?? null);
            $item['detalle'] = $nuevo;
            $item['valor_anterior'] = $anterior;
            $item['valor_nuevo'] = $nuevo;
            $item['cambios'] = is_array($nuevo) && isset($nuevo['cambios']) && is_array($nuevo['cambios'])
                ? $nuevo['cambios']
                : [];
            $item['origen'] = is_array($nuevo) && isset($nuevo['origen']) ? $nuevo['origen'] : null;
            $entidad = $item['entidad'] !== null ? (string)$item['entidad'] : '';
            $accion = (string)$item['accion'];
            $item['modulo'] = $this->moduloDe($entidad, $accion);
            $item['modulo_etiqueta'] = $this->etiquetaModulo($entidad, $accion);
            $item['accion_etiqueta'] = self::ETIQUETAS_ACCION[$accion] ?? $accion;
            $item['ruta_relacionada'] = $this->rutaDe($entidad, $accion);
            $item['resumen'] = $this->resumen($accion, $entidad, $item['entidad_id'], $item['cambios'], $nuevo);
            unset($item['usuario_nombre']);
        }
        unset($item);

        return [
            'items' => $items,
            'total' => $this->repo->contar($limpios),
            'page' => $pagina,
            'per_page' => $porPagina,
        ];
    }

    /** @param array<string,mixed> $filtros */
    private function normalizarFiltros(array $filtros): array
    {
        $desde = isset($filtros['desde']) ? trim((string)$filtros['desde']) : '';
        $hasta = isset($filtros['hasta']) ? trim((string)$filtros['hasta']) : '';

        if ($desde !== '' && !$this->esFecha($desde)) {
            throw new HttpException('La fecha desde debe tener formato AAAA-MM-DD.', 422);
        }
        if ($hasta !== '' && !$this->esFecha($hasta)) {
            throw new HttpException('La fecha hasta debe tener formato AAAA-MM-DD.', 422);
        }
        if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
            throw new HttpException('La fecha desde no puede ser posterior a la fecha hasta.', 422);
        }

        $modulo = isset($filtros['modulo']) ? trim((string)$filtros['modulo']) : '';
        $mapaModulo = $this->filtroModulo($modulo);

        return [
            'entidad' => isset($filtros['entidad']) ? trim((string)$filtros['entidad']) : '',
            'accion' => isset($filtros['accion']) ? trim((string)$filtros['accion']) : '',
            'usuario' => isset($filtros['usuario']) ? trim((string)$filtros['usuario']) : '',
            'usuario_id' => isset($filtros['usuario_id']) ? (int)$filtros['usuario_id'] : 0,
            'entidad_id' => isset($filtros['entidad_id']) ? (int)$filtros['entidad_id'] : 0,
            'desde' => $desde !== '' ? $desde : '',
            'hasta' => $hasta !== '' ? $hasta : '',
            'q' => isset($filtros['q']) ? trim((string)$filtros['q']) : '',
            'modulo_grupos' => $mapaModulo,
        ];
    }

    /**
     * @return list<array{entidades:list<string>,acciones:?list<string>}>
     */
    private function filtroModulo(string $modulo): array
    {
        if ($modulo === '') {
            return [];
        }

        switch ($modulo) {
            case 'capacitaciones':
                return [['entidades' => ['capacitaciones'], 'acciones' => null]];
            case 'matriz':
                return [['entidades' => ['matriz_aplicabilidad'], 'acciones' => null]];
            case 'plan-anual':
                return [
                    ['entidades' => ['planes_anuales'], 'acciones' => null],
                    ['entidades' => ['plan_anual_detalle'], 'acciones' => self::ACCIONES_PLAN_DETALLE],
                ];
            case 'cronograma':
                return [
                    ['entidades' => ['sesiones_capacitacion'], 'acciones' => null],
                    ['entidades' => ['plan_anual_detalle'], 'acciones' => self::ACCIONES_CRONOGRAMA_DETALLE],
                ];
            case 'asignaciones':
                return [['entidades' => ['asignaciones_capacitacion'], 'acciones' => null]];
            case 'cumplimientos':
                return [['entidades' => ['cumplimientos_capacitacion', 'soportes_cumplimiento'], 'acciones' => null]];
            case 'catalogos':
                return [['entidades' => self::tablasCatalogo(), 'acciones' => null]];
            case 'personal':
                return [['entidades' => ['personal'], 'acciones' => null]];
            case 'migracion':
                return [['entidades' => ['migraciones'], 'acciones' => null]];
            case 'reportes':
                return [['entidades' => ['reportes'], 'acciones' => null]];
            default:
                throw new HttpException('El módulo de filtro no es válido.', 422);
        }
    }

    private function moduloDe(string $entidad, string $accion): string
    {
        if ($entidad === 'capacitaciones') {
            return 'capacitaciones';
        }
        if ($entidad === 'matriz_aplicabilidad') {
            return 'matriz';
        }
        if ($entidad === 'planes_anuales' || ($entidad === 'plan_anual_detalle' && in_array($accion, self::ACCIONES_PLAN_DETALLE, true))) {
            return 'plan-anual';
        }
        if ($entidad === 'sesiones_capacitacion' || ($entidad === 'plan_anual_detalle' && in_array($accion, self::ACCIONES_CRONOGRAMA_DETALLE, true))) {
            return 'cronograma';
        }
        if ($entidad === 'plan_anual_detalle') {
            return 'plan-anual';
        }
        if ($entidad === 'asignaciones_capacitacion') {
            return 'asignaciones';
        }
        if ($entidad === 'cumplimientos_capacitacion' || $entidad === 'soportes_cumplimiento') {
            return 'cumplimientos';
        }
        if ($entidad === 'personal') {
            return 'personal';
        }
        if ($entidad === 'migraciones') {
            return 'migracion';
        }
        if ($entidad === 'reportes') {
            return 'reportes';
        }
        if (in_array($entidad, self::tablasCatalogo(), true)) {
            return 'catalogos';
        }

        return $entidad;
    }

    private function etiquetaModulo(string $entidad, string $accion): string
    {
        $modulo = $this->moduloDe($entidad, $accion);
        foreach (self::opcionesModulo() as $op) {
            if ($op['valor'] === $modulo) {
                return $op['etiqueta'];
            }
        }

        return self::ETIQUETAS_ENTIDAD[$entidad] ?? ($entidad !== '' ? $entidad : '—');
    }

    private function rutaDe(string $entidad, string $accion): ?string
    {
        if ($entidad === 'plan_anual_detalle' && in_array($accion, self::ACCIONES_CRONOGRAMA_DETALLE, true)) {
            return '/cronograma';
        }
        if (isset(self::RUTAS_ENTIDAD[$entidad])) {
            return self::RUTAS_ENTIDAD[$entidad];
        }
        if (in_array($entidad, self::tablasCatalogo(), true)) {
            return '/catalogos';
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $cambios
     */
    private function resumen(string $accion, string $entidad, ?int $entidadId, array $cambios, mixed $nuevo): string
    {
        $accionEtiqueta = self::ETIQUETAS_ACCION[$accion] ?? $accion;
        if ($cambios !== []) {
            $primero = $cambios[0];
            $campo = (string)($primero['etiqueta'] ?? $primero['campo'] ?? 'campo');
            $persona = isset($primero['persona_nombre']) && is_string($primero['persona_nombre']) && $primero['persona_nombre'] !== ''
                ? $primero['persona_nombre'] . ': '
                : '';
            $extra = count($cambios) > 1 ? ' (+' . (count($cambios) - 1) . ')' : '';

            return $persona . $campo . ': ' . $this->textoCorto($primero['anterior'] ?? null)
                . ' → ' . $this->textoCorto($primero['nuevo'] ?? null) . $extra;
        }

        if (is_array($nuevo)) {
            if (isset($nuevo['agregadas']) || isset($nuevo['retiradas'])) {
                $ag = is_array($nuevo['agregadas'] ?? null) ? count($nuevo['agregadas']) : (int)($nuevo['creadas'] ?? 0);
                $re = is_array($nuevo['retiradas'] ?? null) ? count($nuevo['retiradas']) : (int)($nuevo['inactivadas'] ?? 0);

                return "Agregadas: {$ag}. Retiradas: {$re}.";
            }
            if (isset($nuevo['seleccionados']) || isset($nuevo['creadas'])) {
                $sel = (int)($nuevo['seleccionados'] ?? $nuevo['trabajadores'] ?? 0);
                $cre = (int)($nuevo['creadas'] ?? $nuevo['trabajadores'] ?? 0);
                $omi = (int)($nuevo['omitidas'] ?? 0);

                return "Seleccionados: {$sel}. Creadas: {$cre}. Omitidas: {$omi}.";
            }
            if (isset($nuevo['codigo']) && is_string($nuevo['codigo'])) {
                return $accionEtiqueta . ' ' . $nuevo['codigo'];
            }
            if (isset($nuevo['nombre_archivo'])) {
                return $accionEtiqueta . ': ' . (string)$nuevo['nombre_archivo'];
            }
            if (isset($nuevo['numero_documento'])) {
                return $accionEtiqueta . ' documento ' . (string)$nuevo['numero_documento'];
            }
        }

        $registro = $entidadId !== null && $entidadId > 0 ? ' #' . $entidadId : '';

        return $accionEtiqueta . $registro;
    }

    private function textoCorto(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }
        if (is_bool($valor)) {
            return $valor ? 'Sí' : 'No';
        }
        if (is_scalar($valor)) {
            $txt = (string)$valor;

            return strlen($txt) > 40 ? substr($txt, 0, 37) . '...' : $txt;
        }

        return '…';
    }

    private function esFecha(string $valor): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return false;
        }
        $partes = explode('-', $valor);

        return checkdate((int)$partes[1], (int)$partes[2], (int)$partes[0]);
    }

    private function normalizarValor(mixed $valor): mixed
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_bool($valor)) {
            return $valor;
        }
        if (is_int($valor) || is_float($valor) || (is_string($valor) && is_numeric($valor))) {
            $numero = (float)$valor;
            if (is_finite($numero) && abs($numero - round($numero)) < 0.00001) {
                return (int)round($numero);
            }

            return round($numero, 4);
        }
        if (is_string($valor)) {
            $txt = trim($valor);
            if ($txt === '') {
                return null;
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $txt) === 1) {
                return substr($txt, 0, 10);
            }

            return $txt;
        }

        return $valor;
    }

    private function aJson(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $codificado = json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $codificado === false ? null : $codificado;
    }

    private function desdeJson(mixed $raw): mixed
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $decodificado = json_decode((string)$raw, true);

        return json_last_error() === JSON_ERROR_NONE ? $decodificado : $raw;
    }
}

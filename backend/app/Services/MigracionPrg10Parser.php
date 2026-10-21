<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Lee la plantilla HSEQ-PRG-10 (CRONOGRAMA, MATRIZ POR CARGO / alias, SEGUIMIENTO_PERSONAL / SEGUIM_*).
 */
class MigracionPrg10Parser
{
    public const HOJA_CRONOGRAMA = 'CRONOGRAMA';
    public const HOJA_MATRIZ = 'MATRIZ POR CARGO';
    public const HOJA_SEGUIMIENTO = 'SEGUIMIENTO_PERSONAL';

    /** @var list<string> Nombres canónicos reportados al usuario si faltan. */
    public const HOJAS = [self::HOJA_CRONOGRAMA, self::HOJA_MATRIZ, self::HOJA_SEGUIMIENTO];

    /**
     * @return array{
     *   hojas:list<string>,
     *   faltantes:list<string>,
     *   estructura_ok:bool,
     *   mensaje:?string,
     *   capacitaciones:list<array<string,mixed>>,
     *   matriz:list<array<string,mixed>>,
     *   trabajadores:list<array<string,mixed>>,
     *   seguimientos:list<array<string,mixed>>
     * }
     */
    public function leer(string $ruta): array
    {
        $libro = IOFactory::load($ruta);
        $nombres = $libro->getSheetNames();
        $mapa = [
            self::HOJA_CRONOGRAMA => $this->resolverNombreHoja($nombres, self::HOJA_CRONOGRAMA),
            self::HOJA_MATRIZ => $this->resolverNombreHoja($nombres, self::HOJA_MATRIZ),
            self::HOJA_SEGUIMIENTO => $this->resolverNombreHoja($nombres, self::HOJA_SEGUIMIENTO),
        ];

        $faltantes = [];
        foreach ($mapa as $canonica => $real) {
            if ($real === null) {
                $faltantes[] = $canonica;
            }
        }

        if ($faltantes !== []) {
            return [
                'hojas' => $nombres,
                'faltantes' => $faltantes,
                'estructura_ok' => false,
                'mensaje' => 'Hoja faltante: ' . $faltantes[0],
                'capacitaciones' => [],
                'matriz' => [],
                'trabajadores' => [],
                'seguimientos' => [],
            ];
        }

        $crono = $this->hojaPorNombre($libro, (string)$mapa[self::HOJA_CRONOGRAMA]);
        $matriz = $this->hojaPorNombre($libro, (string)$mapa[self::HOJA_MATRIZ]);
        $seg = $this->hojaPorNombre($libro, (string)$mapa[self::HOJA_SEGUIMIENTO]);

        $caps = $this->leerCapacitaciones($crono);
        if ($caps === []) {
            return [
                'hojas' => $nombres,
                'faltantes' => [],
                'estructura_ok' => false,
                'mensaje' => 'No fue posible procesar el archivo. Verifique que corresponde a la matriz HSEQ requerida.',
                'capacitaciones' => [],
                'matriz' => [],
                'trabajadores' => [],
                'seguimientos' => [],
            ];
        }

        $porItem = [];
        foreach ($caps as $cap) {
            $porItem[(int)$cap['item']] = $cap;
        }

        return [
            'hojas' => $nombres,
            'faltantes' => [],
            'estructura_ok' => true,
            'mensaje' => null,
            'capacitaciones' => $caps,
            'matriz' => $this->leerMatriz($matriz, $caps, $crono),
            'trabajadores' => $this->leerTrabajadores($seg),
            'seguimientos' => $this->leerSeguimientos($seg, $caps, $crono),
        ];
    }

    /**
     * Devuelve el título real de la hoja que cubre el rol canónico, o null.
     *
     * @param list<string> $nombres
     */
    private function resolverNombreHoja(array $nombres, string $canonica): ?string
    {
        $candidatos = [];
        foreach ($nombres as $nombre) {
            $titulo = trim((string)$nombre);
            if ($titulo === '') {
                continue;
            }
            if ($this->hojaCoincideConRol($titulo, $canonica)) {
                $candidatos[] = $titulo;
            }
        }

        if ($candidatos === []) {
            return null;
        }

        return $this->elegirMejorCandidato($candidatos, $canonica);
    }

    private function hojaCoincideConRol(string $titulo, string $canonica): bool
    {
        $clave = $this->normalizarTituloHoja($titulo);
        $esperada = $this->normalizarTituloHoja($canonica);

        if ($clave === $esperada) {
            return true;
        }

        if ($canonica === self::HOJA_MATRIZ) {
            return $clave === 'MATRIZ_CARGO'
                || $clave === 'MATRIZPORCARGO'
                || str_starts_with($clave, 'MATRIZ_CARGO')
                || str_starts_with($clave, 'MATRIZPORCARGO');
        }

        if ($canonica === self::HOJA_SEGUIMIENTO) {
            return $clave === 'SEGUIMIENTO_PERSONAL'
                || str_starts_with($clave, 'SEGUIMIENTO_PERSONAL')
                || str_starts_with($clave, 'SEGUIM_')
                || str_starts_with($clave, 'SEGUIMIENTO');
        }

        return false;
    }

    /**
     * @param list<string> $candidatos
     */
    private function elegirMejorCandidato(array $candidatos, string $canonica): string
    {
        if (count($candidatos) === 1) {
            return $candidatos[0];
        }

        usort($candidatos, function (string $a, string $b) use ($canonica): int {
            return $this->prioridadCandidato($b, $canonica) <=> $this->prioridadCandidato($a, $canonica);
        });

        return $candidatos[0];
    }

    private function prioridadCandidato(string $titulo, string $canonica): int
    {
        $clave = $this->normalizarTituloHoja($titulo);
        $esperada = $this->normalizarTituloHoja($canonica);

        if ($clave === $esperada) {
            return 100;
        }

        if ($canonica === self::HOJA_SEGUIMIENTO) {
            if ($clave === 'SEGUIMIENTO_PERSONAL') {
                return 100;
            }
            // Preferir SEGUIM_ADMON / SEGUIM_PETRO / etc. sobre brigadas u otros anexos.
            if (str_contains($clave, 'BRIG') || str_contains($clave, 'COPASST') || str_contains($clave, 'CCL')) {
                return 10;
            }
            if (str_starts_with($clave, 'SEGUIM_')) {
                return 80;
            }
            if (str_starts_with($clave, 'SEGUIMIENTO')) {
                return 70;
            }
        }

        if ($canonica === self::HOJA_MATRIZ) {
            if ($clave === 'MATRIZ_CARGO' || $clave === 'MATRIZPORCARGO') {
                return 90;
            }
        }

        return 50;
    }

    private function normalizarTituloHoja(string $titulo): string
    {
        $titulo = mb_strtoupper(trim($titulo), 'UTF-8');
        $titulo = strtr($titulo, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'Ä' => 'A', 'Ë' => 'E', 'Ï' => 'I', 'Ö' => 'O', 'Ü' => 'U',
            'Ñ' => 'N',
        ]);
        $titulo = str_replace([' ', '-'], '_', $titulo);
        $titulo = preg_replace('/_+/', '_', $titulo) ?? $titulo;

        return trim($titulo, '_');
    }

    private function hojaPorNombre(Spreadsheet $libro, string $titulo): Worksheet
    {
        foreach ($libro->getWorksheetIterator() as $hoja) {
            if (strcasecmp(trim($hoja->getTitle()), $titulo) === 0) {
                return $hoja;
            }
        }

        return $libro->getSheetByName($titulo) ?? $libro->getActiveSheet();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function leerCapacitaciones(Worksheet $hoja): array
    {
        $max = (int)$hoja->getHighestRow();
        $items = [];
        for ($fila = 5; $fila <= $max; $fila++) {
            $itemBruto = $this->celda($hoja, 1, $fila);
            if (!preg_match('/^\d+$/', $itemBruto)) {
                continue;
            }
            $item = (int)$itemBruto;
            if ($item < 1 || $item > 99) {
                continue;
            }
            $tema = $this->primeraNoVacia($hoja, $fila, [5, 4, 2]);
            if ($tema === '') {
                continue;
            }
            $temario = $this->celda($hoja, 6, $fila);
            $horas = $this->celda($hoja, 7, $fila);
            $objetivo = $this->celda($hoja, 8, $fila);
            $metodologia = $this->celda($hoja, 9, $fila);
            $codigo = sprintf('HSEQ-%02d', $item);
            $items[] = [
                'fila' => $fila,
                'item' => $item,
                'codigo' => $codigo,
                'nombre' => $tema,
                'objetivo' => $objetivo !== '' ? $objetivo : $tema,
                'temario' => $temario,
                'horas' => $horas,
                'metodologia' => $metodologia,
            ];
        }

        return $items;
    }

    /**
     * @param list<array<string,mixed>> $caps
     * @return list<array<string,mixed>>
     */
    private function leerMatriz(Worksheet $hoja, array $caps, ?Worksheet $crono = null): array
    {
        $maxCol = Coordinate::columnIndexFromString($hoja->getHighestColumn());
        $maxRow = (int)$hoja->getHighestRow();
        $temaPorCol = [];
        for ($col = 4; $col <= $maxCol; $col++) {
            $nombre = '';
            for ($r = 1; $r <= 8; $r++) {
                $valor = $this->textoTemaCelda($hoja, $col, $r, $crono);
                if ($valor !== '' && !$this->esNumeroTema($valor)) {
                    $nombre = $valor;
                    break;
                }
            }
            $codigo = $this->codigoPorNombre($caps, $nombre);
            if ($codigo !== null) {
                $temaPorCol[$col] = $codigo;
            }
        }

        $filas = [];
        for ($fila = 4; $fila <= $maxRow; $fila++) {
            $cargo = $this->celda($hoja, 1, $fila);
            if ($cargo === '' || $this->pareceEncabezadoCargo($cargo)) {
                continue;
            }
            $proyecto = $this->celda($hoja, 2, $fila);
            $proceso = $this->celda($hoja, 3, $fila);
            foreach ($temaPorCol as $col => $codigo) {
                $marca = strtoupper($this->celda($hoja, $col, $fila));
                if ($marca !== 'X') {
                    continue;
                }
                $filas[] = [
                    'fila' => $fila,
                    'cargo' => $cargo,
                    'proyecto' => $proyecto,
                    'proceso' => $proceso,
                    'codigo' => $codigo,
                ];
            }
        }

        return $filas;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function leerTrabajadores(Worksheet $hoja): array
    {
        $mapa = $this->mapaIdentidad($hoja);
        if ($mapa === null) {
            return [];
        }
        [$headerRow, $cols] = $mapa;
        $max = (int)$hoja->getHighestRow();
        $items = [];
        for ($fila = $headerRow + 1; $fila <= $max; $fila++) {
            $nombre = $this->celda($hoja, $cols['nombre'], $fila);
            $doc = $cols['documento'] > 0 ? $this->celda($hoja, $cols['documento'], $fila) : '';
            if ($nombre === '' && $doc === '') {
                continue;
            }
            if ($this->parecePieSeguimiento($nombre)) {
                break;
            }
            $items[] = [
                'fila' => $fila,
                'documento' => $doc,
                'nombre' => $nombre,
                'correo' => $cols['correo'] > 0 ? $this->celda($hoja, $cols['correo'], $fila) : '',
                'cargo' => $cols['cargo'] > 0 ? $this->celda($hoja, $cols['cargo'], $fila) : '',
                'estado' => $cols['estado'] > 0 ? $this->celda($hoja, $cols['estado'], $fila) : '',
                'fecha_ingreso' => $cols['fecha_ingreso'] > 0 ? $this->valorCrudo($hoja, $cols['fecha_ingreso'], $fila) : '',
                'area' => $cols['area'] > 0 ? $this->celda($hoja, $cols['area'], $fila) : '',
                'tiene_columna_ingreso' => $cols['fecha_ingreso'] > 0,
            ];
        }

        return $items;
    }

    /**
     * @param list<array<string,mixed>> $caps
     * @return list<array<string,mixed>>
     */
    private function leerSeguimientos(Worksheet $hoja, array $caps, ?Worksheet $crono = null): array
    {
        $mapa = $this->mapaIdentidad($hoja);
        if ($mapa === null) {
            return [];
        }
        [$headerRow, $cols] = $mapa;
        $maxCol = Coordinate::columnIndexFromString($hoja->getHighestColumn());
        $inicioBase = max($cols['ultima_identidad'] + 1, 10);
        $bloques = $this->bloquesSeguimientoMejorAlineacion($hoja, $caps, $headerRow, $inicioBase, $maxCol, $crono);
        if ($bloques === []) {
            return [];
        }

        $max = (int)$hoja->getHighestRow();
        $items = [];
        for ($fila = $headerRow + 1; $fila <= $max; $fila++) {
            $doc = $cols['documento'] > 0 ? $this->celda($hoja, $cols['documento'], $fila) : '';
            $nombre = $this->celda($hoja, $cols['nombre'], $fila);
            if ($doc === '' && $nombre === '') {
                continue;
            }
            if ($this->parecePieSeguimiento($nombre)) {
                break;
            }
            foreach ($bloques as $bloque) {
                $estado = strtoupper($this->celda($hoja, $bloque['estado'], $fila));
                if ($estado === '') {
                    continue;
                }
                $items[] = [
                    'fila' => $fila,
                    'documento' => $doc,
                    'codigo' => $bloque['codigo'],
                    'estado' => $estado,
                    'nota' => $this->valorCrudo($hoja, $bloque['resultado'], $fila),
                    'certificado' => strtoupper($this->celda($hoja, $bloque['certificado'], $fila)),
                    'mes' => $this->valorCrudo($hoja, $bloque['mes'], $fila),
                ];
            }
        }

        return $items;
    }

    /**
     * @return array{0:int,1:array<string,int>}|null
     */
    private function mapaIdentidad(Worksheet $hoja): ?array
    {
        $maxCol = min(20, Coordinate::columnIndexFromString($hoja->getHighestColumn()));
        $maxRow = min(15, (int)$hoja->getHighestRow());
        for ($fila = 1; $fila <= $maxRow; $fila++) {
            $cols = [
                'documento' => 0,
                'nombre' => 0,
                'correo' => 0,
                'cargo' => 0,
                'estado' => 0,
                'fecha_ingreso' => 0,
                'area' => 0,
                'ultima_identidad' => 0,
            ];
            $halloDoc = false;
            $halloNombre = false;
            for ($col = 1; $col <= $maxCol; $col++) {
                $clave = $this->claveEncabezado($this->celda($hoja, $col, $fila));
                if ($clave === '') {
                    continue;
                }
                // Tras la columna de temas no seguir tomando "estado"/otros como identidad.
                if ($this->esEtiquetaBloque($clave) || str_contains($clave, 'evaluacion') || str_contains($clave, 'requiere')) {
                    break;
                }
                if ($this->esEncabezadoDocumento($clave)) {
                    $cols['documento'] = $col;
                    $halloDoc = true;
                } elseif (in_array($clave, ['nombre completo', 'nombre'], true) && $cols['nombre'] === 0) {
                    $cols['nombre'] = $col;
                    $halloNombre = true;
                } elseif (($clave === 'correo' || str_starts_with($clave, 'correo')) && $cols['correo'] === 0) {
                    $cols['correo'] = $col;
                } elseif ($clave === 'cargo' && $cols['cargo'] === 0) {
                    $cols['cargo'] = $col;
                } elseif ($clave === 'estado' && $cols['estado'] === 0) {
                    $cols['estado'] = $col;
                } elseif ($this->esEncabezadoFechaIngreso($clave) && $cols['fecha_ingreso'] === 0) {
                    $cols['fecha_ingreso'] = $col;
                } elseif ($clave === 'area' && $cols['area'] === 0) {
                    $cols['area'] = $col;
                }
            }
            if ($halloDoc || $halloNombre) {
                $cols['ultima_identidad'] = max(
                    $cols['documento'],
                    $cols['nombre'],
                    $cols['correo'],
                    $cols['cargo'],
                    $cols['estado'],
                    $cols['fecha_ingreso'],
                    $cols['area'],
                    9
                );

                return [$fila, $cols];
            }
        }

        return null;
    }

    /**
     * Prueba desplazamientos 0..3 para alinear bloques de 4 columnas (Petro arranca en 12, Frontera en 10).
     *
     * @param list<array<string,mixed>> $caps
     * @return list<array{codigo:string,estado:int,resultado:int,certificado:int,mes:int}>
     */
    private function bloquesSeguimientoMejorAlineacion(
        Worksheet $hoja,
        array $caps,
        int $headerRow,
        int $inicioBase,
        int $maxCol,
        ?Worksheet $crono
    ): array {
        $mejor = [];
        for ($offset = 0; $offset < 4; $offset++) {
            $inicio = $inicioBase + $offset;
            $bloques = $this->detectarBloquesSeguimiento($hoja, $caps, $headerRow, $inicio, $maxCol, $crono);
            if (count($bloques) > count($mejor)) {
                $mejor = $bloques;
            }
        }

        return $mejor;
    }

    /**
     * @param list<array<string,mixed>> $caps
     * @return list<array{codigo:string,estado:int,resultado:int,certificado:int,mes:int}>
     */
    private function detectarBloquesSeguimiento(
        Worksheet $hoja,
        array $caps,
        int $headerRow,
        int $inicioTemas,
        int $maxCol,
        ?Worksheet $crono
    ): array {
        $bloques = [];
        for ($col = $inicioTemas; $col <= $maxCol; $col += 4) {
            $nombre = '';
            $itemHint = '';
            $formulaHint = '';
            for ($r = max(1, $headerRow - 4); $r <= $headerRow; $r++) {
                $bruto = $this->valorCrudo($hoja, $col, $r);
                if (is_string($bruto) && str_starts_with($bruto, '=') && $formulaHint === '') {
                    $formulaHint = $bruto;
                }
                $texto = $this->textoTemaCelda($hoja, $col, $r, $crono);
                if ($texto === '') {
                    continue;
                }
                if ($this->esNumeroTema($texto)) {
                    $itemHint = $texto;
                    continue;
                }
                if ($this->esEtiquetaBloque($texto)) {
                    continue;
                }
                $nombre = $texto;
                break;
            }
            $codigo = $this->codigoPorNombre($caps, $nombre);
            if ($codigo === null && $itemHint !== '') {
                $codigo = $this->codigoPorItem($caps, (int)$itemHint);
            }
            if ($codigo === null && $formulaHint !== '') {
                $codigo = $this->codigoDesdeFormulaCronograma($formulaHint, $caps, $crono);
            }
            if ($codigo === null) {
                continue;
            }
            $bloques[] = [
                'codigo' => $codigo,
                'estado' => $col,
                'resultado' => $col + 1,
                'certificado' => $col + 2,
                'mes' => $col + 3,
            ];
        }

        return $bloques;
    }

    /**
     * @param list<array<string,mixed>> $caps
     */
    private function codigoPorItem(array $caps, int $item): ?string
    {
        if ($item < 1) {
            return null;
        }
        foreach ($caps as $cap) {
            if ((int)$cap['item'] === $item) {
                return (string)$cap['codigo'];
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $caps
     */
    private function codigoDesdeFormulaCronograma(string $formula, array $caps, ?Worksheet $crono): ?string
    {
        if ($crono === null || !str_starts_with($formula, '=')) {
            return null;
        }
        if (!preg_match('/CRONOGRAMA!\$?([A-Z]+)\$?(\d+)/i', $formula, $m)) {
            return null;
        }
        $col = Coordinate::columnIndexFromString(strtoupper($m[1]));
        $fila = (int)$m[2];
        $itemBruto = $this->celda($crono, 1, $fila);
        if (preg_match('/^\d+$/', $itemBruto)) {
            $porItem = $this->codigoPorItem($caps, (int)$itemBruto);
            if ($porItem !== null) {
                return $porItem;
            }
        }
        $tema = $this->primeraNoVacia($crono, $fila, [$col, 5, 4, 2]);

        return $this->codigoPorNombre($caps, $tema);
    }

    private function textoTemaCelda(Worksheet $hoja, int $col, int $fila, ?Worksheet $crono): string
    {
        $bruto = $this->valorCrudo($hoja, $col, $fila);
        if (is_string($bruto) && str_starts_with($bruto, '=') && $crono !== null) {
            if (preg_match('/CRONOGRAMA!\$?([A-Z]+)\$?(\d+)/i', $bruto, $m)) {
                $cCol = Coordinate::columnIndexFromString(strtoupper($m[1]));
                $cFila = (int)$m[2];
                $tema = $this->primeraNoVacia($crono, $cFila, [$cCol, 5, 4, 2]);
                if ($tema !== '') {
                    return $tema;
                }
            }
        }

        return $this->celda($hoja, $col, $fila);
    }

    /**
     * @param list<array<string,mixed>> $caps
     */
    private function codigoPorNombre(array $caps, string $nombre): ?string
    {
        $objetivo = $this->claveTema($nombre);
        if ($objetivo === '') {
            return null;
        }
        foreach ($caps as $cap) {
            if ($this->claveTema((string)$cap['nombre']) === $objetivo) {
                return (string)$cap['codigo'];
            }
        }

        return null;
    }

    private function celda(Worksheet $hoja, int $col, int $fila): string
    {
        $valor = $this->valorCrudo($hoja, $col, $fila);
        if ($valor === null) {
            return '';
        }
        if (is_float($valor) || is_int($valor)) {
            if ((float)$valor == floor((float)$valor)) {
                return sprintf('%.0f', $valor);
            }

            return trim((string)$valor);
        }

        return trim((string)$valor);
    }

    private function valorCrudo(Worksheet $hoja, int $col, int $fila): mixed
    {
        $coord = Coordinate::stringFromColumnIndex($col) . $fila;
        $celda = $hoja->getCell($coord);
        $valor = $celda->getValue();
        if (is_string($valor) && str_starts_with($valor, '=')) {
            try {
                return $celda->getCalculatedValue();
            } catch (\Throwable) {
                // Fórmulas rotas o referencias a hojas renombradas: no tumbar la carga.
                return $celda->getOldCalculatedValue() ?? '';
            }
        }

        return $valor;
    }

    private function primeraNoVacia(Worksheet $hoja, int $fila, array $columnas): string
    {
        foreach ($columnas as $col) {
            $valor = $this->celda($hoja, $col, $fila);
            if ($valor !== '') {
                return $valor;
            }
        }

        return '';
    }

    private function claveTema(string $texto): string
    {
        $linea = preg_split("/\r\n|\n|\r/", $texto)[0] ?? $texto;
        $linea = rtrim($linea, " \t:");

        return $this->claveEncabezado($linea);
    }

    private function claveEncabezado(string $texto): string
    {
        $texto = trim($texto);
        if ($texto === '') {
            return '';
        }
        $texto = str_replace(['.', '°', 'º', '?', '/', '_', '&'], ' ', $texto);
        if (function_exists('mb_strtolower')) {
            $texto = mb_strtolower($texto, 'UTF-8');
        } else {
            $texto = strtolower($texto);
        }
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
        ]);
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

        return trim($texto);
    }

    private function esEncabezadoDocumento(string $clave): bool
    {
        return in_array($clave, [
            'identificacion',
            'no identificacion',
            'no de identificacion',
            'n identificacion',
            'n de identificacion',
            'documento',
            'numero documento',
            'numero de identificacion',
        ], true);
    }

    private function esEncabezadoFechaIngreso(string $clave): bool
    {
        if ($clave === '' || str_contains($clave, 'examen')) {
            return false;
        }

        return $clave === 'fecha de ingreso'
            || $clave === 'fecha ingreso'
            || (str_contains($clave, 'fecha') && str_contains($clave, 'ingreso'));
    }

    private function esNumeroTema(string $valor): bool
    {
        return preg_match('/^\d{1,2}$/', $valor) === 1;
    }

    private function esEtiquetaBloque(string $valor): bool
    {
        $clave = $this->claveEncabezado($valor);

        return in_array($clave, ['estado', 'resultado', 'certificado', 'certificado descargado', 'mes', 'mes programado', 'mes programado/ejecutado', 'requiere evaluacion'], true);
    }

    private function pareceEncabezadoCargo(string $valor): bool
    {
        $clave = $this->claveEncabezado($valor);

        return str_contains($clave, 'cargo') || str_contains($clave, 'rol');
    }

    private function parecePieSeguimiento(string $nombre): bool
    {
        $clave = $this->claveEncabezado($nombre);

        return in_array($clave, ['cobertura', 'eficacia', 'total', 'total certificados', 'certificados descargados'], true);
    }
}

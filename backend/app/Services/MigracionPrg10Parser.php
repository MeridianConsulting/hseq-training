<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Lee la plantilla HSEQ-PRG-10 (CRONOGRAMA, MATRIZ POR CARGO, SEGUIMIENTO_PERSONAL).
 */
class MigracionPrg10Parser
{
    public const HOJAS = ['CRONOGRAMA', 'MATRIZ POR CARGO', 'SEGUIMIENTO_PERSONAL'];

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
        $faltantes = [];
        foreach (self::HOJAS as $esperada) {
            if (!$this->tieneHoja($nombres, $esperada)) {
                $faltantes[] = $esperada;
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

        $crono = $this->hoja($libro, 'CRONOGRAMA');
        $matriz = $this->hoja($libro, 'MATRIZ POR CARGO');
        $seg = $this->hoja($libro, 'SEGUIMIENTO_PERSONAL');

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
            'matriz' => $this->leerMatriz($matriz, $caps),
            'trabajadores' => $this->leerTrabajadores($seg),
            'seguimientos' => $this->leerSeguimientos($seg, $caps),
        ];
    }

    /** @param list<string> $nombres */
    private function tieneHoja(array $nombres, string $esperada): bool
    {
        foreach ($nombres as $nombre) {
            if (strcasecmp(trim($nombre), $esperada) === 0) {
                return true;
            }
        }

        return false;
    }

    private function hoja(Spreadsheet $libro, string $titulo): Worksheet
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
    private function leerMatriz(Worksheet $hoja, array $caps): array
    {
        $maxCol = Coordinate::columnIndexFromString($hoja->getHighestColumn());
        $maxRow = (int)$hoja->getHighestRow();
        $temaPorCol = [];
        for ($col = 4; $col <= $maxCol; $col++) {
            $nombre = '';
            for ($r = 1; $r <= 8; $r++) {
                $valor = $this->celda($hoja, $col, $r);
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
    private function leerSeguimientos(Worksheet $hoja, array $caps): array
    {
        $mapa = $this->mapaIdentidad($hoja);
        if ($mapa === null) {
            return [];
        }
        [$headerRow, $cols] = $mapa;
        $maxCol = Coordinate::columnIndexFromString($hoja->getHighestColumn());
        $inicioTemas = max($cols['ultima_identidad'] + 1, 10);
        $bloques = [];
        for ($col = $inicioTemas; $col <= $maxCol; $col += 4) {
            $nombre = '';
            for ($r = max(1, $headerRow - 4); $r <= $headerRow; $r++) {
                $valor = $this->celda($hoja, $col, $r);
                if ($valor !== '' && !$this->esEtiquetaBloque($valor) && !$this->esNumeroTema($valor)) {
                    $nombre = $valor;
                    break;
                }
            }
            $codigo = $this->codigoPorNombre($caps, $nombre);
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
        $maxCol = min(9, Coordinate::columnIndexFromString($hoja->getHighestColumn()));
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
                if (in_array($clave, ['identificacion', 'no identificacion', 'documento', 'numero documento', 'n identificacion'], true)) {
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
                } elseif (in_array($clave, ['fecha de ingreso', 'fecha ingreso'], true) && $cols['fecha_ingreso'] === 0) {
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
     * @param list<array<string,mixed>> $caps
     */
    private function codigoPorNombre(array $caps, string $nombre): ?string
    {
        $objetivo = $this->claveEncabezado($nombre);
        if ($objetivo === '') {
            return null;
        }
        foreach ($caps as $cap) {
            if ($this->claveEncabezado((string)$cap['nombre']) === $objetivo) {
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
            $calc = $celda->getCalculatedValue();

            return $calc;
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

    private function claveEncabezado(string $texto): string
    {
        $texto = trim($texto);
        if ($texto === '') {
            return '';
        }
        $texto = str_replace(['.', '°', 'º'], ' ', $texto);
        if (function_exists('mb_strtolower')) {
            $texto = mb_strtolower($texto, 'UTF-8');
        } else {
            $texto = strtolower($texto);
        }
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ]);
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

        return trim($texto);
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

<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Core\Logger;
use App\Repositories\PersonalRepository;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PDOException;
use Throwable;

class PersonalImportacionService
{
    private const TAMANO_MAXIMO = 5242880;
    private const EXTENSIONES = ['xlsx', 'xls', 'csv'];

    private const COLUMNAS_REQUERIDAS = [
        'documento' => 'Documento',
        'nombre' => 'Nombre',
        'cargo' => 'Cargo',
        'fecha_ingreso' => 'Fecha de ingreso',
    ];

    private PersonalService $personal;

    public function __construct()
    {
        $this->personal = new PersonalService();
    }

    public function generarPlantilla(): string
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Trabajadores');

        $encabezados = ['Documento', 'Nombre', 'Correo', 'Cargo', 'Proyecto', 'Fecha de ingreso'];

        foreach ($encabezados as $indice => $titulo) {
            $hoja->setCellValueByColumnAndRow($indice + 1, 1, $titulo);
        }

        $hoja->getStyle('1:1')->getFont()->setBold(true);
        $hoja->getColumnDimension('A')->setWidth(18);
        $hoja->getColumnDimension('B')->setWidth(32);
        $hoja->getColumnDimension('C')->setWidth(28);
        $hoja->getColumnDimension('D')->setWidth(28);
        $hoja->getColumnDimension('E')->setWidth(22);
        $hoja->getColumnDimension('F')->setWidth(20);

        $hoja->getStyle('A:A')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $hoja->setCellValueExplicit('A2', '001234567', DataType::TYPE_STRING);
        $hoja->setCellValue('B2', 'Juan Pérez Gómez');
        $hoja->setCellValue('C2', 'juan.perez@empresa.com');
        $hoja->setCellValue('D2', 'PRACTICANTE');
        $hoja->setCellValue('E2', 'Proyecto ejemplo');
        $hoja->setCellValue('F2', '01/08/2026');
        $hoja->getStyle('F:F')->getNumberFormat()->setFormatCode('DD/MM/YYYY');

        $notas = $libro->createSheet();
        $notas->setTitle('Indicaciones');
        $notas->setCellValue('A1', 'Use fechas en formato DD/MM/YYYY.');
        $notas->setCellValue('A2', 'La columna Documento debe ser texto para conservar ceros a la izquierda.');
        $notas->setCellValue('A3', 'El cargo debe coincidir con un cargo existente del catálogo corporativo. No se crean cargos nuevos.');
        $notas->setCellValue('A4', 'Correo y proyecto son opcionales.');
        $notas->setCellValue('A5', 'Un error en una fila no impide importar el resto de filas válidas.');
        $notas->getColumnDimension('A')->setWidth(110);

        $libro->setActiveSheetIndex(0);

        $escritor = new Xlsx($libro);
        ob_start();
        $escritor->save('php://output');
        $contenido = (string)ob_get_clean();
        $libro->disconnectWorksheets();

        return $contenido;
    }

    /**
     * @param array<string, mixed> $archivo
     * @return array{
     *   total_procesados:int,
     *   total_importados:int,
     *   total_rechazados:int,
     *   rechazados:list<array{fila:int, documento:string, nombre:string, estado:string, motivo:string}>
     * }
     */
    public function importar(array $archivo): array
    {
        $ruta = $this->validarArchivo($archivo);

        try {
            $filas = $this->leerFilas($ruta);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Logger::error('No fue posible leer el archivo de personal: ' . $e->getMessage());
            throw new HttpException('No fue posible leer el archivo. Verifique que no esté dañado y que sea Excel o CSV.', 422);
        }

        $mapaCargos = $this->personal->repositorio()->mapaCargos();
        $documentosFila = [];

        foreach ($filas as $fila) {
            $doc = $this->personal->normalizarDocumento($fila['documento']);
            if ($doc !== '') {
                $documentosFila[] = $doc;
            }
        }

        $documentosEnBd = $this->personal->repositorio()->documentosExistentes($documentosFila);
        $vistosEnArchivo = [];
        $importados = 0;
        $rechazados = [];

        foreach ($filas as $fila) {
            $documento = $this->personal->normalizarDocumento($fila['documento']);
            $nombre = $this->personal->normalizarTexto($fila['nombre']);

            if ($documento !== '' && isset($vistosEnArchivo[$documento])) {
                $rechazados[] = $this->rechazo($fila['fila'], $documento, $nombre, 'Documento duplicado dentro del archivo.');
                continue;
            }

            if ($documento !== '') {
                $vistosEnArchivo[$documento] = true;
            }

            $preparado = $this->personal->prepararEntrada(
                [
                    'numero_documento' => $fila['documento'],
                    'nombre_completo' => $fila['nombre'],
                    'correo' => $fila['correo'],
                    'cargo' => $fila['cargo'],
                    'proyecto' => $fila['proyecto'],
                    'fecha_ingreso' => $fila['fecha_ingreso'],
                ],
                null,
                $documentosEnBd,
                false,
                $mapaCargos
            );

            if (!$preparado['ok']) {
                $rechazados[] = $this->rechazo($fila['fila'], $documento, $nombre, (string)$preparado['motivo']);
                continue;
            }

            try {
                $personaId = $this->personal->persistirAlta($preparado['datos']);
                $documentosEnBd[$preparado['datos']['numero_documento']] = true;
                $importados++;
                try {
                    $this->personal->sincronizarAsignaciones($this->personal->ver($personaId));
                } catch (Throwable $e) {
                    Logger::error('RF-008 no pudo sincronizar fila importada', [
                        'fila' => $fila['fila'],
                        'persona_id' => $personaId,
                        'error' => $e->getMessage(),
                    ]);
                }
            } catch (HttpException $e) {
                if ($e->getStatusCode() >= 500) {
                    Logger::error('Error al importar fila de personal: ' . $e->getMessage(), ['fila' => $fila['fila']]);
                    $rechazados[] = $this->rechazo($fila['fila'], $documento, $nombre, 'No fue posible guardar este registro.');
                    continue;
                }

                $rechazados[] = $this->rechazo($fila['fila'], $documento, $nombre, $e->getMessage());
            } catch (PDOException $e) {
                if (PersonalRepository::esConflictoUnico($e)) {
                    $rechazados[] = $this->rechazo(
                        $fila['fila'],
                        $documento,
                        $nombre,
                        'El documento ya se encuentra registrado.'
                    );
                    continue;
                }

                Logger::error('PDO al importar personal: ' . $e->getMessage(), ['fila' => $fila['fila']]);
                $rechazados[] = $this->rechazo($fila['fila'], $documento, $nombre, 'No fue posible guardar este registro.');
            } catch (Throwable $e) {
                Logger::error('Error inesperado al importar personal: ' . $e->getMessage(), ['fila' => $fila['fila']]);
                $rechazados[] = $this->rechazo($fila['fila'], $documento, $nombre, 'No fue posible guardar este registro.');
            }
        }

        $procesados = count($filas);

        return [
            'total_procesados' => $procesados,
            'total_importados' => $importados,
            'total_rechazados' => count($rechazados),
            'rechazados' => $rechazados,
        ];
    }

    /**
     * @param array<string, mixed> $archivo
     */
    private function validarArchivo(array $archivo): string
    {
        $error = (int)($archivo['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE || ($archivo['tmp_name'] ?? '') === '') {
            throw new HttpException('Debe seleccionar un archivo Excel o CSV.', 422);
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new HttpException('No fue posible cargar el archivo.', 422);
        }

        $tamano = (int)($archivo['size'] ?? 0);

        if ($tamano <= 0) {
            throw new HttpException('El archivo está vacío.', 422);
        }

        if ($tamano > self::TAMANO_MAXIMO) {
            throw new HttpException('El archivo supera el tamaño máximo de 5 MB.', 422);
        }

        $nombre = (string)($archivo['name'] ?? '');
        $extension = strtolower((string)pathinfo($nombre, PATHINFO_EXTENSION));

        if (!in_array($extension, self::EXTENSIONES, true)) {
            throw new HttpException('El archivo debe ser Excel (.xlsx, .xls) o CSV (.csv).', 422);
        }

        $tmp = (string)$archivo['tmp_name'];

        if (is_uploaded_file($tmp)) {
            return $tmp;
        }

        if (PHP_SAPI === 'cli' && is_file($tmp)) {
            return $tmp;
        }

        throw new HttpException('No fue posible cargar el archivo.', 422);
    }

    /**
     * @return list<array{fila:int, documento:mixed, nombre:mixed, correo:mixed, cargo:mixed, proyecto:mixed, fecha_ingreso:mixed}>
     */
    private function leerFilas(string $ruta): array
    {
        $lector = IOFactory::createReaderForFile($ruta);

        if ($lector instanceof CsvReader) {
            $lector->setDelimiter($this->detectarDelimitador($ruta));
            $lector->setInputEncoding('UTF-8');
        }

        if (method_exists($lector, 'setReadDataOnly')) {
            $lector->setReadDataOnly(true);
        }

        $libro = $lector->load($ruta);
        $hoja = $libro->getSheet(0);
        $maxFila = (int)$hoja->getHighestDataRow();
        $maxCol = Coordinate::columnIndexFromString($hoja->getHighestDataColumn());

        if ($maxFila < 1 || $maxCol < 1) {
            $libro->disconnectWorksheets();
            throw new HttpException('El archivo está vacío.', 422);
        }

        $mapaColumnas = null;
        $filas = [];

        for ($fila = 1; $fila <= $maxFila; $fila++) {
            $valores = [];

            for ($col = 1; $col <= $maxCol; $col++) {
                $valores[$col] = $this->valorCelda($hoja->getCellByColumnAndRow($col, $fila));
            }

            if ($this->filaVacia($valores)) {
                continue;
            }

            if ($mapaColumnas === null) {
                $mapaColumnas = $this->mapearEncabezados($valores);
                continue;
            }

            $filas[] = [
                'fila' => $fila,
                'documento' => $this->valorColumna($valores, $mapaColumnas, 'documento'),
                'nombre' => $this->valorColumna($valores, $mapaColumnas, 'nombre'),
                'correo' => $this->valorColumna($valores, $mapaColumnas, 'correo'),
                'cargo' => $this->valorColumna($valores, $mapaColumnas, 'cargo'),
                'proyecto' => $this->valorColumna($valores, $mapaColumnas, 'proyecto'),
                'fecha_ingreso' => $this->valorColumna($valores, $mapaColumnas, 'fecha_ingreso'),
            ];
        }

        $libro->disconnectWorksheets();

        if ($mapaColumnas === null) {
            throw new HttpException('El archivo está vacío.', 422);
        }

        if ($filas === []) {
            throw new HttpException('El archivo no contiene registros para procesar.', 422);
        }

        return $filas;
    }

    /**
     * @param array<int, mixed> $valores
     * @return array<string, int>
     */
    private function mapearEncabezados(array $valores): array
    {
        $mapa = [];

        foreach ($valores as $columna => $valor) {
            $clave = $this->normalizarEncabezado((string)$valor);

            if ($clave === '') {
                continue;
            }

            $campo = $this->campoDesdeEncabezado($clave);

            if ($campo !== null && !isset($mapa[$campo])) {
                $mapa[$campo] = (int)$columna;
            }
        }

        foreach (self::COLUMNAS_REQUERIDAS as $campo => $etiqueta) {
            if (!isset($mapa[$campo])) {
                throw new HttpException(
                    'El archivo no contiene la columna obligatoria ' . $etiqueta . '.',
                    422
                );
            }
        }

        return $mapa;
    }

    private function campoDesdeEncabezado(string $clave): ?string
    {
        $alias = [
            'documento' => 'documento',
            'numero de documento' => 'documento',
            'numero_documento' => 'documento',
            'cedula' => 'documento',
            'nombre' => 'nombre',
            'nombre completo' => 'nombre',
            'correo' => 'correo',
            'correo electronico' => 'correo',
            'email' => 'correo',
            'cargo' => 'cargo',
            'proyecto' => 'proyecto',
            'fecha de ingreso' => 'fecha_ingreso',
            'fecha ingreso' => 'fecha_ingreso',
            'fecha_ingreso' => 'fecha_ingreso',
        ];

        return $alias[$clave] ?? null;
    }

    private function normalizarEncabezado(string $texto): string
    {
        $texto = trim($texto);
        $texto = preg_replace('/^\xEF\xBB\xBF/', '', $texto) ?? $texto;

        if (function_exists('mb_strtolower')) {
            $texto = mb_strtolower($texto, 'UTF-8');
        } else {
            $texto = strtolower($texto);
        }

        $reemplazos = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n',
        ];
        $texto = strtr($texto, $reemplazos);
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

        return trim($texto);
    }

    /**
     * @param array<int, mixed> $valores
     * @param array<string, int> $mapa
     */
    private function valorColumna(array $valores, array $mapa, string $campo): mixed
    {
        if (!isset($mapa[$campo])) {
            return '';
        }

        return $valores[$mapa[$campo]] ?? '';
    }

    private function detectarDelimitador(string $ruta): string
    {
        $handle = fopen($ruta, 'r');
        if ($handle === false) {
            return ',';
        }

        $linea = (string)fgets($handle);
        fclose($handle);
        $linea = preg_replace('/^\xEF\xBB\xBF/', '', $linea) ?? $linea;
        $puntos = substr_count($linea, ';');
        $comas = substr_count($linea, ',');

        return $puntos > $comas ? ';' : ',';
    }

    private function valorCelda($celda): mixed
    {
        $valor = $celda->getValue();

        if ($valor === null || $valor === '') {
            return '';
        }

        if (is_object($valor) && method_exists($valor, 'getPlainText')) {
            return $this->personal->normalizarTexto($valor->getPlainText());
        }

        try {
            if (ExcelDate::isDateTime($celda)) {
                $fecha = ExcelDate::excelToDateTimeObject($celda->getValue());

                return $fecha->format('Y-m-d');
            }
        } catch (Throwable $e) {
            // Continuar con el valor crudo.
        }

        if (is_float($valor) || is_int($valor)) {
            if ((float)$valor == floor((float)$valor)) {
                return sprintf('%.0f', $valor);
            }

            return $valor;
        }

        $formateado = trim((string)$celda->getFormattedValue());

        return $formateado !== '' ? $formateado : (string)$valor;
    }

    /**
     * @param array<int, mixed> $valores
     */
    private function filaVacia(array $valores): bool
    {
        foreach ($valores as $valor) {
            if ($this->personal->normalizarTexto($valor) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{fila:int, documento:string, nombre:string, estado:string, motivo:string}
     */
    private function rechazo(int $fila, string $documento, string $nombre, string $motivo): array
    {
        return [
            'fila' => $fila,
            'documento' => $documento,
            'nombre' => $nombre,
            'estado' => 'Rechazado',
            'motivo' => $motivo,
        ];
    }
}

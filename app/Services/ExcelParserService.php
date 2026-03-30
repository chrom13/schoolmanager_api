<?php

namespace App\Services;

use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Servicio para leer archivos Excel/CSV sin imponer estructura de columnas.
 * Usa OpenSpout directamente (disponible vía openspout/openspout,
 * dependencia transitiva de spatie/simple-excel).
 */
class ExcelParserService
{
    private const PREVIEW_ROWS = 5;

    /**
     * Lee todas las hojas del archivo y devuelve su metadata.
     *
     * @param  string $filePath  Ruta absoluta al archivo en storage
     * @return array  [{nombre: string, columnas: string[], preview: array[][]}]
     */
    public function readSheets(string $filePath): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            return $this->readCsvAsSheets($filePath);
        }

        return $this->readXlsxSheets($filePath);
    }

    /**
     * Lee todas las filas de una hoja específica como arrays asociativos.
     * La primera fila se usa como encabezados.
     *
     * @param  string $filePath  Ruta absoluta al archivo
     * @param  string $sheetName Nombre de la hoja (ignorado para CSV)
     * @return array  [{columna => valor}, ...]
     */
    public function readSheetRows(string $filePath, string $sheetName): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            return $this->readCsvRows($filePath);
        }

        return $this->readXlsxSheetRows($filePath, $sheetName);
    }

    // -------------------------------------------------------------------------
    // XLSX / ODS
    // -------------------------------------------------------------------------

    private function readXlsxSheets(string $filePath): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $reader = $extension === 'ods' ? new OdsReader() : new XlsxReader();

        $reader->open($filePath);
        $sheets = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $headers = [];
            $preview = [];
            $rowIndex = 0;

            foreach ($sheet->getRowIterator() as $row) {
                $cells = $row->getCells();
                $values = array_map(fn($c) => $this->cellValue($c), $cells);

                if ($rowIndex === 0) {
                    // Primera fila = encabezados; filtrar vacíos
                    $headers = array_values(array_filter(
                        array_map('strval', $values),
                        fn($v) => trim($v) !== ''
                    ));
                } elseif ($rowIndex <= self::PREVIEW_ROWS) {
                    // Siguientes filas = preview, mapear a encabezados
                    $preview[] = $this->rowToAssoc($headers, $values);
                } else {
                    break;
                }

                $rowIndex++;
            }

            if (!empty($headers)) {
                $sheets[] = [
                    'nombre'   => $sheet->getName(),
                    'columnas' => $headers,
                    'preview'  => $preview,
                ];
            }
        }

        $reader->close();

        return $sheets;
    }

    private function readXlsxSheetRows(string $filePath, string $sheetName): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $reader = $extension === 'ods' ? new OdsReader() : new XlsxReader();

        $reader->open($filePath);
        $rows = [];
        $headers = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() !== $sheetName) {
                continue;
            }

            $rowIndex = 0;
            foreach ($sheet->getRowIterator() as $row) {
                $cells = $row->getCells();
                $values = array_map(fn($c) => $this->cellValue($c), $cells);

                if ($rowIndex === 0) {
                    $headers = array_map('strval', $values);
                } else {
                    // Omitir filas completamente vacías
                    $filtered = array_filter($values, fn($v) => $v !== null && trim((string) $v) !== '');
                    if (!empty($filtered)) {
                        $rows[] = $this->rowToAssoc($headers, $values);
                    }
                }
                $rowIndex++;
            }
            break; // ya encontramos la hoja
        }

        $reader->close();

        return $rows;
    }

    // -------------------------------------------------------------------------
    // CSV
    // -------------------------------------------------------------------------

    private function readCsvAsSheets(string $filePath): array
    {
        $rows = $this->readCsvRows($filePath);

        if (empty($rows)) {
            return [];
        }

        $headers = array_keys($rows[0]);
        $preview = array_slice($rows, 0, self::PREVIEW_ROWS);

        return [[
            'nombre'   => 'Hoja1',
            'columnas' => $headers,
            'preview'  => $preview,
        ]];
    }

    private function readCsvRows(string $filePath): array
    {
        $reader = new CsvReader();
        $reader->open($filePath);

        $headers = [];
        $rows = [];
        $rowIndex = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $values = array_map(fn($c) => $this->cellValue($c), $row->getCells());

                if ($rowIndex === 0) {
                    $headers = array_map('strval', $values);
                } else {
                    $filtered = array_filter($values, fn($v) => $v !== null && trim((string) $v) !== '');
                    if (!empty($filtered)) {
                        $rows[] = $this->rowToAssoc($headers, $values);
                    }
                }
                $rowIndex++;
            }
            break;
        }

        $reader->close();

        return $rows;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function cellValue(mixed $cell): mixed
    {
        $value = $cell->getValue();

        // DateTime → string ISO
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $value;
    }

    /**
     * Construye un array asociativo a partir de encabezados y valores de fila.
     * Si la fila tiene más columnas que encabezados, las columnas extra se ignoran.
     * Si tiene menos, se completan con null.
     */
    private function rowToAssoc(array $headers, array $values): array
    {
        $result = [];
        foreach ($headers as $i => $header) {
            if (trim($header) === '') {
                continue;
            }
            $result[$header] = isset($values[$i]) ? $values[$i] : null;
        }
        return $result;
    }
}

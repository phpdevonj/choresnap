<?php

namespace App\Helper;

use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Database\Eloquent\Builder;

class ReportHelper {

    private static $chunkSize = 500;


    public static function exportToExcel(Builder $query, array $headerMap, array $footerRows = [], string $transformerMethod = 'defaultItemTransformer') {

        $chunkSize = self::$chunkSize;
        $excelHeaders = array_values($headerMap);
        $objectKeysToUse = array_keys($headerMap);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray(
            [$excelHeaders],
            NULL,
            'A1'
        );

        $startRow = 2;

        if (!method_exists(static::class, $transformerMethod)) {
            session()->flash("Transformer method '{$transformerMethod}' not found in ReportHelper.");
            return false;
        }

        if ($query->count() === 0) {
            session()->flash('error', 'No results found.');
            return false;
        }

        $query->chunk($chunkSize, function ($items) use (&$sheet, &$startRow, $objectKeysToUse, $transformerMethod) {

            $rows = [];

            foreach ($items as $item) {
                $rowData = static::$transformerMethod($item, $objectKeysToUse);
                $rows[] = $rowData;
            }

            // Write the chunk to the sheet
            $sheet->fromArray($rows, NULL, 'A' . $startRow);
            $startRow += count($rows);
        });

        if (!empty($footerRows)) {
            $sheet->fromArray(
                $footerRows,
                NULL,
                'A' . $startRow
            );
        }


        $headerStyle = $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID);
        $headerStyle->getFill()->getStartColor()->setARGB('FFA0A0A0');

        $highestColumn = $sheet->getHighestColumn();
        foreach (range('A', $highestColumn) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }


        $filename = uniqid() . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');

        exit;
    }

    protected static function defaultItemTransformer($item, array $keys): array {
        $rowData = [];
        foreach ($keys as $key) {
            $value = '';
            if (str_contains($key, '.')) {
                $parts = explode('.', $key);
                $currentData = $item;

                foreach ($parts as $part) {
                    if (isset($currentData->$part) && is_object($currentData->$part)) {
                        $currentData = $currentData->$part;
                    } elseif (isset($currentData->$part)) {
                        $value = $currentData->$part;
                        break;
                    } else {
                        $value = '';
                        break;
                    }
                }
            } elseif (isset($item->$key)) {
                $value = $item->$key;
            }

            if (is_bool($value)) {
                $value = $value ? 'Yes' : 'No';
            }

            $rowData[] = $value;
        }
        return $rowData;
    }

}

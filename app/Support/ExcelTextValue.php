<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExcelTextValue
{
    public static function forCsv(?string $value): string
    {
        $value = trim((string) $value);

        return $value === ''
            ? ''
            : '="'.str_replace('"', '""', $value).'"';
    }

    public static function setCell(Worksheet $sheet, string $coordinate, ?string $value, string $emptyValue = ''): void
    {
        $value = trim((string) $value);
        $sheet->setCellValueExplicit(
            $coordinate,
            $value !== '' ? $value : $emptyValue,
            DataType::TYPE_STRING
        );
        $sheet->getStyle($coordinate)
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
    }
}

<?php

declare(strict_types=1);

class ReportImportParser
{
    public function parse(string $filePath): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($extension === 'csv') {
            return $this->parseCsv($filePath);
        }
        if ($extension === 'xlsx') {
            return $this->parseXlsx($filePath);
        }

        throw new RuntimeException('Unsupported file format. Only CSV and XLSX are allowed.');
    }

    private function parseCsv(string $filePath): array
    {
        $rows = [];
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            throw new RuntimeException('Unable to open CSV file.');
        }

        $header = null;
        while (($line = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = array_map(static fn ($value) => strtolower(trim((string) $value)), $line);
                continue;
            }

            $row = [];
            foreach ($header as $index => $key) {
                $row[$key] = trim((string) ($line[$index] ?? ''));
            }
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    private function parseXlsx(string $filePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException('Unable to open XLSX file.');
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $xml = simplexml_load_string($sharedXml);
            if ($xml) {
                foreach ($xml->si as $item) {
                    $sharedStrings[] = (string) ($item->t ?? '');
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) {
            throw new RuntimeException('XLSX sheet1 not found.');
        }

        $sheet = simplexml_load_string($sheetXml);
        if (!$sheet) {
            throw new RuntimeException('Invalid XLSX content.');
        }

        $rows = [];
        $header = null;
        foreach ($sheet->sheetData->row as $rowNode) {
            $cells = [];
            foreach ($rowNode->c as $cell) {
                $type = (string) ($cell['t'] ?? '');
                $value = (string) ($cell->v ?? '');
                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                }
                $cells[] = trim($value);
            }

            if ($header === null) {
                $header = array_map(static fn ($value) => strtolower(trim((string) $value)), $cells);
                continue;
            }

            $assoc = [];
            foreach ($header as $index => $key) {
                $assoc[$key] = trim((string) ($cells[$index] ?? ''));
            }
            $rows[] = $assoc;
        }

        return $rows;
    }
}

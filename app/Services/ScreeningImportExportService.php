<?php

namespace App\Services;

use App\Models\Screening;
use App\Models\Movie;
use App\Models\Room;
use Illuminate\Support\Collection;
use Illuminate\Http\UploadedFile;
use Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

class ScreeningImportExportService
{
    /**
     * Columnas esperadas en el archivo
     */
    protected array $columns = [
        'id',
        'movie_title',
        'room_name',
        'cinema_name',
        'start_time',
        'end_time',
        'price',
        'format',
        'is_active',
    ];

    /**
     * Exportar screenings a archivo Excel/CSV
     * 
     * @param string $format 'xlsx' o 'csv'
     * @param array|null $ids IDs específicos a exportar (null = todos)
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportScreenings(string $format = 'xlsx', ?array $ids = null)
    {
        // Obtener screenings
        $query = Screening::with(['movie', 'room.cinema']);
        
        if ($ids) {
            $query->whereIn('id', $ids);
        }

        $screenings = $query->get();

        // Preparar datos
        $data = $this->prepareExportData($screenings);

        // Crear spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $headers = array_values($this->columns);
        foreach ($headers as $index => $header) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($col . '1', $header);
        }

        // Datos
        foreach ($data as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                $sheet->setCellValue($col . ($rowIndex + 2), $value);
            }
        }

        // Auto-ajustar columnas
        foreach (range(1, count($this->columns)) as $col) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Generar archivo
        $filename = 'screenings_' . date('Y-m-d_H-i-s');

        if ($format === 'csv') {
            $writer = new Csv($spreadsheet);
            $filename .= '.csv';
            $contentType = 'text/csv';
        } else {
            $writer = new Xlsx($spreadsheet);
            $filename .= '.xlsx';
            $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }

        return response()->stream(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Exportar ejemplo de template
     * 
     * @param string $format 'xlsx' o 'csv'
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportTemplate(string $format = 'xlsx')
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $headers = array_values($this->columns);
        foreach ($headers as $index => $header) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($col . '1', $header);
        }

        // Ejemplo de fila
        $exampleRow = [
            '',  // id (opcional, para crear nuevo)
            'Inception',
            'Sala 1',
            'Cine Central',
            '2025-12-20 14:00:00',
            '2025-12-20 16:15:00',
            '150.00',
            '2D',
            '1',
        ];

        foreach ($exampleRow as $colIndex => $value) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet->setCellValue($col . '2', $value);
        }

        // Auto-ajustar columnas
        foreach (range(1, count($this->columns)) as $col) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $filename = 'screenings_template_' . date('Y-m-d');

        if ($format === 'csv') {
            $writer = new Csv($spreadsheet);
            $filename .= '.csv';
            $contentType = 'text/csv';
        } else {
            $writer = new Xlsx($spreadsheet);
            $filename .= '.xlsx';
            $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }

        return response()->stream(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Importar screenings desde archivo
     * 
     * @param UploadedFile $file
     * @return array Resultado con resumen de cambios
     * @throws Exception
     */
    public function importScreenings(UploadedFile $file): array
    {
        $data = $this->parseFile($file);
        
        $results = [
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($data as $rowIndex => $row) {
            try {
                if (empty(array_filter($row))) {
                    continue; // Saltar filas vacías
                }

                $validated = $this->validateRow($row, $rowIndex);
                $screening = $this->saveScreening($validated);

                if ($screening['action'] === 'created') {
                    $results['created']++;
                } else {
                    $results['updated']++;
                }
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'row' => $rowIndex,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Parsear archivo Excel o CSV
     */
    protected function parseFile(UploadedFile $file): array
    {
        $path = $file->store('imports');
        $fullPath = storage_path('app/' . $path);

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullPath);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = [];

            $headerRow = $worksheet->getRowIterator(1, 1)->current();
            $headers = [];
            
            foreach ($headerRow->getCellIterator() as $cell) {
                $headers[] = strtolower(trim($cell->getValue() ?? ''));
            }

            $rows = $worksheet->getRowIterator(2);
            foreach ($rows as $rowIndex => $row) {
                $rowData = [];
                $colIndex = 0;
                
                foreach ($row->getCellIterator() as $cell) {
                    if ($colIndex < count($headers)) {
                        $header = $headers[$colIndex];
                        $value = $cell->getValue();
                        
                        // Procesar valores especiales
                        if ($header === 'is_active') {
                            $value = in_array(strtolower($value), ['1', 'true', 'yes', 'sí']) ? 1 : 0;
                        } elseif (in_array($header, ['start_time', 'end_time'])) {
                            if (is_numeric($value)) {
                                $value = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d H:i:s');
                            }
                        }
                        
                        $rowData[$header] = $value;
                    }
                    $colIndex++;
                }
                
                if (!empty(array_filter($rowData))) {
                    $data[] = $rowData;
                }
            }

            return $data;
        } finally {
            @unlink($fullPath);
        }
    }

    /**
     * Validar una fila de datos
     */
    protected function validateRow(array $row, int $rowIndex): array
    {
        // Campos requeridos (excepto id para creación)
        $required = ['movie_title', 'room_name', 'cinema_name', 'start_time', 'end_time', 'price'];
        
        foreach ($required as $field) {
            if (empty($row[$field] ?? null)) {
                throw new Exception("Fila $rowIndex: Campo requerido '$field' vacío");
            }
        }

        // Validar formato de fechas
        try {
            $startTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $row['start_time']);
            $endTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $row['end_time']);
            
            if ($startTime >= $endTime) {
                throw new Exception("Fila $rowIndex: start_time debe ser anterior a end_time");
            }
        } catch (\Exception $e) {
            throw new Exception("Fila $rowIndex: Formato de fecha inválido. Usar: Y-m-d H:i:s");
        }

        // Validar precio
        if (!is_numeric($row['price']) || $row['price'] <= 0) {
            throw new Exception("Fila $rowIndex: Precio debe ser un número positivo");
        }

        return $row;
    }

    /**
     * Guardar o actualizar screening
     */
    protected function saveScreening(array $row): array
    {
        // Buscar o crear película
        $movie = Movie::where('title', $row['movie_title'])
            ->where('is_active', true)
            ->first();
        
        if (!$movie) {
            throw new Exception("Película '{$row['movie_title']}' no encontrada o inactiva");
        }

        // Buscar room por nombre y cine
        $room = Room::whereHas('cinema', function ($query) use ($row) {
            $query->where('name', $row['cinema_name']);
        })->where('name', $row['room_name'])
            ->first();
        
        if (!$room) {
            throw new Exception("Sala '{$row['room_name']}' en cine '{$row['cinema_name']}' no encontrada");
        }

        $attributes = [
            'movie_id' => $movie->id,
            'room_id' => $room->id,
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'price' => $row['price'],
            'format' => $row['format'] ?? '2D',
            'is_active' => $row['is_active'] ?? 1,
        ];

        if (!empty($row['id'])) {
            // Actualizar
            $screening = Screening::findOrFail($row['id']);
            $screening->update($attributes);
            $action = 'updated';
        } else {
            // Crear
            $screening = Screening::create($attributes);
            $action = 'created';
        }

        return ['screening' => $screening, 'action' => $action];
    }

    /**
     * Preparar datos para exportación
     */
    protected function prepareExportData(Collection $screenings): array
    {
        $data = [];

        foreach ($screenings as $screening) {
            $data[] = [
                $screening->id,
                $screening->movie->title,
                $screening->room->name,
                $screening->room->cinema->name,
                $screening->start_time->format('Y-m-d H:i:s'),
                $screening->end_time->format('Y-m-d H:i:s'),
                $screening->price,
                $screening->format,
                $screening->is_active ? 1 : 0,
            ];
        }

        return $data;
    }
}

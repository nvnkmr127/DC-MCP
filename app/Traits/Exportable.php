<?php

namespace App\Traits;

use Illuminate\Support\Facades\Response;
use Illuminate\Database\Eloquent\Builder;

trait Exportable
{
    /**
     * Export a query builder result to a CSV file using streaming.
     *
     * @param Builder $query
     * @param string $filename
     * @param array $headers Mapping of model attributes to CSV column headers
     * @param int $chunkSize
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportCsv($query, string $filename, array $headers, int $chunkSize = 1000)    {
        $headersLine = array_values($headers);
        $attributes = array_keys($headers);

        $callback = function () use ($query, $headersLine, $attributes, $chunkSize) {
            $file = fopen('php://output', 'w');

            // Write the CSV header
            fputcsv($file, $headersLine);

            // Stream data in chunks to save memory
            $query->chunk($chunkSize, function ($items) use ($file, $attributes) {
                foreach ($items as $item) {
                    $row = [];
                    foreach ($attributes as $attribute) {
                        // Handle nested attributes using dot notation (e.g., 'project.name')
                        $value = data_get($item, $attribute);
                        $row[] = is_array($value) ? json_encode($value) : $value;
                    }
                    fputcsv($file, $row);
                }
            });

            fclose($file);
        };

        return Response::streamDownload($callback, "{$filename}.csv", [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}

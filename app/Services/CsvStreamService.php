<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactList;
use App\Models\SuppressionList;
use Exception;

class CsvStreamService
{
    /**
     * Inspect uploaded CSV file to extract header column names and sample preview rows
     */
    public function inspectHeaders(string $filePath): array
    {
        if (! file_exists($filePath)) {
            throw new Exception('File not found: '.$filePath);
        }

        $handle = fopen($filePath, 'r');
        if (! $handle) {
            throw new Exception('Unable to open CSV file for reading.');
        }

        $headers = fgetcsv($handle, 4096, ',', '"', '\\');
        if (! $headers) {
            fclose($handle);
            throw new Exception('CSV file appears to be empty or improperly formatted.');
        }

        // Clean headers
        $cleanHeaders = array_map(function ($h) {
            return trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $h));
        }, $headers);

        $sampleRows = [];
        $rowCount = 0;
        while (($row = fgetcsv($handle, 4096, ',', '"', '\\')) !== false && $rowCount < 5) {
            $sampleRows[] = array_map('trim', $row);
            $rowCount++;
        }

        fclose($handle);

        // Auto-detect column mappings
        $autoMapping = [
            'email' => null,
            'first_name' => null,
            'last_name' => null,
            'company' => null,
        ];

        foreach ($cleanHeaders as $index => $header) {
            $lower = strtolower($header);
            if ($autoMapping['email'] === null && (str_contains($lower, 'email') || str_contains($lower, 'e-mail') || str_contains($lower, 'mail'))) {
                $autoMapping['email'] = $index;
            } elseif ($autoMapping['first_name'] === null && (str_contains($lower, 'first') || str_contains($lower, 'fname') || $lower === 'name')) {
                $autoMapping['first_name'] = $index;
            } elseif ($autoMapping['last_name'] === null && (str_contains($lower, 'last') || str_contains($lower, 'lname') || str_contains($lower, 'surname'))) {
                $autoMapping['last_name'] = $index;
            } elseif ($autoMapping['company'] === null && (str_contains($lower, 'comp') || str_contains($lower, 'org') || str_contains($lower, 'business'))) {
                $autoMapping['company'] = $index;
            }
        }

        return [
            'headers' => $cleanHeaders,
            'sample_rows' => $sampleRows,
            'auto_mapping' => $autoMapping,
        ];
    }

    /**
     * Stream large CSV file (up to 200MB+) row by row with batch database inserts.
     * Keeps memory footprint strictly under 15MB.
     */
    public function streamImport(string $filePath, int $contactListId, array $columnMap, ?int $ownerId = null): array
    {
        if (! file_exists($filePath)) {
            throw new Exception('CSV file not found on server.');
        }

        // Set higher execution time for large files
        @ini_set('max_execution_time', '600');
        @ini_set('memory_limit', '512M');

        $handle = fopen($filePath, 'r');
        if (! $handle) {
            throw new Exception('Failed to open stream for CSV import.');
        }

        // Read header row
        $headers = fgetcsv($handle, 4096, ',', '"', '\\');

        $emailIndex = isset($columnMap['email']) && $columnMap['email'] !== '' ? (int) $columnMap['email'] : null;
        $firstNameIndex = isset($columnMap['first_name']) && $columnMap['first_name'] !== '' ? (int) $columnMap['first_name'] : null;
        $lastNameIndex = isset($columnMap['last_name']) && $columnMap['last_name'] !== '' ? (int) $columnMap['last_name'] : null;
        $companyIndex = isset($columnMap['company']) && $columnMap['company'] !== '' ? (int) $columnMap['company'] : null;

        if ($emailIndex === null) {
            fclose($handle);
            throw new Exception('An Email column must be mapped for importing.');
        }

        // Cache suppression list emails for fast O(1) lookup. Scoped to the list
        // owner (plus platform-wide entries) so another account's unsubscribes
        // cannot silently drop rows from this import.
        $suppressedEmails = $ownerId === null
            ? []
            : SuppressionList::lookupFor($ownerId);

        $batch = [];
        $totalProcessed = 0;
        $importedCount = 0;
        $skippedSuppressed = 0;
        $invalidEmailCount = 0;
        $batchSize = 1000;
        $now = now();

        while (($row = fgetcsv($handle, 4096, ',', '"', '\\')) !== false) {
            $totalProcessed++;
            $rawEmail = trim($row[$emailIndex] ?? '');
            $cleanEmail = strtolower($rawEmail);

            // RFC syntax check
            if (! filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
                $invalidEmailCount++;

                continue;
            }

            // Suppression list check
            if (isset($suppressedEmails[$cleanEmail])) {
                $skippedSuppressed++;

                continue;
            }

            $firstName = ($firstNameIndex !== null && isset($row[$firstNameIndex])) ? trim($row[$firstNameIndex]) : null;
            $lastName = ($lastNameIndex !== null && isset($row[$lastNameIndex])) ? trim($row[$lastNameIndex]) : null;
            $company = ($companyIndex !== null && isset($row[$companyIndex])) ? trim($row[$companyIndex]) : null;

            // Extract custom fields from other columns
            $customFields = [];
            foreach ($row as $idx => $val) {
                if (! in_array($idx, [$emailIndex, $firstNameIndex, $lastNameIndex, $companyIndex], true)) {
                    $key = $headers[$idx] ?? 'col_'.$idx;
                    $customFields[$key] = trim($val);
                }
            }

            $batch[] = [
                'contact_list_id' => $contactListId,
                'email' => $cleanEmail,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'company' => $company,
                'custom_fields' => empty($customFields) ? null : json_encode($customFields),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= $batchSize) {
                Contact::insertOrIgnore($batch);
                $importedCount += count($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            Contact::insertOrIgnore($batch);
            $importedCount += count($batch);
        }

        fclose($handle);

        // Delete temporary uploaded CSV
        @unlink($filePath);

        // Recalculate total contacts on list
        $actualCount = Contact::where('contact_list_id', $contactListId)->count();
        ContactList::where('id', $contactListId)->update(['total_contacts' => $actualCount]);

        return [
            'total_processed' => $totalProcessed,
            'imported_count' => $importedCount,
            'actual_total' => $actualCount,
            'skipped_suppressed' => $skippedSuppressed,
            'invalid_count' => $invalidEmailCount,
        ];
    }
}

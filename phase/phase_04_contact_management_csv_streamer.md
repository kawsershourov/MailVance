# Phase 4: Contact Management & High-Performance 200MB CSV Streamer

## 1. Goal Description
Build an enterprise-grade audience and contact management module capable of importing massive CSV files (up to **200MB+**) without hitting PHP memory limits or server timeouts. Includes interactive column header auto-mapping, RFC email validation, duplicate filtering, and global unsubscribe suppression.

---

## 2. Feature Specifications

### A. Contact Lists & Organization
- Create, rename, duplicate, and delete contact lists.
- View list statistics: Total Contacts, Active Subscribers, Unsubscribed, Bounced.
- Search, filter, paginate, and manually add/edit contacts.

### B. 200MB Memory-Safe CSV Streaming Engine
- **Streaming Parser**: Uses PHP stream generators (`fopen` + `fgetcsv` / `League\Csv`) to read row-by-row.
- **Ultra-Low Memory Footprint**: Keeps RAM usage under **15MB** regardless of whether the file is 5MB or 200MB.
- **Chunked Database Batch Inserts**: Inserts records in batches of 1,000 using `DB::table('contacts')->insertOrIgnore(...)` for lightning-fast ingestion.
- **Interactive Column Mapping**:
  - Auto-detects columns: Email, First Name, Last Name, Phone, Company, Custom Fields.
  - Allows user to manually map CSV headers to contact attributes.
- **Data Cleansing & Validation**:
  - Strips whitespace and normalizes email casing.
  - Validates RFC email syntax and discards malformed strings.
  - Deduplication against existing list contacts.
- **Global Suppression List**:
  - Automatically skips any email previously marked as "Unsubscribed" or "Hard Bounced".

---

## 3. Streaming Algorithm Architecture
```php
public function streamImport(string $filePath, int $listId, array $columnMap): array {
    $handle = fopen($filePath, "r");
    $batch = [];
    $count = 0;
    
    while (($row = fgetcsv($handle, 4096, ",")) !== false) {
        $email = trim($row[$columnMap["email"]] ?? "");
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
        
        $batch[] = [
            "contact_list_id" => $listId,
            "email" => strtolower($email),
            "first_name" => trim($row[$columnMap["first_name"]] ?? ""),
            "last_name" => trim($row[$columnMap["last_name"]] ?? ""),
            "custom_fields" => json_encode($this->extractCustomFields($row, $columnMap)),
            "status" => "active",
            "created_at" => now(),
            "updated_at" => now(),
        ];
        
        if (count($batch) >= 1000) {
            Contact::insertOrIgnore($batch);
            $count += count($batch);
            $batch = [];
        }
    }
    if (!empty($batch)) {
        Contact::insertOrIgnore($batch);
        $count += count($batch);
    }
    fclose($handle);
    return ["imported" => $count];
}
```

---

## 4. Step-by-Step Implementation Tasks
1. Create `contact_lists`, `contacts`, and `suppression_lists` migrations and models.
2. Build `CsvStreamService` with streaming generator and batch insertion logic.
3. Build `ContactController` with file upload, preview, header mapping, and execution steps.
4. Implement chunked import UI with real-time progress indicators.
5. Create export functionality to download cleaned contact lists as CSV.

---

## 5. Verification & Acceptance Criteria
- [ ] 200MB CSV file parses without exceeding PHP `memory_limit` (RAM stays < 15MB).
- [ ] Invalid email rows and duplicates are safely skipped and reported in the import summary.
- [ ] Unsubscribed contacts are automatically suppressed.

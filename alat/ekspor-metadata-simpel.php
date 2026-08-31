<?php

declare(strict_types=1);

$configPath = '/var/www/html/production/webapps/webservice/config/autoload/local.php';
$outputDirectory = dirname(__DIR__).'/referensi-simpel';

if (! is_file($configPath)) {
    fwrite(STDERR, "Konfigurasi SIMPel tidak ditemukan.\n");
    exit(1);
}

$config = require $configPath;
$adapter = $config['db']['adapters']['SIMpelAdapter'] ?? null;

if (! is_array($adapter)) {
    fwrite(STDERR, "Adapter SIMPel tidak ditemukan.\n");
    exit(1);
}

$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%d;dbname=information_schema;charset=utf8mb4',
        $adapter['hostname'],
        $adapter['port'] ?? 3306,
    ),
    $adapter['username'],
    $adapter['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

$excludedSchemas = "'information_schema','mysql','performance_schema','sys'";

function exportCsv(PDO $pdo, string $path, string $query): void
{
    $statement = $pdo->query($query);
    $file = fopen($path, 'wb');

    if ($file === false) {
        throw new RuntimeException("Tidak dapat menulis {$path}");
    }

    $columns = [];
    for ($index = 0; $index < $statement->columnCount(); $index++) {
        $columns[] = $statement->getColumnMeta($index)['name'];
    }

    fputcsv($file, $columns);
    while ($row = $statement->fetch(PDO::FETCH_NUM)) {
        fputcsv($file, $row);
    }

    fclose($file);
}

$exports = [
    'ringkasan-schema.csv' => "
        SELECT TABLE_SCHEMA AS nama_schema, COUNT(*) AS jumlah_tabel
        FROM TABLES
        WHERE TABLE_SCHEMA NOT IN ({$excludedSchemas})
        GROUP BY TABLE_SCHEMA
        ORDER BY TABLE_SCHEMA
    ",
    'katalog-tabel.csv' => "
        SELECT TABLE_SCHEMA AS nama_schema, TABLE_NAME AS nama_tabel,
               TABLE_TYPE AS jenis_tabel, ENGINE AS mesin
        FROM TABLES
        WHERE TABLE_SCHEMA NOT IN ({$excludedSchemas})
        ORDER BY TABLE_SCHEMA, TABLE_NAME
    ",
    'katalog-kolom.csv' => "
        SELECT TABLE_SCHEMA AS nama_schema, TABLE_NAME AS nama_tabel,
               ORDINAL_POSITION AS urutan, COLUMN_NAME AS nama_kolom,
               COLUMN_TYPE AS tipe, IS_NULLABLE AS boleh_null,
               COLUMN_KEY AS jenis_kunci, EXTRA AS atribut_tambahan
        FROM COLUMNS
        WHERE TABLE_SCHEMA NOT IN ({$excludedSchemas})
        ORDER BY TABLE_SCHEMA, TABLE_NAME, ORDINAL_POSITION
    ",
    'relasi-foreign-key.csv' => "
        SELECT CONSTRAINT_SCHEMA AS nama_schema, TABLE_NAME AS nama_tabel,
               COLUMN_NAME AS nama_kolom, CONSTRAINT_NAME AS nama_relasi,
               REFERENCED_TABLE_SCHEMA AS schema_tujuan,
               REFERENCED_TABLE_NAME AS tabel_tujuan,
               REFERENCED_COLUMN_NAME AS kolom_tujuan
        FROM KEY_COLUMN_USAGE
        WHERE REFERENCED_TABLE_NAME IS NOT NULL
          AND CONSTRAINT_SCHEMA NOT IN ({$excludedSchemas})
        ORDER BY CONSTRAINT_SCHEMA, TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION
    ",
    'katalog-index.csv' => "
        SELECT TABLE_SCHEMA AS nama_schema, TABLE_NAME AS nama_tabel,
               INDEX_NAME AS nama_index, NON_UNIQUE AS tidak_unik,
               SEQ_IN_INDEX AS urutan, COLUMN_NAME AS nama_kolom,
               INDEX_TYPE AS jenis_index
        FROM STATISTICS
        WHERE TABLE_SCHEMA NOT IN ({$excludedSchemas})
        ORDER BY TABLE_SCHEMA, TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX
    ",
    'ketergantungan-view.csv' => "
        SELECT VIEW_SCHEMA AS nama_schema, VIEW_NAME AS nama_view,
               TABLE_SCHEMA AS schema_sumber, TABLE_NAME AS tabel_sumber
        FROM VIEW_TABLE_USAGE
        WHERE VIEW_SCHEMA NOT IN ({$excludedSchemas})
        ORDER BY VIEW_SCHEMA, VIEW_NAME, TABLE_SCHEMA, TABLE_NAME
    ",
    'katalog-routine.csv' => "
        SELECT ROUTINE_SCHEMA AS nama_schema, ROUTINE_NAME AS nama_routine,
               ROUTINE_TYPE AS jenis_routine, DATA_TYPE AS tipe_hasil
        FROM ROUTINES
        WHERE ROUTINE_SCHEMA NOT IN ({$excludedSchemas})
        ORDER BY ROUTINE_SCHEMA, ROUTINE_NAME
    ",
];

foreach ($exports as $filename => $query) {
    exportCsv($pdo, $outputDirectory.'/'.$filename, $query);
}

exportCsv($pdo, $outputDirectory.'/katalog-menu.csv', '
    SELECT ID AS kode, NAMA AS nama, LEVEL AS tingkat,
           STATUS AS aktif, CLASS AS kelas_frontend
    FROM aplikasi.modules
    ORDER BY ID
');

echo "Metadata SIMPel berhasil diekspor tanpa data transaksi.\n";

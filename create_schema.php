<?php
try {
    $pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=master_db', 'postgres', 'postgres');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE SCHEMA IF NOT EXISTS erp');
    echo "OK: Schema 'erp' created in master_db\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

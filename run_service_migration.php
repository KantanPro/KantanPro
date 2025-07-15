<?php
/**
 * Migration script for adding tax_rate column to service table
 */

// Load WordPress
require_once 'wp-load.php';

// Check if we're in WordPress environment
if ( ! defined( 'ABSPATH' ) ) {
    echo "Error: WordPress not loaded.\n";
    exit( 1 );
}

global $wpdb;

$table_name = $wpdb->prefix . 'ktp_service';

echo "Checking table: {$table_name}\n";

// Check if table exists
$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );

if ( ! $table_exists ) {
    echo "Error: Table {$table_name} does not exist.\n";
    exit( 1 );
}

echo "Table {$table_name} exists.\n";

// Check if tax_rate column already exists
$column_exists = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'tax_rate'",
        DB_NAME,
        $table_name
    )
);

if ( empty( $column_exists ) ) {
    echo "Adding tax_rate column to {$table_name} table...\n";
    
    // Add tax_rate column
    $sql = "ALTER TABLE `{$table_name}` ADD COLUMN `tax_rate` DECIMAL(5,2) DEFAULT 10.00 NOT NULL AFTER `price`";
    $result = $wpdb->query( $sql );
    
    if ( $result !== false ) {
        echo "Successfully added tax_rate column to {$table_name} table.\n";
        
        // Update existing records with default tax rate
        $update_result = $wpdb->query( "UPDATE `{$table_name}` SET `tax_rate` = 10.00 WHERE `tax_rate` IS NULL" );
        if ( $update_result !== false ) {
            echo "Updated existing records with default tax rate.\n";
        }
    } else {
        echo "Error adding tax_rate column to {$table_name} table: " . $wpdb->last_error . "\n";
        exit( 1 );
    }
} else {
    echo "tax_rate column already exists in {$table_name} table.\n";
}

// Show table structure
echo "\nCurrent table structure:\n";
$columns = $wpdb->get_results( "DESCRIBE `{$table_name}`" );
foreach ( $columns as $column ) {
    echo "- {$column->Field}: {$column->Type} {$column->Null} {$column->Key} {$column->Default}\n";
}

echo "\nMigration completed successfully.\n"; 
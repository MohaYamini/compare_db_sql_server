<?php

require_once __DIR__ . '/../config/database.php';  // Database configuration
require_once __DIR__ . '/../src/Compare.php';      // Compare class

// Function to prompt user input
function prompt($message)
{
    $green = "\033[0;32m";  // Green color code
    $reset = "\033[0m";     // Reset color code

    echo $green . $message . $reset . "\n";  // Print the message in green and reset color after
    return trim(fgets(STDIN));  // Get input from command line
}

// Get the database configuration from the config file
$config = require __DIR__ . '/../config/database.php';

// Ask user for action choice
$action = prompt("1: Do you want to generate scripts for all databases? \n2: Do you want to generate scripts for specific tables? (from file /data/tables_to_compare.txt)");
switch ($action) {
    case '1':
        // Generate scripts for all tables in both databases
        echo "Generating scripts for all tables...\n";

        // Initialize Compare class for all tables
        $compare = new Compare($config['db1'], $config['db2'], null); // Pass null for tables file to include all tables
        $compare->compare_and_generate_scripts();
        echo "Scripts generated successfully.\n";
        break;
    case '2':
        // Generate scripts for specific tables
        echo "Generating scripts for specific tables...\n";

        // Load the tables from the file 'tables_to_compare.txt'
        $tables_file = '../data/tables_to_compare.txt';
        if (!file_exists($tables_file)) {
            die("Table list file not found: $tables_file\n");
        }

        $tables = array_map('trim', file($tables_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));  // Read and trim the table names
        if (empty($tables)) {
            die("No tables found in the file $tables_file\n");
        }

        // Initialize Compare class for specific tables
        $compare = new Compare($config['db1'], $config['db2'], $tables_file);
        $compare->compare_and_generate_scripts();
        echo "Scripts generated successfully.\n";
        break;

    default:
        echo "Invalid selection. Please run the script again and choose 1 or 2.\n";
        break;
}

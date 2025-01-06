is not check my work :
<?php
ini_set('memory_limit', '1024M'); // Increase memory limit to 1GB

class Compare
{
    private $db1;
    private $db2;
    private $tables_to_compare = [];

    public function __construct($db1_config, $db2_config, $tables_file)
    {
        $this->db1 = $this->connect_to_database($db1_config);
        $this->db2 = $this->connect_to_database($db2_config);

        // If tables_file is null, compare all tables
        if ($tables_file === null) {
            $this->tables_to_compare = $this->get_all_tables($this->db1);
        } else {
            $this->load_tables_to_compare($tables_file);
        }
    }

    private function connect_to_database($config)
    {
        $dsn = "sqlsrv:Server={$config['host']};Database={$config['dbname']}";
        $username = !empty($config['user']) ? $config['user'] : null;
        $password = !empty($config['password']) ? $config['password'] : null;

        try {
            return new PDO($dsn, $username, $password);
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    private function load_tables_to_compare($file_path)
    {
        if (!file_exists($file_path)) {
            die("Table file not found: $file_path");
        }

        $this->tables_to_compare = array_map('trim', file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    }

    private function get_all_tables(PDO $connection)
    {
        $sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'";
        $stmt = $connection->prepare($sql);
        $stmt->execute();
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $tables;
    }

    private function get_procedure_definition(PDO $connection, $procedure_name)
    {
        // Fetch the procedure definition from the database
        $sql = "SELECT OBJECT_DEFINITION(OBJECT_ID('$procedure_name')) AS definition";
        $stmt = $connection->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $result['definition'] : null;
    }

    private function get_function_definition(PDO $connection, $function_name)
    {
        // Fetch the function definition from the database
        $sql = "SELECT OBJECT_DEFINITION(OBJECT_ID('$function_name')) AS definition";
        $stmt = $connection->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $result['definition'] : null;
    }

    private function format_sql_for_sqlserver($sql)
    {
        if (empty($sql)) {
            echo "Warning: Empty SQL statement provided.\n";
            return '';
        }

        // Replace backticks with square brackets for SQL Server
        $sql = preg_replace('/`([^`]*)`/', '[$1]', $sql);

        // Handle ALTER TABLE with multiple ADD clauses
        if (preg_match('/^ALTER TABLE \[([^\]]+)\] ADD (.+)$/is', $sql, $matches)) {
            $table_name = $matches[1];
            $columns = explode(',', $matches[2]); // Split multiple ADD columns
            $formatted_alter = '';
            foreach ($columns as $column) {
                $formatted_alter .= "ALTER TABLE [$table_name] ADD " . trim($column) . ";\n";
            }
            return $formatted_alter;
        }

        return $sql;
    }

    private function execute_sql(PDO $db_connection, $sql)
    {
        if (empty($sql)) {
            echo "Skipping execution: Empty SQL statement.\n";
            return;
        }

        // Split and execute each statement
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $statement) {
            try {
                $stmt = $db_connection->prepare($statement);
                $stmt->execute();
                echo "SQL executed successfully: $statement\n";
            } catch (PDOException $e) {
                echo "Error executing statement: " . $e->getMessage() . "\n";
            }
        }
    }

    private function get_all_procedures(PDO $connection)
    {
        $sql = "SELECT ROUTINE_NAME FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_TYPE = 'PROCEDURE'";
        $stmt = $connection->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function get_all_functions(PDO $connection)
    {
        $sql = "SELECT ROUTINE_NAME FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_TYPE = 'FUNCTION'";
        $stmt = $connection->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function compare_and_generate_scripts()
    {
        // Use the provided or loaded tables for comparison
        $db1_metadata = $this->get_tables_and_columns($this->db1);
        $db2_metadata = $this->get_tables_and_columns($this->db2);

        // Filter metadata to only include specified tables
        $db1_metadata = array_intersect_key($db1_metadata, array_flip($this->tables_to_compare));
        $db2_metadata = array_intersect_key($db2_metadata, array_flip($this->tables_to_compare));

        $tables_script = '';
        $columns_script = '';
        $foreign_keys_script = '';
        $procedures_script = '';
        $functions_script = '';

        // Compare tables
        $tables_in_db1_only = array_diff_key($db1_metadata, $db2_metadata);
        foreach ($tables_in_db1_only as $table => $columns) {
            $tables_script .= $this->generate_create_table_script($table, $columns) . PHP_EOL;
        }

        // Compare columns in common tables
        $common_tables = array_intersect_key($db1_metadata, $db2_metadata);
        foreach ($common_tables as $table => $db1_columns) {
            $db2_columns = $db2_metadata[$table];
            $missing_columns = $this->get_missing_columns($db1_columns, $db2_columns);
            if (!empty($missing_columns)) {
                $columns_script .= $this->generate_alter_table_script($table, $missing_columns) . PHP_EOL;
            }
        }

        // Compare foreign keys
        $this->get_foreign_keys($this->db1);
        $this->get_foreign_keys($this->db2);

        // Compare procedures and functions
        $db1_procedures = $this->get_all_procedures($this->db1);
        $db2_procedures = $this->get_all_procedures($this->db2);
        $missing_procedures = array_diff($db1_procedures, $db2_procedures);
        foreach ($missing_procedures as $procedure) {
            $procedure_definition = $this->get_procedure_definition($this->db1, $procedure);
            if ($procedure_definition) {
                $procedures_script .= $procedure_definition . "\nGO\n" . PHP_EOL;
            }
        }

        // Same logic for functions
        $db1_functions = $this->get_all_functions($this->db1);
        $db2_functions = $this->get_all_functions($this->db2);
        $missing_functions = array_diff($db1_functions, $db2_functions);
        foreach ($missing_functions as $function) {
            $function_definition = $this->get_function_definition($this->db1, $function);
            if ($function_definition) {
                $functions_script .= $function_definition . "\nGO\n" . PHP_EOL;
            }
        }

        // Save scripts to files
        $current_datetime = date('Y-m-d_H-i-s');
        $this->save_script_to_file("script_tables_$current_datetime.sql", $tables_script);
        $this->save_script_to_file("script_column_missing_$current_datetime.sql", $columns_script);
        $this->save_script_to_file("script_procedures_$current_datetime.sql", $procedures_script);
        $this->save_script_to_file("script_functions_$current_datetime.sql", $functions_script);

        // Define green color escape code
        $green = "\033[32m";
        $reset = "\033[0m"; // Reset to default color

        // Print the prompt message in green
        echo $green . "Do you want to apply these changes to all scripts in database? (y/n): " . $reset;
        $apply_changes = trim(fgets(STDIN));

        if (strtolower($apply_changes) === 'y') {
            // Apply the changes to both databases
            echo "Applying changes to the database...\n";

            $this->execute_sql($this->db2, $this->format_sql_for_sqlserver($tables_script));
            $this->execute_sql($this->db2, $this->format_sql_for_sqlserver($columns_script));
            $this->execute_sql($this->db2, $this->format_sql_for_sqlserver($foreign_keys_script));
            //$this->execute_sql($this->db2, $this->format_sql_for_sqlserver($procedures_script));
            //$this->execute_sql($this->db2, $this->format_sql_for_sqlserver($functions_script));

            echo "Changes applied successfully.\n";
        } else {
            echo "Changes not applied.\n";
        }
    }

    private function save_script_to_file($filename, $content)
    {
        $file_path = __DIR__ . "/../scripts/$filename";
        if (!is_dir(dirname($file_path))) {
            mkdir(dirname($file_path), 0777, true);
        }

        $file = fopen($file_path, 'w');  // Open file for writing
        if ($file) {
            fwrite($file, $content);  // Write to the file
            fclose($file);
            echo "Script saved to $file_path" . PHP_EOL;
        } else {
            echo "Failed to open file: $file_path" . PHP_EOL;
        }
    }

    private function get_tables_and_columns(PDO $connection)
    {
        // Query to fetch table columns along with identity and primary key info
        $sql = "
        SELECT 
            C.TABLE_NAME AS table_name, 
            C.COLUMN_NAME AS column_name, 
            C.DATA_TYPE AS type, 
            C.CHARACTER_MAXIMUM_LENGTH AS length, 
            C.IS_NULLABLE AS nullable,
            COLUMNPROPERTY(OBJECT_ID(C.TABLE_NAME), C.COLUMN_NAME, 'IsIdentity') AS AIDENTITY
        FROM INFORMATION_SCHEMA.COLUMNS C
        INNER JOIN INFORMATION_SCHEMA.TABLES T
            ON C.TABLE_NAME = T.TABLE_NAME
        WHERE T.TABLE_TYPE = 'BASE TABLE'
    ";

        $stmt = $connection->prepare($sql);
        $stmt->execute();

        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[$row['table_name']][] = [
                'column' => $row['column_name'],
                'type' => $row['type'],
                'length' => $row['length'],
                'nullable' => $row['nullable'],
                'identity' => $row['AIDENTITY'],  // Identity info
            ];
        }

        // Now, fetch the primary key columns
        $primary_keys_sql = "
        SELECT 
            KCU.TABLE_NAME AS table_name, 
            KCU.COLUMN_NAME AS column_name
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE KCU
        INNER JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS TC
            ON KCU.CONSTRAINT_NAME = TC.CONSTRAINT_NAME
        WHERE TC.CONSTRAINT_TYPE = 'PRIMARY KEY'
    ";

        $stmt = $connection->prepare($primary_keys_sql);
        $stmt->execute();

        $primary_keys = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $primary_keys[$row['table_name']][] = $row['column_name'];
        }

        // Merge identity and primary key info with the columns data
        foreach ($columns as $table_name => &$table_columns) {
            foreach ($table_columns as &$column) {
                // Check if the column is part of the primary key
                if (isset($primary_keys[$table_name]) && in_array($column['column'], $primary_keys[$table_name])) {
                    $column['primary_key'] = true;
                } else {
                    $column['primary_key'] = false;
                }

                // Mark the column as identity if applicable
                if ($column['identity'] == 1) {
                    $column['identity'] = true;
                } else {
                    $column['identity'] = false;
                }
            }
        }

        return $columns;
    }

    private function get_foreign_keys(PDO $connection)
    {
        // SQL query to get foreign key information
        $sql = "SELECT fk.name AS constraint_name,
               tp.name AS table_name,
               ref.name AS referenced_table,
               cp.name AS column_name,
               cref.name AS referenced_column
        FROM sys.foreign_keys AS fk
        INNER JOIN sys.tables AS tp ON fk.parent_object_id = tp.object_id
        INNER JOIN sys.tables AS ref ON fk.referenced_object_id = ref.object_id
        INNER JOIN sys.foreign_key_columns AS fkc ON fkc.constraint_object_id = fk.object_id
        INNER JOIN sys.columns AS cp ON fkc.parent_column_id = cp.column_id AND cp.object_id = tp.object_id
        INNER JOIN sys.columns AS cref ON fkc.referenced_column_id = cref.column_id AND cref.object_id = ref.object_id";

        try {
            $stmt = $connection->prepare($sql);
            $stmt->execute();
        } catch (PDOException $e) {
            echo "Error executing query: " . $e->getMessage();
            return;
        }

        $foreign_keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate SQL script content
        $foreign_keys_script = "";
        foreach ($foreign_keys as $key) {
            $foreign_keys_script .= "ALTER TABLE " . $key['table_name'] . " ADD CONSTRAINT " . $key['constraint_name'] . " FOREIGN KEY (" . $key['column_name'] . ") REFERENCES " . $key['referenced_table'] . " (" . $key['referenced_column'] . ");\n";
        }

        // Get current date and time for filename
        $current_datetime = date('Y-m-d_H-i-s');
        $filename = "script_foreign_key_$current_datetime.sql";

        // Check if the file already exists
        if (!file_exists(__DIR__ . "/../scripts/$filename")) {
            // Save script to file using save_script_to_file method
            $this->save_script_to_file($filename, $foreign_keys_script);
        } else {
            echo "File already exists: $filename\n";
        }
    }
    private function generate_create_table_script($table, $columns)
    {
        // Initialize variables for column definitions and primary key columns
        $primary_key_columns = [];
        $columns_sql = '';

        // Iterate through columns to build column definitions
        foreach ($columns as $column) {
            // Start building the column definition
            $column_definition = "[{$column['column']}] {$column['type']}";

            // Handle length for types that support it
            if (in_array(strtolower($column['type']), ['nvarchar', 'varchar', 'varbinary'])) {
                if (isset($column['length'])) {
                    if (in_array($column['length'], [-1, 1073741823, 2147483647])) {
                        $column_definition .= "(MAX)";
                    } elseif ($column['length'] > 0) {
                        $column_definition .= "({$column['length']})";
                    }
                }
            }

            // Add IDENTITY for identity columns
            if ($column['identity'] === true) {
                $column_definition .= " IDENTITY(1, 1)";
            }

            // Add NOT NULL constraint if the column is not nullable
            if (isset($column['nullable']) && $column['nullable'] === 'NO') {
                $column_definition .= " NOT NULL";
            }

            // If it's a primary key column, add it to the list
            if (isset($column['primary_key']) && $column['primary_key'] === true) {
                $primary_key_columns[] = $column['column'];
            }

            // Append the column definition to columns_sql
            $columns_sql .= $column_definition . ", ";
        }

        // Remove the last comma and space from column definitions
        $columns_sql = rtrim($columns_sql, ', ');

        // Add the primary key constraint if specified
        $primary_key_sql = '';
        if (count($primary_key_columns) > 0) {
            $primary_key_sql = ", CONSTRAINT [PK_{$table}] PRIMARY KEY ([" . implode('], [', $primary_key_columns) . "])";
        }

        // Combine everything into the final CREATE TABLE SQL statement
        return "CREATE TABLE [dbo].[{$table}] (\n  {$columns_sql}\n  {$primary_key_sql}\n);";
    }

    private function generate_alter_table_script($table, $missing_columns)
    {
        // Initialize an array to store individual ALTER TABLE statements
        $alter_statements = [];

        foreach ($missing_columns as $column) {
            // Start with the basic ADD statement
            $column_sql = "ALTER TABLE [$table] ADD [{$column['column']}] {$column['type']}";

            // Handle length for types that support it, excluding 'ntext'
            if (strtolower($column['type']) == 'ntext' || $column['type'] == 'image') {
                // Do not append any length for 'ntext'
                $column_sql .= "";  // No length specifier
            } elseif (!empty($column['length']) && $column['length'] == -1) {
                // Adjust the length for max types like nvarchar, varchar, etc.
                if (strtolower($column['type']) == 'nvarchar' || strtolower($column['type']) == 'varchar') {
                    $column_sql .= "(MAX)";
                }
            } elseif (!empty($column['length'])) {
                // Add the length if it's not -1 or null
                $column_sql .= "({$column['length']})";
            }

            // Add NOT NULL if applicable
            if ($column['nullable'] === 'NO') {
                $column_sql .= ' NOT NULL';
            }

            // Add the statement to the array
            $alter_statements[] = $column_sql;
        }

        // Join all statements with a newline to execute them separately
        return implode(";\n", $alter_statements) . ';';
    }

    private function get_missing_columns($db1_columns, $db2_columns)
    {
        $db2_column_names = array_column($db2_columns, 'column');

        return array_filter($db1_columns, function ($column) use ($db2_column_names) {
            return !in_array($column['column'], $db2_column_names);
        });
    }
}

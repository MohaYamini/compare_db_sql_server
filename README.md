# Database Comparison Tool

This project provides a tool to compare two databases and generate SQL scripts to synchronize the schema of the target database (`db2`) with the source database (`db1`).

## Features
- Compare the structures of two databases.
- Generate SQL scripts for schema synchronization.
- Option to compare all tables or specific tables listed in a file.

---

## Prerequisites

1. **PHP**: Install PHP on your system.
2. **Database Access**: Ensure access credentials for both databases are configured.
3. **ODBC Driver**: Install the correct driver for SQL Server.

---

## Project Structure

```
project-root/
├── config/
│   └── config.php       # Configuration file for database connections
├── public/
│   └── index.php       # Entry point for the application
├── src/
│   └── Compare.php     # Core class for database comparison and script generation
├── scripts/
│   └── output.sql      # Generated SQL scripts (output file)
└── README.md               # Project documentation
```

---

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/your-repo/db-compare-tool.git
   ```

2. Navigate to the project directory:
   ```bash
   cd db-compare-tool
   ```

3. Install dependencies using Composer:
   ```bash
   composer install
   ```

---

## Configuration

1. Navigate to the `config/` directory.
2. Open `config.php` and update the database connection details:

   ```php
   <?php
   return [
       'db1' => [
           'host' => 'YOUR_DB1_HOST',
           'dbname' => 'YOUR_DB1_NAME',
           'user' => 'YOUR_DB1_USERNAME',
           'password' => 'YOUR_DB1_PASSWORD',
       ],
       'db2' => [
           'host' => 'YOUR_DB2_HOST',
           'dbname' => 'YOUR_DB2_NAME',
           'user' => 'YOUR_DB2_USERNAME',
           'password' => 'YOUR_DB2_PASSWORD',
       ],
   ];
   ```

3. Save the file.

---

## Usage

1. Run the application from the command line or a browser:
   ```bash
   php public/index.php
   ```

2. The tool will:
   - Compare `db1` and `db2` schemas.
   - Generate SQL scripts to synchronize `db2` with `db1`.

3. Check the generated SQL script in the `scripts/output.sql` file.

---

## Example Output

Sample SQL script generated in `scripts/output.sql`:

```sql
ALTER TABLE [Leads] ADD [form_juridique] VARCHAR(255) NULL;
ALTER TABLE [Leads] ADD [capital] FLOAT NULL;
ALTER TABLE [Leads] ADD [date_creation_association] DATE NULL;
ALTER TABLE [Leads] ADD [nom_association] VARCHAR(255) NULL;
ALTER TABLE [Leads] ADD [id_quartier] INT NULL;
```

---

## Error Handling

- **Empty SQL Statement**: The tool skips execution for empty SQL statements.
- **SQL Syntax Errors**: Ensure the generated SQL aligns with the target database's syntax.
- **Permission Issues**: Verify database user credentials have the required permissions.

---

## Notes

- This tool is designed specifically for Microsoft SQL Server. If using a different database, adapt the SQL generation logic accordingly.
- Always back up your databases before applying generated SQL scripts.

---

## License

This project is open-source and available under the [MIT License](LICENSE).

---

## Contributions

Contributions are welcome! Feel free to submit issues or pull requests to improve the tool.


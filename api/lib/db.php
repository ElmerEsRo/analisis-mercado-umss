<?php
function db_driver(): string {
    $url = getenv('DATABASE_URL_UNPOOLED') ?: getenv('DATABASE_URL') ?: getenv('POSTGRES_URL') ?: getenv('NEON_DATABASE_URL');
    return $url ? 'pgsql' : 'mysql';
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    try {
        $url = getenv('DATABASE_URL_UNPOOLED') ?: getenv('DATABASE_URL') ?: getenv('POSTGRES_URL') ?: getenv('NEON_DATABASE_URL');

        if ($url) {
            $parts = parse_url($url);
            if ($parts === false || empty($parts['host']) || empty($parts['path'])) {
                throw new RuntimeException('DATABASE_URL no tiene un formato válido.');
            }

            $host = $parts['host'];
            $port = $parts['port'] ?? 5432;
            $dbname = ltrim($parts['path'], '/');

            $endpointId = explode('.', $host)[0];
            $endpointId = preg_replace('/-pooler$/', '', $endpointId);

            $user = isset($parts['user']) ? urldecode($parts['user']) : '';
            $password = isset($parts['pass']) ? urldecode($parts['pass']) : '';
            parse_str($parts['query'] ?? '', $query);

            $sslmode = $query['sslmode'] ?? 'require';
            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode={$sslmode};options='endpoint={$endpointId}'";

            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $pdo->exec("CREATE TABLE IF NOT EXISTS datasets (
                id BIGSERIAL PRIMARY KEY,
                type VARCHAR(20) NOT NULL CHECK (type IN ('titulados','empleadores')),
                filename VARCHAR(255) NOT NULL,
                uploaded_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
                rows_count INTEGER NOT NULL DEFAULT 0,
                columns_count INTEGER NOT NULL DEFAULT 0,
                headers_json TEXT NOT NULL
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS responses (
                id BIGSERIAL PRIMARY KEY,
                dataset_id BIGINT NOT NULL REFERENCES datasets(id) ON DELETE CASCADE,
                row_number INTEGER NOT NULL,
                data_json TEXT NOT NULL
            )");

            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_responses_dataset ON responses(dataset_id)");
            return $pdo;
        }

        $c = require __DIR__ . '/../config/database.php';
        $base = "mysql:host={$c['host']};port={$c['port']};charset={$c['charset']}";

        $server = new PDO($base, $c['user'], $c['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $dbname = preg_replace('/[^a-zA-Z0-9_]/', '', $c['dbname']);
        $server->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $pdo = new PDO($base.";dbname={$dbname}", $c['user'], $c['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $pdo->exec("CREATE TABLE IF NOT EXISTS datasets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type ENUM('titulados','empleadores') NOT NULL,
            filename VARCHAR(255) NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            rows_count INT NOT NULL DEFAULT 0,
            columns_count INT NOT NULL DEFAULT 0,
            headers_json LONGTEXT NOT NULL
        ) ENGINE=InnoDB");

        $pdo->exec("CREATE TABLE IF NOT EXISTS responses (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            dataset_id INT NOT NULL,
            row_number INT NOT NULL,
            data_json LONGTEXT NOT NULL,
            CONSTRAINT fk_responses_dataset FOREIGN KEY (dataset_id) REFERENCES datasets(id) ON DELETE CASCADE,
            INDEX idx_dataset(dataset_id)
        ) ENGINE=InnoDB");

        return $pdo;
    } catch (Throwable $e) {
        throw new RuntimeException(
            'No se pudo conectar con la base de datos. En Vercel verifica DATABASE_URL; en local verifica MySQL/XAMPP. Detalle: '.$e->getMessage()
        );
    }
}

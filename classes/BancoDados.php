<?php
declare(strict_types=1);

final class BancoDados
{
    private ?PDO $pdo = null;

    /** @param array<string,mixed> $config */
    public function __construct(private readonly array $config) {}

    public function enabled(): bool { return (bool) ($this->config['enabled'] ?? false); }

    public function pdo(): PDO
    {
        if (!$this->enabled()) throw new RuntimeException('O banco de dados está desabilitado.');
        if ($this->pdo instanceof PDO) return $this->pdo;
        if (!extension_loaded('pdo_pgsql')) throw new RuntimeException('A extensão pdo_pgsql não está habilitada no PHP.');
        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
            $this->config['host'], $this->config['port'], $this->config['dbname'], $this->config['sslmode'] ?? 'prefer');
        $this->pdo = new PDO($dsn, (string) $this->config['user'], (string) $this->config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $timeout = max(1000, (int) ($this->config['statement_timeout_ms'] ?? 15000));
        $this->pdo->exec("SET statement_timeout = {$timeout}");
        $this->pdo->exec('SET default_transaction_read_only = on');
        return $this->pdo;
    }

    /** @return array<string,mixed> */
    public function status(): array
    {
        if (!$this->enabled()) return ['enabled' => false, 'online' => false];
        $version = $this->pdo()->query('SELECT current_database() AS database, current_user AS user, version() AS version')->fetch();
        return ['enabled' => true, 'online' => true] + $version;
    }

    /** @return array<int,array<string,mixed>> */
    public function schema(): array
    {
        $sql = "SELECT c.table_schema, c.table_name, c.column_name, c.data_type, c.is_nullable
                FROM information_schema.columns c
                JOIN information_schema.tables t ON t.table_schema=c.table_schema AND t.table_name=c.table_name
                WHERE c.table_schema NOT IN ('pg_catalog','information_schema') AND t.table_type='BASE TABLE'
                ORDER BY c.table_schema,c.table_name,c.ordinal_position";
        return $this->pdo()->query($sql)->fetchAll();
    }

    /** @return array<string,mixed> */
    public function countAllTables(): array
    {
        $tables=$this->pdo()->query("SELECT table_schema,table_name FROM information_schema.tables WHERE table_schema NOT IN ('pg_catalog','information_schema') AND table_type='BASE TABLE' ORDER BY table_schema,table_name")->fetchAll();
        $counts=[];
        foreach($tables as $table){
            $schema=str_replace('"','""',(string)$table['table_schema']);
            $name=str_replace('"','""',(string)$table['table_name']);
            $count=(int)$this->pdo()->query('SELECT COUNT(*) FROM "'.$schema.'"."'.$name.'"')->fetchColumn();
            $counts[]=['schema'=>$table['table_schema'],'table'=>$table['table_name'],'records'=>$count];
        }
        return ['tables'=>$counts,'table_count'=>count($counts),'total_records'=>array_sum(array_column($counts,'records'))];
    }

    /** @return array<string,mixed> */
    public function select(string $sql, array $params = []): array
    {
        $clean = trim(preg_replace('/\s+/', ' ', $sql) ?? $sql);
        if (!preg_match('/^(SELECT|WITH)\b/i', $clean)) throw new InvalidArgumentException('Somente SELECT ou WITH são permitidos.');
        if (str_contains($clean, ';')) throw new InvalidArgumentException('Envie apenas uma consulta, sem ponto e vírgula.');
        if (preg_match('/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|CREATE|GRANT|REVOKE|COPY|CALL|DO|VACUUM|ANALYZE|REFRESH)\b/i', $clean)) {
            throw new InvalidArgumentException('A consulta contém uma operação não permitida.');
        }
        $limit = max(1, min(1000, (int) ($this->config['max_rows'] ?? 200)));
        $wrapped = "SELECT * FROM ({$clean}) AS ollama_web_resultado LIMIT {$limit}";
        $stmt = $this->pdo()->prepare($wrapped);
        foreach ($params as $name => $value) {
            $key = str_starts_with((string) $name, ':') ? (string) $name : ':' . $name;
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return ['rows' => $rows, 'count' => count($rows), 'limited_to' => $limit];
    }
}

<?php
declare(strict_types=1);

final class BancoResumo implements Ferramenta
{
    public function __construct(private readonly BancoDados $db) {}
    public function definition(): array
    {
        return ['type'=>'function','function'=>[
            'name'=>'banco_resumo',
            'description'=>'Retorna a quantidade e os nomes das tabelas do PostgreSQL. Use para perguntas sobre quantas tabelas existem ou quais são elas.',
            'parameters'=>['type'=>'object','properties'=>new stdClass()]
        ]];
    }
    public function execute(array $arguments): array
    {
        return $this->db->select("SELECT table_schema, table_name FROM information_schema.tables WHERE table_schema NOT IN ('pg_catalog','information_schema') AND table_type='BASE TABLE' ORDER BY table_schema,table_name");
    }
}

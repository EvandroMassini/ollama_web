<?php
declare(strict_types=1);
final class BancoConsultar implements Ferramenta
{
    public function __construct(private readonly BancoDados $db) {}
    public function definition(): array { return ['type'=>'function','function'=>[
        'name'=>'banco_consultar','description'=>'Executa uma consulta PostgreSQL somente leitura. Use parâmetros nomeados para valores fornecidos pelo usuário. Nunca invente tabelas ou colunas.',
        'parameters'=>['type'=>'object','properties'=>[
            'sql'=>['type'=>'string','description'=>'Uma única consulta SELECT ou WITH, sem ponto e vírgula.'],
            'params'=>['type'=>'object','description'=>'Valores dos parâmetros nomeados usados no SQL.','additionalProperties'=>true],
        ],'required'=>['sql']]
    ]]; }
    public function execute(array $arguments): array
    {
        $sql = $arguments['sql'] ?? '';
        $params = $arguments['params'] ?? [];
        if (!is_string($sql) || !is_array($params)) throw new InvalidArgumentException('Argumentos inválidos para banco_consultar.');
        return $this->db->select($sql, $params);
    }
}

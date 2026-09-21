<?php
declare(strict_types=1);

final class BancoContarRegistros implements Ferramenta
{
    public function __construct(private readonly BancoDados $db) {}
    public function definition(): array
    {
        return ['type'=>'function','function'=>[
            'name'=>'banco_contar_registros_tabelas',
            'description'=>'Conta exatamente os registros de todas as tabelas em uma única operação. Use quando pedirem a quantidade de registros em cada tabela ou o total de registros do banco. Não chame banco_estrutura nem banco_consultar para esse tipo de pergunta.',
            'parameters'=>['type'=>'object','properties'=>new stdClass()]
        ]];
    }
    public function execute(array $arguments): array { return $this->db->countAllTables(); }
}

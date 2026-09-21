<?php
declare(strict_types=1);
final class BancoEstrutura implements Ferramenta
{
    public function __construct(private readonly BancoDados $db) {}
    public function definition(): array { return ['type'=>'function','function'=>['name'=>'banco_estrutura','description'=>'Lista tabelas e colunas disponíveis no PostgreSQL. Use antes de escrever consultas quando não conhecer o esquema.','parameters'=>['type'=>'object','properties'=>new stdClass()]]]; }
    public function execute(array $arguments): array { return ['columns'=>$this->db->schema()]; }
}

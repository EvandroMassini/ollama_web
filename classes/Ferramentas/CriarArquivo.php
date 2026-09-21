<?php
declare(strict_types=1);

/** Transforma o conteúdo produzido pelo modelo em um download validado. */
final class CriarArquivo implements Ferramenta
{
    public function __construct(private readonly Artefatos $artifacts){}
    public function definition(): array{return ['type'=>'function','function'=>[
        'name'=>'criar_arquivo','description'=>'Cria arquivo para download quando o usuário pedir código, documento, relatório, CSV, PDF, DOCX, XLSX ou ZIP. Para XLSX, content deve ser CSV. Para ZIP, forneça files.',
        'parameters'=>['type'=>'object','properties'=>[
            'filename'=>['type'=>'string','description'=>'Nome com extensão.'],
            'content'=>['type'=>'string','description'=>'Conteúdo completo do arquivo.'],
            'files'=>['type'=>'array','description'=>'Arquivos internos do ZIP.','items'=>['type'=>'object','properties'=>['name'=>['type'=>'string'],'content'=>['type'=>'string']],'required'=>['name','content']]],
        ],'required'=>['filename','content']]
    ]];}
    public function execute(array $arguments): array{return ['artifact'=>$this->artifacts->create((string)($arguments['filename']??''),(string)($arguments['content']??''),is_array($arguments['files']??null)?$arguments['files']:[])];}
}

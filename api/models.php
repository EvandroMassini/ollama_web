<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require dirname(__DIR__).'/bootstrap.php';
function modelReply(array $body,int $status=200): never { http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit; }

try{
    if($_SERVER['REQUEST_METHOD']==='GET'){
        $installed=$ollama->status();
        // /api/ps não existe em versões muito antigas do Ollama; a lista instalada
        // continua útil mesmo quando o estado em memória não está disponível.
        try{$running=$ollama->running();}catch(Throwable){$running=['models'=>[]];}
        modelReply(['ok'=>true,'installed'=>$installed['models'],'running'=>$running['models']??[],'selected'=>$ollamaModel]);
    }
    if($_SERVER['REQUEST_METHOD']!=='POST') modelReply(['ok'=>false,'error'=>'Método não permitido.'],405);
    $seguranca->verify();
    $input=json_decode(file_get_contents('php://input')?:'{}',true,512,JSON_THROW_ON_ERROR);
    $action=(string)($input['action']??'');$model=trim((string)($input['model']??''));
    if($model===''||!preg_match('/^[a-zA-Z0-9._\/-]+(?::[a-zA-Z0-9._-]+)?$/',$model)) throw new InvalidArgumentException('Nome de modelo inválido.');
    // Downloads grandes não devem herdar o limite curto de execução do Apache/PHP.
    if($action==='pull') set_time_limit(0);
    $result=match($action){
        'pull'=>$ollama->pull($model),
        'load'=>$ollama->load($model,(string)($input['keep_alive']??'30m')),
        'unload'=>$ollama->unload($model),
        default=>throw new InvalidArgumentException('Ação de modelo desconhecida.'),
    };
    modelReply(['ok'=>true,'action'=>$action,'model'=>$model,'result'=>$result]);
}catch(Throwable $e){modelReply(['ok'=>false,'error'=>$e->getMessage()],502);}

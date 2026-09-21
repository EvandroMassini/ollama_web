<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require dirname(__DIR__).'/bootstrap.php';

function reply(array $body,int $status=200): never { http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit; }

if($_SERVER['REQUEST_METHOD']==='GET') reply(['ok'=>true,'url'=>$ollamaUrl,'model'=>$ollamaModel,'temperature'=>$temperature,'num_ctx'=>$numCtx,'keep_alive'=>$keepAlive,'runtime_allowed'=>(bool)$config['allow_runtime_settings']]);
if($_SERVER['REQUEST_METHOD']!=='POST') reply(['ok'=>false,'error'=>'Método não permitido.'],405);
$seguranca->verify();
if(!($config['allow_runtime_settings']??false)) reply(['ok'=>false,'error'=>'Alterações pela interface estão desabilitadas.'],403);

try{
    $input=json_decode(file_get_contents('php://input')?:'{}',true,512,JSON_THROW_ON_ERROR);
    $url=rtrim(trim((string)($input['url']??'')),'/');
    $model=trim((string)($input['model']??''));
    $temperature=(float)($input['temperature']??0.4);
    $numCtx=(int)($input['num_ctx']??8192);
    $keepAlive=trim((string)($input['keep_alive']??'30m'));
    $parts=parse_url($url);
    if(!is_array($parts)||!in_array($parts['scheme']??'',['http','https'],true)||empty($parts['host'])) throw new InvalidArgumentException('Informe uma URL HTTP ou HTTPS válida.');
    if(isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])) throw new InvalidArgumentException('A URL não pode conter credenciais, consulta ou fragmento.');
    $port=(int)($parts['port']??(($parts['scheme']??'')==='https'?443:80));
    if(!in_array($port,(array)$config['allowed_ollama_ports'],true)) throw new InvalidArgumentException('A porta informada não está autorizada em config.php.');
    // O modelo pode ficar vazio: após testar a URL, selecionamos o primeiro
    // modelo realmente instalado. Assim, textos de estado da interface nunca
    // são enviados ao Ollama como se fossem nomes de modelos.
    if($model!==''&&!preg_match('/^[a-zA-Z0-9._\/-]+(?::[a-zA-Z0-9._-]+)?$/',$model)) throw new InvalidArgumentException('Nome de modelo inválido.');
    if($temperature<0||$temperature>2)throw new InvalidArgumentException('A temperatura deve ficar entre 0 e 2.');
    if($numCtx<1024||$numCtx>262144)throw new InvalidArgumentException('O contexto deve ficar entre 1.024 e 262.144 tokens.');
    if(!preg_match('/^(0|\d+[smh])$/',$keepAlive))throw new InvalidArgumentException('Use keep-alive como 5m, 1h ou 0.');
    // Testa antes de salvar, evitando deixar a sessão presa a um servidor incorreto.
    $probe=new Ollama($url,$model,15);
    $probeStatus=$probe->status();
    $installedModels=[];
    foreach((array)($probeStatus['models']??[]) as $installed){
        if(!is_array($installed))continue;
        $name=trim((string)($installed['name']??$installed['model']??''));
        if($name!=='')$installedModels[]=$name;
    }
    $installedModels=array_values(array_unique($installedModels));
    if($installedModels===[])throw new RuntimeException('Servidor conectado, mas nenhum modelo está instalado. Instale um modelo no Ollama e tente novamente.');

    $requestedModel=$model;
    $normalizeModel=static fn(string $name): string => str_contains($name,':')?$name:$name.':latest';
    $matchedModel=null;
    if($model!==''){
        foreach($installedModels as $installedModel){
            if($normalizeModel($installedModel)===$normalizeModel($model)){$matchedModel=$installedModel;break;}
        }
    }
    $model=$matchedModel??$installedModels[0];
    $autoSelected=$requestedModel===''||$matchedModel===null;
    $memoria->saveSettings($url,$model,$temperature,$numCtx,$keepAlive);
    $saved=['ollama_url'=>$url,'model'=>$model,'temperature'=>$temperature,'num_ctx'=>$numCtx,'keep_alive'=>$keepAlive];
    $runtimeFile=(string)($config['runtime_settings_file']??'');
    if($runtimeFile!==''){
        $directory=dirname($runtimeFile);
        if(!is_dir($directory)&&!mkdir($directory,0775,true)&&!is_dir($directory))throw new RuntimeException('Não foi possível criar a pasta de configuração.');
        $json=json_encode($saved,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        if(file_put_contents($runtimeFile,$json,LOCK_EX)===false)throw new RuntimeException('A conexão funcionou, mas não foi possível salvar a configuração persistente.');
    }
    // Força a gravação da sessão antes que a interface faça a verificação seguinte.
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    reply(['ok'=>true,'url'=>$url,'model'=>$model,'temperature'=>$temperature,'num_ctx'=>$numCtx,'keep_alive'=>$keepAlive,'model_auto_selected'=>$autoSelected]);
}catch(Throwable $e){reply(['ok'=>false,'error'=>$e->getMessage()],422);}

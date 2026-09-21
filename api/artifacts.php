<?php
declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';

// Downloads são vinculados à sessão. Conhecer apenas o ID não basta em outra sessão.
if($_SERVER['REQUEST_METHOD']==='GET'&&isset($_GET['id'])){
    $item=$artefatos->find((string)$_GET['id']);
    if(!$item){http_response_code(404);exit('Arquivo não encontrado ou expirado.');}
    header('Content-Type: '.$item['mime']);header('Content-Length: '.filesize($item['path']));
    header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($item['name']));
    header('X-Content-Type-Options: nosniff');readfile($item['path']);exit;
}
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if($_SERVER['REQUEST_METHOD']==='GET'){echo json_encode(['ok'=>true,'artifacts'=>$artefatos->all()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if($_SERVER['REQUEST_METHOD']==='DELETE'){$seguranca->verify();$input=json_decode(file_get_contents('php://input')?:'{}',true);$ok=$artefatos->delete((string)($input['id']??''));echo json_encode(['ok'=>$ok,'error'=>$ok?null:'Arquivo não encontrado.'],JSON_UNESCAPED_UNICODE);exit;}
http_response_code(405);echo json_encode(['ok'=>false,'error'=>'Método não permitido.']);

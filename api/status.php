<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require dirname(__DIR__) . '/bootstrap.php';

try {
    $status=['ok'=>true,'url'=>$ollamaUrl]+$ollama->status();
    try { $status['database']=$banco->status(); } catch(Throwable $dbError) { $status['database']=['enabled'=>$banco->enabled(),'online'=>false,'error'=>$dbError->getMessage()]; }
    echo json_encode($status, JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'online' => false, 'error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
}

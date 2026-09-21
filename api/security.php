<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
require dirname(__DIR__).'/bootstrap.php';
echo json_encode(['ok'=>true,'csrf_token'=>$seguranca->token()]);

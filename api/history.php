<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require dirname(__DIR__) . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $seguranca->verify();
    $memoria->clear();
    echo json_encode(['ok' => true]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['ok' => true, 'history' => $memoria->all()], JSON_UNESCAPED_UNICODE);
    exit;
}
http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método não permitido.']);

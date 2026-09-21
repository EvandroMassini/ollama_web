<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__).'/classes/Upload.php';

function respond(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ok' => false, 'error' => 'Método não permitido.'], 405);
}
$seguranca->verify();

try {
    // Mede o ciclo completo: Ollama, ferramentas, banco e arquivos gerados.
    $requestStarted=microtime(true);
    // JSON é usado no chat comum; multipart/form-data é usado quando há anexos.
    $isMultipart=str_starts_with($_SERVER['CONTENT_TYPE']??'','multipart/form-data');
    $input=$isMultipart?$_POST:json_decode(file_get_contents('php://input') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
    $message = is_array($input) ? ($input['message'] ?? '') : '';
    if (!is_string($message)) {
        throw new InvalidArgumentException('Mensagem inválida.');
    }
    $attachments=$isMultipart?(new Upload((array)$config['uploads']))->parse($_FILES['files']??[]):[];
    $answer=$chat->responder($message,$attachments);
    $durationMs=(int)round((microtime(true)-$requestStarted)*1000);
    respond(['ok' => true, 'answer' => $answer, 'trace' => $chat->trace(), 'artifacts'=>$artefatos->created(), 'duration_ms'=>$durationMs]);
} catch (InvalidArgumentException|JsonException $error) {
    respond(['ok' => false, 'error' => $error->getMessage()], 422);
} catch (Throwable $error) {
    error_log('[OllamaWebPHP] ' . $error->getMessage());
    respond(['ok' => false, 'error' => $error->getMessage()], 502);
}

<?php
declare(strict_types=1);

/** Proteções pequenas e sem dependências para a interface web. */
final class Seguranca
{
    private const KEY='ollama_web_csrf';
    public function __construct()
    {
        if(session_status()!==PHP_SESSION_ACTIVE)session_start();
        if(empty($_SESSION[self::KEY]))$_SESSION[self::KEY]=bin2hex(random_bytes(32));
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    }
    public function token(): string{return (string)$_SESSION[self::KEY];}
    public function verify(): void
    {
        $provided=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');
        if(!hash_equals($this->token(),$provided)){http_response_code(403);header('Content-Type: application/json');echo json_encode(['ok'=>false,'error'=>'Token de segurança inválido. Recarregue a página.']);exit;}
    }
}

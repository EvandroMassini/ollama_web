<?php
declare(strict_types=1);

final class Memoria
{
    private const KEY = 'ollama_web_historico';

    public function __construct(private readonly int $limit = 20)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /** @return array<int, array{role:string,content:string}> */
    public function all(): array
    {
        $history = $_SESSION[self::KEY] ?? [];
        return is_array($history) ? $history : [];
    }

    public function add(string $role, string $content): void
    {
        $history = $this->all();
        $history[] = ['role' => $role, 'content' => $content];
        $_SESSION[self::KEY] = array_slice($history, -$this->limit);
    }

    public function clear(): void
    {
        unset($_SESSION[self::KEY]);
    }

    /** Guarda preferências sem editar config.php nem expor dados entre usuários. */
    public function settings(): array
    {
        $settings=$_SESSION['ollama_web_settings']??[];
        return is_array($settings)?$settings:[];
    }

    public function saveSettings(string $url,string $model,float $temperature,int $numCtx,string $keepAlive): void
    {
        $_SESSION['ollama_web_settings']=['ollama_url'=>$url,'model'=>$model,'temperature'=>$temperature,'num_ctx'=>$numCtx,'keep_alive'=>$keepAlive];
    }
}

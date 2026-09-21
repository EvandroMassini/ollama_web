<?php
declare(strict_types=1);

// Configuração privada do Ollama Web PHP. Copie para config.local.php.
return [
    'ollama_url' => 'http://ollama-host.local:11434',
    'model' => 'qwen3.5:9b',
    'database' => [
        'enabled' => true,
        'host' => 'postgres-host.local',
        'port' => 5432,
        'dbname' => 'seu_banco',
        'user' => 'seu_usuario',
        'password' => 'sua_senha',
    ],
];

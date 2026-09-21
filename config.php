<?php
declare(strict_types=1);

$config = [
    'app_name' => 'Ollama Web PHP',
    'ollama_url' => getenv('OLLAMA_URL') ?: 'http://127.0.0.1:11434',
    // Troque pelo nome exibido por: ollama list
    'model' => getenv('OLLAMA_MODEL') ?: 'qwen3.5:9b',
    'generation' => [
        'temperature' => 0.4,
        'num_ctx' => 8192,
        'keep_alive' => '30m',
    ],
    'timeout' => 120,
    'max_history_messages' => 20,
    'max_tool_rounds' => 6,
    'timezone' => 'America/Sao_Paulo',
    // Permite que a interface troque o servidor/modelo apenas para a sessão atual.
    'allow_runtime_settings' => true,
    // Configurações salvas pela interface tornam-se o padrão deste servidor web.
    'runtime_settings_file' => __DIR__ . '/storage/runtime-settings.json',
    // Por segurança, aceite somente HTTP(S) e portas do Ollama em redes confiáveis.
    'allowed_ollama_ports' => [11434, 80, 443],
    'uploads' => [
        'max_files' => 5,
        'max_file_bytes' => 10 * 1024 * 1024,
        'max_text_chars' => 50000,
    ],
    'artifacts' => [
        'directory' => __DIR__ . '/storage/artifacts',
        'ttl_seconds' => 24 * 60 * 60,
        'max_file_bytes' => 5 * 1024 * 1024,
        'max_files_per_zip' => 30,
        'allowed_extensions' => ['txt','md','csv','json','xml','html','css','js','ts','php','py','sql','java','c','cpp','h','yaml','yml','pdf','docx','xlsx','zip'],
    ],
    'system_prompt_file' => __DIR__ . '/prompts/sistema.txt',
    'database' => [
        'enabled' => filter_var(getenv('DB_ENABLED') ?: 'false', FILTER_VALIDATE_BOOL),
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => 5432,
        'dbname' => getenv('DB_NAME') ?: 'seu_banco',
        'user' => getenv('DB_USER') ?: 'postgres',
        'password' => getenv('DB_PASSWORD') ?: '',
        'sslmode' => 'prefer',
        'statement_timeout_ms' => 15000,
        'max_rows' => 200,
    ],
    'log_file' => __DIR__ . '/logs/ollama-web.log',
];

// Configurações privadas podem ser colocadas aqui sem serem versionadas.
$localFile=__DIR__.'/config.local.php';
if(is_file($localFile)) $config=array_replace_recursive($config,require $localFile);
return $config;

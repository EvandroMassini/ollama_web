<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Ollama.php';
require_once __DIR__ . '/classes/Memoria.php';
require_once __DIR__ . '/classes/Prompt.php';
require_once __DIR__ . '/classes/Chat.php';
require_once __DIR__ . '/classes/BancoDados.php';
require_once __DIR__ . '/classes/Ferramenta.php';
require_once __DIR__ . '/classes/Ferramentas.php';
require_once __DIR__ . '/classes/Logger.php';
require_once __DIR__ . '/classes/Artefatos.php';
require_once __DIR__ . '/classes/Seguranca.php';
require_once __DIR__ . '/classes/Ferramentas/BancoEstrutura.php';
require_once __DIR__ . '/classes/Ferramentas/BancoResumo.php';
require_once __DIR__ . '/classes/Ferramentas/BancoContarRegistros.php';
require_once __DIR__ . '/classes/Ferramentas/BancoConsultar.php';
require_once __DIR__ . '/classes/Ferramentas/CriarArquivo.php';

$memoria = new Memoria((int) $config['max_history_messages']);

// Preferências persistentes do servidor são combinadas com eventuais ajustes da sessão.
$persistent=[];
$runtimeFile=(string)($config['runtime_settings_file']??'');
if($runtimeFile!==''&&is_file($runtimeFile)){
    $decoded=json_decode(file_get_contents($runtimeFile)?:'{}',true);
    if(is_array($decoded))$persistent=$decoded;
}
$runtime=array_replace($persistent,$memoria->settings());
$ollamaUrl=(string)($runtime['ollama_url']??$config['ollama_url']);
$ollamaModel=(string)($runtime['model']??$config['model']);
$temperature=(float)($runtime['temperature']??$config['generation']['temperature']);
$numCtx=(int)($runtime['num_ctx']??$config['generation']['num_ctx']);
$keepAlive=(string)($runtime['keep_alive']??$config['generation']['keep_alive']);
$seguranca=new Seguranca();
$ollama = new Ollama($ollamaUrl,$ollamaModel,(int)$config['timeout'],$temperature,$numCtx,$keepAlive);
$banco = new BancoDados((array) $config['database']);
$ferramentas = new Ferramentas();
$artefatos = new Artefatos((array)$config['artifacts']);
$ferramentas->add(new CriarArquivo($artefatos));
if ($banco->enabled()) { $ferramentas->add(new BancoResumo($banco)); $ferramentas->add(new BancoContarRegistros($banco)); $ferramentas->add(new BancoEstrutura($banco)); $ferramentas->add(new BancoConsultar($banco)); }
$logger = new Logger((string) $config['log_file']);
date_default_timezone_set((string)($config['timezone']??'America/Sao_Paulo'));
$chat = new Chat(
    $ollama,
    $memoria,
    Prompt::system((string)$config['system_prompt_file']),
    $ferramentas,
    $logger,
    (int)$config['max_tool_rounds']
);

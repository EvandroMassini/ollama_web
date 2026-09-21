<?php
declare(strict_types=1);

final class Ollama
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $timeout = 120,
        private readonly float $temperature = 0.4,
        private readonly int $numCtx = 8192,
        private readonly string $keepAlive = '30m'
    ) {}

    /**
     * Envia a conversa ao Ollama. $messages pode conter o campo "images",
     * usado por modelos multimodais como LLaVA e Gemma 3.
     *
     * @param array<int,array<string,mixed>> $messages
     * @param array<int,array<string,mixed>> $tools
     * @return array<string,mixed>
     */
    public function chat(array $messages, array $tools = []): array
    {
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => false,
            'keep_alive' => $this->keepAlive,
            'options' => ['temperature' => $this->temperature, 'num_ctx'=>$this->numCtx],
        ];
        if ($tools !== []) $payload['tools'] = $tools;
        $data = $this->request('/api/chat', $payload);
        $message = $data['message'] ?? null;
        if (!is_array($message)) throw new RuntimeException('O Ollama respondeu sem uma mensagem válida.');
        return $message;
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $data = $this->request('/api/tags', null);
        $models = is_array($data['models'] ?? null) ? $data['models'] : [];
        return ['online' => true, 'configured_model' => $this->model, 'models' => $models];
    }

    /** Solicita o download de um modelo no servidor remoto. */
    public function pull(string $model): array
    {
        return $this->request('/api/pull', ['model'=>$model,'stream'=>false], max($this->timeout, 3600));
    }

    /** Carrega o modelo na memória e o mantém pelo período indicado. */
    public function load(string $model, string $keepAlive='30m'): array
    {
        return $this->request('/api/generate', ['model'=>$model,'prompt'=>'','stream'=>false,'keep_alive'=>$keepAlive]);
    }

    /** Descarrega o modelo da memória sem apagar seus arquivos. */
    public function unload(string $model): array
    {
        return $this->request('/api/generate', ['model'=>$model,'prompt'=>'','stream'=>false,'keep_alive'=>0]);
    }

    /** Retorna os modelos atualmente carregados em CPU/GPU. */
    public function running(): array { return $this->request('/api/ps', null); }

    /** @return array<string, mixed> */
    private function request(string $path, ?array $payload, ?int $timeout=null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('A extensão cURL do PHP não está habilitada.');
        }
        $ch = curl_init(rtrim($this->baseUrl, '/') . $path);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeout ?? $this->timeout,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ];
        if ($payload !== null) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Não foi possível acessar o Ollama: ' . $error);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $data = json_decode($body, true);
        if ($status >= 400) {
            $message = is_array($data) ? ($data['error'] ?? $body) : $body;
            throw new RuntimeException('Erro do Ollama (' . $status . '): ' . $message);
        }
        if (!is_array($data)) {
            throw new RuntimeException('Resposta inválida recebida do Ollama.');
        }
        return $data;
    }
}

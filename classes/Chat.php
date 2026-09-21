<?php
declare(strict_types=1);

final class Chat
{
    /** @var array<int,array<string,mixed>> */
    private array $trace=[];
    public function __construct(
        private readonly Ollama $ollama,
        private readonly Memoria $memoria,
        private readonly string $systemPrompt,
        private readonly Ferramentas $ferramentas,
        private readonly Logger $logger,
        private readonly int $maxToolRounds = 6
    ) {}

    /**
     * @param array<int,array{name:string,type:string,text?:string,base64?:string}> $attachments
     */
    public function responder(string $question, array $attachments=[]): string
    {
        $this->trace=[];
        $question = trim($question);
        if ($question === '') {
            throw new InvalidArgumentException('Digite uma pergunta.');
        }
        if (mb_strlen($question) > 8000) {
            throw new InvalidArgumentException('A pergunta é muito longa (máximo: 8.000 caracteres).');
        }
        $this->memoria->add('user', $question);
        $messages = array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt]],
            $this->memoria->all()
        );
        // O PHP não tenta descobrir a intenção. O Ollama recebe as ferramentas
        // registradas e decide se deve responder ou solicitar uma delas.
        $availableTools=$this->ferramentas->definitions();
        if($attachments!==[]){
            $context=[];$images=[];
            foreach($attachments as $file){
                if(isset($file['text'])) $context[]="ARQUIVO: {$file['name']}\n{$file['text']}";
                if(isset($file['base64'])) $images[]=$file['base64'];
            }
            $last=array_key_last($messages);
            if($context!==[]) $messages[$last]['content'].="\n\nCONTEÚDO DOS ANEXOS:\n".implode("\n\n---\n\n",$context);
            if($images!==[]) $messages[$last]['images']=$images;
        }
        try {
            $executedCalls=[];
            for ($round=0; $round<$this->maxToolRounds; $round++) {
                $assistant = $this->ollama->chat($messages, $availableTools);
                $calls = $assistant['tool_calls'] ?? [];

                /*
                 * Alguns modelos imprimem a chamada como JSON no campo content,
                 * em vez de preencher tool_calls. Convertemos somente o formato
                 * exato {"name":"...","arguments":{...}} e apenas para uma
                 * ferramenta realmente registrada. Assim o JSON técnico nunca
                 * precisa aparecer na conversa.
                 */
                if((!is_array($calls)||$calls===[]) && is_string($assistant['content']??null)){
                    $legacy=json_decode(trim((string)$assistant['content']),true);
                    if(is_array($legacy)&&isset($legacy['name'])&&array_key_exists('arguments',$legacy)){
                        $legacyName=(string)$legacy['name'];
                        if($this->ferramentas->has($legacyName)){
                            $calls=[['function'=>['name'=>$legacyName,'arguments'=>$legacy['arguments']]]];
                        }else{
                            // O modelo inventou ou escolheu uma ferramenta que não
                            // está disponível. Pedimos uma resposta comum, sem tools.
                            $correction=$messages;
                            $correction[]=['role'=>'user','content'=>'Sua tentativa de ferramenta não é aplicável a esta pergunta. Responda diretamente em linguagem natural, sem JSON e sem chamar ferramentas. Se não souber um dado sobre seu próprio treinamento, diga isso com honestidade.'];
                            $assistant=$this->ollama->chat($correction,[]);
                            $calls=[];
                        }
                    }
                }
                if (!is_array($calls) || $calls === []) {
                    $messages[] = $assistant;
                    $answer = trim((string)($assistant['content'] ?? ''));
                    if ($answer === '') throw new RuntimeException('O modelo não produziu uma resposta final.');
                    $this->memoria->add('assistant', $answer);
                    return $answer;
                }
                // Algumas versões/modelos retornam arguments como texto JSON. A API
                // do Ollama exige um objeto quando a mensagem é reenviada no ciclo.
                foreach ($calls as $index => $call) {
                    $rawArgs=$call['function']['arguments']??[];
                    if (is_string($rawArgs)) {
                        try { $rawArgs=json_decode($rawArgs,true,512,JSON_THROW_ON_ERROR); }
                        catch(JsonException $error) { throw new RuntimeException('O modelo gerou argumentos de ferramenta inválidos: '.$error->getMessage()); }
                    }
                    if (!is_array($rawArgs)) $rawArgs=[];
                    $calls[$index]['function']['arguments']=$rawArgs;
                }
                // Não reenviamos tool_calls no histórico. Algumas combinações de
                // Ollama/Qwen falham ao reinterpretar essa estrutura na rodada
                // seguinte (HTTP 400, chave de fechamento não encontrada).
                // O resultado será incorporado abaixo como contexto textual.
                foreach ($calls as $call) {
                    $name=(string)($call['function']['name']??'');
                    $args=$call['function']['arguments']??[];
                    if (!is_array($args)) $args=[];
                    $signature=$name.':'.json_encode($args,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                    if(isset($executedCalls[$signature])){
                        $this->logger->write('tool_loop_prevented',['tool'=>$name]);
                        $final=$this->ollama->chat(array_merge($messages,[['role'=>'user','content'=>'A chamada solicitada já foi executada. Não use ferramentas novamente; responda agora à pergunta original com os resultados disponíveis.']]),[]);
                        $answer=trim((string)($final['content']??''));
                        if($answer==='') throw new RuntimeException('O modelo repetiu uma ferramenta e não produziu a resposta final.');
                        $this->memoria->add('assistant',$answer);
                        return $answer;
                    }
                    $executedCalls[$signature]=true;
                    $started=microtime(true);
                    try {
                        $result=$this->ferramentas->execute($name,$args);
                        $elapsed=(int)round((microtime(true)-$started)*1000);
                        $rows=$result['count']??$result['table_count']??null;
                        $this->trace[]=['tool'=>$name,'status'=>'success','duration_ms'=>$elapsed,'rows'=>$rows];
                        $this->logger->write('tool_success',['tool'=>$name,'arguments'=>$args,'rows'=>$rows,'duration_ms'=>$elapsed]);
                    }
                    catch(Throwable $toolError) {
                        $elapsed=(int)round((microtime(true)-$started)*1000);
                        $result=['error'=>$toolError->getMessage()];
                        $this->trace[]=['tool'=>$name,'status'=>'error','duration_ms'=>$elapsed,'error'=>$toolError->getMessage()];
                        $this->logger->write('tool_error',['tool'=>$name,'error'=>$toolError->getMessage(),'duration_ms'=>$elapsed]);
                    }
                    $resultJson=json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
                    $messages[]=[
                        'role'=>'user',
                        'content'=>"RESULTADO CONFIÁVEL DA FERRAMENTA {$name} (não é uma nova solicitação do usuário):\n{$resultJson}\nContinue atendendo à pergunta original. Não repita esta ferramenta. Se os dados já forem suficientes, responda agora; somente chame outra ferramenta se faltar informação indispensável."
                    ];
                }
            }
            throw new RuntimeException('O chat excedeu o limite de etapas de ferramentas.');
        } catch (Throwable $error) {
            // Evita manter no contexto uma pergunta que não chegou a ser respondida.
            $history = $this->memoria->all();
            $this->memoria->clear();
            foreach (array_slice($history, 0, -1) as $message) {
                $this->memoria->add($message['role'], $message['content']);
            }
            throw $error;
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function trace(): array { return $this->trace; }

}

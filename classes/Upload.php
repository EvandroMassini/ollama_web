<?php
declare(strict_types=1);

/**
 * Converte arquivos enviados pelo navegador para formatos compreendidos pelo Ollama.
 * Imagens viram base64; arquivos textuais viram contexto. Nenhum arquivo é salvo em disco.
 */
final class Upload
{
    public function __construct(private readonly array $config) {}

    /** @return array<int,array{name:string,type:string,text?:string,base64?:string}> */
    public function parse(array $files): array
    {
        if(!isset($files['name'])) return [];
        $names=is_array($files['name'])?$files['name']:[$files['name']];
        $tmp=is_array($files['tmp_name'])?$files['tmp_name']:[$files['tmp_name']];
        $errors=is_array($files['error'])?$files['error']:[$files['error']];
        $sizes=is_array($files['size'])?$files['size']:[$files['size']];
        $maxFiles=(int)($this->config['max_files']??5);
        if(count($names)>$maxFiles) throw new InvalidArgumentException("Envie no máximo {$maxFiles} arquivos.");
        $result=[];
        foreach($names as $i=>$name){
            if(($errors[$i]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) continue;
            if(($errors[$i]??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK) throw new RuntimeException("Falha ao receber {$name}.");
            if(($sizes[$i]??0)>(int)($this->config['max_file_bytes']??10485760)) throw new InvalidArgumentException("O arquivo {$name} excede o limite permitido.");
            $path=(string)$tmp[$i];
            // fileinfo vem habilitado no XAMPP; o fallback mantém o erro compreensível.
            $mime=class_exists('finfo')?((new finfo(FILEINFO_MIME_TYPE))->file($path)?:'application/octet-stream'):'application/octet-stream';
            if(str_starts_with($mime,'image/')){
                if(!in_array($mime,['image/jpeg','image/png','image/webp','image/gif'],true)) throw new InvalidArgumentException("Formato de imagem não suportado: {$mime}.");
                $bytes=file_get_contents($path);
                if($bytes===false) throw new RuntimeException("Não foi possível ler {$name}.");
                $result[]=['name'=>(string)$name,'type'=>$mime,'base64'=>base64_encode($bytes)];
                continue;
            }
            if($this->isText($mime,(string)$name)){
                $text=file_get_contents($path);
                if($text===false) throw new RuntimeException("Não foi possível ler {$name}.");
                $limit=(int)($this->config['max_text_chars']??50000);
                $result[]=['name'=>(string)$name,'type'=>$mime,'text'=>mb_substr($text,0,$limit)];
                continue;
            }
            throw new InvalidArgumentException("{$name}: este tipo não pode ser enviado diretamente ao Ollama. Converta PDF/Word para TXT ou Markdown; imagens PNG/JPG/WebP são aceitas.");
        }
        return $result;
    }

    private function isText(string $mime,string $name): bool
    {
        if(str_starts_with($mime,'text/')) return true;
        $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        return in_array($ext,['txt','md','csv','json','xml','yaml','yml','log','php','js','ts','css','html','sql','py','java','c','cpp','h'],true);
    }
}

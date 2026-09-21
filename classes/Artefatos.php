<?php
declare(strict_types=1);

/** Cria downloads sem permitir que o modelo escolha caminhos no servidor. */
final class Artefatos
{
    private const KEY='ollama_web_artefatos';
    private array $created=[];
    public function __construct(private readonly array $config){if(session_status()!==PHP_SESSION_ACTIVE)session_start();$dir=$this->dir();if(!is_dir($dir)&&!mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('Não foi possível criar a pasta de artefatos.');$this->cleanup();}

    public function create(string $filename,string $content,array $files=[]): array
    {
        $filename=$this->safeName($filename);$ext=strtolower(pathinfo($filename,PATHINFO_EXTENSION));
        if(!in_array($ext,(array)$this->config['allowed_extensions'],true))throw new InvalidArgumentException("Extensão .{$ext} não autorizada.");
        $id=bin2hex(random_bytes(16));$path=$this->dir().DIRECTORY_SEPARATOR.$id.'.'.$ext;
        match($ext){'pdf'=>$this->pdf($path,$content),'docx'=>$this->docx($path,$content),'xlsx'=>$this->xlsx($path,$content),'zip'=>$this->zip($path,$files),default=>$this->write($path,$content)};
        $size=filesize($path)?:0;if($size>(int)$this->config['max_file_bytes']){@unlink($path);throw new RuntimeException('O arquivo excedeu o limite permitido.');}
        $item=['id'=>$id,'name'=>$filename,'extension'=>$ext,'mime'=>$this->mime($ext),'size'=>$size,'created_at'=>time(),'expires_at'=>time()+(int)$this->config['ttl_seconds'],'url'=>'api/artifacts.php?id='.$id];
        $_SESSION[self::KEY][$id]=$item;$this->created[]=$item;return $item;
    }
    public function created(): array{return $this->created;}
    public function all(): array{return array_values($_SESSION[self::KEY]??[]);}
    public function find(string $id): ?array{$item=$_SESSION[self::KEY][$id]??null;if(!is_array($item)||($item['expires_at']??0)<time())return null;$path=$this->dir().DIRECTORY_SEPARATOR.$id.'.'.$item['extension'];return is_file($path)?$item+['path'=>$path]:null;}
    public function delete(string $id): bool{$item=$this->find($id);if(!$item)return false;@unlink($item['path']);unset($_SESSION[self::KEY][$id]);return true;}

    private function safeName(string $name): string{$name=basename(str_replace('\\','/',$name));$name=preg_replace('/[^\pL\pN._ -]/u','_',$name)??'';$name=trim($name," .\t\n\r\0\x0B");if($name===''||!str_contains($name,'.'))throw new InvalidArgumentException('Informe um nome com extensão.');return mb_substr($name,0,120);}
    private function write(string $path,string $content): void{if(strlen($content)>(int)$this->config['max_file_bytes'])throw new InvalidArgumentException('Conteúdo muito grande.');if(file_put_contents($path,$content,LOCK_EX)===false)throw new RuntimeException('Não foi possível gravar o arquivo.');}

    private function zip(string $path,array $files): void
    {
        $this->requireZip();if($files===[])throw new InvalidArgumentException('Um ZIP precisa conter arquivos.');if(count($files)>(int)$this->config['max_files_per_zip'])throw new InvalidArgumentException('Muitos arquivos no ZIP.');
        $zip=new ZipArchive();$zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE);foreach($files as $file)$zip->addFromString($this->safeName((string)($file['name']??'')),(string)($file['content']??''));$zip->close();
    }
    private function docx(string $path,string $content): void
    {
        $this->requireZip();$zip=new ZipArchive();$zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml','<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $body='';foreach(preg_split('/\R/u',$content)?:[] as $line)$body.='<w:p><w:r><w:t xml:space="preserve">'.htmlspecialchars($line,ENT_XML1|ENT_QUOTES,'UTF-8').'</w:t></w:r></w:p>';
        $zip->addFromString('word/document.xml','<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'<w:sectPr/></w:body></w:document>');$zip->close();
    }
    private function xlsx(string $path,string $content): void
    {
        $this->requireZip();$rows=[];$h=fopen('php://temp','r+');fwrite($h,$content);rewind($h);while(($row=fgetcsv($h,0,','))!==false)$rows[]=$row;fclose($h);$sheet='';
        foreach($rows as $r=>$row){$cells='';foreach($row as $c=>$value){$ref=$this->column($c+1).($r+1);$cells.='<c r="'.$ref.'" t="inlineStr"><is><t>'.htmlspecialchars((string)$value,ENT_XML1|ENT_QUOTES,'UTF-8').'</t></is></c>';}$sheet.='<row r="'.($r+1).'">'.$cells.'</row>';}
        $zip=new ZipArchive();$zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE);$zip->addFromString('[Content_Types].xml','<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');$zip->addFromString('_rels/.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');$zip->addFromString('xl/workbook.xml','<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Dados" sheetId="1" r:id="rId1"/></sheets></workbook>');$zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');$zip->addFromString('xl/worksheets/sheet1.xml','<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheet.'</sheetData></worksheet>');$zip->close();
    }
    private function pdf(string $path,string $content): void
    {
        $lines=array_slice(preg_split('/\R/u',wordwrap($content,90,"\n",true))?:[],0,55);$stream="BT /F1 10 Tf 45 800 Td 14 TL\n";foreach($lines as $line){$ascii=iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$line);$safe=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],(string)$ascii);$stream.='('.$safe.") Tj T*\n";}$stream.='ET';
        $objects=["1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj","2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj","3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Resources<</Font<</F1 4 0 R>>>>/Contents 5 0 R>>endobj","4 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj","5 0 obj<</Length ".strlen($stream).">>stream\n{$stream}\nendstream endobj"];$pdf="%PDF-1.4\n";$offset=[0];foreach($objects as $o){$offset[]=strlen($pdf);$pdf.=$o."\n";}$xref=strlen($pdf);$pdf.="xref\n0 6\n0000000000 65535 f \n";for($i=1;$i<=5;$i++)$pdf.=sprintf('%010d 00000 n ',$offset[$i])."\n";$pdf.="trailer<</Size 6/Root 1 0 R>>\nstartxref\n{$xref}\n%%EOF";$this->write($path,$pdf);
    }
    private function requireZip(): void{if(!class_exists('ZipArchive'))throw new RuntimeException('Habilite extension=zip no php.ini para este formato.');}
    private function column(int $n): string{$s='';while($n>0){$n--;$s=chr(65+$n%26).$s;$n=intdiv($n,26);}return $s;}
    private function dir(): string{return rtrim((string)$this->config['directory'],'/\\');}
    private function mime(string $e): string{return match($e){'pdf'=>'application/pdf','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','zip'=>'application/zip','csv'=>'text/csv','json'=>'application/json','html'=>'text/html',default=>'text/plain'};}
    private function cleanup(): void{foreach($_SESSION[self::KEY]??[] as $id=>$item){if(($item['expires_at']??0)<time()){$path=$this->dir().DIRECTORY_SEPARATOR.$id.'.'.($item['extension']??'');if(is_file($path))@unlink($path);unset($_SESSION[self::KEY][$id]);}}}
}

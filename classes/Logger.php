<?php
declare(strict_types=1);
final class Logger
{
    public function __construct(private readonly string $file) {}
    /** @param array<string,mixed> $context */
    public function write(string $event, array $context=[]): void
    {
        $dir=dirname($this->file); if(!is_dir($dir)) @mkdir($dir,0775,true);
        $line=json_encode(['time'=>date(DATE_ATOM),'event'=>$event,'context'=>$context],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        @file_put_contents($this->file,$line.PHP_EOL,FILE_APPEND|LOCK_EX);
    }
}

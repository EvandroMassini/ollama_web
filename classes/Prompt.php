<?php
declare(strict_types=1);

final class Prompt
{
    public static function system(string $file): string
    {
        if (!is_file($file)) {
            throw new RuntimeException('Arquivo de prompt do sistema não encontrado.');
        }
        $content = file_get_contents($file);
        if ($content === false) {
            throw new RuntimeException('Não foi possível ler o prompt do sistema.');
        }
        return trim($content);
    }
}

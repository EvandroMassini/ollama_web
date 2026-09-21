<?php
declare(strict_types=1);

interface Ferramenta
{
    /** @return array<string,mixed> */
    public function definition(): array;
    /** @param array<string,mixed> $arguments @return array<string,mixed> */
    public function execute(array $arguments): array;
}

<?php
declare(strict_types=1);

final class Ferramentas
{
    /** @var array<string,Ferramenta> */
    private array $items = [];
    public function add(Ferramenta $tool): void { $this->items[$tool->definition()['function']['name']] = $tool; }
    /** @return array<int,array<string,mixed>> */
    public function definitions(): array { return array_values(array_map(fn($t) => $t->definition(), $this->items)); }

    /** Permite ao chat validar chamadas legadas antes de tentar executá-las. */
    public function has(string $name): bool { return isset($this->items[$name]); }
    /** @param array<string,mixed> $arguments @return array<string,mixed> */
    public function execute(string $name, array $arguments): array
    {
        if (!isset($this->items[$name])) throw new RuntimeException("Ferramenta desconhecida: {$name}");
        return $this->items[$name]->execute($arguments);
    }
}

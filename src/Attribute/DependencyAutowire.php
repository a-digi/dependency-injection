<?php

declare (strict_types = 1);

namespace AriAva\DependencyInjection\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class DependencyAutowire
{
    public function __construct(private string|null $qualifiedName = null, private array $customConstructorArguments = [])
    {
    }

    public function hasQualifiedName(): bool
    {
        return null !== $this->qualifiedName;
    }

    public function getQualifiedName(): ?string
    {
        return $this->qualifiedName;
    }

    public function getCustomConstructorArguments(): array
    {
        return $this->customConstructorArguments;
    }
}
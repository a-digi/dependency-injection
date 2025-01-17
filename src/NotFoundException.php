<?php
declare(strict_types=1);

namespace AriAva\DependencyInjection;

final class NotFoundException extends \Exception
{
    public function __construct(string $id)
    {
        parent::__construct("Dependency Container: Can not find the entry with {$id}");
    }
}

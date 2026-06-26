<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Services;

trait HasDisk
{
    private string $_disk;

    public function disk(?string $disk = null): string|static
    {
        if ($disk) {
            $this->_disk = $disk;

            return $this;
        }

        return $this->_disk ??= config('filesystems.default');
    }
}

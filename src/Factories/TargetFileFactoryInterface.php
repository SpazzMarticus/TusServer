<?php

namespace SpazzMarticus\Tus\Factories;

use SplFileInfo;
use Ramsey\Uuid\UuidInterface;

interface TargetFileFactoryInterface
{

    public function generateFilename(UuidInterface $uuid, array $metadata): SplFileInfo;
}

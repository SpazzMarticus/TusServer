<?php

namespace SpazzMarticus\Tus\Factories;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\UuidInterface;
use Ramsey\Uuid\Uuid;

abstract class AbstractFilenameFactoryTest extends TestCase
{

    protected string $directory;
    protected UuidInterface $uuid;

    protected function setUp(): void
    {
        $this->setupUuid();
        $this->setupFilesystem();
    }

    protected function setupFilesystem(): void
    {
        vfsStream::setup('root', null, [
            'uploads' => []
        ]);

        $this->directory = vfsStream::url('root/uploads/');
    }

    protected function setupUuid(): void
    {
        $this->uuid = Uuid::fromString('d9af80ad-44a1-445b-86dc-88b42a880d35');
    }
}

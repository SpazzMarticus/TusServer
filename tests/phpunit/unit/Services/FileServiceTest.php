<?php

namespace SpazzMarticus\Tus\Services;

use Mockery;
use Psr\Http\Message\StreamInterface;
use SpazzMarticus\Tus\Exceptions\ConflictException;
use SpazzMarticus\Tus\Exceptions\RuntimeException;
use SpazzMarticus\Tus\Services\FileService;
use SplFileInfo;
use SplFileObject;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use RuntimeException as GlobalRuntimeException;

class FileServiceTest extends \PHPUnit\Framework\TestCase
{
    protected FileService $fileService;
    protected vfsStreamDirectory $fsRoot;
    protected vfsStreamDirectory $fsDir;

    protected function setUp(): void
    {
        $this->fileService = new FileService();
        $this->fsRoot = vfsStream::setup('root', null);
        $this->fsDir = vfsStream::newDirectory('files');

        $this->fsRoot->addChild($this->fsDir);
    }

    public function testInstance(): void
    {
        $this->assertSame(__FILE__, $this->fileService->instance(__FILE__)->getPathname());
    }

    protected function getTargetFile(): SplFileInfo
    {
        return $this->fileService->instance(vfsStream::url('root/files/target.file'));
    }

    /**
     * @depends testInstance
     */
    public function testCreateSuccess(): void
    {
        $file = $this->getTargetFile();

        $this->assertFalse($this->fileService->exists($file));
        $this->fileService->create($file);
        $this->assertTrue($this->fileService->exists($file));
    }

    /**
     * @depends testInstance
     * @depends testCreateSuccess
     */
    public function testCreateNoOverwrite(): void
    {
        $this->fsDir->addChild(vfsStream::newFile('target.file'));

        $file = $this->getTargetFile();

        $this->assertTrue($this->fileService->exists($file));

        $this->expectException(RuntimeException::class);
        $this->fileService->create($file);
    }

    /**
     * @depends testInstance
     */
    public function testCreateFailure(): void
    {
        $this->fsDir->chmod(0000);
        $file = $this->getTargetFile();

        $this->assertFalse($this->fileService->exists($file));

        $this->expectException(RuntimeException::class);
        $this->fileService->create($file);
    }

    /**
     * @depends testInstance
     * @depends testCreateSuccess
     */
    public function testDeleteSuccess(): void
    {
        $this->fsDir->addChild(vfsStream::newFile('target.file')->withContent('1234567'));
        $file = $this->getTargetFile();

        $this->assertTrue($this->fileService->exists($file));
        $this->fileService->delete($file);
        $this->assertFalse($this->fileService->exists($file));
    }

    /**
     * @depends testInstance
     * @depends testCreateSuccess
     */
    public function testDeleteFailure(): void
    {
        $this->fsDir->addChild(vfsStream::newFile('target.file')->withContent('1234567'));
        /**
         * vfsStream author mikey179:
         * ... In short: whether you can delete a file/directory doesn't depend on the rights you have for the file/directory you want to delete, but on the rights of the directory the file is in. ...
         * @see https://github.com/bovigo/vfsStream/issues/166#issuecomment-375649341
         */
        $this->fsDir->chmod(0000);
        $file = $this->getTargetFile();

        $this->assertTrue($this->fileService->exists($file));

        $this->expectException(RuntimeException::class);
        $this->fileService->delete($file);
    }

    protected function mockStream(array $chunks): StreamInterface
    {
        $stream = Mockery::mock(StreamInterface::class);

        $count = count($chunks);
        $eof = array_fill(0, $count, false);
        $eof[] = true;

        $stream->expects('eof')
            ->times($count + 1)
            ->andReturn(...$eof);

        $stream->expects('read')
            ->times($count)
            ->andReturn(...$chunks);

        return $stream;
    }

    protected function getTargetHandle(): SplFileObject
    {
        $targetFile = $this->getTargetFile();

        $this->fileService->create($targetFile);
        return $this->fileService->open($targetFile);
    }

    protected function chunkString(string $string, int $chunkSize): array
    {
        /**
         * Chunk if chunk size > 0
         */
        return $chunkSize > 0 ? str_split($string, $chunkSize) : [$string];
    }

    /**
     * @depends testInstance
     * @depends testCreateSuccess
     * @dataProvider providerCopyFromStream
     */
    public function testCopyFromStream(string $content, int $chunkSize): void
    {
        $stream = $this->mockStream($this->chunkString($content, $chunkSize));

        $targetHandle = $this->getTargetHandle();

        $this->fileService->setChunkSize($chunkSize);
        $bytesTransfered = $this->fileService->copyFromStream($targetHandle, $stream);

        $this->assertSame(strlen($content), $bytesTransfered);
        $this->assertSame($bytesTransfered, $this->fileService->size($targetHandle));
        $this->assertSame($content, file_get_contents($targetHandle->getPathname()));
    }

    public function providerCopyFromStream(): array
    {
        return [
            /**
             * Negative numbers...
             */
            [
                '123456789',
                -123
            ],
            /**
             * and zero will result in reading whole file at once
             */
            [
                '123456789',
                0
            ],
            [
                '123456789',
                1
            ],
            [
                '123456789',
                3
            ],
            [
                '123456789',
                2 ^ 10
            ],
            [
                /**
                 * @see http://www.gutenberg.org/cache/epub/61540/pg61540.txt
                 */
                <<<EOT
                He drew back mistrustfully. Then he looked around the room, found
                another gun, unloaded it, and handed it to me. "Go ahead," he said.

                It was a lousy job. I was in a state and in a hurry and the sweat
                running down my forehead and dripping off my eyebrows didn't help any.
                The workshop wasn't too well equipped, either, and I hate working from
                my head. I like a nice diagram to look at.

                But I made it somehow, very crudely, replacing one hand by the chamber
                and barrel and attaching the trigger so that it would be worked by the
                same nerve currents as actuated the finger movements to fire a separate
                gun.

                The android loaded himself awkwardly. I stood aside, and Quinby
                tossed up the disk. You never saw a prettier piece of instantaneous
                trap-shooting. The android stretched his face into that very rare
                thing, a robot grin, and expressed himself in pungently jubilant
                military language.

                "You like it?" Quinby asked.
EOT,
                32
            ]
        ];
    }

    /**
     * @depends testInstance
     * @depends testCopyFromStream
     */
    public function testCopyFromStreamSizeLimit(): void
    {
        $content = '01020304050607080910';
        $chunkSize = 2;
        $sizeLimit = 15;

        $stream = $this->mockStream($this->chunkString($content, $chunkSize));

        $targetHandle = $this->getTargetHandle();

        $this->fileService->setChunkSize($chunkSize);

        $this->expectException(ConflictException::class);
        $this->fileService->copyFromStream($targetHandle, $stream, $sizeLimit);
    }

    /**
     * @depends testInstance
     * @depends testCopyFromStream
     */
    public function testCopyFromStreamChunkSize(): void
    {
        $content = '01020304050607080910';
        $chunkSize = 0;

        $stream = $this->mockStream($this->chunkString($content, $chunkSize + 1));

        $targetHandle = $this->getTargetHandle();

        $this->fileService->setChunkSize($chunkSize);
        $bytesTransfered = $this->fileService->copyFromStream($targetHandle, $stream);

        $this->assertSame(strlen($content), $bytesTransfered);
        $this->assertSame($bytesTransfered, $this->fileService->size($targetHandle));
        $this->assertSame($content, file_get_contents($targetHandle->getPathname()));
    }
    /**
     * @depends testInstance
     * @depends testCopyFromStream
     */
    public function testCopyFromStreamThrowingException(): void
    {
        $stream = Mockery::mock(StreamInterface::class);

        $stream->expects('eof')
            ->once()
            ->andReturn(false);

        $stream->expects('read')
            ->once()
            ->andThrow(new GlobalRuntimeException("Test-Exception"));

        $targetHandle = $this->getTargetHandle();

        $this->expectException(RuntimeException::class);
        $this->fileService->copyFromStream($targetHandle, $stream);
    }
    /**
     * @depends testInstance
     * @depends testCreateSuccess
     * @depends testCopyFromStream
     */
    public function testCopyFromStreamWritingThrowsException(): void
    {
        $stream = $this->mockStream(['1234','5678']);

        $file = $this->getTargetFile();

        $this->fileService->create($file);

        $targetHandle = Mockery::mock(SplFileObject::class . '[fwrite]', [$file->getPathname()]);

        $targetHandle->expects('fwrite')
            ->once()
            ->andReturn(0);

        $this->expectException(RuntimeException::class);
        $this->fileService->copyFromStream($targetHandle, $stream);
    }

    /**
     * @depends testInstance
     * @depends testCreateSuccess
     * @depends testCopyFromStream
     */
    public function testCopyFromStreamFlushingThrowsException(): void
    {
        $stream = $this->mockStream(['1234','5678']);

        $file = $this->getTargetFile();

        $this->fileService->create($file);

        $targetHandle = Mockery::mock(SplFileObject::class . '[fwrite,fflush]', [$file->getPathname()]);

        $targetHandle->expects('fwrite')
        ->once()
        ->andReturn(4);

        $targetHandle->expects('fflush')
            ->once()
            ->andReturn(false);

        $this->expectException(RuntimeException::class);
        $this->fileService->copyFromStream($targetHandle, $stream);
    }


    /**
     * @depends testInstance
     * @depends testCreateSuccess
     */
    public function testPointSuccess(): void
    {
        $targetHandle = $this->getTargetHandle();
        $targetHandle->fwrite('0000-0000-0000-0000-0000');
        $this->fileService->point($targetHandle, 10);
        $targetHandle->fwrite('4711');

        $this->assertSame('0000-0000-4711-0000-0000', file_get_contents($targetHandle->getPathname()));
    }

    /**
     * @depends testInstance
     * @depends testCreateSuccess
     * @depends testCopyFromStream
     */
    public function testPointFailure(): void
    {
        $targetHandle = $this->getTargetHandle();

        $this->expectException(RuntimeException::class);
        $this->fileService->point($targetHandle, -1000);
    }
}

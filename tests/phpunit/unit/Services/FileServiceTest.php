<?php

declare(strict_types=1);

namespace SpazzMarticus\Tus\Services;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use RuntimeException as GlobalRuntimeException;
use SpazzMarticus\Tus\Exceptions\ConflictException;
use SpazzMarticus\Tus\Exceptions\RuntimeException;
use SplFileInfo;
use SplFileObject;

class FileServiceTest extends TestCase
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
        self::assertSame(__FILE__, $this->fileService->instance(__FILE__)->getPathname());
    }

    protected function getTargetFile(): SplFileInfo
    {
        return $this->fileService->instance(vfsStream::url('root/files/target.file'));
    }

    #[Depends('testInstance')]
    public function testCreateSuccess(): void
    {
        $file = $this->getTargetFile();

        self::assertFalse($this->fileService->exists($file));
        $this->fileService->create($file);
        self::assertTrue($this->fileService->exists($file));
    }

    #[Depends('testInstance')]
    #[Depends('testCreateSuccess')]
    public function testCreateNoOverwrite(): void
    {
        $this->fsDir->addChild(vfsStream::newFile('target.file'));

        $file = $this->getTargetFile();

        self::assertTrue($this->fileService->exists($file));

        $this->expectException(RuntimeException::class);
        $this->fileService->create($file);
    }

    #[Depends('testInstance')]
    public function testCreateFailure(): void
    {
        $this->fsDir->chmod(0o000);
        $file = $this->getTargetFile();

        self::assertFalse($this->fileService->exists($file));

        $this->expectException(RuntimeException::class);
        $this->fileService->create($file);
    }

    #[Depends('testInstance')]
    #[Depends('testCreateSuccess')]
    public function testDeleteSuccess(): void
    {
        $this->fsDir->addChild(vfsStream::newFile('target.file')->withContent('1234567'));
        $file = $this->getTargetFile();

        self::assertTrue($this->fileService->exists($file));
        $this->fileService->delete($file);
        self::assertFalse($this->fileService->exists($file));
    }

    #[Depends('testInstance')]
    #[Depends('testCreateSuccess')]
    public function testDeleteFailure(): void
    {
        $this->fsDir->addChild(vfsStream::newFile('target.file')->withContent('1234567'));
        /**
         * vfsStream author mikey179:
         * ... In short: whether you can delete a file/directory doesn't depend on the rights you have for the file/directory you want to delete, but on the rights of the directory the file is in. ...
         * @see https://github.com/bovigo/vfsStream/issues/166#issuecomment-375649341
         */
        $this->fsDir->chmod(0o000);

        $file = $this->getTargetFile();

        self::assertTrue($this->fileService->exists($file));

        $this->expectException(RuntimeException::class);
        $this->fileService->delete($file);
    }

    /**
     * @param string[] $chunks
     */
    protected function mockStream(array $chunks): StreamInterface
    {
        $stream = $this->createMock(StreamInterface::class);

        $count = \count($chunks);
        $eof = array_fill(0, $count, false);
        $eof[] = true;

        $stream
            ->method('eof')
            ->willReturn(...$eof)
        ;

        $stream
            ->method('read')
            ->willReturn(...$chunks)
        ;

        return $stream;
    }

    protected function getTargetHandle(): SplFileObject
    {
        $targetFile = $this->getTargetFile();

        $this->fileService->create($targetFile);

        return $this->fileService->open($targetFile);
    }

    /**
     * @return string[]
     */
    protected function chunkString(string $string, int $chunkSize): array
    {
        /**
         * Chunk if chunk size > 0
         */
        return $chunkSize > 0 ? str_split($string, $chunkSize) : [$string];
    }

    #[Depends('testInstance')]
    #[Depends('testCreateSuccess')]
    #[DataProvider('providerCopyFromStream')]
    public function testCopyFromStream(string $content, int $chunkSize): void
    {
        $stream = $this->mockStream($this->chunkString($content, $chunkSize));

        $targetHandle = $this->getTargetHandle();

        $this->fileService->setChunkSize($chunkSize);
        $bytesTransferred = $this->fileService->copyFromStream($targetHandle, $stream);

        self::assertSame(\strlen($content), $bytesTransferred);
        self::assertSame($bytesTransferred, $this->fileService->size($targetHandle));
        self::assertSame($content, file_get_contents($targetHandle->getPathname()));
    }

    public static function providerCopyFromStream(): \Iterator
    {
        /**
         * Negative numbers...
         */
        yield [
            '123456789',
            -123,
        ];
        /**
         * and zero will result in reading whole file at once
         */
        yield [
            '123456789',
            0,
        ];
        yield [
            '123456789',
            1,
        ];
        yield [
            '123456789',
            3,
        ];
        yield [
            '123456789',
            2 ^ 10,
        ];
        yield [
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
            32,
        ];
    }

    #[Depends('testInstance')]
    #[Depends('testCopyFromStream')]
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

    #[Depends('testInstance')]
    #[Depends('testCopyFromStream')]
    public function testCopyFromStreamChunkSize(): void
    {
        $content = '01020304050607080910';
        $chunkSize = 0;

        $stream = $this->mockStream($this->chunkString($content, 1));

        $targetHandle = $this->getTargetHandle();

        $this->fileService->setChunkSize($chunkSize);
        $bytesTransferred = $this->fileService->copyFromStream($targetHandle, $stream);

        self::assertSame(\strlen($content), $bytesTransferred);
        self::assertSame($bytesTransferred, $this->fileService->size($targetHandle));
        self::assertSame($content, file_get_contents($targetHandle->getPathname()));
    }

    #[Depends('testInstance')]
    #[Depends('testCopyFromStream')]
    public function testCopyFromStreamThrowingException(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream
            ->expects(self::once())
            ->method('eof')
            ->willReturn(false)
        ;
        $stream
            ->expects(self::once())
            ->method('read')
            ->willThrowException(new GlobalRuntimeException("Test-Exception"))
        ;

        $targetHandle = $this->getTargetHandle();

        $this->expectException(RuntimeException::class);
        $this->fileService->copyFromStream($targetHandle, $stream);
    }

    #[Depends('testInstance')]
    #[Depends('testCreateSuccess')]
    #[Depends('testCopyFromStream')]
    public function testCopyFromStreamWritingThrowsException(): void
    {
        $stream = $this->mockStream(['1234','5678']);

        $file = $this->getTargetFile();

        $this->fileService->create($file);

        $targetHandle = $this
            ->getMockBuilder(SplFileObject::class)
            ->setConstructorArgs([ $file->getPathname()])
            ->getMock()
        ;
        $targetHandle
            ->expects(self::once())
            ->method('fwrite')
            ->willReturn(0)
        ;

        $this->expectException(RuntimeException::class);
        $this->fileService->copyFromStream($targetHandle, $stream);
    }

    #[Depends('testInstance')]
    #[Depends('testCreateSuccess')]
    #[Depends('testCopyFromStream')]
    public function testCopyFromStreamFlushingThrowsException(): void
    {
        $stream = $this->mockStream(['1234','5678']);

        $file = $this->getTargetFile();

        $this->fileService->create($file);

        $targetHandle = $this
            ->getMockBuilder(SplFileObject::class)
            ->setConstructorArgs([ $file->getPathname()])
            ->getMock()
        ;
        $targetHandle
            ->expects(self::once())
            ->method('fwrite')
            ->willReturn(4)
        ;
        $targetHandle
            ->expects(self::once())
            ->method('fflush')
            ->willReturn(false)
        ;

        $this->expectException(RuntimeException::class);
        $this->fileService->copyFromStream($targetHandle, $stream);
    }


    #[Depends('testInstance')]
    #[Depends('testCreateSuccess')]
    public function testPointSuccess(): void
    {
        $targetHandle = $this->getTargetHandle();
        $targetHandle->fwrite('0000-0000-0000-0000-0000');

        $this->fileService->point($targetHandle, 10);
        $targetHandle->fwrite('4711');

        self::assertSame('0000-0000-4711-0000-0000', file_get_contents($targetHandle->getPathname()));
    }

    #[Depends('testInstance')]
    #[Depends('testCreateSuccess')]
    #[Depends('testCopyFromStream')]
    public function testPointFailure(): void
    {
        $targetHandle = $this->getTargetHandle();

        $this->expectException(RuntimeException::class);
        $this->fileService->point($targetHandle, -1000);
    }
}

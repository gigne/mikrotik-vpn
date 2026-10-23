<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\FileStore;
use App\Support\SandboxFileStore;
use PHPUnit\Framework\TestCase;

class SandboxFileStoreTest extends TestCase
{
    private string $base;
    private FileStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir() . '/vpnpanel-test-' . bin2hex(random_bytes(4));
        $this->store = new SandboxFileStore($this->base);
    }

    protected function tearDown(): void
    {
            $remove = function (string $dir) use (&$remove): void {
                if (! is_dir($dir)) {
                    return;
                }
                foreach (scandir($dir) ?: [] as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }
                    $path = $dir . DIRECTORY_SEPARATOR . $item;
                    if (is_dir($path)) {
                        $remove($path);
                    } else {
                        @unlink($path);
                    }
                }
                @rmdir($dir);
            };

            $remove($this->base);
            parent::tearDown();
        }

    public function test_logical_path_is_rerooted(): void
    {
        $logical = '/etc/ppp/chap-secrets';
        $physical = $this->store->resolve($logical);

        $this->assertStringStartsWith($this->base, $physical);
        $this->assertStringEndsWith('etc/ppp/chap-secrets', $physical);
    }

    public function test_write_read_delete_roundtrip(): void
    {
        $path = '/etc/ppp/chap-secrets';

        $this->assertFalse($this->store->exists($path));
        $this->assertNull($this->store->get($path));

        $contents = "# test\n" . '"mt-test" l2tpd "pwd" 192.168.47.10' . "\n";
        $this->store->put($path, $contents);

        $this->assertTrue($this->store->exists($path));
        $this->assertSame($contents, $this->store->get($path));
        $this->assertSame(strlen($contents), $this->store->size($path));
        $this->assertNotNull($this->store->lastModified($path));

        $this->store->delete($path);
        $this->assertFalse($this->store->exists($path));
        $this->assertNull($this->store->get($path));
    }

    public function test_copy_and_list_files(): void
    {
        $this->store->put('/backups/a.txt', 'a');
        $this->store->put('/backups/b.txt', 'b');
        $this->store->put('/backups/nested/c.txt', 'c');

        $this->store->copy('/backups/a.txt', '/copied/a-copy.txt');
        $this->assertSame('a', $this->store->get('/copied/a-copy.txt'));

        $files = $this->store->listFiles('/backups');
        $this->assertSame(['a.txt', 'b.txt'], $files);

        $this->store->deleteDirectory('/backups');
        $this->assertFalse($this->store->exists('/backups/a.txt'));
    }
}
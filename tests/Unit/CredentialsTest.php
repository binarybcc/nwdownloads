<?php

namespace NWDownloads\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CirculationDashboard\Credentials;

/**
 * Tests for the CLI credential loader.
 *
 * Regression context: the MariaDB root password was hardcoded in eight
 * executable files and committed to the repository.
 */
class CredentialsTest extends TestCase
{
    private string $file;

    /** @var array<string, string|false> Ambient values restored after each test */
    private array $savedEnv = [];

    protected function setUp(): void
    {
        require_once PROJECT_ROOT . '/web/lib/Credentials.php';
        $this->file = sys_get_temp_dir() . '/creds_test_' . uniqid() . '.env';

        // Credentials::require() intentionally prefers a real environment
        // variable over the file, so the suite's own DB_* values must be
        // cleared for these tests to exercise the file path.
        foreach (['DB_USER', 'DB_PASSWORD', 'DB_NAME', 'DB_SOCKET', 'CREDS_TEST_KEY',
                  'PROD_DB_USERNAME', 'PROD_DB_PASSWORD', 'PROD_DB_DATABASE', 'PROD_DB_SOCKET'] as $key) {
            $this->savedEnv[$key] = getenv($key);
            putenv($key);
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        foreach ($this->savedEnv as $key => $value) {
            if (is_string($value) && $value !== '') {
                putenv("{$key}={$value}");
            } else {
                putenv($key);
            }
        }
    }

    private function write(string $contents): void
    {
        file_put_contents($this->file, $contents);
    }

    public function testParsesPlainKeyValuePairs(): void
    {
        $this->write("DB_USER=root\nDB_PASSWORD=secret123\n");
        $values = Credentials::load($this->file);

        $this->assertSame('root', $values['DB_USER']);
        $this->assertSame('secret123', $values['DB_PASSWORD']);
    }

    public function testHandlesExportPrefixCommentsAndQuotes(): void
    {
        $this->write(
            "# a comment\n"
            . "\n"
            . "export DB_USER=root\n"
            . "DB_PASSWORD=\"quoted secret\"\n"
            . "DB_NAME='single'\n"
        );
        $values = Credentials::load($this->file);

        $this->assertSame('root', $values['DB_USER']);
        $this->assertSame('quoted secret', $values['DB_PASSWORD']);
        $this->assertSame('single', $values['DB_NAME']);
    }

    public function testValueContainingEqualsIsPreserved(): void
    {
        $this->write("DB_PASSWORD=a=b=c\n");
        $this->assertSame('a=b=c', Credentials::load($this->file)['DB_PASSWORD']);
    }

    public function testRequireReturnsValue(): void
    {
        $this->write("DB_PASSWORD=secret123\n");
        $this->assertSame('secret123', Credentials::require('DB_PASSWORD', $this->file));
    }

    public function testRequireThrowsOnMissingKey(): void
    {
        $this->write("DB_USER=root\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB_PASSWORD');
        Credentials::require('DB_PASSWORD', $this->file);
    }

    public function testRequireThrowsOnMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        Credentials::require('DB_PASSWORD', '/nonexistent/path/.env.credentials');
    }

    public function testEnvironmentVariableWinsOverFile(): void
    {
        $this->write("CREDS_TEST_KEY=from-file\n");
        putenv('CREDS_TEST_KEY=from-environment');

        $this->assertSame('from-environment', Credentials::require('CREDS_TEST_KEY', $this->file));
    }

    /**
     * The developer credential file documented in operations-reference.md uses
     * PROD_DB_* names; the app and Apache use DB_*. One file must serve both.
     */
    public function testProdDbAliasesAreAccepted(): void
    {
        $this->write("PROD_DB_USERNAME=root\nPROD_DB_PASSWORD=secret123\nPROD_DB_DATABASE=circulation_dashboard\n");

        $this->assertSame('root', Credentials::require('DB_USER', $this->file));
        $this->assertSame('secret123', Credentials::require('DB_PASSWORD', $this->file));
        $this->assertSame('circulation_dashboard', Credentials::require('DB_NAME', $this->file));
    }

    public function testCanonicalNameWinsOverAlias(): void
    {
        $this->write("DB_PASSWORD=canonical\nPROD_DB_PASSWORD=alias\n");
        $this->assertSame('canonical', Credentials::require('DB_PASSWORD', $this->file));
    }

    public function testGetFallsBackToDefaultWhenFileMissing(): void
    {
        $this->assertSame(
            'fallback',
            Credentials::get('DB_PASSWORD', 'fallback', '/nonexistent/path/.env.credentials')
        );
    }

    /**
     * Guard against the original defect returning: no source file that runs in
     * production may carry a literal password.
     */
    public function testNoExecutableFileContainsAHardcodedPassword(): void
    {
        $files = [
            'web/fetch_call_logs.php',
            'web/auto_process.php',
            'web/file_processing.php',
            'web/process-inbox.php',
            'scripts/backup-circulation.sh',
            'scripts/restore-database.sh',
            'scripts/deploy-production.sh',
            'tests/bootstrap.php',
        ];

        foreach ($files as $relative) {
            $path = PROJECT_ROOT . '/' . $relative;
            $this->assertFileExists($path);
            $contents = file_get_contents($path);

            $this->assertDoesNotMatchRegularExpression(
                '/(DB_PASS(WORD)?|password)\s*[=:]\s*[\'"][^\'"$}\s]{6,}[\'"]/i',
                $contents,
                "{$relative} appears to contain a hardcoded password. "
                . 'Credentials belong in .env.credentials, loaded via Credentials.php or load-db-credentials.sh.'
            );
        }
    }
}

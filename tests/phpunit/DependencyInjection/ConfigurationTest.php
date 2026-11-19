<?php

namespace Lle\EntityFileBundle\Tests\DependencyInjection;

use Lle\EntityFileBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    public function testMimeTypesAreNormalized(): void
    {
        $config = $this->process(["included_mime_types" => [" Image/PNG ", "text/csv"]]);
        self::assertSame(["image/png", "text/csv"], $config["included_mime_types"]);

        $config = $this->process(["excluded_mime_types" => [" Text/X-PHP "]]);
        self::assertSame(["text/x-php"], $config["excluded_mime_types"]);
    }

    public function testExecutableTypesAreExcludedByDefault(): void
    {
        $config = $this->process([]);

        self::assertSame([], $config["included_mime_types"]);
        self::assertSame(Configuration::DEFAULT_EXCLUDED_MIME_TYPES, $config["excluded_mime_types"]);
        self::assertContains("application/x-php", $config["excluded_mime_types"]);
        self::assertContains("application/sql", $config["excluded_mime_types"]);
    }

    public function testIncludedMimeTypesReplaceTheDefaultExclusions(): void
    {
        $config = $this->process(["included_mime_types" => ["text/csv"]]);

        self::assertSame(["text/csv"], $config["included_mime_types"]);
        self::assertSame([], $config["excluded_mime_types"]);
    }

    public function testExcludedMimeTypesCanBeOverridden(): void
    {
        $config = $this->process(["excluded_mime_types" => ["text/x-php"]]);

        self::assertSame([], $config["included_mime_types"]);
        self::assertSame(["text/x-php"], $config["excluded_mime_types"]);
    }

    public function testEmptyExcludedMimeTypesAcceptEveryType(): void
    {
        $config = $this->process(["excluded_mime_types" => []]);

        self::assertSame([], $config["included_mime_types"]);
        self::assertSame([], $config["excluded_mime_types"]);
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider("provideBothMimeTypeLists")]
    public function testIncludedAndExcludedMimeTypesCannotBeUsedTogether(array $options): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Configuration \"test\"");

        $this->process($options);
    }

    public static function provideBothMimeTypeLists(): iterable
    {
        yield "both filled" => [["included_mime_types" => ["image/png"], "excluded_mime_types" => ["text/x-php"]]];
        // declaring a key counts, even empty
        yield "both empty" => [["included_mime_types" => [], "excluded_mime_types" => []]];
        yield "dashed keys" => [["included-mime-types" => ["image/png"], "excluded-mime-types" => ["text/x-php"]]];
    }

    public function testUnknownIncludedMimeTypeIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("image/jpg");

        $this->process(["included_mime_types" => ["image/png", "image/jpg"]]);
    }

    public function testUnknownExcludedMimeTypeIsAccepted(): void
    {
        // finfo detects PHP as text/x-php, a type symfony/mime has no extension for
        $config = $this->process(["excluded_mime_types" => ["text/x-php"]]);

        self::assertSame(["text/x-php"], $config["excluded_mime_types"]);
    }

    public function testRolesDefaultToRoleUser(): void
    {
        $config = $this->process([]);

        self::assertSame("ROLE_USER", $config["role"]);
        self::assertSame("ROLE_USER", $config["role_write"]);
        self::assertSame("ROLE_USER", $config["role_delete"]);
    }

    public function testRolesAreIndependent(): void
    {
        $config = $this->process(["role_write" => "ROLE_EDITOR", "role_delete" => "ROLE_ADMIN"]);

        self::assertSame("ROLE_USER", $config["role"]);
        self::assertSame("ROLE_EDITOR", $config["role_write"]);
        self::assertSame("ROLE_ADMIN", $config["role_delete"]);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed> the "test" configuration
     */
    private function process(array $options): array
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            "configurations" => [
                "test" => ["class" => "App\\Entity\\Test"] + $options,
            ],
        ]]);

        return $config["configurations"]["test"];
    }
}

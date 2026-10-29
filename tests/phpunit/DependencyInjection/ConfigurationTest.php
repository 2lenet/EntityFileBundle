<?php

namespace Lle\EntityFileBundle\Tests\DependencyInjection;

use Lle\EntityFileBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    public function testMimeTypesAreNormalized(): void
    {
        $config = $this->process(["mime_types" => [" Image/PNG ", "text/csv"]]);

        self::assertSame(["image/png", "text/csv"], $config["configurations"]["test"]["mime_types"]);
    }

    public function testMimeTypesDefaultToImagesAndPdf(): void
    {
        $config = $this->process([]);

        self::assertSame(
            ["image/jpeg", "image/png", "image/webp", "application/pdf"],
            $config["configurations"]["test"]["mime_types"]
        );
    }

    public function testMimeTypesCanBeOverridden(): void
    {
        $config = $this->process(["mime_types" => ["text/csv"]]);

        self::assertSame(["text/csv"], $config["configurations"]["test"]["mime_types"]);
    }

    public function testEmptyMimeTypesAcceptEveryType(): void
    {
        $config = $this->process(["mime_types" => []]);

        self::assertSame([], $config["configurations"]["test"]["mime_types"]);
    }

    public function testRolesDefaultToRoleUser(): void
    {
        $config = $this->process([])["configurations"]["test"];

        self::assertSame("ROLE_USER", $config["role"]);
        self::assertSame("ROLE_USER", $config["role_write"]);
        self::assertSame("ROLE_USER", $config["role_delete"]);
    }

    public function testRolesAreIndependent(): void
    {
        $config = $this->process(["role_write" => "ROLE_EDITOR", "role_delete" => "ROLE_ADMIN"])["configurations"]["test"];

        self::assertSame("ROLE_USER", $config["role"]);
        self::assertSame("ROLE_EDITOR", $config["role_write"]);
        self::assertSame("ROLE_ADMIN", $config["role_delete"]);
    }

    public function testUnknownMimeTypeIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("image/jpg");

        $this->process(["mime_types" => ["image/png", "image/jpg"]]);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function process(array $options): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [[
            "configurations" => [
                "test" => ["class" => "App\\Entity\\Test"] + $options,
            ],
        ]]);
    }
}

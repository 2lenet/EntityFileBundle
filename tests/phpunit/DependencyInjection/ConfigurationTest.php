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

    public function testMimeTypesDefaultToEmpty(): void
    {
        $config = $this->process([]);

        self::assertSame([], $config["configurations"]["test"]["mime_types"]);
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

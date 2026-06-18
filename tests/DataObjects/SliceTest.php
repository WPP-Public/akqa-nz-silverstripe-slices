<?php

namespace Heyday\SilverStripeSlices\Tests\DataObjects;

use Heyday\SilverStripeSlices\DataObjects\Slice;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

class SliceTest extends SapphireTest
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::modify()->set(TestSlice::class, 'defaultTemplate', null);
        Config::modify()->set(TestSlice::class, 'templates', []);
    }

    public function testTemplateNamesUseConfiguredNamesAndHumanisedFallbacks(): void
    {
        Config::modify()->set(TestSlice::class, 'templates', [
            'HeroBanner' => [
                'name' => 'Homepage hero',
            ],
            'CallToAction' => [],
        ]);

        $this->assertSame(
            [
                'HeroBanner' => 'Homepage hero',
                'CallToAction' => 'Call to action',
            ],
            (new TestSlice())->getTemplateNames()
        );
    }

    public function testDefaultTemplateUsesConfiguredValueOrFirstTemplate(): void
    {
        Config::modify()->set(TestSlice::class, 'templates', [
            'HeroBanner' => [],
            'CallToAction' => [],
        ]);

        $slice = new TestSlice();

        $this->assertSame('HeroBanner', $slice->getDefaultTemplate());

        Config::modify()->set(TestSlice::class, 'defaultTemplate', 'CallToAction');

        $this->assertSame('CallToAction', $slice->getDefaultTemplate());
    }

    public function testTemplateListReturnsCandidateNamesForSilverstripeTemplateEngine(): void
    {
        $slice = new TestSlice();
        $slice->Template = 'HeroBanner';

        $this->assertSame(
            [TestSlice::class . '_HeroBanner'],
            $slice->exposeTemplateList()
        );
    }

    public function testTemplateConfigNormalisesShortcutFieldLabels(): void
    {
        Config::modify()->set(TestSlice::class, 'templates', [
            'HeroBanner' => [
                'fields' => [
                    'Title' => 'Heading',
                    'Content' => [
                        'label' => 'Body',
                        'help' => 'Short supporting copy.',
                    ],
                ],
            ],
        ]);

        $this->assertSame(
            [
                'fields' => [
                    'Title' => [
                        'label' => 'Heading',
                    ],
                    'Content' => [
                        'label' => 'Body',
                        'help' => 'Short supporting copy.',
                    ],
                ],
            ],
            (new TestSlice())->exposeTemplateConfig('HeroBanner')
        );
    }

    public function testClassNameCanBeConfiguredByTemplate(): void
    {
        Config::modify()->set(TestSlice::class, 'templates', [
            'Special' => [
                'className' => AlternateTestSlice::class,
            ],
            'Standard' => [],
        ]);

        $slice = new TestSlice();

        $slice->setClassNameByTemplate('Special');
        $this->assertSame(AlternateTestSlice::class, $slice->ClassName);

        $slice->setClassNameByTemplate('Standard');
        $this->assertSame(TestSlice::class, $slice->ClassName);
    }
}

class TestSlice extends Slice
{
    private static $table_name = 'SliceTest_TestSlice';

    public function exposeTemplateList(): array
    {
        return $this->getTemplateList();
    }

    public function exposeTemplateConfig(string $name): ?array
    {
        return $this->getTemplateConfig($name);
    }

    protected function getBaseSliceClass()
    {
        return self::class;
    }
}

class AlternateTestSlice extends TestSlice
{
    private static $table_name = 'SliceTest_AlternateTestSlice';
}

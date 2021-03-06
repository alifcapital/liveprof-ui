<?php declare(strict_types=1);

/**
 * @maintainer Timur Shagiakhmetov <timur.shagiakhmetov@corp.badoo.com>
 */

namespace unit\Badoo\LiveProfilerUI;

class MethodUsagePageTest extends \unit\Badoo\BaseTestCase
{
    public function providerGetTemplateData()
    {
        $MethodDataMock = $this->getMockBuilder(\Badoo\LiveProfilerUI\Entity\MethodData::class)
            ->disableOriginalConstructor()
            ->setMethods(['getSnapshotId', 'getFormattedValues', 'getMethodId'])
            ->getMock();
        $MethodDataMock->method('getSnapshotId')->willReturn(1);
        $MethodDataMock->method('getMethodId')->willReturn(1);
        $MethodDataMock->method('getFormattedValues')->willReturn(['wt' => 1, 'ct' => 1]);

        return [
            'non_exists_method' => [
                'method_name' => 'test',
                'found_methods' => [],
                'methods_data' => [],
                'expected' => [
                    'method' => 'test',
                    'results' => [],
                    'field_descriptions' => [],
                    'error' => 'Method "test" not found'
                ]
            ],
            'exists_method_no_snapshots' => [
                'method_name' => 'test',
                'found_methods' => [1 => ['id' => 1, 'name' => 'test', 'date' => '2019-01-01']],
                'methods_data' => [],
                'expected' => [
                    'method' => 'test',
                    'results' => [],
                    'field_descriptions' => [],
                    'error' => 'There is no result for test. Last time it was called on 2019-01-01.'
                ]
            ],
            'exists_method' => [
                'method_name' => 'test',
                'found_methods' => [1 => ['id' => 1, 'name' => 'test', 'date' => '2019-01-01']],
                'methods_data' => [$MethodDataMock],
                'expected' => [
                    'method' => 'test',
                    'results' => [
                        [
                            'date' => 'date',
                            'method_id' => 1,
                            'app' => 'app',
                            'label' => 'label',
                            'fields' => [
                                'ct' => 1,
                                'wt' => 1,
                                'calls_count' => 1
                            ]
                        ]
                    ],
                    'field_descriptions' => [],
                    'error' => ''
                ]
            ],
        ];
    }

    /**
     * @dataProvider providerGetTemplateData
     * @param $method_name
     * @param $found_methods
     * @param $methods_data
     * @param $expected
     * @throws \ReflectionException
     */
    public function testGetTemplateData($method_name, $found_methods, $methods_data, $expected)
    {
        $FieldList = new \Badoo\LiveProfilerUI\FieldList(['wt', 'ct'], [], []);

        $MethodMock = $this->getMockBuilder(\Badoo\LiveProfilerUI\DataProviders\Method::class)
            ->disableOriginalConstructor()
            ->setMethods(['findByName'])
            ->getMock();
        $MethodMock->method('findByName')->willReturn($found_methods);

        $snapshot = [
            'id' => 1,
            'app' => 'app',
            'label' => 'label',
            'date' => 'date',
            'calls_count' => 1
        ];
        $SnapshotMock = $this->getMockBuilder(\Badoo\LiveProfilerUI\DataProviders\Snapshot::class)
            ->disableOriginalConstructor()
            ->setMethods(['getListByIds'])
            ->getMock();
        $SnapshotMock->method('getListByIds')->willReturn([1 => $snapshot]);

        $MethodDataMock = $this->getMockBuilder(\Badoo\LiveProfilerUI\DataProviders\MethodData::class)
            ->disableOriginalConstructor()
            ->setMethods(['getDataByMethodIdsAndSnapshotIds'])
            ->getMock();
        $MethodDataMock->method('getDataByMethodIdsAndSnapshotIds')->willReturn($methods_data);

        $data = [
            'method' => $method_name,
        ];

        /** @var \Badoo\LiveProfilerUI\Pages\MethodUsagePage $PageMock */
        $PageMock = $this->getMockBuilder(\Badoo\LiveProfilerUI\Pages\MethodUsagePage::class)
            ->disableOriginalConstructor()
            ->setMethods(['__construct'])
            ->getMock();
        $this->setProtectedProperty($PageMock, 'FieldList', $FieldList);
        $this->setProtectedProperty($PageMock, 'Method', $MethodMock);
        $this->setProtectedProperty($PageMock, 'MethodData', $MethodDataMock);
        $this->setProtectedProperty($PageMock, 'Snapshot', $SnapshotMock);
        $PageMock->setData($data);

        $result = $this->invokeMethod($PageMock, 'getTemplateData');

        static::assertEquals($expected, $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testCleanData()
    {
        $PageMock = $this->getMockBuilder(\Badoo\LiveProfilerUI\Pages\MethodUsagePage::class)
            ->disableOriginalConstructor()
            ->setMethods(['__construct'])
            ->getMock();

        /** @var \Badoo\LiveProfilerUI\Pages\MethodUsagePage $PageMock */
        $PageMock->setData(['method' => ' method name ']);
        $this->invokeMethod($PageMock, 'cleanData');

        $data = $this->getProtectedProperty($PageMock, 'data');

        $expected = ['method' => 'method name'];
        self::assertEquals($expected, $data);
    }

    /**
     * @throws \ReflectionException
     */
    public function testConstruct()
    {
        $FieldList = new \Badoo\LiveProfilerUI\FieldList([], [], []);

        /** @var \Badoo\LiveProfilerUI\DataProviders\Method $MethodMock */
        $MethodMock = $this->getMockBuilder(\Badoo\LiveProfilerUI\DataProviders\Method::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        /** @var \Badoo\LiveProfilerUI\DataProviders\MethodData $MethodDataMock */
        $MethodDataMock = $this->getMockBuilder(\Badoo\LiveProfilerUI\DataProviders\MethodData::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        /** @var \Badoo\LiveProfilerUI\DataProviders\Snapshot $SnapshotMock */
        $SnapshotMock = $this->getMockBuilder(\Badoo\LiveProfilerUI\DataProviders\Snapshot::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        /** @var \Badoo\LiveProfilerUI\View $ViewMock */
        $ViewMock = $this->getMockBuilder(\Badoo\LiveProfilerUI\View::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        $Page = new \Badoo\LiveProfilerUI\Pages\MethodUsagePage(
            $ViewMock,
            $SnapshotMock,
            $MethodMock,
            $MethodDataMock,
            $FieldList
        );

        $View = $this->getProtectedProperty($Page, 'View');
        $Snapshot = $this->getProtectedProperty($Page, 'Snapshot');
        $Method = $this->getProtectedProperty($Page, 'Method');
        $MethodData = $this->getProtectedProperty($Page, 'MethodData');
        $FieldListNew = $this->getProtectedProperty($Page, 'FieldList');

        self::assertSame($ViewMock, $View);
        self::assertSame($SnapshotMock, $Snapshot);
        self::assertSame($MethodMock, $Method);
        self::assertSame($MethodDataMock, $MethodData);
        self::assertSame($FieldList, $FieldListNew);
    }
}

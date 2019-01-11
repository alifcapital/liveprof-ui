<?php declare(strict_types=1);

/**
 * A page with inheritance of methods of the snapshot
 * @maintainer Timur Shagiakhmetov <timur.shagiakhmetov@corp.badoo.com>
 */

namespace Badoo\LiveProfilerUI\Pages;

use Badoo\LiveProfilerUI\DataProviders\Interfaces\MethodInterface;
use Badoo\LiveProfilerUI\DataProviders\Interfaces\MethodDataInterface;
use Badoo\LiveProfilerUI\DataProviders\Interfaces\MethodTreeInterface;
use Badoo\LiveProfilerUI\DataProviders\Interfaces\SnapshotInterface;
use Badoo\LiveProfilerUI\FieldList;
use Badoo\LiveProfilerUI\Interfaces\ViewInterface;

class ProfileMethodTreePage extends BasePage
{
    const STAT_INTERVAL_WEEK = 7;
    const STAT_INTERVAL_MONTH = 31;
    const STAT_INTERVAL_HALF_YEAR = 182;

    /** @var string */
    protected static $template_path = 'profile_method_tree';
    /** @var SnapshotInterface */
    protected $Snapshot;
    /** @var MethodInterface */
    protected $Method;
    /** @var MethodTreeInterface */
    protected $MethodTree;
    /** @var MethodDataInterface */
    protected $MethodData;
    /** @var FieldList */
    protected $FieldList;
    /** @var string */
    protected $calls_count_field = '';
    /** @var array */
    protected static $graph_intervals = [
        '7 days' => self::STAT_INTERVAL_WEEK,
        '1 month' => self::STAT_INTERVAL_MONTH,
        '6 months' => self::STAT_INTERVAL_HALF_YEAR,
    ];

    public function __construct(
        ViewInterface $View,
        SnapshotInterface $Snapshot,
        MethodInterface $Method,
        MethodTreeInterface $MethodTree,
        MethodDataInterface $MethodData,
        FieldList $FieldList,
        string $calls_count_field
    ) {
        $this->View = $View;
        $this->Snapshot = $Snapshot;
        $this->Method = $Method;
        $this->MethodTree = $MethodTree;
        $this->MethodData = $MethodData;
        $this->FieldList = $FieldList;
        $this->calls_count_field = $calls_count_field;
    }

    protected function cleanData() : bool
    {
        $this->data['app'] = isset($this->data['app']) ? trim($this->data['app']) : '';
        $this->data['label'] = isset($this->data['label']) ? trim($this->data['label']) : '';
        $this->data['snapshot_id'] = isset($this->data['snapshot_id']) ? (int)$this->data['snapshot_id'] : 0;
        $this->data['stat_interval'] = isset($this->data['stat_interval']) ? (int)$this->data['stat_interval'] : 0;
        $this->data['method_id'] = isset($this->data['method_id']) ? (int)$this->data['method_id'] : 0;

        if (!$this->data['snapshot_id'] && (!$this->data['app'] || !$this->data['label'])) {
            throw new \InvalidArgumentException('Empty snapshot_id, app and label');
        }

        if (!\in_array($this->data['stat_interval'], self::$graph_intervals, true)) {
            $this->data['stat_interval'] = self::STAT_INTERVAL_WEEK;
        }

        return true;
    }

    /**
     * @return array
     * @throws \InvalidArgumentException
     */
    public function getTemplateData() : array
    {
        $link_base = '/profiler/tree-view.phtml?';
        $Snapshot = false;
        if ($this->data['snapshot_id']) {
            $Snapshot = $this->Snapshot->getOneById($this->data['snapshot_id']);
            $link_base .= 'snapshot_id=' . $this->data['snapshot_id'];
        } elseif ($this->data['app'] && $this->data['label']) {
            $Snapshot = $this->Snapshot->getOneByAppAndLabel($this->data['app'], $this->data['label']);
            $link_base .= 'app=' . urlencode($this->data['app']) . '&label=' . urlencode($this->data['label']);
        }

        if (empty($Snapshot)) {
            throw new \InvalidArgumentException('Can\'t get snapshot');
        }

        if (!\in_array($this->data['stat_interval'], self::$graph_intervals, true)) {
            $this->data['stat_interval'] = self::STAT_INTERVAL_WEEK;
        }

        if (!$this->data['method_id']) {
            $this->data['method_id'] = $this->getMainMethodId();
        }

        $dates = \Badoo\LiveProfilerUI\DateGenerator::getDatesArray(
            $Snapshot->getDate(),
            $this->data['stat_interval'],
            $this->data['stat_interval']
        );

        $date_to_snapshot_map = $this->Snapshot->getSnapshotIdsByDates(
            $dates,
            $Snapshot->getApp(),
            $Snapshot->getLabel()
        );

        $view_data = [
            'snapshot' => $Snapshot,
            'method_dates' => $dates,
            'stat_intervals' => $this->getIntervalsFormData($link_base),
        ];

        $common_block_data = [
            'link_base' => $link_base,
            'fields' => $this->FieldList->getAllFieldsWithVariations(),
            'field_descriptions' => $this->FieldList->getFieldDescriptions()
        ];

        $method_data = $this->getMethodDataWithHistory($date_to_snapshot_map, $this->data['method_id']);
        if ($method_data) {
            /** @var \Badoo\LiveProfilerUI\Entity\MethodData $MainMethod */
            $MainMethod = current($method_data);
            $view_data['available_graphs'] = $this->getGraphsData($MainMethod);
            $view_data['method_name'] = $MainMethod->getMethodName();
            $view_data['method_data'] = $this->View->fetchFile(
                'profiler_result_view_part',
                $common_block_data + ['data' => $method_data, 'hide_lines_column' => true],
                false
            );
        }

        $parents = $this->getMethodParentsWithHistory($date_to_snapshot_map, $this->data['method_id']);
        if (!empty($parents)) {
            $view_data['parents'] = $this->View->fetchFile(
                'profiler_result_view_part',
                $common_block_data + ['data' => $parents],
                false
            );
        }

        $children = $this->getMethodChildrenWithHistory($date_to_snapshot_map, $this->data['method_id']);
        if ($children) {
            $view_data['children'] = $this->View->fetchFile(
                'profiler_result_view_part',
                $common_block_data + ['data' => $children, 'hide_lines_column' => true],
                false
            );
        }

        $view_data['js_graph_data_all'] = array_merge($method_data, $children);

        return $view_data;
    }

    protected function getMainMethodId() : int
    {
        $methods = $this->Method->findByName('main()', true);
        if (!empty($methods)) {
            return array_keys($methods)[0];
        }
        return 0;
    }

    protected function getGraphsData(\Badoo\LiveProfilerUI\Entity\MethodData $MainMethod) : array
    {
        $data = [];
        foreach (array_keys($MainMethod->getHistoryData()) as $field) {
            if (strpos($field, 'mem') !== false) {
                $type = 'memory';
            } elseif (strpos($field, $this->calls_count_field) !== false) {
                $type = 'times';
            } else {
                $type = 'time';
            }
            $data[$field] = [
                'type' => $type,
                'label' => $field,
                'graph_label' => $field . ' self + children calls graph'
            ];
        }

        return $data;
    }

    protected function getIntervalsFormData(string $link_base) : array
    {
        $data = [];
        foreach (self::$graph_intervals as $name => $value) {
            $data[] = [
                'name' => $name,
                'link' => $link_base . "&method_id={$this->data['method_id']}&stat_interval=$value",
                'selected' => $value === $this->data['stat_interval'],
            ];
        }
        return $data;
    }

    protected function getMethodDataWithHistory(array $dates_to_snapshots, int $method_id) : array
    {
        $snapshot_ids = array_filter(array_values($dates_to_snapshots));
        if (empty($snapshot_ids)) {
            return [];
        }

        $MethodData = $this->MethodData->getDataByMethodIdsAndSnapshotIds($snapshot_ids, [$method_id]);
        $MethodData = $this->Method->injectMethodNames($MethodData);

        return $this->getProfilerRecordsWithHistory($MethodData, $dates_to_snapshots);
    }

    protected function getMethodParentsWithHistory(array $dates_to_snapshots, int $method_id) : array
    {
        $snapshot_ids = array_filter(array_values($dates_to_snapshots));
        if (empty($snapshot_ids)) {
            return [];
        }

        $MethodTree = $this->MethodTree->getDataByMethodIdsAndSnapshotIds($snapshot_ids, [$method_id]);

        foreach ($MethodTree as &$Item) {
            $Item->setMethodId($Item->getParentId());
        }
        unset($Item);

        $MethodTree = $this->Method->injectMethodNames($MethodTree);

        return $this->getProfilerRecordsWithHistory($MethodTree, $dates_to_snapshots);
    }

    protected function getMethodChildrenWithHistory(array $dates_to_snapshots, int $method_id) : array
    {
        $snapshot_ids = array_filter(array_values($dates_to_snapshots));
        if (empty($snapshot_ids)) {
            return [];
        }

        $MethodTree = $this->MethodTree->getDataByParentIdsAndSnapshotIds($snapshot_ids, [$method_id]);
        $MethodTree = $this->Method->injectMethodNames($MethodTree);

        return $this->getProfilerRecordsWithHistory($MethodTree, $dates_to_snapshots);
    }

    /**
     * @param \Badoo\LiveProfilerUI\Entity\MethodData[] $result
     * @param array $dates_to_snapshots
     * @return array
     */
    protected function getProfilerRecordsWithHistory(array $result, array $dates_to_snapshots) : array
    {
        $last_snapshot_id = end($dates_to_snapshots);
        if (!$last_snapshot_id) {
            return [];
        }

        $history = [];
        foreach ($result as $Row) {
            $history[$Row->getMethodId()][$Row->getSnapshotId()] = $Row;
        }

        $all_fields = $this->FieldList->getAllFieldsWithVariations();

        $result = [];
        foreach ($history as $method_rows) {
            // the method was not called in the last snapshot, so it will not displayed
            if (!isset($method_rows[$last_snapshot_id])) {
                continue;
            }

            /** @var \Badoo\LiveProfilerUI\Entity\MethodData $Row */
            $Row = $method_rows[$last_snapshot_id];

            $data = [];
            foreach ($all_fields as $field) {
                $data[$field] = [];
            }

            // extract data from previous snapshots
            foreach ($dates_to_snapshots as $snapshot_id) {
                if ($snapshot_id && isset($method_rows[$snapshot_id])) {
                    /** @var \Badoo\LiveProfilerUI\Entity\MethodData $PreviousRow */
                    $PreviousRow = $method_rows[$snapshot_id];
                    $values = $PreviousRow->getValues();

                    foreach ($all_fields as $field) {
                        $data[$field][] = ['val' => $values[$field]];
                    }
                } else {
                    foreach ($all_fields as $field) {
                        $data[$field][] = ['val' => 0];
                    }
                }
            }

            $Row->setHistoryData($data);

            $result[] = $Row;
        }

        return $result;
    }
}

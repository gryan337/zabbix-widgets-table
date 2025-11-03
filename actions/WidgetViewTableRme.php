<?php declare(strict_types = 0);


namespace Modules\TableModuleRME\Actions;

use API,
	CArrayHelper,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CItemHelper,
	CMacrosResolverHelper,
	CMathHelper,
	CNumberParser,
	CSettingsHelper,
	CWidgetsData,
	CSvgGraph,
	Manager;

use Modules\TableModuleRME\Includes\{
	WidgetForm,
	CWidgetFieldColumnsList
};

use Modules\TableModuleRME\Widget;
use Zabbix\Widgets\CWidgetField;

class WidgetViewTableRme extends CControllerDashboardWidgetView {

	/** @property int $sparkline_max_samples  Limit of samples when requesting sparkline graph data for time period. */
	protected int $sparkline_max_samples;
	protected array $filteredItemids;
	protected array $filteredTags;

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'contents_width'	=> 'int32'
		]);
	}

	protected function doAction(): void {
		$data = [
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'layout' => $this->fields_values['layout'],
			'show_column_header' => $this->fields_values['show_column_header'],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		// Editing template dashboard?
		if ($this->isTemplateDashboard() && !$this->fields_values['override_hostid']) {
			$data['error'] = _('No data found');
		}
		else {
			$data += $this->getData();
			$data['is_template_dashboard'] = $this->isTemplateDashboard();
		}

		$this->setResponse(new CControllerResponseData($data));
	}

	private function getData(): array {
		$db_hosts = $this->getHosts();

		if (!$db_hosts) {
			return [
				'error' => _('No data found')
			];
		}

		$db_items = [];
		$column_tables = [];
		$item_cache = [];

		$columns = $this->getPreparedColumns();
		$this->sparkline_max_samples = ceil($this->getInput('contents_width') / count($columns));

		$result = $this->normalizeItemFilter();
		$this->filteredItemids = $result['filteredItemidArray'];
		$this->filteredTags = $result['filteredTagsArray'];

		if ($this->fields_values['update_item_filter_only']) {
			if (empty($this->filteredItemids) || (count($this->filteredItemids) == 1 && $this->filteredItemids[0] == '000000')) {
				return ['error' => _('Make a Selection to Display Metrics')];
			}

			if ($this->fields_values['item_filter_type'] == WidgetForm::ITEM_FILTER_ITEMIDS &&
					(empty($this->filteredItemids) || (count($this->filteredItemids) == 1 && $this->filteredItemids[0] == '000000'))) {
				return ['error' => _('Make a Selection to Display Metrics')];
			}

			if ($this->fields_values['item_filter_type'] == WidgetForm::ITEM_FILTER_TAGS &&
					empty($this->filteredTags)) {
				return ['error' => _('Make a Selection to Display Metrics')];
			}
		}

		foreach ($columns as $column_index => $column) {
			$cache_key = $this->normalizeColumnKey($column);
			
			if (array_key_exists($cache_key, $item_cache)) {
				$db_column_items = $item_cache[$cache_key];
			}
			else {
				$db_column_items = $this->getItems($column, array_keys($db_hosts));
				$item_cache[$cache_key] = $db_column_items;
			}
			
			if (!$db_column_items) {
				continue;
			}
			
			$iname_strip = $this->fields_values['item_name_strip'];
			if ($iname_strip) {
				$new_db_column_items = [];
				foreach ($db_column_items as $itemid => $values) {
					$resolved_label = CMacrosResolverHelper::resolveItemBasedWidgetMacros(
						[$itemid => $values + ['label' => $this->fields_values['item_name_strip']]],
						['label' => 'label']
					);
					$values['original_name'] = $values['name'];
					$values['name'] = $resolved_label[$values['itemid']]['label'];
					$new_db_column_items[$itemid] = $values;
				}
				$db_column_items = $new_db_column_items;
			}

			if ($column['display'] == CWidgetFieldColumnsList::DISPLAY_SPARKLINE) {
				$config = $column + ['contents_width' => $this->sparkline_max_samples];
				$db_sparkline_values = self::getItemSparklineValues($db_column_items, $config);
			}
			else {
				$db_sparkline_values = [];
			}

			// Each column has different aggregation function and time period.
			if ($this->fields_values['show_grouping_only']) {
				$table = [];
				foreach ($db_column_items as $itemid => $item) {
					$table[$item['hostid']][] = [
						Widget::CELL_HOSTID => $item['hostid'],
						Widget::CELL_ITEMID => $itemid,
						Widget::CELL_VALUE => 0,
						Widget::CELL_SPARKLINE_VALUE => null,
						Widget::CELL_METADATA => [
							'name' => $item['name'],
							'column_index' => $column['column_index'],
							'original_name' => $item['name'],
							'units' => $item['units'],
							'key_' => $item['key_']
						]
					];
				}
			}
			else {
				$db_values = self::getItemValues($db_column_items, $column);
				$table = self::makeColumnizedTable($db_column_items, $column, $db_values, $db_sparkline_values, $this->fields_values['layout']);
				unset($db_values);
			}

			$db_items += $db_column_items;
			unset($db_column_items);

			// Each pattern result must be ordered before applying limit.
			if ($this->fields_values['layout'] == WidgetForm::LAYOUT_VERTICAL ||
					$this->fields_values['layout'] == WidgetForm::LAYOUT_HORIZONTAL) {
				$this->applyItemOrdering($table, $db_hosts);
				$this->applyItemOrderingLimit($table);
			}
			
			$column_tables[$column_index] = $table;
			unset($table);
		}
		unset($item_cache);

		if ($this->fields_values['layout'] == WidgetForm::LAYOUT_THREE_COL) {
			$this->applyItemOrderingLimitThreeCol($column_tables);
		}
		
		if ($this->fields_values['layout'] == WidgetForm::LAYOUT_COLUMN_PER) {
			$groupby_host = (count($this->fields_values['item_group_by']) === 1 &&
					$this->fields_values['item_group_by'][0]['tag_name'] === '{HOST.HOST}')
				? true
				: false;
				
			if ($groupby_host) {
				foreach ($column_tables as $column_index => &$host_values) {
					foreach ($host_values as $hostid => &$metrics) {
						$metrics = array_filter($metrics, function ($cell) {
							return !empty($cell[Widget::CELL_ITEMID]);
						});
						
						foreach ($metrics as $cindex => &$cell) {
							$cell[Widget::CELL_METADATA]['grouping_name'] = '{HOST.HOST}';
						}
					}
				}
			}
			else {
				$groupings_to_keep = false;
				foreach ($column_tables as $column_index => &$host_values) {
					foreach ($host_values as $hostid => &$metrics) {
						foreach ($metrics as $cindex => &$cell) {
							if ($cell[Widget::CELL_ITEMID]) {
								$name = self::computeNameForPerColumn(
									$db_items[$cell[Widget::CELL_ITEMID]]['tags'],
									$this->fields_values['item_group_by']
								);

								$cell[Widget::CELL_METADATA]['grouping_name'] = $name;
								if (!$name) {
									unset($metrics[$cindex]);
								}
								else {
									$groupings_to_keep = true;
								}
							}
							else {
								unset($metrics[$cindex]);
							}
						}
						if (empty($metrics)) {
							unset($host_values[$hostid]);
						}
					}
					if (empty($host_values)) {
						unset($column_tables[$column_index]);
					}
				}

				if (!$groupings_to_keep) {
					$column_tables = [];
				}
			}
		}

		$table = self::concatenateTables($column_tables);
		unset($column_tables);

		if (!$table) {
			return [
				'error' => _('No data found')
			];
		}

		if ($this->fields_values['layout'] != WidgetForm::LAYOUT_THREE_COL && $this->fields_values['layout'] != WidgetForm::LAYOUT_COLUMN_PER) {
			$this->applyHostOrdering($table, $db_hosts);
			$this->applyHostOrderingLimit($table);
			$this->applyItemOrdering($table, $db_hosts);
		}
		else {
			foreach ($table as $hostid => $values) {
				if (empty($values)) {
					unset($table[$hostid]);
				}
			}
		}

		if ($this->fields_values['layout'] == WidgetForm::LAYOUT_COLUMN_PER) {
			$table = self::perColumnAggregation($columns, $table, $groupby_host);
			if (!$groupby_host && !$this->fields_values['host_ordering_order_by'] == WidgetForm::ORDERBY_ITEM_VALUE) {
				$table = self::perColumnOrdering($columns, $table, $this->fields_values);
			}

			if (!$this->isTemplateDashboard() && !$this->fields_values['aggregate_all_hosts']) {
				$this->applyHostOrdering($table, $db_hosts);
				$this->applyHostOrderingLimit($table);
			}
		}
		
		self::calculateExtremes($columns, $table);
		self::calculateValueViews($columns, $table);

		// Remove hostids.
		if (!$this->isTemplateDashboard() &&
				$this->fields_values['layout'] == WidgetForm::LAYOUT_COLUMN_PER &&
				$this->fields_values['aggregate_all_hosts']) {
			$table = [$table];
		}
		else {
			$table = array_values($table);
		}

		$db_item_problem_triggers = [];
		if ($this->fields_values['problems'] != WidgetForm::PROBLEMS_NONE) {
			$db_item_problem_triggers = $this->getProblemTriggers(array_keys($db_items));
		}

		$data = [
			'error' => null,
			'configuration' => $columns,
			'rows' => (
					$this->fields_values['layout'] == WidgetForm::LAYOUT_VERTICAL ||
					(!$this->isTemplateDashboard() &&
						$this->fields_values['layout'] == WidgetForm::LAYOUT_COLUMN_PER &&
						!$this->fields_values['aggregate_all_hosts']
					)
			)
				? self::transposeTable($table)
				: $table,
			'db_hosts' => $db_hosts,
			'db_items' => $db_items,
			'db_item_problem_triggers' => $db_item_problem_triggers,
			'item_header' => $this->fields_values['item_header'],
			'host_header' => $this->fields_values['host_header'],
			'item_order' => $this->fields_values['item_ordering_order'],
			'item_order_limit' => $this->fields_values['item_ordering_limit'],
			'item_order_by' => $this->fields_values['item_ordering_order_by'],
			'host_order' => $this->fields_values['host_ordering_order'],
			'host_order_limit' => $this->fields_values['host_ordering_limit'],
			'host_order_by' => $this->fields_values['host_ordering_order_by'],
			'host_order_item' => $this->fields_values['host_ordering_item'],
			'item_grouping' => $this->fields_values['item_group_by'],
			'no_broadcast_hostid' => $this->fields_values['no_broadcast_hostid'],
			'aggregate_all_hosts' => $this->isTemplateDashboard()
				? null
				: $this->fields_values['aggregate_all_hosts'],
			'show_grouping_only' => $this->fields_values['show_grouping_only'],
			'row_reset' => $this->fields_values['reset_row'],
			'footer' => $this->fields_values['footer'],
			'num_hosts' => [],
			'bar_gauge_layout' => $this->fields_values['bar_gauge_layout'],
			'bar_gauge_tooltip' => $this->fields_values['bar_gauge_tooltip'],
			'delimiter' => $this->fields_values['grouping_delimiter']
		];
		
		if (!$this->isTemplateDashboard() &&
				$this->fields_values['layout'] == WidgetForm::LAYOUT_COLUMN_PER &&
				!$this->fields_values['aggregate_all_hosts']) {
			$num_hosts = [];
			foreach ($table as $tindex => $stat) {
				foreach ($stat as $smet) {
					if (count($num_hosts) > 1) {
						break;
					}
					
					if (!in_array($smet[Widget::CELL_HOSTID], $num_hosts)) {
						$num_hosts[] = $smet[Widget::CELL_HOSTID];
					}
				}
			}
			$data['num_hosts'] = $num_hosts;
		}

		return $data;
	}
	
	private function normalizeColumnKey(array $column): string {
		$normalized = [];
		
		$normalized['items'] = array_map('strtolower', $column['items']);
		sort($normalized['items']);
		
		$normalized['item_tags'] = $column['item_tags'];
		usort($normalized['item_tags'], function($a, $b) {
			return [$a['tag'], $a['operator'], $a['value']]
				<=> [$b['tag'], $b['operator'], $b['value']];
		});
		
		$normalized['item_tags_evaltype'] = $column['item_tags_evaltype'];
		
		return sha1(serialize($normalized));
	}
	
	private function computeNameForPerColumn(array $tags, array $groupings): string {
		$delimiter = $this->fields_values['grouping_delimiter'];
		$delimiter_length = mb_strlen($delimiter, 'UTF-8');
		$name = '';
		foreach ($groupings as $i => $attrs) {
			$tag = $attrs['tag_name'];
			foreach ($tags as $tag_index => $values) {
				if ($values['tag'] == $tag) {
					$name .= $values['value'] . $delimiter;
					break;
				}
			}
		}
		$name = substr($name, 0, -$delimiter_length);
		return $name;
	}

	private function getHosts(): array {
		$groupids = !$this->isTemplateDashboard() && $this->fields_values['groupids']
			? getSubGroups($this->fields_values['groupids'])
			: null;

		if ($this->isTemplateDashboard()) {
			$hostids = $this->fields_values['override_hostid'];
		}
		else {
			$hostids = $this->fields_values['hostids'] ?: null;
		}

		$tags = !$this->isTemplateDashboard() && $this->fields_values['host_tags']
			? $this->fields_values['host_tags']
			: null;

		if (!$groupids && !$hostids && !$tags) {
			return [];
		}

		$evaltype = !$this->isTemplateDashboard()
			? $this->fields_values['host_tags_evaltype']
			: null;
			
		$options = [
			'output' => ['name', 'hostid'],
			'groupids' => $groupids,
			'hostids' => $hostids,
			'tags' => $tags,
			'evaltype' => $evaltype,
			'monitored_hosts' => true,
			'with_monitored_items' => true,
			'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT),
			'preservekeys' => true
		];

		$db_hosts = API::Host()->get($options);
		if ($db_hosts === false) {
			return [];
		}

		return $db_hosts;
	}

	/**
	 * Inserts default column configuration that selects all items, if no columns declared.
	 * Parses min, max values if declared.
	 */
	private function getPreparedColumns(): array {
		$default = [
			'column_index' => 0,
			'items' => ['*'],
			'item_tags_evaltype' => TAG_EVAL_TYPE_AND_OR,
			'item_tags' => [],
			'base_color' => '',
			'font_color' => '',
			'display_value_as' => CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC,
			'display' => CWidgetFieldColumnsList::DISPLAY_AS_IS,
			'sparkline' => CWidgetFieldColumnsList::SPARKLINE_DEFAULT,
			'min' => '',
			'max' => '',
			'highlights' => [],
			'thresholds' => [],
			'decimal_places' => CWidgetFieldColumnsList::DEFAULT_DECIMAL_PLACES,
			'go_to_history_values' => 0,
			'valuemap_override' => CWidgetFieldColumnsList::VALUEMAP_AS_IS,
			'aggregate_function' => AGGREGATE_NONE,
			'time_period' => [
				CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(
					CWidgetField::REFERENCE_DASHBOARD, CWidgetsData::DATA_TYPE_TIME_PERIOD
				)
			],
			'history' => CWidgetFieldColumnsList::HISTORY_DATA_AUTO
		];

		$result = [];
		if (!$this->fields_values['columns']) {
			$result[] = $default;
		}
		else {
			$number_parser = new CNumberParser([
				'with_size_suffix' => true,
				'with_time_suffix' => true,
				'is_binary_size' => false
			]);

			$number_parser_binary = new CNumberParser([
				'with_size_suffix' => true,
				'with_time_suffix' => true,
				'is_binary_size' => true
			]);

			foreach ($this->fields_values['columns'] as $column_index => $column) {
				$column += $default;
				$column['sparkline'] += $default['sparkline'];
				$column['column_index'] = $column_index;

				$column['original_min'] = $column['min'];
				$column['original_max'] = $column['max'];
				if ($column['min'] !== '') {
					$number_parser_binary->parse($column['min']);
					$column['min_binary'] = $number_parser_binary->calcValue();

					$number_parser->parse($column['min']);
					$column['min'] = $number_parser->calcValue();
				}

				if ($column['max'] !== '') {
					$number_parser_binary->parse($column['max']);
					$column['max_binary'] = $number_parser_binary->calcValue();

					$number_parser->parse($column['max']);
					$column['max'] = $number_parser->calcValue();
				}

				$result[] = $column;
			}
		}

		return $result;
	}

	private function normalizeItemFilter(): array {
		$inputArray = $this->getInput('fields')['itemid'];
		$itemidArray = [];
		$tagsArray = [];

		foreach ($inputArray as $item) {
			if (is_string($item) && json_decode($item) !== null) {
				$decodeArray = json_decode($item, true);
				foreach ($decodeArray as $subItem) {
					if (isset($subItem['itemid'])) {
						$itemidArray[] = $subItem['itemid'];
					}

					if (isset($subItem['tags'])) {
						foreach ($subItem['tags'] as $tag) {
							$tagsArray[] = [
								'operator' => 1,
								'tag' => $tag['tag'],
								'value' => $tag['value']
							];
						}
					}
				}
			}
			else {
				$itemidArray[] = $item;
			}
		}

		return [
			'filteredItemidArray' => $itemidArray,
			'filteredTagsArray' => $tagsArray
		];
	}

	private function updateTagsFromFilters(array &$column): void {
		$existingTags = $column['item_tags'];
		$newTags = $this->filteredTags;

		$newTagsMap = [];
		foreach ($newTags as $tag) {
			$newTagsMap[$tag['tag']] = $tag;
		}

		$finalTags = [];

		foreach ($existingTags as $tag) {
			$tagKey = $tag['tag'];
			if (!isset($newTagsMap[$tagKey])) {
				$finalTags[] = $tag;
			}
		}

		foreach ($newTags as $newTag) {
			$finalTags[] = $newTag;
		}

		$column['item_tags'] = $finalTags;
	}

	private function getItems(array $column, array $hostids): array {
		$transformed_keys = array();

		foreach ($column['items'] as $index => $item) {
			if (strpos($item, 'key=') === 0) {
				$key_part = substr($item, 4);
				$transformed_keys[] = $key_part;
				unset($column['items'][$index]);
			}
		}

		$column['items'] = array_values($column['items']);

		$search_field = $this->isTemplateDashboard() ? 'name' : 'name_resolved';
		$numeric_only = $column['display_value_as'] == CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC;
		$options = [
			'output' => [
				'itemid', 'hostid', 'name_resolved', 'value_type', 'units', 'valuemapid', 'history',
				'trends', 'key_', 'type', 'delay'
			],
			'selectValueMap' => ['mappings'],
			'monitored' => true,
			'webitems' => true,
			'searchWildcardsEnabled' => true,
			'searchByAny' => true,
			'filter' => [
				'status' => ITEM_STATUS_ACTIVE,
				'value_type' => $numeric_only ? [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64] : null
			],
			'preservekeys' => true
		];

		if ($this->fields_values['layout'] == WidgetForm::LAYOUT_COLUMN_PER) {
			$options['selectTags'] = 'extend';
		}

		if (!$transformed_keys && !$column['items']) {
			$options['search'][$search_field] = '*';
		}

		if ($column['items']) {
			$options['search'][$search_field] = in_array('*', $column['items'], true)
				? null
				: $column['items'];
		}

		if ($transformed_keys) {
			$options['search']['key_'] = $transformed_keys;
		}

		if (array_key_exists('item_tags', $column) && $column['item_tags']) {
			$options['tags'] = $column['item_tags'];
			$options['evaltype'] = $column['item_tags_evaltype'];
		}

		if (!empty($this->filteredItemids) && !(count($this->filteredItemids) == 1 && $this->filteredItemids[0] == '000000')) {
			if ($this->fields_values['item_filter_type'] == WidgetForm::ITEM_FILTER_ITEMIDS) {
				$options['itemids'] = $this->filteredItemids;
			}
			else {
				$this->updateTagsFromFilters($column);
				$options['tags'] = $column['item_tags'];
			}
		}

		$results = [];
		$num_hosts = count($hostids);
		$chunk_size = 950;
		$num_chunks = ceil($num_hosts / $chunk_size);

		for ($i = 0; $i < $num_chunks; $i++) {
			$chunk = array_slice($hostids, $i * $chunk_size, $chunk_size);
			$options['hostids'] = $chunk;
			$results += API::Item()->get($options);
		}

		return CArrayHelper::renameObjectsKeys($results, ['name_resolved' => 'name']);
	}

	private static function getItemValues(array &$db_column_items, array $column): array {
		static $history_period_s;

		if ($history_period_s === null) {
			$history_period_s = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));
		}

		$time_from = $column['aggregate_function'] != AGGREGATE_NONE
			? $column['time_period']['from_ts']
			: time() - $history_period_s;

		$items = self::addDataSource($db_column_items, $time_from, $column);

		$result = [];

		if ($column['aggregate_function'] != AGGREGATE_NONE) {
			$values = Manager::History()->getAggregatedValues($items, $column['aggregate_function'], $time_from,
				$column['time_period']['to_ts']
			);

			$result += array_column($values, 'value', 'itemid');
		}
		else {
			$items_by_source = ['history' => [], 'trends' => []];

			foreach (self::addDataSource($items, $time_from, $column) as $itemid => $item) {
				$items_by_source[$item['source']][$itemid] = $item;
			}

			if ($items_by_source['history']) {
				$values = Manager::History()->getLastValues($items_by_source['history'], 1, $history_period_s);
				$result += array_column(array_column($values, 0), 'value', 'itemid');
			}

			if ($items_by_source['trends']) {
				$values = Manager::History()->getAggregatedValues($items_by_source['trends'], AGGREGATE_LAST,
					$time_from
				);

				$result += array_column($values, 'value', 'itemid');
			}
		}

		return $result;
	}

	/**
	 * Return sparkline graph item values, applies data function SVG_GRAPH_MISSING_DATA_NONE on points for each item.
	 *
	 * @param array $items   Items required to get sparkline data for.
	 * @param array $column  Column configuration with sparkline configuration data.
	 *
	 * @return array itemid as key, sparkline data array of arrays as value, itemid with no data will be not present.
	 */
	private static function getItemSparklineValues(array $items, array $column): array {
		$result = [];
		$sparkline = $column['sparkline'];

		$items = self::addDataSource($items, $sparkline['time_period']['from_ts'],
			['history' => $sparkline['history']] + $column
		);

		if (!$items) {
			return $result;
		}

		$itemids_rows = Manager::History()->getGraphAggregationByWidth($items, $sparkline['time_period']['from_ts'],
			$sparkline['time_period']['to_ts'], $column['contents_width']
		);

		foreach ($itemids_rows as $itemid => $rows) {
			if (!$rows['data']) {
				continue;
			}

			$result[$itemid] = [];
			$points = array_column($rows['data'], 'avg', 'clock');
			/**
			 * Postgres may return entries in mixed 'clock' order, getMissingData for calculations
			 * requires order by 'clock'.
			 */
			ksort($points);
			$points += CSvgGraph::getMissingData($points, SVG_GRAPH_MISSING_DATA_NONE);
			ksort($points);

			foreach ($points as $ts => $value) {
				$result[$itemid][] = [$ts, $value];
			}
		}

		return $result;
	}

	private static function makeColumnizedTable(array $db_items, array $column, array $db_values,
			array $db_sparkline_values, string $layout): array {
		$columns_map = [];
		foreach ($db_items as $itemid => $db_item) {
			$value_type_group = match ((int) $db_item['value_type']) {
				ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT => 'numeric',
				ITEM_VALUE_TYPE_TEXT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG => 'text',
				ITEM_VALUE_TYPE_BINARY => 'binary'
			};

			$columns_map[$db_item['name']][$value_type_group][$db_item['key_']][$db_item['hostid']] = $itemid;
		}

		$result_columns = [];
		foreach ($columns_map as $name => $column_values) {
			foreach ($column_values as $value_type => $type_values) {
				usort($type_values, fn (array $left, array $right) => count($right) <=> count($left));
				$type_values = array_values($type_values);

				$columns = [];
				$values_size = count($type_values);
				foreach (array_keys($type_values) as $value_index) {
					$result = [];
					for ($next_index = $value_index; $next_index < $values_size; $next_index++) {
						if (!array_intersect_key($result, $type_values[$next_index])) {
							$result += $type_values[$next_index];
							$type_values[$next_index] = [];
						}
					}

					if ($result) {
						$columns[] = $result;
					}
				}

				$result_columns[$name][$value_type] = $columns;
			}
		}

		$table_column_index = -1;
		$hostids = array_keys(array_column($db_items, 'hostid', 'hostid'));
		$table = [];
		foreach ($result_columns as $name => $column_value_types) {
			foreach ($column_value_types as $hosts_columns) {
				foreach ($hosts_columns as $itemids) {
					$table_column_index += 1;

					foreach ($hostids as $hostid) {
						$itemid = $itemids[$hostid] ?? null;
						if ($itemid === null && ($layout == WidgetForm::LAYOUT_COLUMN_PER || $layout == WidgetForm::LAYOUT_THREE_COL)) {
							continue;
						}
						
						$value = $db_values[$itemid] ?? null;
						if ($value === null && ($layout == WidgetForm::LAYOUT_COLUMN_PER || $layout == WidgetForm::LAYOUT_THREE_COL)) {
							continue;
						}

						$sparkline_value = array_key_exists($itemid, $db_sparkline_values)
							? $db_sparkline_values[$itemid]
							: null;

						$table[$hostid][$table_column_index] = [
							Widget::CELL_HOSTID => $hostid,
							Widget::CELL_ITEMID => $itemid,
							Widget::CELL_VALUE => $value,
							Widget::CELL_SPARKLINE_VALUE => $sparkline_value,
							Widget::CELL_METADATA => [
								'name' => $name,
								'column_index' => $column['column_index'],
								'original_name' => ($itemid !== null &&
										array_key_exists($itemid, $db_items) &&
										array_key_exists('original_name', $db_items[$itemid]))
									? $db_items[$itemid]['original_name']
									: $name,
								'units' => ($itemid !== null &&
										array_key_exists($itemid, $db_items) &&
										array_key_exists('units', $db_items[$itemid]))
									? $db_items[$itemid]['units']
									: '',
								'key_' => ($itemid !== null && array_key_exists($itemid, $db_items))
									? $db_items[$itemid]['key_']
									: ''
								
							]
						];
					}
				}
			}
		}

		return $table;
	}

	private function applyItemOrderingLimitThreeCol(array &$table): void {
		$complete_set = [];
		foreach ($table as &$row) {
			foreach ($row as $hostid => &$values) {
				foreach ($values as $index => &$metric) {
					if ($metric[Widget::CELL_ITEMID] &&
							$metric[Widget::CELL_VALUE] !== null &&
							$metric[Widget::CELL_VALUE] !== '') {
						$complete_set[] = $metric;
					}
					else {
						unset($row[$hostid][$index]);
					}
					unset($metric);
				}
				unset($values);
			}
			unset($row);
		}
		
		switch ($this->fields_values['item_ordering_order']) {
			case WidgetForm::ORDER_TOP_N:
				usort($complete_set, function($a, $b) {
					return $b[2] <=> $a[2];
				});
				break;
			case WidgetForm::ORDER_BOTTOM_N:
				usort($complete_set, function($a, $b) {
					return $a[2] <=> $b[2];
				});
				break;
				
		}
		
		$complete_set = array_slice($complete_set, 0, $this->fields_values['item_ordering_limit']);
		$itemids_to_keep = [];
		$itemid_map = [];
		foreach ($complete_set as $metric) {
			$itemid = $metric[Widget::CELL_ITEMID];
			if (!isset($itemid_map[$itemid])) {
				$itemids_to_keep[] = $itemid;
				$itemid_map[$itemid] = true;
			}
		}
		
		foreach ($table as &$rowb) {
			foreach ($rowb as $hostidb => &$valuesb) {
				foreach ($valuesb as $indexb => &$metricb) {
					$itemida = $metricb[Widget::CELL_ITEMID];
					if (isset($itemid_map[$itemida]) && $itemid_map[$itemida]) {
						$itemid_map[$itemida] = false;
					}
					else {
						unset($rowb[$hostidb][$indexb]);
					}
					unset($metricb);
				}
				unset($valueb);
			}
			unset($rowb);
		}
	}
	
	private function applyItemOrdering(array &$table, array $db_hosts): void {
		if (!$table) {
			return;
		}

		$this->applyItemOrderingByName($table);
		if ($this->fields_values['item_ordering_order_by'] == WidgetForm::ORDERBY_ITEM_VALUE) {
			$this->applyItemOrderingByValue($table);
		}
		elseif ($this->fields_values['item_ordering_order_by'] == WidgetForm::ORDERBY_HOST) {
			$this->applyItemOrderingByHost($table, $db_hosts);
		}
	}

	private function applyItemOrderingLimit(array &$table): void {
		foreach ($table as &$row) {
			$row = array_slice($row, 0, $this->fields_values['item_ordering_limit']);
		}
		unset($row);
	}

	private static function concatenateTables(array $tables): array {
		$result_hostids = [];
		foreach ($tables as $table) {
			$result_hostids += array_flip(array_keys($table));
		}
		$result_hostids = array_keys($result_hostids);

		$result = [];
		foreach ($result_hostids as $hostid) {
			foreach ($tables as $table) {
				$result_row = $result[$hostid] ?? [];

				if (!array_key_exists($hostid, $table)) {
					$first_row = reset($table);
					if ($first_row === false) {
						continue;
					}
					$cells = [];
					foreach ($first_row as $cell) {
						$cells[] = [
							Widget::CELL_HOSTID => $hostid,
							Widget::CELL_ITEMID => null,
							Widget::CELL_VALUE => null,
							Widget::CELL_SPARKLINE_VALUE => null,
							Widget::CELL_METADATA => &$cell[Widget::CELL_METADATA]
						];
					}
				}
				else {
					$cells = $table[$hostid];
				}

				$result[$hostid] = [...$result_row, ...$cells];
			}
		}

		return $result;
	}

	private function applyHostOrdering(array &$table, array $db_hosts): void {
		if (!$table) {
			return;
		}

		if ($this->fields_values['host_ordering_order_by'] == WidgetForm::ORDERBY_ITEM_VALUE) {
			$this->orderHostsByItemValue($table);
		}
		else {
			$this->orderHostsByName($table, $db_hosts);
		}
	}

	private function applyHostOrderingLimit(array &$table): void {
		$result = [];
		$limit = $this->fields_values['host_ordering_limit'];
		foreach ($table as $hostid => $row) {
			if (--$limit < 0) {
				break;
			}

			$result[$hostid] = $row;
		}

		$table = $result;
	}

	private function calculateValueViews(array $columns, array &$table): void {
		if (!$table) {
			return;
		}

		function shouldAddToRowsWithViewValues($cell, $columns) {
			['column_index' => $column_index] = $cell[Widget::CELL_METADATA];
			$column = $columns[$column_index];

			return $column['display_value_as'] == CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC
					&& $column['display'] != CWidgetFieldColumnsList::DISPLAY_AS_IS
					&& $cell[Widget::CELL_VALUE] !== null;
		}

		$columns_with_view_values = [];
		$width = count($table[array_key_first($table)]);
		if ($this->fields_values['layout'] == WidgetForm::LAYOUT_COLUMN_PER) {
			$width = [];
			foreach ($table as $hostid => $t) {
				$width[] = count($t);
			}
			$width = min($width);
		}
		
		if ($this->fields_values['layout'] != WidgetForm::LAYOUT_THREE_COL) {
			if (!$this->isTemplateDashboard() &&
					$this->fields_values['layout'] == WidgetForm::LAYOUT_COLUMN_PER &&
					$this->fields_values['aggregate_all_hosts']) {
				foreach ($table as $grouping => $cell) {
					if (shouldAddToRowsWithViewValues($cell, $columns)) {
						$columns_with_view_values[] = $grouping;
					}
				}
			}
			else {
				for ($i = 0; $i < $width; $i++) {
					if (!array_key_exists($i, $table) &&
							$this->fields_values['layout'] != WidgetForm::LAYOUT_HORIZONTAL) {
						continue;
					}

					foreach ($table as [$i => $cell]) {
						if (shouldAddToRowsWithViewValues($cell, $columns)) {
							$columns_with_view_values[] = $i;
						}
					}
				}
			}
		}

		$rows_with_view_values = [];

		if (!$this->isTemplateDashboard() &&
				$this->fields_values['layout'] == WidgetForm::LAYOUT_COLUMN_PER &&
				$this->fields_values['aggregate_all_hosts']) {
			foreach ($table as $grouping => $cell) {
				if (shouldAddToRowsWithViewValues($cell, $columns)) {
					$rows_with_view_values[] = $grouping;
				}
			}
		}
		else {
			foreach ($table as $hostid => $row) {
				foreach ($row as $cell) {
					if (shouldAddToRowsWithViewValues($cell, $columns)) {
						$rows_with_view_values[] = $hostid;
					}
				}
			}
		}

		$rows_with_view_values = array_flip($rows_with_view_values);
		$columns_with_view_values = array_flip($columns_with_view_values);
		if (!$this->isTemplateDashboard() &&
				$this->fields_values['layout'] == WidgetForm::LAYOUT_COLUMN_PER
				&& $this->fields_values['aggregate_all_hosts']) {
			foreach ($table as $table_column_index => &$cell) {
				$cell[Widget::CELL_METADATA]['is_view_value_in_column'] = array_key_exists($table_column_index, $columns_with_view_values);
				$cell[Widget::CELL_METADATA]['is_view_value_in_row'] = array_key_exists($table_column_index, $rows_with_view_values);
			}
		}
		else {
			foreach ($table as $hostid => &$row) {
				foreach ($row as $table_column_index => &$cell) {
					$cell[Widget::CELL_METADATA]['is_view_value_in_column'] = array_key_exists($table_column_index, $columns_with_view_values);
					$cell[Widget::CELL_METADATA]['is_view_value_in_row'] = array_key_exists($hostid, $rows_with_view_values);
				}
			}
		}
	}
	
	private function perColumnOrdering(array $columns, array $table, array $fields): array {
		$orderComparison = function ($a, $b) use ($fields) {
			if ($fields['item_ordering_order_by'] == WidgetForm::ORDERBY_ITEM_NAME) {
				$fieldA = $a[Widget::CELL_METADATA]['grouping_name'];
				$fieldB = $b[Widget::CELL_METADATA]['grouping_name'];
				return ($fields['item_ordering_order'] == WidgetForm::ORDER_BOTTOM_N) ? strcmp($fieldB, $fieldA) : strcmp($fieldA, $fieldB);
			}
			else {
				$fieldA = $a[Widget::CELL_VALUE];
				$fieldB = $b[Widget::CELL_VALUE];
				return ($fields['item_ordering_order'] == WidgetForm::ORDER_BOTTOM_N) ? $fieldA <=> $fieldB : $fieldB <=> $fieldA;
			}
		};

		if (!$this->isTemplateDashboard() && $this->fields_values['aggregate_all_hosts']) {
			uasort($table, $orderComparison);
			return $table;
		}

		$groupedData = [];
		foreach ($table as $values) {
			foreach ($values as $item) {
				if (isset($item[Widget::CELL_VALUE]) && !is_null($item[Widget::CELL_VALUE]) && $item[Widget::CELL_VALUE] !== '') {
					$columnIndex = $item[Widget::CELL_METADATA]['column_index'];
					$groupedData[$columnIndex][] = $item;
				}
			}
		}
		
		$filteredData = [];

		if ($fields['host_ordering_order_by'] === WidgetForm::ORDERBY_ITEM_VALUE) {
			$limit = $fields['host_ordering_limit'];
		}
		else {
			$limit = $fields['item_ordering_limit'];
		}

		foreach ($groupedData as $columnIndex => $items) {
			usort($items, $orderComparison);
			$filteredData[$columnIndex] = array_slice($items, 0, $limit);
		}

		
		$uniqueGroupingNames = [];
		foreach ($filteredData as $items) {
			foreach ($items as $item) {
				$uniqueGroupingNames[] = $item[Widget::CELL_METADATA]['grouping_name'];
			}
		}
		$uniqueGroupingNames = array_unique($uniqueGroupingNames);
		
		foreach ($table as &$values) {
			$values = array_filter($values, function($item) use ($uniqueGroupingNames) {
				return in_array($item[Widget::CELL_METADATA]['grouping_name'], $uniqueGroupingNames);
			});
			$values = array_values($values);
		}
		
		foreach ($table as &$host) {
			usort($host, $orderComparison);
		}
		
		return $table;
	}
	
	private function perColumnAggregation(array &$columns, array &$table, bool $groupby_host): array {
		$final_table = [];
		foreach ($columns as $column_index => $column_configs) {
			if ($column_configs['column_agg_method'] !== AGGREGATE_NONE) {
				foreach ($table as $hostid => $items) {
					$values = [];
					$itemids = [];
					$my_cell = [];
					if ($groupby_host) {
						foreach ($items as $i => &$cell) {
							if ($cell[Widget::CELL_METADATA]['column_index'] == $column_index) {
								$values[] = $cell[Widget::CELL_VALUE];
								if ($cell[Widget::CELL_ITEMID]) {
									$itemids[] = $cell[Widget::CELL_ITEMID];
								}
								$my_cell = $cell;
							}
						}
						unset($cell);
						
						if (!$my_cell) {
							continue;
						}

						$final = $this->applyAggregation($column_configs['column_agg_method'], $values);
						$my_cell[Widget::CELL_VALUE] = $final;
						$my_cell[Widget::CELL_ITEMID] = null;
						if ($itemids) {
							$my_cell[Widget::CELL_ITEMID] = implode(',', $itemids);
						}
						$final_table[$hostid][] = $my_cell;
					}
					else {
						foreach ($items as $i => &$cell) {
							if ($cell[Widget::CELL_METADATA]['column_index'] == $column_index) {
								if ($cell[Widget::CELL_ITEMID]) {
									$grouping = $cell[Widget::CELL_METADATA]['grouping_name'];
									if (array_key_exists($grouping, $values)) {
										$values[$grouping]['values'][] = $cell[Widget::CELL_VALUE];
										$values[$grouping][Widget::CELL_ITEMID] .= ',' . $cell[Widget::CELL_ITEMID];
									}
									else {
										$values[$grouping] = $cell;
										$values[$grouping]['values'] = [$cell[Widget::CELL_VALUE]];
									}
								}
							}
						}
						
						
						foreach ($values as $group => &$value) {
							$value[Widget::CELL_VALUE] = $this->applyAggregation($column_configs['column_agg_method'], $value['values']);
							unset($value['values']);
							$final_table[$hostid][] = $value;
						}
					}
				}
			}
			else {
				foreach ($table as $hostid => $items) {
					foreach ($items as $i => &$cell) {
						if ($cell[Widget::CELL_METADATA]['column_index'] == $column_index) {
							if (array_key_exists($hostid, $final_table)) {
								$final_table[$hostid][] = $cell;
							}
							else {
								$final_table[$hostid] = [$column_index => $cell];
							}
						}
					}
				}
			}
		}

		if (!$this->isTemplateDashboard() && $this->fields_values['aggregate_all_hosts']) {
			$aggregatedArray = [];

			foreach ($final_table as $hostId => $hostData) {
				foreach ($hostData as $data) {
					$groupingName = $data[Widget::CELL_METADATA]['grouping_name'];
					$columnIndex = $data[Widget::CELL_METADATA]['column_index'];
					$key = $groupingName.chr(31).$columnIndex;

					if (!isset($aggregatedArray[$key])) {
						$aggregatedArray[$key] = [
							Widget::CELL_HOSTID => $hostId,
							Widget::CELL_ITEMID => (string)$data[Widget::CELL_ITEMID],
							Widget::CELL_VALUE => $data[Widget::CELL_VALUE],
							Widget::CELL_SPARKLINE_VALUE => $data[Widget::CELL_SPARKLINE_VALUE] ?? null,
							Widget::CELL_METADATA => $data[Widget::CELL_METADATA],
						];
					}
					else {
						$aggregatedArray[$key][Widget::CELL_HOSTID] .= ','.$hostId;
						$aggregatedArray[$key][Widget::CELL_ITEMID] .= ','.$data[Widget::CELL_ITEMID];

						$method = $columns[$columnIndex]['column_agg_method'];
						if ($aggregatedArray[$key][Widget::CELL_VALUE]) {
							$currentValues = explode(',', $aggregatedArray[$key][Widget::CELL_VALUE]);
						}
						else {
							$currentValues = [];
						}

						$currentValues[] = $data[Widget::CELL_VALUE];
						$aggregatedArray[$key][Widget::CELL_VALUE] = $this->applyAggregation($method, $currentValues);

						if (!empty($data[Widget::CELL_SPARKLINE_VALUE])) {
							if ($aggregatedArray[$key][Widget::CELL_SPARKLINE_VALUE] === null) {
								$aggregatedArray[$key][Widget::CELL_SPARKLINE_VALUE] = $data[Widget::CELL_SPARKLINE_VALUE];
							}
							else {
								foreach ($data[Widget::CELL_SPARKLINE_VALUE] as $subArray) {
									$timestamp = $subArray[Widget::CELL_HOSTID];
									$value = $subArray[1];
									$timestampExists = false;
									foreach ($aggregatedArray[$key][Widget::CELL_SPARKLINE_VALUE] as &$existingSubArray) {
										if ($existingSubArray[Widget::CELL_HOSTID] == $timestamp) {
											$timestampExists = true;
											$existingSubArray[1] = $this->applyAggregation($method, [$existingSubArray[1], $value]);
											break;
										}
									}

									if (!$timestampExists) {
										$aggregatedArray[$key][Widget::CELL_SPARKLINE_VALUE][] = $subArray;
									}
								}
							}
						}
					}
				}
			}

			foreach ($aggregatedArray as &$agg) {
				if (is_array($agg[Widget::CELL_SPARKLINE_VALUE])) {
					usort($agg[Widget::CELL_SPARKLINE_VALUE], function($a, $b) {
						return $a[0] <=> $b[0];
					});
				}
			}
			$final_table = $aggregatedArray;
		}
		return $final_table;
	}

	private function applyAggregation($method, $values) {
		switch ($method) {
			case AGGREGATE_SUM:
				return array_sum($values);
			case AGGREGATE_MAX:
				return max($values);
			case AGGREGATE_MIN:
				return min($values);
			case AGGREGATE_COUNT:
				return count($values);
			case AGGREGATE_AVG:
				return CMathHelper::safeAvg($values);
			default:
				return null;
		}
	}


	private function calculateExtremes(array &$columns, array $table): void {
		$column_min = [];
		$column_max = [];

		function updateColumnMinMax($cell, &$column_min, &$column_max) {
			$column_index = $cell[Widget::CELL_METADATA]['column_index'];
			$value = $cell[Widget::CELL_VALUE];
			if ($value === null) {
				return;
			}

			if (!array_key_exists($column_index, $column_min) || $column_min[$column_index] > $value) {
				$column_min[$column_index] = $value;
			}

			if (!array_key_exists($column_index, $column_max) || $column_max[$column_index] < $value) {
				$column_max[$column_index] = $value;
			}
		}

		if (!$this->isTemplateDashboard() &&
				$this->fields_values['layout'] == WidgetForm::LAYOUT_COLUMN_PER &&
				$this->fields_values['aggregate_all_hosts']) {
			foreach ($table as $cell) {
				updateColumnMinMax($cell, $column_min, $column_max);
			}
		}
		else {
			foreach ($table as $row) {
				foreach ($row as $cell) {
					updateColumnMinMax($cell, $column_min, $column_max);
				}
			}
		}

		foreach ($columns as $column_index => &$column) {
			$column['original_min'] = $column['min'];
			$column['original_max'] = $column['max'];
			if ($column['min'] === '') {
				$column['min'] = $column_min[$column_index] ?? '';
				$column['min_binary'] = $column['min'];
			}

			if ($column['max'] === '') {
				$column['max'] = $column_max[$column_index] ?? '';
				$column['max_binary'] = $column['max'];
			}
		}
		unset($column);
	}

	private function getProblemTriggers(array $itemids): array {
		$db_triggers = getTriggersWithActualSeverity([
			'output' => ['triggerid', 'priority', 'value'],
			'selectItems' => ['itemid'],
			'itemids' => $itemids,
			'only_true' => true,
			'monitored' => true,
			'preservekeys' => true
		], ['show_suppressed' => $this->fields_values['problems'] == WidgetForm::PROBLEMS_ALL]);

		$itemid_to_triggerids = [];
		foreach ($db_triggers as $triggerid => $db_trigger) {
			foreach ($db_trigger['items'] as $item) {
				if (!array_key_exists($item['itemid'], $itemid_to_triggerids)) {
					$itemid_to_triggerids[$item['itemid']] = [];
				}
				$itemid_to_triggerids[$item['itemid']][] = $triggerid;
			}
		}

		$result = [];
		foreach ($itemids as $itemid) {
			if (array_key_exists($itemid, $itemid_to_triggerids)) {
				$max_priority = -1;
				$max_priority_triggerid = -1;
				foreach ($itemid_to_triggerids[$itemid] as $triggerid) {
					$trigger = $db_triggers[$triggerid];

					if ($trigger['priority'] > $max_priority) {
						$max_priority_triggerid = $triggerid;
						$max_priority = $trigger['priority'];
					}
				}
				$result[$itemid] = $db_triggers[$max_priority_triggerid];
			}
		}

		return $result;
	}

	private static function reorderTableColumns(array &$table, array $index_map): void {
		foreach ($table as &$row) {
			$new_row = [];
			foreach ($index_map as $new_index) {
				$new_row[] = $row[$new_index];
			}
			$row = $new_row;
		}

		unset($row);
	}

	/**
	 * Table columns are mutually ordered by maximum or minimum value it has across hosts.
	 */
	private function applyItemOrderingByValue(array &$table): void {
		// Find max/min value for column across all hosts.
		$first_row = reset($table);
		$column_max = array_fill_keys(array_keys($first_row), null);
		$column_min = array_fill_keys(array_keys($first_row), null);
		foreach ($table as $row) {
			foreach ($row as $column_index => $cell) {
				$value = $cell[Widget::CELL_VALUE];
				if ($value === null) {
					continue;
				}

				if ($column_max[$column_index] === null) {
					$column_max[$column_index] = $value;
				}
				elseif ($value > $column_max[$column_index]) {
					$column_max[$column_index] = $value;
				}

				if ($column_min[$column_index] === null) {
					$column_min[$column_index] = $value;
				}
				elseif ($column_min[$column_index] > $value) {
					$column_min[$column_index] = $value;
				}
			}
		}

		$ordering_row_values = $this->fields_values['item_ordering_order'] == WidgetForm::ORDER_TOP_N
			? $column_max
			: $column_min;

		if ($this->fields_values['item_ordering_order'] == WidgetForm::ORDER_TOP_N) {
			arsort($ordering_row_values);
		}
		else {
			asort($ordering_row_values);
		}

		$index_map = array_keys($ordering_row_values);

		self::reorderTableColumns($table, $index_map);
	}

	/**
	 * If a column is found, it's values are used to order host rows.
	 */
	private function orderHostsByItemValue(array &$table): bool {
		$patterns = self::castWildcards($this->fields_values['host_ordering_item']);
		if (!$patterns) {
			return false;
		}

		$column_names = [];
		$column_keys = [];
		$column_idx = [];
		foreach ($table as $row) {
			foreach ($row as $cell) {
				$column_names[] = $cell[Widget::CELL_METADATA]['name'];
				$column_keys[] = $cell[Widget::CELL_METADATA]['key_'];
				$column_idx[] = $cell[Widget::CELL_METADATA]['column_index'];
			}
			break;
		}

		$column_idx = array_unique($column_idx);
		if (count($column_idx) > 1) {
			return false;
		}

		$ordering_column_options = [];
		foreach ($patterns as ['regex' => $regex, 'pattern' => $pattern]) {
			if (strpos($pattern, 'key\\=') === 0) {
				$regex = '/^' . substr($regex, 7);
				$pattern = substr($pattern, 5);
				foreach ($column_keys as $index => $column_key) {
					if ($column_key === $pattern || preg_match($regex, $column_key)) {
						$ordering_column_options[] = [$index, $column_key];
					}
				}
			}
			else {	
				foreach ($column_names as $index => $column_name) {
					if ($column_name === $pattern || preg_match($regex, $column_name)) {
						$ordering_column_options[] = [$index, $column_name];
					}
				}
			}
		}

		if (!$ordering_column_options) {
			return false;
		}

		usort($ordering_column_options, fn (array $left, array $right) => strnatcasecmp($left[1], $right[1]));
		$ordering_column_index = $ordering_column_options[0][0];

		$table_column = array_column($table, $ordering_column_index);
		$ordering_values = [];
		foreach ($table_column as $cell) {
			$hostid = $cell[Widget::CELL_HOSTID];
			$value = $cell[Widget::CELL_VALUE];

			$ordering_values[$hostid] = $value;
		}

		if ($this->fields_values['host_ordering_order'] == WidgetForm::ORDER_TOP_N) {
			arsort($ordering_values);
		}
		else {
			asort($ordering_values);
		}

		$result = [];
		foreach (array_keys($ordering_values) as $hostid) {
			$result[$hostid] = $table[$hostid];
		}

		$table = $result;

		return true;
	}

	private function orderHostsByName(array &$table, array $db_hosts): void {
		uksort($table, function (string $hostid_left, string $hostid_right) use (&$db_hosts) {
			$name_left = $db_hosts[$hostid_left]['name'];
			$name_right = $db_hosts[$hostid_right]['name'];

			return $this->fields_values['host_ordering_order'] == WidgetForm::ORDER_TOP_N
				? strnatcasecmp($name_left, $name_right)
				: strnatcasecmp($name_right, $name_left);
		});
	}

	private static function addDataSource(array $items, int $time, array $column): array {
		if ($column['history'] == CWidgetFieldColumnsList::HISTORY_DATA_AUTO) {
			$items = CItemHelper::addDataSource($items, $time);
		}
		else {
			foreach ($items as &$item) {
				$item['source'] = $column['history'] == CWidgetFieldColumnsList::HISTORY_DATA_TRENDS
					? 'trends'
					: 'history';
			}
			unset($item);
		}

		foreach ($items as &$item) {
			if (!in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])) {
				$item['source'] = 'history';
			}
		}

		unset($item);

		return $items;
	}

	private static function transposeTable(array $rows): array {
		$transposed = [];

		foreach ($rows as $rowidx => $row) {
			foreach ($row as $colidx => $cell) {
				foreach ($cell as $elementidx => $element) {
					$transposed[$colidx][$rowidx][$elementidx] = $element;
				}
			}
		}

		return $transposed;
	}

	public static function castWildcards(array $patterns): array {
		$result = [];

		foreach ($patterns as $pattern) {
			$pattern = preg_quote($pattern, '/');
			$result[] = [
				'regex' => '/^'.strtr($pattern, ['\\*' => '.*?']).'$/',
				'pattern' => $pattern
			];
		}

		return $result;
	}

	private function applyItemOrderingByHost(array &$table, array $db_hosts): bool {
		$patterns = self::castWildcards($this->fields_values['item_ordering_host']);
		if (!$patterns) {
			return false;
		}

		$table_host_names = [];
		foreach (array_keys($table) as $hostid) {
			$table_host_names[$hostid] = $db_hosts[$hostid]['name'];
		}

		$ordering_hosts = [];
		foreach ($patterns as ['regex' => $regex, 'pattern' => $pattern]) {
			foreach ($table_host_names as $hostid => $host_name) {
				if ($host_name === $pattern || preg_match($regex, $host_name)) {
					$ordering_hosts[] = [$hostid, $host_name];
				}
			}
		}

		if (!$ordering_hosts) {
			return false;
		}

		usort($ordering_hosts, fn (array $left, array $right) => strnatcasecmp($left[1], $right[1]));
		$ordering_hostid = $ordering_hosts[0][0];

		$ordering_row_values = array_column($table[$ordering_hostid], Widget::CELL_VALUE);

		if ($this->fields_values['item_ordering_order'] == WidgetForm::ORDER_TOP_N) {
			arsort($ordering_row_values);
		}
		else {
			asort($ordering_row_values);
		}

		$index_map = array_keys($ordering_row_values);

		self::reorderTableColumns($table, $index_map);

		return true;
	}

	private function applyItemOrderingByName(array &$table): void {
		$column_names = [];
		foreach ($table as $row) {
			foreach ($row as $cell) {
				$column_names[] = $cell[Widget::CELL_METADATA]['name'];
			}
			break;
		}

		if ($this->fields_values['item_ordering_order'] == WidgetForm::ORDER_TOP_N) {
			uasort($column_names, fn (string $name_left, string $name_right) => strnatcasecmp($name_left, $name_right));
		}
		else {
			uasort($column_names, fn (string $name_left, string $name_right) => strnatcasecmp($name_right, $name_left));
		}

		$index_map = array_keys($column_names);

		self::reorderTableColumns($table, $index_map);
	}
}

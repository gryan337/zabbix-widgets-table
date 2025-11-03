<?php declare(strict_types = 0);


/**
 * Top items widget view.
 *
 * @var CView $this
 * @var array $data
 */

use Modules\TableModuleRME\Includes\{
	CWidgetFieldColumnsList,
	WidgetForm
};
use Modules\TableModuleRME\Actions\WidgetViewTableRme;
use Modules\TableModuleRME\Widget;

$table = (new CTableInfo())->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);
$groupby_host = false;

if ($data['error'] !== null) {
	$table->setNoDataMessage($data['error'], null, ZBX_ICON_SEARCH_LARGE);
}
else {
	$header = [];
	$item_header = empty($data['item_header']) ? 'Items' : $data['item_header'];
	$host_header = empty($data['host_header']) ? 'Host' : $data['host_header'];

	$class = '';
	foreach ($data['configuration'] as $config) {
		if ($config['display'] === CWidgetFieldColumnsList::DISPLAY_SPARKLINE ||
				$config['display'] === CWidgetFieldColumnsList::DISPLAY_AS_IS) {
			$class = ZBX_STYLE_CENTER;
			break;
		}
	}

	if ($data['layout'] == WidgetForm::LAYOUT_VERTICAL) {
		$header[] = new CColHeader(_($item_header));

		foreach ($data['rows'][0] as $cell) {
			$hostid = $cell[Widget::CELL_HOSTID];
			$title = $data['db_hosts'][$hostid]['name'];
			['is_view_value_in_row' => $is_view_value] = $cell[Widget::CELL_METADATA];
			$header[] = (new CColHeader(
				($data['show_column_header'] == WidgetForm::COLUMN_HEADER_VERTICAL
					? (class_exists('CVertical') ? (new CVertical($title)) : (new CSpan($title))->addClass(ZBX_STYLE_TEXT_VERTICAL))
					: (new CSpan($title))
				)->setTitle($title)
			))->setColSpan($is_view_value ? 2 : 1)->addClass($class);
		}
	}
	elseif ($data['layout'] == WidgetForm::LAYOUT_HORIZONTAL) {
		$header[] = new CColHeader(_($host_header));

		foreach ($data['rows'][0] as $cell) {
			['name' => $title, 'is_view_value_in_column' => $is_view_value] = $cell[Widget::CELL_METADATA];
			$header[] = (new CColHeader(
				($data['show_column_header'] == WidgetForm::COLUMN_HEADER_VERTICAL
					? (class_exists('CVertical') ? (new CVertical($title)) : (new CSpan($title))->addClass(ZBX_STYLE_TEXT_VERTICAL))
					: (new CSpan($title))
				)->setTitle($title)
			))->setColSpan($is_view_value ? 2 : 1)->addClass($class);
		}
	}
	elseif ($data['layout'] == WidgetForm::LAYOUT_THREE_COL) {
		$header[] = new CColHeader(_($item_header));
		$header[] = new CColHeader(_($host_header));
		$is_view_value = false;
		foreach ($data['rows'] as $index => $values) {
			if ($is_view_value) {
				break;
			}
			foreach ($values as $index => $cell) {
				['is_view_value_in_row' => $is_view_value] = $cell[Widget::CELL_METADATA];
				if ($is_view_value) {
					break;
				}
			}
		}

		$header[] = (new CColHeader(
			($data['show_column_header'] == WidgetForm::COLUMN_HEADER_VERTICAL
				? (class_exists('CVertical') ? (new CVertical('Value')) : (new CSpan('Value'))->addClass(ZBX_STYLE_TEXT_VERTICAL))
				: (new CSpan('Value'))
			)->setTitle('Value')
		))->setColSpan($is_view_value ? 2 : 1)->addClass($class);
	}
	elseif ($data['layout'] == WidgetForm::LAYOUT_COLUMN_PER) {
		$groupby_host = (count($data['item_grouping']) === 1 &&
				$data['item_grouping'][0]['tag_name'] === '{HOST.HOST}')
			? true
			: false;

		if (!$groupby_host) {
			$header[] = new CColHeader(_($item_header));
		}

		if (count($data['num_hosts']) > 1 || $groupby_host) {
			$header[] = new CColHeader(_($host_header));
		}

		$is_view_value = [];
		foreach ($data['configuration'] as $column_index => $column) {
			switch ($column['display']) {
				case CWidgetFieldColumnsList::DISPLAY_SPARKLINE:
				case CWidgetFieldColumnsList::DISPLAY_INDICATORS:
				case CWidgetFieldColumnsList::DISPLAY_BAR:
					$is_view_value[$column_index] = 1;
					break;
				case CWidgetFieldColumnsList::DISPLAY_AS_IS:
					$is_view_value[$column_index] = '';
					break;
				default:
					$is_view_value[$column_index] = '';
			}
		}

		foreach ($data['rows'] as $row_index => &$cell) {
			foreach ($cell as $mindex => &$metrics) {
				$column_index = $metrics[Widget::CELL_METADATA]['column_index'];
				$metrics[Widget::CELL_METADATA]['is_view_value_in_column'] = $is_view_value[$column_index];
				$metrics[Widget::CELL_METADATA]['is_view_value_in_row'] = $is_view_value[$column_index];
			}
		}

		if (!$data['show_grouping_only']) {
			foreach ($data['configuration'] as $index => $config) {
				$title = $config['column_title'] ? $config['column_title'] : $config['items'][0];
				$ivv = array_key_exists($index, $is_view_value) ? ($is_view_value[$index] ? 2 : 1) : 1;
				$header[] = (new CColHeader(
					($data['show_column_header'] == WidgetForm::COLUMN_HEADER_VERTICAL
						? (class_exists('CVertical') ? (new CVertical($title)) : (new CSpan($title))->addClass(ZBX_STYLE_TEXT_VERTICAL))
						: (new CSpan($title))
					)->setTitle($title)
				))->setColSpan($ivv)->addClass($class);
			}
		}
	}

	if ($data['show_column_header'] != WidgetForm::COLUMN_HEADER_OFF) {
		$table->setHeader($header);
	}

	if ($data['layout'] == WidgetForm::LAYOUT_COLUMN_PER) {
		$new_rows = [];
		foreach ($data['rows'] as &$cell) {
			foreach ($cell as &$metrics) {
				$itemid = $metrics[Widget::CELL_ITEMID];
				$column_index = $metrics[Widget::CELL_METADATA]['column_index'];
				$name = $metrics[Widget::CELL_METADATA]['grouping_name'];
				if (!$name) {
					continue;
				}

				if (count($data['num_hosts']) > 1 || $groupby_host) {
					$name .= chr(31).$metrics[Widget::CELL_HOSTID];
				}

				if (!array_key_exists($name, $new_rows)) {
					$keys = array_keys($data['configuration']);
					$new_rows[$name] = array_fill_keys($keys, '');
				}

				$metrics[Widget::CELL_METADATA]['name'] = $name;

				$new_rows[$name][$column_index] = $metrics;
			}
		}

		if (!$groupby_host) {
			foreach ($new_rows as $n => $c) {
				$has = false;
				foreach ($c as $ri => $r) {
					if ($r && $r[Widget::CELL_ITEMID]) {
						$has = true;
						break;
					}
				}
				if (!$has) {
					unset($new_rows[$n]);
				}
			}
		}

		$data['rows'] = $new_rows;
		if ($data['aggregate_all_hosts'] ||
				($data['layout'] == WidgetForm::LAYOUT_COLUMN_PER && $data['host_order_by'] === WidgetForm::ORDERBY_ITEM_VALUE)) {
			$data['rows'] = topBottomNColPerPattern($data);
		}
	}

	global $min_max_sum;
	$min_max_sum = [];
	$three_column_layout = [];

	if ($data['bar_gauge_layout'] === WidgetForm::BAR_GAUGE_LAYOUT_COLUMN || $data['layout'] == WidgetForm::LAYOUT_THREE_COL) {
		foreach ($data['rows'] as $row_index => $dr) {
			foreach ($dr as $data_row) {
				if (!$data_row) {
					continue;
				}

				if ($data_row[Widget::CELL_ITEMID]) {
					if ($is_view_value) {
						$data_row[Widget::CELL_METADATA]['is_view_value_in_row'] = 1;
					}
					$three_column_layout[] = $data_row;
				}
				$column_index = $data_row[Widget::CELL_METADATA]['column_index'];

				$value = $data_row[Widget::CELL_VALUE];
				if (!is_numeric($value)) {
					continue;
				}
				
				if ($data['layout'] == WidgetForm::LAYOUT_VERTICAL) {
					$column_index = 0;
					$key = $data_row[Widget::CELL_HOSTID];
				}
				elseif ($data['layout'] == WidgetForm::LAYOUT_THREE_COL) {
					$column_index = 0;
					$key = 'None';
				}
				elseif ($data['layout'] == WidgetForm::LAYOUT_COLUMN_PER) {
					$key = 'None';
				}
				else {
					$key = $data_row[Widget::CELL_METADATA]['name'];
				}

				if (!array_key_exists($column_index, $min_max_sum)) {
					$min_max_sum[$column_index] = [];
				}

				if (array_key_exists($key, $min_max_sum[$column_index])) {
					$min_max_sum[$column_index][$key]['min'] = $value < $min_max_sum[$column_index][$key]['min']
						? $value
						: $min_max_sum[$column_index][$key]['min'];
					$min_max_sum[$column_index][$key]['max'] = $value > $min_max_sum[$column_index][$key]['max']
						? $value
						: $min_max_sum[$column_index][$key]['max'];
					$min_max_sum[$column_index][$key]['sum'] += $value;
				}
				else {
					$min_max_sum[$column_index][$key] = ['min' => $value, 'max' => $value, 'sum' => $value];
				}
			}
		}

		if ($data['layout'] == WidgetForm::LAYOUT_THREE_COL) {
			CArrayHelper::sort($three_column_layout, [[
				'field' => Widget::CELL_VALUE,
				'order' => $data['item_order'] == WidgetForm::ORDER_TOP_N ? ZBX_SORT_DOWN : ZBX_SORT_UP
			]]);
			$data['rows'] = $three_column_layout;
		}
	}

	if ($data['bar_gauge_layout'] === WidgetForm::BAR_GAUGE_LAYOUT_ROW && !($data['layout'] == WidgetForm::LAYOUT_THREE_COL)) {
		foreach ($data['rows'] as $row_index => $dr) {
			foreach ($dr as $data_row) {
				if (!$data_row) {
					continue;
				}

				$value = $data_row[Widget::CELL_VALUE];
				if (!is_numeric($value)) {
					continue;
				}
				
				if ($data['layout'] == WidgetForm::LAYOUT_HORIZONTAL) {
					$key = $data_row[Widget::CELL_HOSTID];
				}
				else {
					$key = $data_row[Widget::CELL_METADATA]['name'];
				}

				if (array_key_exists($key, $min_max_sum)) {
					$min_max_sum[$key]['min'] = $value < $min_max_sum[$key]['min']
						? $value
						: $min_max_sum[$key]['min'];
					$min_max_sum[$key]['max'] = $value > $min_max_sum[$key]['max']
						? $value
						: $min_max_sum[$key]['max'];
					$min_max_sum[$key]['sum'] += $value;
				}
				else {
					$min_max_sum[$key] = ['min' => $value, 'max' => $value, 'sum' => $value];
				}
			}
		}
	}

	if ($data['row_reset']) {
		$reset_row = [];
		$host_attributes = [
			'type' => 'host',
			'hostid' => '000000'
		];
		$host_cell_values = (new CSpan($data['row_reset']))
			->addClass(ZBX_STYLE_CURSOR_POINTER)
			->addStyle('font-weight: bold; color: #4796c4')
			->setAttribute('reset-row', '')
			->setAttribute('data-menu', json_encode($host_attributes));

		if ($data['layout'] == WidgetForm::LAYOUT_HORIZONTAL) {
			$reset_row[] = new CCol($host_cell_values);
			foreach ($data['rows'][0] as $row) {
				if ($row[Widget::CELL_METADATA]['is_view_value_in_column']) {
					$reset_row = [...$reset_row, ...[(new CCol()), (new CCol())]];
				}
				else {
					$reset_row = [...$reset_row, ...[(new CCol())]];
				}
			}
		}
		elseif ($data['layout'] == WidgetForm::LAYOUT_THREE_COL) {
			$reset_row = [(new CCol()), (new CCol($host_cell_values))];
			if ($data['rows'][0][Widget::CELL_METADATA]['is_view_value_in_row']) {
				$reset_row = [...$reset_row, ...[(new CCol()), (new CCol())]];
			}
			else {
				$reset_row = [...$reset_row, ...[(new CCol())]];
			}
		}
		elseif ($data['layout'] == WidgetForm::LAYOUT_COLUMN_PER && (count($data['num_hosts']) > 1 || $groupby_host)) {
			if (!$groupby_host) {
				$reset_row = [(new CCol()), (new CCol($host_cell_values))];
			}
			else {
				$reset_row = [(new CCol($host_cell_values))];
			}

			if (!$data['show_grouping_only']) {
				foreach ($is_view_value as $vv) {
					if ($vv) {
						$reset_row = [...$reset_row, ...[(new CCol()), (new CCol())]];
					}
					else {
						$reset_row = [...$reset_row, ...[(new CCol())]];
					}
				}
			}
		}
		$table->addRow($reset_row);
	}

	$bottom_row = [];

	foreach ($data['rows'] as $row_index => $data_row) {
		$table_row = [];

		$host_context_msg = 'Click here for host context menu';
		$host_attributes = [
			'type' => 'host',
			'hostid' => ''
		];

                // Table row heading.
		if ($data['layout'] == WidgetForm::LAYOUT_VERTICAL) {
			if ($data['footer']) {
				$bottom_row['host_column'] = (count($data['db_hosts']) > 1) ? 1 : 0;
				foreach ($data_row as $i => $r) {
					$bottom_row = buildBottomRow($bottom_row, $r, $i, $data);
				}
			}

            ['name' => $title] = $data_row[0][Widget::CELL_METADATA];
        	$table_row[] = new CCol($title);
		}
		elseif ($data['layout'] == WidgetForm::LAYOUT_THREE_COL) {
			if ($data['footer']) {
				$brindex = '0';
				$bottom_row['host_column'] = 1;
				$bottom_row = buildBottomRow($bottom_row, $data_row, $brindex, $data);
			}

			['name' => $title] = $data_row[Widget::CELL_METADATA];
			$table_row[] = new CCol($title);
			$host_attributes['hostid'] = $data_row[Widget::CELL_HOSTID];

			$host_cell_values = (new CSpan($data['db_hosts'][$host_attributes['hostid']]['name']));
			if (!$data['no_broadcast_hostid']) {
				$host_cell_values
					->addClass(ZBX_STYLE_CURSOR_POINTER)
					->addStyle('text-decoration: underline;')
					->setAttribute('data-menu', json_encode($host_attributes));
			}

			$host_context_button = (new CButton('', ''))
				->addClass('menu-btn')
				->setHint(
					(new CDiv($host_context_msg))->addClass(ZBX_STYLE_HINTBOX_WRAP), '', false, '', 100
				)
				->setMenuPopup(CMenuPopupHelper::getHost($host_attributes['hostid']));

			$table_row[] = new CCol([$host_cell_values, $host_context_button]);
			$table_row = [...$table_row, ...makeTableCellViews($data_row, $data)];
		}
		elseif ($data['layout'] == WidgetForm::LAYOUT_COLUMN_PER) {
			$dmt = ['type' => 'item'];
			$dm_itemids = [];
			foreach ($data_row as $index => $cell) {
				if ($cell && 
						($data['configuration'][$cell[Widget::CELL_METADATA]['column_index']]['broadcast_in_group_row'] || $data['show_grouping_only']) &&
						$cell[Widget::CELL_ITEMID]) {
					$dmt['name'] = $cell[Widget::CELL_METADATA]['grouping_name'];
					$tags = [];
					foreach ($data['item_grouping'] as $index => $grouping) {
						$grouping_name_parts = explode($data['delimiter'], $cell[Widget::CELL_METADATA]['grouping_name']);
						$tag_value = $grouping_name_parts[$index] ?? '';
						$tags[] = ['tag' => $grouping['tag_name'], 'value' => $tag_value];
					}
					$dmt['tags'] = json_encode($tags);

					$temp_itemids = explode(',', $cell[Widget::CELL_ITEMID]);
					foreach ($temp_itemids as $titemids) {
						$dm_itemids[] = [
							'itemid' => $titemids,
							'color' => $data['configuration'][$cell[Widget::CELL_METADATA]['column_index']]['display'] === CWidgetFieldColumnsList::DISPLAY_SPARKLINE
								? $data['configuration'][$cell[Widget::CELL_METADATA]['column_index']]['sparkline']['color']
								: $data['configuration'][$cell[Widget::CELL_METADATA]['column_index']]['base_color']
						];
					}
				}
			}
			$dmt['itemid'] = json_encode($dm_itemids);

			if ($data['footer']) {
				$bottom_row['host_column'] = (count($data['num_hosts']) > 1) ? 1 : 0;
				foreach ($data_row as $i => $r) {
					$bottom_row = buildBottomRow($bottom_row, $r, $i, $data, $is_view_value);
				}
			}

			if (count($data['num_hosts']) > 1 || $groupby_host) {
				if (!$groupby_host) {
					if ($dm_itemids) {
						$dmt_items = (new CSpan(explode(chr(31), $row_index)[0]))
							->addClass(ZBX_STYLE_CURSOR_POINTER)
							->setAttribute('data-menu', json_encode($dmt));
						$table_row[] = (new CCol($dmt_items))->addStyle('word-break: break-word; max-width: 35ch; color: #1187ff; text-decoration: underline');
					}
					else {
						$table_row[] = (new CCol(explode(chr(31), $row_index)[0]))->addStyle('word-break: break-word; max-width: 35ch;');
					}
				}

				foreach ($data_row as $row) {
					if ($row && $row[Widget::CELL_HOSTID]) {
						$host_attributes['hostid'] = $row[Widget::CELL_HOSTID];
						break;
					}
				}

				if (!$data['no_broadcast_hostid']) {
					if ($host_attributes['hostid']) {
						$host_cell_values = (new CSpan($data['db_hosts'][$host_attributes['hostid']]['name']))
							->addClass(ZBX_STYLE_CURSOR_POINTER)
							->addStyle('text-decoration: underline;')
							->setAttribute('data-menu', json_encode($host_attributes));

						$host_context_button = (new CButton('', ''))
							->addClass('menu-btn')
							->setHint(
								(new CDiv($host_context_msg))->addClass(ZBX_STYLE_HINTBOX_WRAP), '', false, '', 100
							)
							->setMenuPopup(CMenuPopupHelper::getHost($host_attributes['hostid']));

						$table_row[] = new CCol([$host_cell_values, $host_context_button]);
					}
					else {
						$table_row[] = new CCol('');
					}
				}
				else {
					if ($host_attributes['hostid']) {
						$host_cell_values = (new CSpan($data['db_hosts'][$host_attributes['hostid']]['name']));

						$host_context_button = (new CButton('', ''))
							->addClass('menu-btn')
							->setHint(
								(new CDiv($host_context_msg))->addClass(ZBX_STYLE_HINTBOX_WRAP), '', false, '', 100
							)
							->setMenuPopup(CMenuPopupHelper::getHost($host_attributes['hostid']));

						$table_row[] = new CCol([$host_cell_values, $host_context_button]);
					}
					else {
						$table_row[] = new CCol('');
					}
				}
			}
			else {
				if (!$groupby_host) {
					if ($dm_itemids) {
						$dmt_items = (new CSpan($row_index))
							->addClass(ZBX_STYLE_CURSOR_POINTER)
							->setAttribute('data-menu', json_encode($dmt));
						$table_row[] = (new CCol($dmt_items))->addStyle('word-break: break-word; max-width: 35ch; color: #1187ff; text-decoration: underline');
					}
					else {
						$table_row[] = (new CCol($row_index))->addStyle('word-break: break-word; max-width: 35ch;');
					}
				}
			}
		}
		else {
			if ($data['footer']) {
				$bottom_row['host_column'] = (count($data['db_hosts']) > 1) ? 1 : 0;
				foreach ($data_row as $i => $r) {
					$bottom_row = buildBottomRow($bottom_row, $r, $i, $data);
				}
			}

			$host_attributes['hostid'] = $data_row[0][Widget::CELL_HOSTID];
			$host_name = $data['db_hosts'][$host_attributes['hostid']]['name'];

			$host_cell_values = (new CSpan($host_name));
			if (!$data['no_broadcast_hostid']) {
				$host_cell_values
					->addClass(ZBX_STYLE_CURSOR_POINTER)
					->addStyle('text-decoration: underline;')
					->setAttribute('data-menu', json_encode($host_attributes));
			}

			$host_context_button = (new CButton('', ''))
				->addClass('menu-btn')
				->setHint(
					(new CDiv($host_context_msg))->addClass(ZBX_STYLE_HINTBOX_WRAP), '', false, '', 100
				)
				->setMenuPopup(CMenuPopupHelper::getHost($host_attributes['hostid']));

			$table_row[] = new CCol([$host_cell_values, $host_context_button]);

		}

		if ($data['layout'] == WidgetForm::LAYOUT_VERTICAL ||
				$data['layout'] == WidgetForm::LAYOUT_HORIZONTAL) {
			foreach ($data_row as $cell) {
				$table_row = [...$table_row, ...makeTableCellViews($cell, $data)];
			}
		}

		if ($data['layout'] == WidgetForm::LAYOUT_COLUMN_PER && !$data['show_grouping_only']) {
			foreach ($data_row as $column_index => $cell) {
				if ($cell) {
					$table_row = [...$table_row, ...makeTableCellViews($cell, $data)];
				}
				else {
					if (array_key_exists($column_index, $is_view_value) && $is_view_value[$column_index]) {
						$table_row[] = new CCol();
						$table_row[] = new CCol();
					}
					else {
						$table_row[] = new CCol();
					}
				}
			}
		}

		$table->addRow($table_row, ZBX_STYLE_DISPLAY_NONE);
        }

	if ($data['footer']) {
		if ($bottom_row && !$data['show_grouping_only']) {
			if (count($data['num_hosts']) <= 1 && $data['layout'] == WidgetForm::LAYOUT_COLUMN_PER) {
				$last_row = addBottomRow($data, $bottom_row, $groupby_host);
				$table->addRow($last_row);
			}
			else {
				$last_row = addBottomRow($data, $bottom_row, $groupby_host);
				$table->addRow($last_row);
			}
		}
	}

}

(new CWidgetView($data))
	->addItem($table)
	->show();


function makeUrl($cell, $column) {
	$urlItemids = array_map('trim', explode(',', $cell[Widget::CELL_ITEMID]));

	$url = (new CUrl('history.php'))
		->setArgument('itemids', $urlItemids)
		->setArgument('action', 'showvalues');

	if (array_key_exists('from', $column['time_period'])) {
		$url->setArgument('from', $column['time_period']['from']);
		$url->setArgument('to', $column['time_period']['to']);
	}

	$svg = (new CTag('svg', true))
		->addClass('ext-icon')
		->setAttribute('viewBox', '0 0 24 24');

	$path1 = (new CTag('path', true))
		->setAttribute('d', 'M14 3h7v7h-2V6.41l-9.29 9.3-1.42-1.42 9.3-9.29H14V3z');

	$path2 = (new CTag('path', true))
		->setAttribute('d', 'M5 5h5V3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-5h-2v5H5V5z');

	$svg->addItem($path1);
	$svg->addItem($path2);

	$link = (new CLink($svg, $url))
		->addClass('ext-btn')
		->setHint(
			(new CDiv('Click here to show raw values'))->addClass(ZBX_STYLE_HINTBOX_WRAP),
			'',
			false,
			'',
			100
		)
		->setTarget('_blank')
		->setAttribute('rel', 'noopener noreferrer');

	return $link;
}


function topBottomNColPerPattern($data) {
	$allRows = [];
	foreach ($data['rows'] as $group) {
		foreach ($group as $row) {
			if ($row) {
				$allRows[] = $row;
			}
		}
	}

	$groupedRows = [];
	foreach ($allRows as $row) {
		$columnIndex = $row[Widget::CELL_METADATA]['column_index'];
		if (!isset($groupedRows[$columnIndex])) {
			$groupedRows[$columnIndex] = [];
		}
		$groupedRows[$columnIndex][] = $row;
	}

	if ($data['host_order_by'] === WidgetForm::ORDERBY_ITEM_VALUE) {
		$patterns = WidgetViewTableRme::castWildcards($data['host_order_item']);
		$column_names = [];
		$column_keys = [];
		foreach ($groupedRows as $column) {
			foreach ($column as $cell) {
				$column_index = $cell[Widget::CELL_METADATA]['column_index'];
				if (!isset($column_names[$column_index])) {
					$column_names[$column_index] = [];
					$column_keys[$column_index] = [];
				}

				$column_names[$column_index][] = $cell[Widget::CELL_METADATA]['original_name'];
				$column_keys[$column_index][] = $cell[Widget::CELL_METADATA]['key_'];
			}
		}

		$ordering_column_options = [];

		foreach ($patterns as ['regex' => $regex, 'pattern' => $pattern]) {
			if (strpos($pattern, 'key\\=') === 0) {
				$regex = '/^' . substr($regex, 7);
				$pattern = substr($pattern, 5);
				foreach ($column_keys as $column_index => $keys) {
					foreach ($keys as $key) {
						if ($key === $pattern || preg_match($regex, $key)) {
							$ordering_column_options[] = [$column_index, $key];
						}
					}
				}
			}
			else {
				foreach ($column_names as $column_index => $names) {
					foreach ($names as $name) {
						if ($name === $pattern || preg_match($regex, $name)) {
							$ordering_column_options[] = [$column_index, $name];
						}
					}
				}
			}
		}

		if (!empty($ordering_column_options)) {
			$ordering_column_index = $ordering_column_options[0][0];
		}
		else {
			$ordering_column_index = null;
		}

		if ($ordering_column_index !== null) {
			uasort($data['rows'], function($a, $b) use ($ordering_column_index, $data) {
				return compareRows($a, $b, $ordering_column_index, $data['host_order']);
			});
		}

		$data['rows'] = array_slice($data['rows'], 0, $data['host_order_limit']);
		return $data['rows'];
	}
	else {
		foreach ($groupedRows as &$rows) {
			if ($data['item_order_by'] === WidgetForm::ORDERBY_ITEM_NAME) {
				usort($rows, function($a, $b) {
					return $b[Widget::CELL_METADATA]['grouping_name'] <=> $a[Widget::CELL_METADATA]['grouping_name'];
				});
			}
			elseif ($data['item_order_by'] === WidgetForm::ORDERBY_ITEM_VALUE) {
				usort($rows, function($a, $b) {
					return $b[Widget::CELL_VALUE] <=> $a[Widget::CELL_VALUE];
				});
			}

			if ($data['item_order'] === WidgetForm::ORDER_TOP_N) {
				$rows = array_slice($rows, 0, $data['item_order_limit']);
			}
			elseif ($data['item_order'] === WidgetForm::ORDER_BOTTOM_N) {
				$rows = array_slice($rows, -$data['item_order_limit']);
			}

			unset($rows);
		}
	}

	$names_to_keep = [];
	foreach ($groupedRows as $columnIndex => $rows) {
		foreach ($rows as $row) {
			if (!in_array($row[Widget::CELL_METADATA]['name'], $names_to_keep)) {
				$names_to_keep[] = $row[Widget::CELL_METADATA]['name'];
			}
		}
	}

	$reducedData = array_filter($data['rows'], function($k) use ($names_to_keep) {
		return in_array($k, $names_to_keep);
	}, ARRAY_FILTER_USE_KEY);

	return $reducedData;
}

function compareRows($a, $b, $ordering_column_index, $host_order) {
	if (!isset($a[$ordering_column_index]) || !isset($b[$ordering_column_index])) {
		return 0;
	}

	$a_value = isset($a[$ordering_column_index][Widget::CELL_VALUE])
		? $a[$ordering_column_index][Widget::CELL_VALUE]
		: null;
	$b_value = isset($b[$ordering_column_index][Widget::CELL_VALUE])
		? $b[$ordering_column_index][Widget::CELL_VALUE]
		: null;

	if ($a_value === null && $b_value === null) {
		return 0;
	}
	elseif ($a_value === null) {
		return ($host_order === WidgetForm::ORDER_TOP_N) ? 1 : -1;
	}
	elseif ($b_value === null) {
		return ($host_order === WidgetForm::ORDER_TOP_N) ? -1 : 1;
	}

	if ($a_value == $b_value) {
		return 0;
	}

	if ($host_order === WidgetForm::ORDER_TOP_N) {
		return ($a_value > $b_value) ? -1 : 1;
	}
	else {
		return ($a_value < $b_value) ? -1 : 1;
	}
}

function safeBcAdd($num1, $num2, $scale = 0) {
	if ($num1 === null) {
		$num1 = '0';
	}
	else {
		$num1 = number_format($num1, 0, '.', '') ?? '0';
	}

	if ($num2 === null) {
		$num2 = '0';
	}
	else {
		$num2 = number_format($num2, 0, '.', '') ?? '0';
	}

	return bcadd((string)$num1, (string)$num2, $scale);
}

function addBottomRow(array $data, array $bottom_row, bool $groupby_host = false): array {
	$user_theme = CWebUser::$data['theme'] === 'default'
		? CSettingsHelper::get(CSettingsHelper::DEFAULT_THEME)
		: CWebUser::$data['theme'];

	switch ($user_theme) {
		case 'dark-theme':
		case 'hc-light':
			$bg_color = '000000';
			$ft_color = 'FFFFFF';
			break;
		case 'hc-dark':
			$bg_color = 'FFFFFF';
			$ft_color = '000000';
			break;
		case 'blue-theme':
			$bg_color = 'B0BEC5';
			$ft_color = '000000';
			break;
		default:
			$bg_color = 'FFFFFF';
			$ft_color = '000000';
			break;
	}

	$new_bottom_row = [];
	$host_column = $bottom_row['host_column'];
	unset($bottom_row['host_column']);

	$footer_title = $data['footer'] == WidgetForm::FOOTER_SUM ? 'Total' : 'Average';

	foreach ($bottom_row as $i => $br) {
		$new_bottom_row[$i] = [];
		$footer_type = $data['footer'];

		if ($data['layout'] == WidgetForm::LAYOUT_COLUMN_PER) {
			switch ($data['configuration'][$i]['override_footer']) {
				case CWidgetFieldColumnsList::FOOTER_SHOW_NONE:
					$footer_type = null;
					break;
				case CWidgetFieldColumnsList::FOOTER_SHOW_SUM:
					$footer_type = WidgetForm::FOOTER_SUM;
					break;
				case CWidgetFieldColumnsList::FOOTER_SHOW_AVERAGE:
					$footer_type = WidgetForm::FOOTER_AVERAGE;
					break;
				case CWidgetFieldColumnsList::FOOTER_DONT_OVERRIDE:
				default:
					break;
			}
		}
		if ($footer_type == WidgetForm::FOOTER_SUM) {
			$sum = '0';
			foreach ($br['values'] as $value) {
				$sum = safeBcAdd($sum, $value, 2);
			}
			$new_bottom_row[$i]['values'] = $sum;
		}
		elseif ($footer_type == WidgetForm::FOOTER_AVERAGE) {
			$averageFilter = array_filter($br['values'], function($value) {
				return $value !== null;
			});
			$new_bottom_row[$i]['values'] = !empty($averageFilter) ? CMathHelper::safeAvg($averageFilter) : null;
		}
		elseif ($footer_type === null) {
			$new_bottom_row[$i]['values'] = null;
		}

		if ($data['layout'] == WidgetForm::LAYOUT_HORIZONTAL || $data['layout'] == WidgetForm::LAYOUT_VERTICAL) {
			$filteredArray = array_filter($br['units'], function($value) {
				return $value !== null && $value !== '';
			});
			$just_values = array_values($filteredArray);
			$new_bottom_row[$i]['units'] = array_unique($just_values);
		}
		else {
			$new_bottom_row[$i]['units'] = array_unique($br['units']);
		}
		$new_bottom_row[$i]['is_view_value'] = array_unique($br['is_view_value'])[0];
	}

	$last_row = [];
	$style = 'background-color: #' . $bg_color . '; color: #' . $ft_color;
	$last_row[] = (new CCol($footer_title))
		->setAttribute('footer-row', '')
		->addStyle($style);
	$style .= '; text-align: center';

	if ($data['layout'] == WidgetForm::LAYOUT_COLUMN_PER || $data['layout'] == WidgetForm::LAYOUT_THREE_COL) {
		if ($host_column && !($data['layout'] == WidgetForm::LAYOUT_COLUMN_PER && $groupby_host)) {
			$last_row[] = (new CCol())->addStyle($style);
		}
	}

	foreach ($new_bottom_row as $nbrindex => $nbrvalues) {
		$value_cell = new CCol(new CDiv());

		if ($nbrvalues['is_view_value']) {
			$last_row[] = (new CCol())->addStyle($style);
		}

		if (is_null($nbrvalues['values'])) {
			$value = '';
		}
		else {
			$if = (floor($nbrvalues['values']) == $nbrvalues['values'] &&
					!empty($nbrvalues['units']) &&
					$nbrvalues['units'][0] !== 'B' &&
					$nbrvalues['units'][0] !== 'bps')
				? 0
				: 2;

			if (count($nbrvalues['units']) === 1) {
				$converted_value = convertUnitsRaw([
					'value' => $nbrvalues['values'],
					'units' => $nbrvalues['units'][0]
				]);
				if ($converted_value['is_numeric']) {
					$converted_value['value'] = number_format($converted_value['value'], $if, '.', '');
				}
				$value = $converted_value['value'] . ($converted_value['units'] !== '' ? ' ' . $converted_value['units'] : '');
			}
			else {
				$value = number_format($nbrvalues['values'], $if, '.', '');
			}
		}

		$value_span = new CSpan($value);

		if ($data['layout'] == WidgetForm::LAYOUT_COLUMN_PER) {
			$override_footer = $data['configuration'][$nbrindex]['override_footer'] ?? CWidgetFieldColumnsList::FOOTER_DONT_OVERRIDE;
			if ($override_footer !== CWidgetFieldColumnsList::FOOTER_DONT_OVERRIDE && $override_footer !== CWidgetFieldColumnsList::FOOTER_SHOW_NONE) {
				$tooltip_text = '';
				switch ($override_footer) {
					case CWidgetFieldColumnsList::FOOTER_SHOW_SUM:
						$tooltip_text = 'Sum';
						break;
					case CWidgetFieldColumnsList::FOOTER_SHOW_AVERAGE:
						$tooltip_text = 'Average';
						break;
				}
				$override_icon = (new CSpan(new CHtmlEntity('&#9432;')))
					->addClass('override-icon')
					->setTitle($tooltip_text)
					->addStyle('background-color: blue; color: white; border-radius: 50%; padding: 0 4px; cursor: pointer; margin-left: 3px;');
				$value_span->addItem($override_icon);
			}
		}

		$value_cell->addItem(new CDiv($value_span));
		$value_cell->addClass(ZBX_STYLE_NOWRAP);
		$value_cell->addStyle($style);
		$last_row[] = $value_cell;
	}

	return $last_row;
}

function buildBottomRow(array $bottom_row, array|string $r, string $i, array $data, array $is_view_value = []): array {
	if (!array_key_exists($i, $bottom_row)) {
		$bottom_row[$i] = [
			'values' => [],
			'units' => [],
			'is_view_value' => []
		];
		if ($data['layout'] == WidgetForm::LAYOUT_THREE_COL) {
			$bottom_row[$i]['is_view_value'] = ['0' => ''];
		}
	}

	if ($r) {
		$bottom_row[$i]['values'][] = is_numeric($r[Widget::CELL_VALUE]) ? $r[Widget::CELL_VALUE] : null;
		$bottom_row[$i]['units'][] = $r[Widget::CELL_METADATA]['units'];
		switch ($data['layout']) {
			case WidgetForm::LAYOUT_VERTICAL:
				$bottom_row[$i]['is_view_value'][] = $r[Widget::CELL_METADATA]['is_view_value_in_row'];
				break;
			case WidgetForm::LAYOUT_COLUMN_PER:
				$bottom_row[$i]['is_view_value'][] = $is_view_value[$i];
				break;
			case WidgetForm::LAYOUT_THREE_COL:
				if (!$bottom_row[$i]['is_view_value'][$i]) {
					$bottom_row[$i]['is_view_value'][$i] = $r[Widget::CELL_METADATA]['is_view_value_in_row'];
				}
				break;
			case WidgetForm::LAYOUT_HORIZONTAL:
				$bottom_row[$i]['is_view_value'][] = $r[Widget::CELL_METADATA]['is_view_value_in_column'];
				break;
		}
	}
	else {
		$bottom_row[$i]['values'][] = null;
		if ($data['layout'] === WidgetForm::LAYOUT_COLUMN_PER) {
			$bottom_row[$i]['is_view_value'][] = $is_view_value[$i];
		}
	}

	return $bottom_row;
}


function makeTableCellViews(array $cell, array $data): array {
	$is_view_value = ($data['layout'] == WidgetForm::LAYOUT_VERTICAL || $data['layout'] == WidgetForm::LAYOUT_THREE_COL)
		? $cell[Widget::CELL_METADATA]['is_view_value_in_row']
		: $cell[Widget::CELL_METADATA]['is_view_value_in_column'];

	$column = $data['configuration'][$cell[Widget::CELL_METADATA]['column_index']];
	$itemid = $cell[Widget::CELL_ITEMID];
	$value = $cell[Widget::CELL_VALUE];

	$units = [];
	if ($itemid) {
		$final_unit = $cell[Widget::CELL_METADATA]['units'];
	}

	if ($itemid === null || $value === null) {
		if ($is_view_value) {
			return [(new CCol()), (new CCol())];
		}
		return [(new CCol())];
	}

	$formatted_value = makeTableCellViewFormattedValue($cell, $data);
	$trigger = $data['db_item_problem_triggers'][$itemid] ?? null;
	if ($trigger !== null) {
		return makeTableCellViewsTrigger($cell, $trigger, $formatted_value, $is_view_value, $final_unit, $data);
	}

	if ($column['display_value_as'] == CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC) {
		return makeTableCellViewsNumeric($cell, $data, $formatted_value, $is_view_value, $final_unit);
	}

	if ($column['display_value_as'] == CWidgetFieldColumnsList::DISPLAY_VALUE_AS_TEXT) {
		return makeTableCellViewsText($cell, $data, $formatted_value, $is_view_value, $final_unit);
	}

	if ($column['display_value_as'] == CWidgetFieldColumnsList::DISPLAY_VALUE_AS_URL) {
		return makeTableCellViewsUrl($cell, $data, $formatted_value, $is_view_value, $final_unit);
	}

	if ($is_view_value) {
		return [(new CCol()), (new CCol())];
	}
	return [(new CCol())];
}

function makeTableCellViewsNumeric(array $cell, array $data, $formatted_value, bool $is_view_value, string $units): array {
	global $min_max_sum;
	$column_index = $cell[Widget::CELL_METADATA]['column_index'];
	$itemid = explode(',', $cell[Widget::CELL_ITEMID])[0];
	$item = $data['db_items'][$itemid];
	$value = $cell[Widget::CELL_VALUE];
	$column = $data['configuration'][$column_index];
	$color = $column['base_color'];
	$font_color = $column['font_color'];

	$column_pos = $column_index;
	if ($min_max_sum &&
			$data['layout'] == WidgetForm::LAYOUT_VERTICAL &&
			$data['bar_gauge_layout'] === WidgetForm::BAR_GAUGE_LAYOUT_COLUMN) {
		$subArrayKeys = array_keys($min_max_sum[0]);
		$position = array_search($cell[Widget::CELL_HOSTID], $subArrayKeys);
		$column_pos = $position !== false
			? $position
			: $column_index;
	}
	else if ($data['layout'] == WidgetForm::LAYOUT_THREE_COL) {
		$column_pos = 0;
	}

	if ($column['go_to_history_values']) {
		$link = makeUrl($cell, $column);

		$value_cell = (new CCol(
			(new CDiv([$formatted_value, $link]))
				->addClass('value-with-icon')
		));
	}
	else {
		$value_cell = (new CCol(new CDiv($formatted_value)));
	}

	$value_cell
		->setAttribute('units', $units)
		->setAttribute('column-id', $column_pos)
		->addClass(ZBX_STYLE_NOWRAP);

	if ($data['layout'] === WidgetForm::LAYOUT_COLUMN_PER) {
		if ($data['configuration'][$column_index]['column_agg_method'] !== AGGREGATE_NONE) {
			if (!$data['configuration'][$column_index]['include_itemids']) {
			}
			else {
				$value_cell->addClass(ZBX_STYLE_CURSOR_POINTER);
			}
		}
		else {
			$value_cell->addClass(ZBX_STYLE_CURSOR_POINTER);
		}
	}
	else {
		$value_cell->addClass(ZBX_STYLE_CURSOR_POINTER);
	}

	if ($value !== '') {
		$value_cell->setHint((new CDiv($value))->addClass(ZBX_STYLE_HINTBOX_WRAP), '', false);
	}

	$styles = ['background-color: #' . $color];

	if ($font_color !== '') {
		$styles[] = 'color: #' . $font_color;
	}

	$styles[] = 'text-align: center';

	switch ($column['display']) {
		case CWidgetFieldColumnsList::DISPLAY_AS_IS:
			if ($column['thresholds']) {
				$is_numeric_data = in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]) ||
						CAggFunctionData::isNumericResult($column['aggregate_function']);

				if ($is_numeric_data) {
					foreach ($column['thresholds'] as $threshold) {
						if ($value < $threshold['threshold']) {
							break;
						}

						foreach ($styles as $key => $style) {
							if (strpos($style, 'background-color:') === 0) {
								unset($styles[$key]);
								break;
							}
						}

						$styles[] = 'background-color: #' . $threshold['color'];
					}
				}
			}

			$style = implode('; ', $styles);
			$value_cell->addStyle($style);

			if (!$is_view_value) {
				return [$value_cell];
			}

			return [(new CCol())->addStyle($style), $value_cell];

		case CWidgetFieldColumnsList::DISPLAY_SPARKLINE:
			if ($column['thresholds']) {
				foreach ($column['thresholds'] as $threshold) {
					if ($value < $threshold['threshold']) {
						break;
					}

					foreach ($styles as $key => $style) {
						if (strpos($style, 'background-color:') === 0) {
							unset($styles[$key]);
							break;
						}
					}
					
					$styles[] = 'background-color: #' . $threshold['color'];
				}
			}

			$style = implode('; ', $styles);
		
			$value_cell->addStyle($style);
			$sparkline_value = $cell[Widget::CELL_SPARKLINE_VALUE] ?? [];
			$sparkline = (new CSparkline())
				->setHeight(20)
				->setColor('#'.$column['sparkline']['color'])
				->setLineWidth($column['sparkline']['width'])
				->setFill($column['sparkline']['fill'])
				->setValue($sparkline_value)
				->setTimePeriodFrom($column['sparkline']['time_period']['from_ts'])
				->setTimePeriodTo($column['sparkline']['time_period']['to_ts']);

			$sparkline_cell = (new CCol($sparkline))->setAttribute('column-id', $column_pos);

			if ($data['layout'] == WidgetForm::LAYOUT_COLUMN_PER) {
				if ($data['configuration'][$cell[Widget::CELL_METADATA]['column_index']]['column_agg_method'] !== AGGREGATE_NONE) {
					if (!$data['configuration'][$cell[Widget::CELL_METADATA]['column_index']]['include_itemids']) {
						return [$sparkline_cell, $value_cell];
					}
				}
			}
			return [$sparkline_cell->addClass(ZBX_STYLE_CURSOR_POINTER), $value_cell];

		case CWidgetFieldColumnsList::DISPLAY_INDICATORS:
		case CWidgetFieldColumnsList::DISPLAY_BAR:
			$style = 'text-align: center; ';
			$style .= $font_color !== '' ? 'color: #'.$font_color : null;
			$value_cell->addStyle($style);

			switch ($data['layout']) {
				case WidgetForm::LAYOUT_VERTICAL:
					$column_index = 0;
					$key = $cell[Widget::CELL_HOSTID];
					break;
				case WidgetForm::LAYOUT_THREE_COL:
					$column_index = 0;
					$key = 'None';
					break;
				case WidgetForm::LAYOUT_COLUMN_PER:
					$key = 'None';
					break;
				default:
					$key = $cell[Widget::CELL_METADATA]['name'];
					break;
			}

			if ($data['bar_gauge_layout'] === WidgetForm::BAR_GAUGE_LAYOUT_ROW && $data['layout'] !== WidgetForm::LAYOUT_THREE_COL) {
				if ($data['layout'] == WidgetForm::LAYOUT_HORIZONTAL) {
					$key = $cell[Widget::CELL_HOSTID];
				}
				else {
					$key = $cell[Widget::CELL_METADATA]['name'];
				}
			}

			$columnar_min = $column['original_min'] !== ''
				? $column['min']
				: determineColumnarValue($min_max_sum, $column_index, $key, 'min', $column['min'], $data);
			$columnar_max = $column['original_max'] !== ''
				? $column['max']
				: determineColumnarValue($min_max_sum, $column_index, $key, 'max', $column['max'], $data);
			$columnar_sum = determineColumnarValue($min_max_sum, $column_index, $key, 'sum', 0, $data);

			$value_cell->setHint((new CDiv($value))->addClass(ZBX_STYLE_HINTBOX_WRAP), '', false);

			$bar_gauge = (new CBarGauge())
				->setValue($value)
				->setAttribute('fill', $color !== '' ? '#' . $color : Widget::DEFAULT_FILL)
				->setAttribute('min', $columnar_min);

			if ($data['bar_gauge_tooltip'] === WidgetForm::BAR_GAUGE_TOOLTIP_SUM) {
				$bar_gauge->setAttribute('max', $columnar_sum);
			}
			else {
				$bar_gauge->setAttribute('max', $columnar_max);
			}

			if ($column['display'] == CWidgetFieldColumnsList::DISPLAY_BAR) {
				$bar_gauge->setAttribute('solid', 1);
			}

			if (array_key_exists('thresholds', $column)) {
				foreach ($column['thresholds'] as $threshold) {
					$bar_gauge->addThreshold($threshold['threshold'], '#'.$threshold['color']);
				}
			}

			$str_word = 'column';
			if ($data['bar_gauge_layout'] === WidgetForm::BAR_GAUGE_LAYOUT_ROW && $data['layout'] !== WidgetForm::LAYOUT_THREE_COL) {
				$str_word = 'row';
			}

			if ($data['bar_gauge_tooltip'] === WidgetForm::BAR_GAUGE_TOOLTIP_MAX) {
				if ($columnar_max != 0) {
					$temp_value = $value / $columnar_max * 100;
				}
				else {
					$temp_value = 0;
				}
				$tooltip_value = number_format($temp_value, 3, '.', '') . ' % of ' . $str_word . ' max';
			}
			else if ($data['bar_gauge_tooltip'] === WidgetForm::BAR_GAUGE_TOOLTIP_SUM) {
				if ($columnar_sum != 0) {
					$temp_value = $value / $columnar_sum * 100;
				}
				else {
					$temp_value = 0;
				}
				$tooltip_value = number_format($temp_value, 3, '.', '') . ' % of ' . $str_word . ' sum';;
			}
			else {
				$tooltip_value = null;
			}

			$bar_gauge_cell = (new CCol($bar_gauge))
				->setAttribute('column-id', $column_pos);

			if ($tooltip_value !== null) {
				$bar_gauge_cell->setHint((new CDiv($tooltip_value))->addClass(ZBX_STYLE_HINTBOX_WRAP), '', false, '', 100);
			}

			if ($data['layout'] === WidgetForm::LAYOUT_COLUMN_PER) {
				if ($data['configuration'][$cell[Widget::CELL_METADATA]['column_index']]['column_agg_method'] !== AGGREGATE_NONE) {
					if (!$data['configuration'][$cell[Widget::CELL_METADATA]['column_index']]['include_itemids']) {
						return [$bar_gauge_cell, $value_cell];
					}
				}
			}

			return [$bar_gauge_cell->addClass(ZBX_STYLE_CURSOR_POINTER), $value_cell];
	}
}

function determineColumnarValue($min_max_sum, $column_index, $key, $type, $default, $data) {
	if ($data['bar_gauge_layout'] === WidgetForm::BAR_GAUGE_LAYOUT_COLUMN || $data['layout'] == WidgetForm::LAYOUT_THREE_COL) {
		return $min_max_sum[$column_index][$key][$type] ?? $default;
	}
	elseif ($data['bar_gauge_layout'] === WidgetForm::BAR_GAUGE_LAYOUT_ROW && $data['layout'] !== WidgetForm::LAYOUT_THREE_COL) {
		return $min_max_sum[$key][$type] ?? $default;
	}
	return $default;
}

function handleValueMapOverrideConfig(string $input, string $mode) {
	if (preg_match('/^(.*)\(([^()]*)\)\s*$/', $input, $matches)) {
		switch ($mode) {
			case CWidgetFieldColumnsList::VALUEMAP_AS_IS:
				return $input;

			case CWidgetFieldColumnsList::VALUEMAP_VALUE:
				return trim($matches[2]);

			case CWidgetFieldColumnsList::VALUEMAP_MAPPING:
				return trim($matches[1]);
		}
	}

	return $input;
}

function makeTableCellViewFormattedValue(array $cell, array $data): CSpan {
	$original_name = $cell[Widget::CELL_METADATA]['original_name'];
	$itemid = explode(',', $cell[Widget::CELL_ITEMID])[0];
	$value = $cell[Widget::CELL_VALUE];
	$column = $data['configuration'][$cell[Widget::CELL_METADATA]['column_index']];
	$color = $column['base_color'];
	$font_color = $column['font_color'];
	$item = $data['db_items'][$itemid];
	$item['units'] = array_key_exists('units', $cell[Widget::CELL_METADATA])
		? $cell[Widget::CELL_METADATA]['units']
		: $item['units'];

	if ($item['value_type'] == ITEM_VALUE_TYPE_BINARY) {
		$formatted_value = italic(_('binary value'))
			->addClass($color === '' ? ZBX_STYLE_GREY : null);
	}
	else {
		$formatted_value = formatAggregatedHistoryValue($value, $item,
			$column['aggregate_function'], false, true, [
				'decimals' => $column['decimal_places'],
				'decimals_exact' => true,
				'small_scientific' => false,
				'zero_as_zero' => false
			]
		);
	}

	if (array_key_exists('mappings', $item['valuemap']) &&
			!empty($item['valuemap']['mappings'])) {
		$formatted_value = handleValueMapOverrideConfig($formatted_value, $column['valuemap_override']);
	}


	$is_multiple_itemids = false;
	if ($data['layout'] === WidgetForm::LAYOUT_COLUMN_PER) {
		if ($data['configuration'][$cell[Widget::CELL_METADATA]['column_index']]['column_agg_method'] !== AGGREGATE_NONE) {
			if (!$data['configuration'][$cell[Widget::CELL_METADATA]['column_index']]['include_itemids']) {
				return (new CSpan($formatted_value));
			}
			else {
				$is_multiple_itemids = true;
			}
		}
	}

	$dmp = [
		'type' => 'item',
		'name' => $original_name
	];

	if ($is_multiple_itemids) {
		$temp_itemids = explode(',', $cell[Widget::CELL_ITEMID]);
		$dm_itemids = [];
		foreach ($temp_itemids as $titemids) {
			$dm_itemids[] = [
				'itemid' => $titemids,
				'color' => $data['configuration'][$cell[Widget::CELL_METADATA]['column_index']]['base_color']
			];
		}
		$dmp['itemid'] = json_encode($dm_itemids);
	}
	else {
		$dmp['itemid'] = $cell[Widget::CELL_ITEMID];
	}

	return (new CSpan($formatted_value))
		->addStyle('text-decoration-line: underline; text-decoration-style: dotted;')
		->setAttribute('data-menu', json_encode($dmp));
}

function makeTableCellViewsText(array $cell, array $data, $formatted_value, bool $is_view_value, string $units): array {
	$value = $cell[Widget::CELL_VALUE];
	$column = $data['configuration'][$cell[Widget::CELL_METADATA]['column_index']];
	$itemid = explode(',', $cell[Widget::CELL_ITEMID])[0];
	$item = $data['db_items'][$itemid];

	$color = $column['base_color'];
	$font_color = $column['font_color'];
	if (array_key_exists('highlights', $column)) {
		foreach ($column['highlights'] as $highlight) {
			if (@preg_match('('.$highlight['pattern'].')', $value)) {
				$color = $highlight['color'];
				break;
			}
		}
	}

	$styles = [];
	if ($color !== '') {
		$styles[] = 'background-color: #' . $color;
	}

	if ($font_color !== '') {
		$styles[] = 'color: #' . $font_color;
	}

	$styles[] = 'text-align: center';
	$style = implode('; ', $styles);

	if ($column['go_to_history_values']) {
		$link = makeUrl($cell, $column);

		$value_cell = (new CCol(
			(new CDiv([$formatted_value, $link]))
				->addClass('value-with-icon')
		));
	}
	else {
		$value_cell = (new CCol(new CDiv($formatted_value)));
	}

	$value_cell
		->addStyle($style)
		->setAttribute('units', $units)
		->addClass(ZBX_STYLE_NOWRAP);

	if ($value !== '') {
		$value_cell->setHint((new CDiv($value))->addClass(ZBX_STYLE_HINTBOX_WRAP), '', false);
	}

	if ($data['layout'] === WidgetForm::LAYOUT_COLUMN_PER) {
		if ($column['column_agg_method'] !== AGGREGATE_NONE) {
			if (!$column['include_itemids']) {
			}
			else {
				$value_cell->addClass(ZBX_STYLE_CURSOR_POINTER);
			}
		}
		else {
			$value_cell->addClass(ZBX_STYLE_CURSOR_POINTER);
		}
	}
	else {
		$value_cell->addClass(ZBX_STYLE_CURSOR_POINTER);
	}

	if ($is_view_value) {
		return [(new CCol())->addStyle($style), $value_cell];
	}

	return [$value_cell];
}

function makeTableCellViewsUrl(array $cell, array $data, $formatted_value, bool $is_view_value, string $units): array {
	$hostid = $cell[Widget::CELL_HOSTID];
	$itemid = explode(',', $cell[Widget::CELL_ITEMID])[0];
	$value = $cell[Widget::CELL_VALUE];
	$column = $data['configuration'][$cell[Widget::CELL_METADATA]['column_index']];
	$color = $column['base_color'];
	$font_color = $column['font_color'];

	$styles = [];
	if ($color !== '') {
		$styles[] = 'background-color: #' . $color;
	}

	$style_link = null;
	if ($font_color !== '') {
		$style_link = 'color: #' . $font_color;
	}

	$styles[] = 'text-align: center';
	$style = implode('; ', $styles);

	if ($column['url_display_mode'] == CWidgetFieldColumnsList::URL_DISPLAY_CUSTOM &&
			$column['url_custom_override'] != '' &&
			$column['url_custom_override'] != null) {
		$custom_url_resolved = CMacrosResolverHelper::resolveItemBasedWidgetMacros(
			[$itemid => [
				'label' => $column['url_custom_override'],
				'hostid' => $hostid
			]],
			['label' => 'label']
		);
		$value = $custom_url_resolved[$itemid]['label'];
	}

	if ($column['url_display_mode'] == CWidgetFieldColumnsList::URL_DISPLAY_CUSTOM &&
			$column['url_display_override'] != '' &&
			$column['url_display_override'] != null &&
			$value) {
		$resolved = CMacrosResolverHelper::resolveItemBasedWidgetMacros(
			[$itemid => [
				'label' => $column['url_display_override'],
				'hostid' => $hostid
			]],
			['label' => 'label']
		);
		$link = (new CLink($resolved[$itemid]['label'], (new CUrl($value))));
	}
	else {
		$link = (new CLink($value, (new CUrl($value))));
	}

	if ($style_link) {
		$link->addStyle($style_link);
	}

	if ($column['url_open_in'] == 1) {
		$link->setTarget('_blank');
	}

	$col = (new CCol($link))->addStyle($style);

	return [$col];

}

function makeTableCellViewsTrigger(array $cell, array $trigger, $formatted_value, bool $is_view_value, string $units, array $data): array {
	$value = $cell[Widget::CELL_VALUE];
	$column = $data['configuration'][$cell[Widget::CELL_METADATA]['column_index']];

	if ($trigger['problem']['acknowledged'] == EVENT_ACKNOWLEDGED) {
		$formatted_value = [$formatted_value, (new CSpan())->addClass(ZBX_ICON_CHECK)];
	}

	$class = CSeverityHelper::getStyle((int) $trigger['priority']);

	if ($column['go_to_history_values']) {
		$link = makeUrl($cell, $column);

		$value_cell = (new CCol(
			(new CDiv([$formatted_value, $link]))
				->addClass('value-with-icon')
		));
	}
	else {
		$value_cell = (new CCol(new CDiv($formatted_value)));
	}

	$value_cell
		->addClass($class)
		->setAttribute('units', $units)
		->addClass(ZBX_STYLE_CURSOR_POINTER)
		->addClass(ZBX_STYLE_NOWRAP);

	if ($value !== '') {
		$value_cell->setHint((new CDiv($value))->addClass(ZBX_STYLE_HINTBOX_WRAP), '', false);
	}

	if ($is_view_value) {
		return [(new CCol())->addClass($class), $value_cell->addStyle('text-align: center;')];
	}

	return [$value_cell->addStyle('text-align: center;')];
}

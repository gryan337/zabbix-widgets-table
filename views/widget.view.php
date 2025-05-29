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
use Modules\TableModuleRME\Widget;

$table = (new CTableInfo())->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER);
$groupby_host = false;

if ($data['error'] !== null) {
	$table->setNoDataMessage($data['error']);
}
else {
	if ($data['show_column_header'] != WidgetForm::COLUMN_HEADER_OFF) {
		$header = [];

		$class = '';
		foreach ($data['configuration'] as $config) {
			if ($config['display'] === CWidgetFieldColumnsList::DISPLAY_SPARKLINE ||
					$config['display'] === CWidgetFieldColumnsList::DISPLAY_AS_IS) {
				$class = ZBX_STYLE_CENTER;
				break;
			}
		}
				
		if ($data['layout'] == WidgetForm::LAYOUT_VERTICAL) {
			$item_header = empty($data['item_header']) ? 'Items' : $data['item_header'];
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
			$header[] = new CColHeader(_('Hosts'));

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
			$item_header = empty($data['item_header']) ? 'Items' : $data['item_header'];
			$header[] = new CColHeader(_($item_header));
			$header[] = new CColHeader(_('Host'));
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
				$item_header = empty($data['item_header']) ? 'Items' : $data['item_header'];
				$header[] = new CColHeader(_($item_header));
			}
			
			if (count($data['num_hosts']) > 1 || $groupby_host) {
				$header[] = new CColHeader(_('Host'));
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

		$table->setHeader($header);
	}

	global $min_and_max;
	$min_and_max = [];
	$three_column_layout = [];
	foreach ($data['rows'] as $row_index => $dr) {
		foreach ($dr as $data_row) {
			if ($data_row[Widget::CELL_ITEMID]) {
				if ($is_view_value) {
					$data_row[Widget::CELL_METADATA]['is_view_value_in_row'] = 1;
				}
				$three_column_layout[] = $data_row;
			}
			$column_index = $data_row[Widget::CELL_METADATA]['column_index'];
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
			$value = $data_row[2];

			if (!array_key_exists($column_index, $min_and_max)) {
				$min_and_max[$column_index] = [];
			}

			if (array_key_exists($key, $min_and_max[$column_index])) {
				$min_and_max[$column_index][$key]['min'] = $value < $min_and_max[$column_index][$key]['min']
					? $value
					: $min_and_max[$column_index][$key]['min'];
				$min_and_max[$column_index][$key]['max'] = $value > $min_and_max[$column_index][$key]['max']
					? $value
					: $min_and_max[$column_index][$key]['max'];
			}
			else {
				$min_and_max[$column_index][$key] = ['min' => $value, 'max' => $value];
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
		elseif ($data['layout'] == WidgetForm::LAYOUT_COLUMN_PER &&
				(count($data['num_hosts']) > 1 || $groupby_host)) {
			if (!$groupby_host) {
				$reset_row = [(new CCol()), (new CCol($host_cell_values))];
			}
			else {
				$reset_row = [(new CCol($host_cell_values))];
			}

			foreach ($is_view_value as $vv) {
				if ($vv) {
					$reset_row = [...$reset_row, ...[(new CCol()), (new CCol())]];
				}
				else {
					$reset_row = [...$reset_row, ...[(new CCol())]];
				}
			}
		}
		$table->addRow($reset_row);
	}

	$bottom_row = [];
	foreach ($data['rows'] as $row_index => $data_row) {
		$table_row = [];

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
			$host_cell_values = (new CSpan($data['db_hosts'][$host_attributes['hostid']]['name']))
				->addClass(ZBX_STYLE_CURSOR_POINTER)
				->addStyle('text-decoration: underline;')
				->setAttribute('data-menu', json_encode($host_attributes));
			$table_row[] = new CCol($host_cell_values);
			$table_row = [...$table_row, ...makeTableCellViews($data_row, $data)];
		}
		elseif ($data['layout'] == WidgetForm::LAYOUT_COLUMN_PER) {
			if ($data['footer']) {
				$bottom_row['host_column'] = (count($data['num_hosts']) > 1) ? 1 : 0;
				foreach ($data_row as $i => $r) {
					$bottom_row = buildBottomRow($bottom_row, $r, $i, $data, $is_view_value);
				}
			}

			if (count($data['num_hosts']) > 1 || $groupby_host) {
				if (!$groupby_host) {
					$table_row[] = (new CCol(explode(chr(31), $row_index)[0]))->addStyle('word-break: break-word; max-width: 35ch;');
				}

				foreach ($data_row as $row) {
					if ($row && $row[Widget::CELL_HOSTID]) {
						$host_attributes['hostid'] = $row[Widget::CELL_HOSTID];
						break;
					}
				}
				if ($host_attributes['hostid']) {
					$host_cell_values = (new CSpan($data['db_hosts'][$host_attributes['hostid']]['name']))
						->addClass(ZBX_STYLE_CURSOR_POINTER)
						->addStyle('text-decoration: underline;')
						->setAttribute('data-menu', json_encode($host_attributes));
					$table_row[] = new CCol($host_cell_values);
				}
				else {
					$table_row[] = new CCol('');
				}
			}
			else {
				if (!$groupby_host) {
					$table_row[] = (new CCol($row_index))->addStyle('word-break: break-word; max-width: 35ch;');
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
			$host_cell_values = (new CSpan($data['db_hosts'][$host_attributes['hostid']]['name']))
				->addClass(ZBX_STYLE_CURSOR_POINTER)
				->addStyle('text-decoration: underline;')
				->setAttribute('data-menu', json_encode($host_attributes));
			$table_row[] = new CCol($host_cell_values);
		}

		if ($data['layout'] == WidgetForm::LAYOUT_VERTICAL ||
				$data['layout'] == WidgetForm::LAYOUT_HORIZONTAL) {
			foreach ($data_row as $cell) {
				$table_row = [...$table_row, ...makeTableCellViews($cell, $data)];
			}
		}

		if ($data['layout'] == WidgetForm::LAYOUT_COLUMN_PER) {
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

		if (count($data['rows']) > WidgetForm::ROWS_PER_PAGE) {
			$table->addRow($table_row, ZBX_STYLE_DISPLAY_NONE);
		}
		else {
			$table->addRow($table_row);
		}
        }

	if ($data['footer']) {
		if ($bottom_row) {
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

function safeBcAdd($num1, $num2, $scale = 0) {
	$num1 = $num1 ?? '0';
	$num2 = $num2 ?? '0';
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
			$if = floor($nbrvalues['values']) == $nbrvalues['values'] ? 0 : 2;
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

		$value_cell->addItem(new CDiv($value_span));

		$value_cell->addClass(ZBX_STYLE_NOWRAP);

		if ($value !== '') {
			$value_cell->setHint(
				(new CDiv($nbrvalues['values']))->addClass(ZBX_STYLE_HINTBOX_WRAP), '', false
			);
		}

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

	if ($itemid === null || $value === null) {
		if ($is_view_value) {
			return [(new CCol()), (new CCol())];
		}
		return [(new CCol())];
	}

	$formatted_value = makeTableCellViewFormattedValue($cell, $data);
	$trigger = $data['db_item_problem_triggers'][$itemid] ?? null;
	if ($trigger !== null) {
		return makeTableCellViewsTrigger($cell, $trigger, $formatted_value, $is_view_value);
	}

	if ($column['display_value_as'] == CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC) {
		return makeTableCellViewsNumeric($cell, $data, $formatted_value, $is_view_value);
	}

	if ($column['display_value_as'] == CWidgetFieldColumnsList::DISPLAY_VALUE_AS_TEXT) {
		return makeTableCellViewsText($cell, $data, $formatted_value, $is_view_value);
	}

	if ($is_view_value) {
		return [(new CCol()), (new CCol())];
	}
	return [(new CCol())];
}

function makeTableCellViewsNumeric(array $cell, array $data, $formatted_value, bool $is_view_value): array {
	global $min_and_max;
	$column_index = $cell[Widget::CELL_METADATA]['column_index'];
	$item = $data['db_items'][$cell[Widget::CELL_ITEMID]];
	$value = $cell[Widget::CELL_VALUE];
	$column = $data['configuration'][$column_index];
	$color = $column['base_color'];

	$value_cell = (new CCol(new CDiv($formatted_value)))
		->addClass(ZBX_STYLE_NOWRAP);

	if ($data['layout'] === WidgetForm::LAYOUT_COLUMN_PER &&
			$data['configuration'][$column_index]['column_agg_method'] !== AGGREGATE_NONE) {
	}
	else {
		$value_cell->addClass(ZBX_STYLE_CURSOR_POINTER);
	}

	if ($value !== '') {
		$value_cell->setHint((new CDiv($value))->addClass(ZBX_STYLE_HINTBOX_WRAP), '', false);
	}

	switch ($column['display']) {
		case CWidgetFieldColumnsList::DISPLAY_AS_IS:
			if ($column['thresholds']) {
				$is_numeric_data = in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]) || CAggFunctionData::isNumericResult($column['aggregate_function']);
				if ($is_numeric_data) {
					foreach ($column['thresholds'] as $threshold) {
						if ($value < $threshold['threshold']) {
							break;
						}

						$color = $threshold['color'];
					}
				}
			}

			$style = $color !== '' ? 'background-color: #'.$color : null;
			$style .= '; text-align: center';
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
					$color = $threshold['color'];
				}
			}

			$style = $color !== '' ? 'background-color: #'.$color : null;
			$style .= ';text-align: center';
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

			if ($data['layout'] == WidgetForm::LAYOUT_COLUMN_PER && $data['configuration'][$cell[Widget::CELL_METADATA]['column_index']]['column_agg_method'] !== AGGREGATE_NONE) {
				return [new CCol($sparkline), $value_cell];
			}
			else {
				return [(new CCol($sparkline))->addClass(ZBX_STYLE_CURSOR_POINTER), $value_cell];
			}

		case CWidgetFieldColumnsList::DISPLAY_INDICATORS:
		case CWidgetFieldColumnsList::DISPLAY_BAR:
			$style = 'text-align: center';
			$value_cell->addStyle($style);
			if ($data['layout'] == WidgetForm::LAYOUT_VERTICAL) {
				$column_index = 0;
				$key = $cell[Widget::CELL_HOSTID];
			}
			elseif ($data['layout'] == WidgetForm::LAYOUT_THREE_COL) {
				$column_index = 0;
				$key = 'None';
			}
			elseif ($data['layout'] == WidgetForm::LAYOUT_COLUMN_PER) {
				$key = 'None';
			}
			else {
				$key = $cell[Widget::CELL_METADATA]['name'];
			}

			if ($column['original_min'] !== '') {
				$columnar_min = $column['min'];
			}
			else {
				$columnar_min = $min_and_max[$column_index][$key]['min']
					? $min_and_max[$column_index][$key]['min']
					: $column['min'];
			}

			if ($column['original_max'] !== '') {
				$columnar_max = $column['max'];
			}
			else {
				$columnar_max = $min_and_max[$column_index][$key]['max']
					? $min_and_max[$column_index][$key]['max']
					: $column['max'];
			}

			$bar_gauge = (new CBarGauge())
				->setValue($value)
				->setAttribute('fill', $color !== '' ? '#' . $color : Widget::DEFAULT_FILL)
				->setAttribute('min', isBinaryUnits($item['units'])
					? $column['min_binary']
					: $columnar_min
				)
				->setAttribute('max', isBinaryUnits($item['units'])
					? $column['max_binary']
					: $columnar_max
				);

			if ($column['display'] == CWidgetFieldColumnsList::DISPLAY_BAR) {
				$bar_gauge->setAttribute('solid', 1);
			}

			if (array_key_exists('thresholds', $column)) {
				foreach ($column['thresholds'] as $threshold) {
					$bar_gauge->addThreshold($threshold['threshold'], '#'.$threshold['color']);
				}
			}

			if ($data['layout'] === WidgetForm::LAYOUT_COLUMN_PER && $data['configuration'][$cell[Widget::CELL_METADATA]['column_index']]['column_agg_method'] !== AGGREGATE_NONE) {
				return [(new CCol($bar_gauge)), $value_cell];
			}
			else {
				return [(new CCol($bar_gauge))->addClass(ZBX_STYLE_CURSOR_POINTER), $value_cell];
			}
	}
}

function makeTableCellViewFormattedValue(array $cell, array $data): CSpan {
	$original_name = $cell[Widget::CELL_METADATA]['original_name'];
	$itemid = $cell[Widget::CELL_ITEMID];
	$value = $cell[Widget::CELL_VALUE];
	$column = $data['configuration'][$cell[Widget::CELL_METADATA]['column_index']];
	$color = $column['base_color'];
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

	if ($data['layout'] === WidgetForm::LAYOUT_COLUMN_PER && $data['configuration'][$cell[Widget::CELL_METADATA]['column_index']]['column_agg_method'] !== AGGREGATE_NONE) {
		return (new CSpan($formatted_value));
	}

	$dmp = [
		'type' => 'item',
		'itemid' => $itemid,
		'name' => $original_name
	];

	return (new CSpan($formatted_value))
		->setAttribute('data-menu', json_encode($dmp));
}

function makeTableCellViewsText(array $cell, array $data, $formatted_value, bool $is_view_value): array {
	$value = $cell[Widget::CELL_VALUE];
	$column = $data['configuration'][$cell[Widget::CELL_METADATA]['column_index']];

	$color = '';
	if (array_key_exists('highlights', $column)) {
		foreach ($column['highlights'] as $highlight) {
			if (@preg_match('('.$highlight['pattern'].')', $value)) {
				$color = $highlight['color'];
				break;
			}
		}
	}

	$style = $color !== '' ? 'background-color: #'.$color : null;
	$style .= '; text-align: center';
	$value_cell = (new CCol(new CDiv($formatted_value)))
		->addStyle($style)
		->addClass(ZBX_STYLE_CURSOR_POINTER)
		->addClass(ZBX_STYLE_NOWRAP);

	if ($value !== '') {
		$value_cell->setHint((new CDiv($value))->addClass(ZBX_STYLE_HINTBOX_WRAP), '', false);
	}

	if ($is_view_value) {
		return [(new CCol())->addStyle($style), $value_cell];
	}

	return [$value_cell];
}

function makeTableCellViewsTrigger(array $cell, array $trigger, $formatted_value, bool $is_view_value): array {
	$value = $cell[Widget::CELL_VALUE];

	if ($trigger['problem']['acknowledged'] == EVENT_ACKNOWLEDGED) {
		$formatted_value = [$formatted_value, (new CSpan())->addClass(ZBX_ICON_CHECK)];
	}

	$class = CSeverityHelper::getStyle((int) $trigger['priority']);
	$value_cell = (new CCol(new CDiv($formatted_value)))
		->addClass($class)
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

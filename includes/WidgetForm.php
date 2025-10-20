<?php declare(strict_types = 0);


namespace Modules\TableModuleRME\Includes;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldCheckBox,
	CWidgetFieldIntegerBox,
	CWidgetFieldMultiSelectGroup,
	CWidgetFieldMultiSelectHost,
	CWidgetFieldMultiSelectItem,
	CWidgetFieldMultiSelectOverrideHost,
	CWidgetFieldPatternSelectHost,
	CWidgetFieldPatternSelectItem,
	CWidgetFieldRadioButtonList,
	CWidgetFieldTags,
	CWidgetFieldTextArea,
	CWidgetFieldTextBox
};

/**
 * Top items data widget form.
 */
class WidgetForm extends CWidgetForm {
	
	public const LAYOUT_HORIZONTAL = 0;
	public const LAYOUT_VERTICAL = 1;
	public const LAYOUT_THREE_COL = 50;
	public const LAYOUT_COLUMN_PER = 51;

	public const ITEM_FILTER_ITEMIDS = 0;
	public const ITEM_FILTER_TAGS = 1;
	
	public const FOOTER_NONE = 0;
	public const FOOTER_SUM = 1;
	public const FOOTER_AVERAGE = 2;

	public const ORDER_TOP_N = 2;
	public const ORDER_BOTTOM_N = 3;

	public const PROBLEMS_ALL = 0;
	public const PROBLEMS_UNSUPPRESSED = 1;
	public const PROBLEMS_NONE = 2;

	public const COLUMN_HEADER_OFF = 0;
	public const COLUMN_HEADER_HORIZONTAL = 1;
	public const COLUMN_HEADER_VERTICAL = 2;

	public const ORDERBY_HOST_NAME = 0;
	public const ORDERBY_HOST = 1;
	public const ORDERBY_ITEM_NAME = 2;
	public const ORDERBY_ITEM_VALUE = 3;

	public const BAR_GAUGE_LAYOUT_COLUMN = 0;
	public const BAR_GAUGE_LAYOUT_ROW = 1;

	public const BAR_GAUGE_TOOLTIP_MAX = 0;
	public const BAR_GAUGE_TOOLTIP_SUM = 1;
	public const BAR_GAUGE_TOOLTIP_NONE = 2;

	public const DEFAULT_DELIMITER = ' / ';

	public function addFields(): self {
		return $this
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldMultiSelectGroup('groupids', _('Host groups'))
			)
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldMultiSelectHost('hostids', _('Hosts'))
			)
			->addField(
				(new CWidgetFieldMultiSelectItem('itemid', _('Item filter')))
					->setMultiple(true)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('item_filter_type', _('Item filter type'), [
					self::ITEM_FILTER_ITEMIDS => _('Itemids'),
					self::ITEM_FILTER_TAGS => _('Tags')
				]))->setDefault(self::ITEM_FILTER_ITEMIDS)
			)
			->addField(
				new CWidgetFieldCheckBox('update_item_filter_only', _('Item filter update only'))
			)
			->addField($this->isTemplateDashboard()
				? null
				: (new CWidgetFieldRadioButtonList('host_tags_evaltype', _('Host tags'), [
					TAG_EVAL_TYPE_AND_OR => _('And/Or'),
					TAG_EVAL_TYPE_OR => _('Or')
				]))->setDefault(TAG_EVAL_TYPE_AND_OR)
			)
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldTags('host_tags')
			)
			->addField(
				(new CWidgetFieldRadioButtonList('problems', _('Show problems'), [
					self::PROBLEMS_ALL => _('All'),
					self::PROBLEMS_UNSUPPRESSED => _('Unsuppressed'),
					self::PROBLEMS_NONE => _('None')
				]))->setDefault(self::PROBLEMS_UNSUPPRESSED)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('layout', _('Layout'), [
					self::LAYOUT_HORIZONTAL => _('Horizontal'),
					self::LAYOUT_VERTICAL => _('Vertical'),
					self::LAYOUT_THREE_COL => _('3 Column'),
					self::LAYOUT_COLUMN_PER => _('Column per pattern')
				]))->setDefault(self::LAYOUT_HORIZONTAL)
			)
			->addField(
				new CWidgetFieldTableModuleItemGrouping('item_group_by', _('Item grouping'))
			)
			->addField(
				(new CWidgetFieldTextBox('grouping_delimiter', _('Grouping delimiter')))
					->setValue(self::DEFAULT_DELIMITER)
					->setFlags(CWidgetField::FLAG_NOT_EMPTY)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('show_column_header', _('Show column header'), [
					self::COLUMN_HEADER_OFF => _('Off'),
					self::COLUMN_HEADER_HORIZONTAL => _('Horizontal'),
					self::COLUMN_HEADER_VERTICAL => _('Vertical')
				]))->setDefault(self::COLUMN_HEADER_VERTICAL)
			)
			->addField(
				(new CWidgetFieldColumnsList('columns', _('Items')))
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('bar_gauge_layout', _('Bar gauge layout'), [
					self::BAR_GAUGE_LAYOUT_COLUMN => _('Column'),
					self::BAR_GAUGE_LAYOUT_ROW => _('Row')
				]))->setDefault(self::BAR_GAUGE_LAYOUT_COLUMN)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('bar_gauge_tooltip', _('Bar gauge tooltip'), [
					self::BAR_GAUGE_TOOLTIP_MAX => _('Max'),
					self::BAR_GAUGE_TOOLTIP_SUM => _('Sum'),
					self::BAR_GAUGE_TOOLTIP_NONE => _('None')
				]))->setDefault(self::BAR_GAUGE_TOOLTIP_MAX)
			)
			->addField(
				new CWidgetFieldCheckBox('no_broadcast_hostid', _('Disallow host broadcasting'))
			)
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldCheckBox('aggregate_all_hosts', _('Aggregate all hosts'))
			)
			->addField(
				new CWidgetFieldCheckBox('show_grouping_only', _('Show item grouping only'))
			)
			->addField(
				new CWidgetFieldCheckBox('autoselect_first', _('Autoselect first cell'))
			)
			->addField(
				(new CWidgetFieldRadioButtonList('footer', _('Show footer row'), [
					self::FOOTER_NONE => _('No footer'),
					self::FOOTER_SUM => _('Sum'),
					self::FOOTER_AVERAGE => _('Average')
				]))->setDefault(self::FOOTER_NONE)
			)
			->addField(
				new CWidgetFieldTextBox('item_header', _('Item header name'))
			)
			->addField(
				new CWidgetFieldTextBox('host_header', _('Host header name'))
			)
			->addField(
				new CWidgetFieldTextBox('reset_row', _('Add reset row'))
			)
			->addField(
				new CWidgetFieldTextArea('item_name_strip', _('Metric label'))
			)

			// Advanced configuration fields - host ordering.
			->addField(
				(new CWidgetFieldRadioButtonList('host_ordering_order_by', _('Order by'), [
					self::ORDERBY_HOST_NAME => _('Host name'),
					self::ORDERBY_ITEM_VALUE => _('Item value')
				]))
					->setDefault(self::ORDERBY_HOST_NAME)
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldPatternSelectItem('host_ordering_item', _('Item')))
					->prefixLabel(_('Host ordering'))
			)
			->addField(
				(new CWidgetFieldRadioButtonList('host_ordering_order', _('Order'), [
					self::ORDER_TOP_N => _('Top N'),
					self::ORDER_BOTTOM_N => _('Bottom N')
				]))->setDefault(self::ORDER_TOP_N)
			)
			->addField(
				(new CWidgetFieldIntegerBox('host_ordering_limit', _('Limit'), ZBX_MIN_WIDGET_LINES,
					ZBX_MAX_WIDGET_LINES
				))
					->prefixLabel(_('Host ordering'))
					->setDefault(10)
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			)

			// Advanced configuration fields - item ordering.
			->addField(
				(new CWidgetFieldRadioButtonList('item_ordering_order_by', _('Order by'), [
					self::ORDERBY_ITEM_VALUE => _('Item value'),
					self::ORDERBY_ITEM_NAME => _('Item name'),
					self::ORDERBY_HOST => _('Host')
				]))
					->setDefault(self::ORDERBY_ITEM_VALUE)
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldPatternSelectHost('item_ordering_host', _('Host')))
					->prefixLabel(_('Item ordering'))
			)
			->addField(
				(new CWidgetFieldRadioButtonList('item_ordering_order', _('Order'), [
					self::ORDER_TOP_N => _('Top N'),
					self::ORDER_BOTTOM_N => _('Bottom N')
				]))->setDefault(self::ORDER_TOP_N)
			)
			->addField(
				(new CWidgetFieldIntegerBox('item_ordering_limit', _('Limit'), ZBX_MIN_WIDGET_LINES,
					ZBX_MAX_WIDGET_LINES
				))
					->prefixLabel(_('Item ordering'))
					->setDefault(10)
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				new CWidgetFieldMultiSelectOverrideHost()
			);
	}

	public function validate(bool $strict = false): array {
		if ($this->getField('host_ordering_order_by')->getValue() == self::ORDERBY_ITEM_VALUE) {
			$this->getField('host_ordering_item')->setFlags(CWidgetField::FLAG_NOT_EMPTY);
		}

		if ($this->getField('item_ordering_order_by')->getValue() == self::ORDERBY_HOST) {
			$this->getField('item_ordering_host')->setFlags(CWidgetField::FLAG_NOT_EMPTY);
		}

		$item_groupings = $this->getField('item_group_by')->getValue();
		if (!$this->isTemplateDashboard() &&
				$this->getField('aggregate_all_hosts')->getValue() == 1 &&
				count($item_groupings) == 1 &&
				$item_groupings[0]['tag_name'] === '{HOST.HOST}') {
			$errors[] = _s('Cannot group by {HOST.HOST} and aggregate by all hosts');
			return $errors;
		}

		$errors = parent::validate($strict);

		$itemIdErrorToCheck = 'Invalid parameter "Item filter/1": a number is expected.';

		$errorIndex = array_search($itemIdErrorToCheck, $errors);
		if ($errorIndex !== false) {
			unset($errors[$errorIndex]);
		}

		if (!$this->isTemplateDashboard()) {
			$aggregate_hosts = $this->getField('aggregate_all_hosts')->getValue();
			if ($aggregate_hosts) {
				$columns = $this->getField('columns')->getValue();
				foreach ($columns as $column) {
					if ($column['column_agg_method'] === AGGREGATE_NONE) {
						$key = $column['items'][0];
						$errors[] = _s('Form validation failure: When using \'Aggregate all hosts\' a \'Column patterns aggregation\' choice is required in the \'Items\' form');
						$errors[] = _s('Column with failure: "%1$s"', $key);
						return $errors;
					}
				}
			}
		}

		return $errors;
	}
}

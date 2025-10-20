<?php declare(strict_types = 0);

/**
 * Top items widget form view.
 *
 * @var CView $this
 * @var array $data
 */

use Modules\TableModuleRME\Includes\CWidgetFieldColumnsListView;
use Modules\TableModuleRME\Includes\CWidgetFieldTableModuleItemGroupingView;

$form = new CWidgetFormView($data);

$groupids = array_key_exists('groupids', $data['fields'])
	? new CWidgetFieldMultiSelectGroupView($data['fields']['groupids'])
	: null;

$form
	->addField($groupids)
	->addField(array_key_exists('hostids', $data['fields'])
		? (new CWidgetFieldMultiSelectHostView($data['fields']['hostids']))
			->setFilterPreselect([
				'id' => $groupids->getId(),
				'accept' => CMultiSelect::FILTER_PRESELECT_ACCEPT_ID,
				'submit_as' => 'groupid'
			])
		: null
	)
	->addField(
		(new CWidgetFieldMultiSelectItemView($data['fields']['itemid']))
			->setPopupParameter('value_types', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])
	)
	->addField(
		(new CWidgetFieldRadioButtonListView($data['fields']['item_filter_type']))
			->setFieldHint(
				makeHelpIcon([
					_('Choose whether you want to filter using itemids or tags from the filtering widget widget'), BR(), BR(),
					_('Currently, only the Item navigator widget broadcasts tags, but only if the Item navigator is configured with a \'Group by\' of \'Item tag value\'')
				])
			)
	)
	->addField(
		(new CWidgetFieldCheckBoxView($data['fields']['update_item_filter_only']))
			->setFieldHint(
				makeHelpIcon([
					_('Checking this box means that this widget will only display metrics when there is an Item filter set'), BR(),
					_('If the Item filter is a widget, a selection from that referred widget is the only way this widget will display metrics')
				])
			)
	)
	->addField(array_key_exists('host_tags_evaltype', $data['fields'])
		? new CWidgetFieldRadioButtonListView($data['fields']['host_tags_evaltype'])
		: null
	)
	->addField(array_key_exists('host_tags', $data['fields'])
		? new CWidgetFieldTagsView($data['fields']['host_tags'])
		: null
	)
	->addField(
		(new CWidgetFieldRadioButtonListView($data['fields']['layout']))
			->setFieldHint(
				makeHelpIcon([
					_('Horizontal - Host in first column. Values per item/metrics in subsequent columns'), BR(),
					_('Vertical - Item/Metric name in first column. Values per host in subsequent columns'), BR(),
					_('3 Column - Item/Metric name in first column. Host in second column. Values per item/metrics in third column'), BR(),
					_('Column Per pattern - Each item pattern specified receives its own column')
				])
			)
	)
	->addField(
		(new CWidgetFieldTableModuleItemGroupingView($data['fields']['item_group_by']))
			->setFieldHint(
				makeHelpIcon([
					_('The tags chosen will be displayed in first column of the table.'), BR(),
					_('Alternatively, you can just group the metrics by host, which will omit the first column, '),
					_('by specifying a grouping of \'{HOST.HOST}\'')
				])
			)
			->addRowClass('field_item_group_by')
	)
	->addField(
		(new CWidgetFieldTextBoxView($data['fields']['grouping_delimiter']))
			->setFieldHint(
				makeHelpIcon([
					_('Allows for customizing the Item grouping delimiter.'), BR(),
					_('By default, the delimiter is \' / \' of nothing is specified here.')
				])
			)
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->addRowClass('field_grouping_delimiter')
	)
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['problems'])
	)
	->addField(
		(new CWidgetFieldColumnsListView($data['fields']['columns']))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	)
	->addField(
		(new CWidgetFieldRadioButtonListView($data['fields']['bar_gauge_layout']))
			->setFieldHint(
				makeHelpIcon([
					_('Choose how to display the bar gauges in the table. Selecting \'Column\' will show proportions within each column, while selecting \'Row\' will show proportions within each row.')
				])
			)
	)
	->addField(
		(new CWidgetFieldRadioButtonListView($data['fields']['bar_gauge_tooltip']))
			->setFieldHint(
				makeHelpIcon([
					_('When hovering over a bar gauge in the table it will show what the corresponding value cell\'s proportion is as a percentage.'), BR(),
					_('The default is to show the ratio to the max value. However, by choosing \'Sum\' you can show what the corresponding value cell\'s proportion is to the sum of all value cells, or you can show no tooltip.'), BR(),
					_('NOTE: The proportion shown will use the choice from \'Bar gauge layout\' above')
				])
			)
	)
	->addField(
		(new CWidgetFieldCheckBoxView($data['fields']['no_broadcast_hostid']))
			->setFieldHint(
				makeHelpIcon([
					_('Turns off the ability to broadcast the hostid to other widgets when hosts are visible in the table')
				])
			)
			->addRowClass('field_no_broadcast_hostid')
	)
	->addField(array_key_exists('aggregate_all_hosts', $data['fields'])
		? (new CWidgetFieldCheckBoxView($data['fields']['aggregate_all_hosts']))
			->setFieldHint(
				makeHelpIcon([
					_('Checking this box will aggregate all values, by the item grouping above, across all hosts'), BR(), BR(),
					_('NOTE: Checking this box requires a \'Column patterns aggregation\' to be set in the \'Items\' '), BR(),
					_('configuration popup under the \'Advanced Configuration\' section'), BR(), BR(),
					_('OTHER NOTE: when using this \'Host ordering\' options from the Advanced configuration section below are ignored')
				])
			)
			->addRowClass('field_aggregate_all_hosts')
		: null
	)
	->addField(
		(new CWidgetFieldCheckBoxView($data['fields']['show_grouping_only']))
			->setFieldHint(
				makeHelpIcon([
					_('Checking this box will cause only the \'Item grouping\' column to be displayed'), BR(),
					_('This is useful for when you want to use this widget to act as a filter to other widgets instead of showing metrics.'), BR(),
					_('NOTE: Checking this box automatically causes \'Broadcast from grouped column\' to be checked for each Item pattern specified')
				])
			)
			->addRowClass('field_show_grouping_only')
	)
	->addField(
		(new CWidgetFieldCheckBoxView($data['fields']['autoselect_first']))
			->setFieldHint(
				makeHelpIcon([
					_('Checking this box will cause the first value and host cell to be automatically selected')
				])
			)
	)
	->addField(
		(new CWidgetFieldRadioButtonListView($data['fields']['footer']))
			->setFieldHint(
				makeHelpIcon([
					_('If set, a footer row will be added at the bottom of the table')
				])
			)
	)
	->addField(
		(new CWidgetFieldTextBoxView($data['fields']['item_header']))
			->setFieldHint(
				makeHelpIcon([
					_('Changes the header name from the default of \'Items\' to this value when using all layouts except Horizontal')
				])
			)
	)
	->addField(
		(new CWidgetFieldTextBoxView($data['fields']['host_header']))
			->setFieldHint(
				makeHelpIcon([
					_('Changes the header name from the default of \'Host\' to this value when using all layouts except Vertical')
				])
			)
	)
	->addField(
		(new CWidgetFieldTextBoxView($data['fields']['reset_row']))
			->setFieldHint(
				makeHelpIcon([
					_('By typing a value into this box you will add a reset row to the widget with the value you entered.'), BR(),
					_('A reset row is used with layouts of \'Horizontal\', \'3 Column\', and \'Column per pattern\'.'), BR(),
					_('After a click on the reset row value, connected widgets will reset back to their base configurations.')
				])
			)
	)
	->addField(
		(new CWidgetFieldTextAreaView($data['fields']['item_name_strip']))
			->setFieldHint(
				makeHelpIcon([
					_('Set the row (Vertical) or column (Horizontal/3 Column) label for the metric name'), BR(),
					_('Supported macros:'),
					(new CList([
						'{HOST.*}',
						'{ITEM.*}',
						'{INVENTORY.*}',
						_('User macros'),
					]))->addClass(ZBX_STYLE_LIST_DASHED)
				])
			)
	)
	->addFieldset(
		(new CWidgetFormFieldsetCollapsibleView(_('Advanced configuration')))
			->addFieldsGroup(
				(new CWidgetFieldsGroupView(_('Host ordering')))
					->addField(
						new CWidgetFieldRadioButtonListView($data['fields']['host_ordering_order_by'])
					)
					->addField(
						(new CWidgetFieldPatternSelectItemView($data['fields']['host_ordering_item']))
							->removeLabel()
							->addClass(CFormField::ZBX_STYLE_FORM_FIELD_OFFSET_1)
					)
					->addField(
						new CWidgetFieldRadioButtonListView($data['fields']['host_ordering_order'])
					)
					->addField(
						new CWidgetFieldIntegerBoxView($data['fields']['host_ordering_limit'])
					)
					->addRowClass('fields-group-host-ordering')
			)
			->addFieldsGroup(
				(new CWidgetFieldsGroupView(_('Item ordering')))
					->addField(
						new CWidgetFieldRadioButtonListView($data['fields']['item_ordering_order_by'])
					)
					->addField(
						(new CWidgetFieldPatternSelectHostView($data['fields']['item_ordering_host']))
							->removeLabel()
							->addClass(CFormField::ZBX_STYLE_FORM_FIELD_OFFSET_1)
					)
					->addField(
						new CWidgetFieldRadioButtonListView($data['fields']['item_ordering_order'])
					)
					->addField(
						(new CWidgetFieldIntegerBoxView($data['fields']['item_ordering_limit']))
							->setFieldHint(makeHelpIcon(_('Limit applies to each "Item pattern" separately')))
					)
					->addRowClass('fields-group-item-ordering')
			)
			->addField(
				new CWidgetFieldRadioButtonListView($data['fields']['show_column_header'])
			)
	)
	->includeJsFile('widget.edit.js.php')
	->initFormJs('widget_tablemodulerme_form.init('.json_encode([
		'templateid' => $data['templateid']
	], JSON_THROW_ON_ERROR).');')
	->show();

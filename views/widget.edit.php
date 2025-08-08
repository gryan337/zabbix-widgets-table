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
					_('Column Per Pattern - Each item pattern specified receives its own column')
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
		new CWidgetFieldRadioButtonListView($data['fields']['problems'])
	)
	->addField(
		(new CWidgetFieldColumnsListView($data['fields']['columns']))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
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
	->addField(
		(new CWidgetFieldCheckBoxView($data['fields']['aggregate_all_hosts']))
			->setFieldHint(
				makeHelpIcon([
					_('Checking this box will aggregate all values, by the item grouping specified, across all hosts'), BR(), BR(),
					_('NOTE: Checking this box requires a \'Column Patterns Aggregation\' to be set in the \'Items\' '), BR(),
					_('configuration popup under the \'Advanced Configuration\' section')
				])
			)
			->addRowClass('field_aggregate_all_hosts')
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
					_('Changes the header name from the default of \'Items\' to this value')
				])
			)
	)
	->addField(
		(new CWidgetFieldTextBoxView($data['fields']['reset_row']))
			->setFieldHint(
				makeHelpIcon([
					_('By typing a value into this box you will add a reset row to the widget with the value you entered.'), BR(),
					_('A reset row is used with layouts of \'Horizontal\', \'3 Column\', and \'Column per Pattern\'.'), BR(),
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

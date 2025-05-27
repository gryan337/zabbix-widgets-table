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
		new CWidgetFieldRadioButtonListView($data['fields']['layout'])
	)
	->addField(
		(new CWidgetFieldTableModuleItemGroupingView($data['fields']['item_group_by']))
			->addRowClass('field_item_group_by')
	)
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['problems'])
	)
	->addField(
		(new CWidgetFieldColumnsListView($data['fields']['columns']))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	)
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['footer'])
	)
	->addField(
		new CWidgetFieldTextBoxView($data['fields']['item_header'])
	)
	->addField(
		new CWidgetFieldTextBoxView($data['fields']['reset_row'])
	)
	->addField(
		new CWidgetFieldTextAreaView($data['fields']['item_name_strip'])
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
	->addJavaScript('widget_tablemodulerme_form.init('.json_encode([
		'templateid' => $data['templateid']
	], JSON_THROW_ON_ERROR).');')
	->show();

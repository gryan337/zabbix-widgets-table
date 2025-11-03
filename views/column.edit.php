<?php


/**
 * @var CView $this
 * @var array $data
 */

use Modules\TableModuleRME\Includes\CWidgetFieldColumnsList;
use Zabbix\Widgets\Fields\CWidgetFieldSparkline;

$form = (new CForm())
	->setId('tablemodulerme_column_edit_form')
	->setName('tablemodulerme_column')
	->addStyle('display: none;')
	->addVar('action', $data['action'])
	->addVar('update', 1);

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$form_grid = new CFormGrid();

if (array_key_exists('edit', $data)) {
	$form->addVar('edit', 1);
}

// Set column title
$form_grid->addItem([
	(new CLabel([
		_('Column title'),
		makeHelpIcon(_('Only used when \'Layout\' is set to \'Column per pattern\''))
	]))->addClass('js-column-title'),
	(new CFormField(
		(new CTextBox('column_title', $data['column_title'], false))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	))->addClass('js-column-title')
]);

// Set if you want to broadcast the itemids in the grouped column cell
$form_grid->addItem([
	(new CLabel([
		_('Broadcast from grouped column'),
		makeHelpIcon([
			_('Checking this box means that the itemid will be broadcasted to listening widgets by clicking the cell in the column with the grouping value'), BR(),
			_('This is useful for when you have multiple columns and you want to broadcast multiple metrics to be plotted simultaneously')
		])
	]))->addClass('js-broadcast-in-group-cell'),
	(new CFormField(
		(new CCheckBox('broadcast_in_group_row'))->setChecked($data['broadcast_in_group_row'])
	))->addClass('js-broadcast-in-group-cell')
]);

// Item patterns
$item_items_field_view = (new CWidgetFieldPatternSelectItemView($data['item_items_field']))
	->setFormName('tablemodulerme_column');

$key_tip = makeHelpIcon([
	_('If you know the item key pattern, you can specify it instead of the item name pattern by typing: "key=<ITEM_KEY>" for each pattern you want.'), BR(), BR(),
	_('Wildcards are still supported for item key patterns.'), BR(),
	_('The reason for item key pattern usage here is it is faster due to indexing.'), BR(), BR(),
	_('In order to find the key you need, to go the Latest data page, search for your item patterns and then check the "Show details" box. The key for each item will display below the item name in the "Name" column')
]);

foreach ($item_items_field_view->getViewCollection() as ['label' => $label, 'view' => $view, 'class' => $class]) {
	$label->addItem($key_tip);
	$form_grid->addItem([
		$label,
		(new CFormField($view))->addClass($class)
	]);
}

$form_grid
	->addItem($item_items_field_view->getTemplates())
	->addItem(new CScriptTag([
		$item_items_field_view->getJavaScript()
	]));

// Item tags.
$form_grid->addItem([
	new CLabel(_('Item tags')),
	new CFormField(
		(new CRadioButtonList('item_tags_evaltype', (int) $data['item_tags_evaltype']))
			->addValue(_('And/Or'), TAG_EVAL_TYPE_AND_OR)
			->addValue(_('Or'), TAG_EVAL_TYPE_OR)
			->setModern()
	)
]);

$tags_view = (new CWidgetFieldTagsView($data['item_tags_field']))->setFormName('tablemodulerme_column');

foreach ($tags_view->getViewCollection() as ['label' => $label, 'view' => $view, 'class' => $class]) {
	$form_grid->addItem([
		$label,
		(new CFormField($view))->addClass($class)
	]);
}
$form_grid
	->addItem($tags_view->getTemplates())
	->addItem(new CScriptTag([
		$tags_view->getJavaScript()
	]));

// Base color.
$form_grid->addItem([
	new CLabel(_('Base color'), 'lbl_base_color'),
	new CFormField(
		(new CColorPicker('base_color'))
			->setColor($data['base_color'])
			->allowEmpty()
	)
]);

// Font color.
$form_grid->addItem([
	new CLabel(_('Font color'), 'lbl_font_color'),
	new CFormField(
		(new CColorPicker('font_color'))
			->setColor($data['font_color'])
			->allowEmpty()
	)
]);

// Display value as.
$form_grid->addItem([
	new CLabel(_('Display value as'), 'display_value_as'),
	new CFormField(
		(new CRadioButtonList('display_value_as', (int) $data['display_value_as']))
			->addValue(_('Numeric'), CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC)
			->addValue(_('Text'), CWidgetFieldColumnsList::DISPLAY_VALUE_AS_TEXT)
			->addValue(_('URL'), CWidgetFieldColumnsList::DISPLAY_VALUE_AS_URL)
			->setModern()
	)
]);

// Display.
$form_grid->addItem([
	(new CLabel(_('Display'), 'display'))->addClass('js-display-row'),
	(new CFormField(
		(new CRadioButtonList('display', (int) $data['display']))
			->addValue(_('As is'), CWidgetFieldColumnsList::DISPLAY_AS_IS)
			->addValue(_('Bar'), CWidgetFieldColumnsList::DISPLAY_BAR)
			->addValue(_('Indicators'), CWidgetFieldColumnsList::DISPLAY_INDICATORS)
			->addValue(_('Sparkline'), CWidgetFieldColumnsList::DISPLAY_SPARKLINE)
			->setModern()
	))->addClass('js-display-row')
]);

// Sparkline.
$sparkline = (new CWidgetFieldSparklineView(
	(new CWidgetFieldSparkline('sparkline', _('Sparkline')))
		->setInType(CWidgetsData::DATA_TYPE_TIME_PERIOD)
		->acceptDashboard()
		->acceptWidget()
		->setValue($data['sparkline'])
))->setFormName($form->getName());

$form_grid->addItem([
	$sparkline->getLabel()->addClass('js-sparkline-row'),
	$sparkline->getView()->addClass('js-sparkline-row')
]);

// Min.
$form_grid->addItem([
	(new CLabel(_('Min'), 'min'))->addClass('js-min-max-row'),
	(new CFormField(
		(new CTextBox('min', $data['min']))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			->setAttribute('placeholder', _('calculated'))
	))->addClass('js-min-max-row')
]);

// Max.
$form_grid->addItem([
	(new CLabel(_('Max'), 'max'))->addClass('js-min-max-row'),
	(new CFormField(
		(new CTextBox('max', $data['max']))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			->setAttribute('placeholder', _('calculated'))
	))->addClass('js-min-max-row')
]);

// Thresholds.
$thresholds = (new CDiv([
	(new CTable())
		->setId('thresholds_table')
		->addClass(ZBX_STYLE_TABLE_FORMS)
		->setHeader(['', _('Threshold'), (new CColHeader(''))->setWidth('100%')])
		->setFooter(new CRow(
			(new CCol(
				(new CButtonLink(_('Add')))->addClass('element-table-add')
			))->setColSpan(3)
		)),
	(new CTemplateTag('thresholds-row-tmpl'))
		->addItem((new CRow([
			(new CColorPicker('thresholds[#{rowNum}][color]'))
				->setColor('#{color}')
				->allowEmpty(),
			(new CTextBox('thresholds[#{rowNum}][threshold]', '#{threshold}', false))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAriaRequired(),
			(new CButton('thresholds[#{rowNum}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		]))->addClass('form_row'))
	]))
	->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

$form_grid->addItem([
	(new CLabel(_('Thresholds'), 'thresholds_table'))->addClass('js-thresholds-row'),
	(new CFormField($thresholds))->addClass('js-thresholds-row')
]);

// Decimal places.
$form_grid->addItem([
	(new CLabel(_('Decimal places'), 'decimal_places'))->addClass('js-decimals-row'),
	(new CFormField(
		(new CNumericBox('decimal_places', $data['decimal_places'], 2))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	))->addClass('js-decimals-row')
]);

// Set if you want to add a link to take you to history values
$form_grid->addItem([
	(new CLabel([
		_('Add link for history'),
		makeHelpIcon([
			_('Checking this box will add an external link icon to each table data cell, allowing you to see the historical values in a table')
		])
	]))->addClass('js-go-to-history-values'),
	(new CFormField(
		(new CCheckBox('go_to_history_values'))->setChecked($data['go_to_history_values'])
	))->addClass('js-go-to-history-values')
]);

// Item patterns
// Valuemap display override
$form_grid->addItem([
	(new CLabel([
		_('Valuemap display option'),
		makeHelpIcon([
			_('By default, values with a value mapping will be displayed in the normal value mapping format of:'), BR(),
			_(' \'<MAPPING> (<RAW_VALUE>)\''), BR(), BR(),
			_('You can optionally choose to display just the MAPPING or the RAW_VALUE, however')
		])
	]))->addClass('js-valuemap-display'),
	(new CFormField(
		(new CSelect('valuemap_override'))
			->setId('valuemap_override')
			->setValue($data['valuemap_override'])
			->addOptions(CSelect::createOptionsFromArray([
				CWidgetFieldColumnsList::VALUEMAP_AS_IS => 'Display as is',
				CWidgetFieldColumnsList::VALUEMAP_MAPPING => 'Mapping only',
				CWidgetFieldColumnsList::VALUEMAP_VALUE => 'Value only'
			]))
			->setFocusableElementId('valuemap_override')
	))->addClass('js-valuemap-display')
]);

// Highlights.
$highlights = (new CDiv([
	(new CTable())
		->setId('highlights_table')
		->addClass(ZBX_STYLE_TABLE_FORMS)
		->setHeader(['', _('Regular expression'), (new CColHeader(''))->setWidth('100%')])
		->setFooter(new CRow(
			(new CCol(
				(new CButtonLink(_('Add')))->addClass('element-table-add')
			))->setColSpan(3)
		)),
	(new CTemplateTag('highlights-row-tmpl'))
		->addItem((new CRow([
			(new CColorPicker('highlights[#{rowNum}][color]'))
				->setColor('#{color}')
				->allowEmpty(),
			(new CTextBox('highlights[#{rowNum}][pattern]', '#{pattern}', false))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setAriaRequired(),
			(new CButton('highlights[#{rowNum}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		]))->addClass('form_row'))
	]))
	->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

$form_grid->addItem([
	(new CLabel(_('Highlights'), 'highlights_table'))->addClass('js-highlights-row'),
	(new CFormField($highlights))->addClass('js-highlights-row')
]);

$form_grid->addItem([
	(new CLabel(_('URL display mode'), 'url_display_mode'))->addClass('js-url-display-mode'),
	(new CFormField(
		(new CRadioButtonList('url_display_mode', (int) $data['url_display_mode']))
			->addValue(_('As is'), CWidgetFieldColumnsList::URL_DISPLAY_AS_IS)
			->addValue(_('Custom'), CWidgetFieldColumnsList::URL_DISPLAY_CUSTOM)
			->setModern()
	))->addClass('js-url-display-mode')
]);

$form_grid->addItem([
	(new CLabel([
		_('URL display override'),
		makeHelpIcon([
			_('Customize the display text of the URL'), BR(), BR(),
			_('Instead of displaying the raw URL you can set arbitrary text to display instead. The URL will be encoded into the text you enter in this text box.'), BR(), BR(),
			_('You can also mix macros with text. Supported macros:'),
			(new CList([
				'{HOST.*}',
				'{ITEM.*}',
				'{INVENTORY.*}',
				_('User macros'),
			]))->addClass(ZBX_STYLE_LIST_DASHED)
		])
	]))->addClass('js-url-display-override'),
	(new CFormField(
		(new CTextBox('url_display_override', $data['url_display_override'], false))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('placeholder', _('Set custom display text'))
	))->addClass('js-url-display-override')
]);

$form_grid->addItem([
	(new CLabel([
		_('URL customization'),
		makeHelpIcon([
			_('Customize the entire URL'), BR(), BR(),
			_('Instead of displaying the metric value as a URL, you can leverage the hosts and items from the returned results to create a fully customized URL. You can also just simply enter any valid URL (i.e. https://www.zabbix.com)'), BR(), BR(),
			_('You can also mix macros with text. Supported macros:'),
			(new CList([
				'{HOST.*}',
				'{ITEM.*}',
				'{INVENTORY.*}',
				_('User macros'),
			]))->addClass(ZBX_STYLE_LIST_DASHED)
		])
	]))->addClass('js-url-custom-override'),
	(new CFormField(
		(new CTextBox('url_custom_override', $data['url_custom_override'], false))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('placeholder', _('Create a fully custom URL'))
	))->addClass('js-url-custom-override')
]);

$form_grid->addItem([
	(new CLabel([
		_('Open URL in new tab'),
		makeHelpIcon([
			_('Check this box to open the URL in a new browser tab, otherwise the link will open in the same tab')
		])
	]))->addClass('js-url-open-in'),
	(new CFormField(
		(new CCheckBox('url_open_in'))->setChecked($data['url_open_in'])
	))->addClass('js-url-open-in')
]);

// Advanced configuration.
$advanced_configuration = new CWidgetFormFieldsetCollapsibleView(_('Advanced configuration'));

// Column aggregation function.
$advanced_configuration->addItem([
	(new CLabel([
		_('Column patterns aggregation'),
		makeHelpIcon([
			_('Choose a function to aggregate all item patterns for this column for each host.')
		])
	]))->addClass('js-column-agg-row'),
	(new CFormField(
		(new CSelect('column_agg_method'))
			->setId('column_agg_method')
			->setValue($data['column_agg_method'])
			->addOptions(CSelect::createOptionsFromArray([
				AGGREGATE_NONE => CItemHelper::getAggregateFunctionName(AGGREGATE_NONE),
				AGGREGATE_MIN => CItemHelper::getAggregateFunctionName(AGGREGATE_MIN),
				AGGREGATE_MAX => CItemHelper::getAggregateFunctionName(AGGREGATE_MAX),
				AGGREGATE_AVG => CItemHelper::getAggregateFunctionName(AGGREGATE_AVG),
				AGGREGATE_COUNT => CItemHelper::getAggregateFunctionName(AGGREGATE_COUNT),
				AGGREGATE_SUM => CItemHelper::getAggregateFunctionName(AGGREGATE_SUM)
			]))
			->setFocusableElementId('column_patterns_aggregation')
	))->addClass('js-column-agg-row')
]);

// Aggregation function.
$advanced_configuration->addItem([
	new CLabel(_('Aggregation function'), 'column_aggregate_function'),
	new CFormField(
		(new CSelect('aggregate_function'))
			->setId('aggregate_function')
			->setValue($data['aggregate_function'])
			->addOptions(CSelect::createOptionsFromArray([
				AGGREGATE_NONE => CItemHelper::getAggregateFunctionName(AGGREGATE_NONE),
				AGGREGATE_MIN => CItemHelper::getAggregateFunctionName(AGGREGATE_MIN),
				AGGREGATE_MAX => CItemHelper::getAggregateFunctionName(AGGREGATE_MAX),
				AGGREGATE_AVG => CItemHelper::getAggregateFunctionName(AGGREGATE_AVG),
				AGGREGATE_COUNT => CItemHelper::getAggregateFunctionName(AGGREGATE_COUNT),
				AGGREGATE_SUM => CItemHelper::getAggregateFunctionName(AGGREGATE_SUM),
				AGGREGATE_FIRST => CItemHelper::getAggregateFunctionName(AGGREGATE_FIRST),
				AGGREGATE_LAST => CItemHelper::getAggregateFunctionName(AGGREGATE_LAST)
			]))
			->setFocusableElementId('column_aggregate_function')
	)
]);

// Time period.
$time_period_field_view = (new CWidgetFieldTimePeriodView($data['time_period_field']))
	->setDateFormat(ZBX_FULL_DATE_TIME)
	->setFromPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
	->setToPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
	->setFormName('tablemodulerme_column')
	->addClass('js-time-period');

foreach ($time_period_field_view->getViewCollection() as ['label' => $label, 'view' => $view, 'class' => $class]) {
	$advanced_configuration->addItem([
		$label,
		(new CFormField($view))->addClass($class)
	]);
}

$advanced_configuration->addItem(new CScriptTag([
	'document.forms.tablemodulerme_column.fields = {};',
	$time_period_field_view->getJavaScript()
]));

// History data.
$advanced_configuration
	->addItem([
		(new CLabel(_('History data'), 'history'))->addClass('js-history-row'),
		(new CFormField(
			(new CRadioButtonList('history', (int) $data['history']))
				->addValue(_('Auto'), CWidgetFieldColumnsList::HISTORY_DATA_AUTO)
				->addValue(_('History'), CWidgetFieldColumnsList::HISTORY_DATA_HISTORY)
				->addValue(_('Trends'), CWidgetFieldColumnsList::HISTORY_DATA_TRENDS)
				->setModern()
		))->addClass('js-history-row')
	]);

// Footer Override
$advanced_configuration
	->addItem([
		(new CLabel(_('Override footer'), 'override_footer'))->addClass('js-override-footer'),
		(new CFormField(
			(new CRadioButtonList('override_footer', (int) $data['override_footer']))
				->addValue(_('No override'), CWidgetFieldColumnsList::FOOTER_DONT_OVERRIDE)
				->addValue(_('None'), CWidgetFieldColumnsList::FOOTER_SHOW_NONE)
				->addValue(_('Sum'), CWidgetFieldColumnsList::FOOTER_SHOW_SUM)
				->addValue(_('Average'), CWidgetFieldColumnsList::FOOTER_SHOW_AVERAGE)
				->setModern()
		))->addClass('js-override-footer')
	]);

// Whether to include itemids in table cells when using Column patterns aggregations
$advanced_configuration
	->addItem([
		(new CLabel([
			_('Include itemids in cell'),
			makeHelpIcon(_('When using \'Column patterns aggregation\' include all itemids for broadcasting to other widgets'))
		]))->addClass('js-include-itemids'),
		(new CFormField(
			(new CCheckBox('include_itemids'))->setChecked($data['include_itemids'])
		))->addClass('js-include-itemids')
	]);

$form_grid->addItem($advanced_configuration);

$form
	->addItem($form_grid)
	->addItem(
		(new CScriptTag('
			tablemodulerme_column_edit_form.init('.json_encode([
				'form_id' => $form->getId(),
				'thresholds' => $data['thresholds'],
				'highlights' => $data['highlights'],
				'colors' => $data['color_palette']
			], JSON_THROW_ON_ERROR).');
		'))->setOnDocumentReady()
	);

$output = [
	'header' => array_key_exists('edit', $data) ? _('Update column') : _('New column'),
	'script_inline' => $sparkline->getJavaScript().$this->readJsFile('column.edit.js.php', null, ''),
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => array_key_exists('edit', $data) ? _('Update') : _('Add'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'tablemodulerme_column_edit_form.submit();'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output, JSON_THROW_ON_ERROR);

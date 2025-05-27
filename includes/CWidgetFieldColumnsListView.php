<?php declare(strict_types = 0);

namespace Modules\TableModuleRME\Includes;

use CButton,
	CCol,
	CColHeader,
	CDiv,
	CList,
	CRow,
	CSpan,
	CTable,
	CTag,
	CTemplateTag,
	CVar,
	CWidgetFieldView;

class CWidgetFieldColumnsListView extends CWidgetFieldView {

	public function __construct(CWidgetFieldColumnsList $field) {
		$this->field = $field;
	}

	public function getView(): CTag {
		$columns = $this->field->getValue();

		$row_actions = [
			(new CButton('edit', _('Edit')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->removeId(),
			(new CButton('remove', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->removeId()
		];

		$row_template = new CTemplateTag($this->field->getName().'-row-tmpl', new CRow([
			(new CDiv('#{items}'))->addClass('text'),
			(new CList(array_merge($row_actions, [(new CSpan())->addClass('js-column-data')])))
				->addClass(ZBX_STYLE_HOR_LIST)
		]));

		$header = [
			(new CColHeader(_('Patterns')))->addStyle('width: 100%')->addItem($row_template),
			_('Actions')
		];

		$view = (new CTable())
			->setId('list_'.$this->field->getName())
			->setHeader($header);

		foreach ($columns as $column_index => $column) {
			$column_data = [];

			foreach ($column as $key => $value) {
				$column_data[] = new CVar($this->field->getName().'['.$column_index.']['.$key.']', $value);
			}

			$items = '*';
			if (array_key_exists('items', $column) && $column['items']) {
				$items = implode(', ', $column['items']);
			}

			$view->addRow((new CRow([
				(new CDiv($items))->addClass('text'),
				(new CList(array_merge($row_actions, [(new CSpan($column_data))->addClass('js-column-data')])))
					->addClass(ZBX_STYLE_HOR_LIST)
			]))->setAttribute('data-index', $column_index));
		}

		$view->addRow(
			(new CCol(
				(new CButton('add', _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->setEnabled(!$this->isDisabled())
			))->setColSpan(count($header))
		);

		return $view;
	}
}

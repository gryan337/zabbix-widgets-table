<?php declare(strict_types = 0);


namespace Modules\TableModuleRME\Includes;

use Zabbix\Widgets\CWidgetField;

class CWidgetFieldTableModuleItemGrouping extends CWidgetField {

	public const DEFAULT_VIEW = CWidgetFieldTableModuleItemGroupingView::class;
	public const DEFAULT_VALUE = [];

	public const GROUP_BY_ITEM_TAG = 0;

	public const MAX_ROWS = 10;

	public function __construct(string $name, ?string $label = null) {
		parent::__construct($name, $label);

		$this
			->setDefault(self::DEFAULT_VALUE)
			->setValidationRules(['type' => API_OBJECTS, 'length' => self::MAX_ROWS, 'fields' => [
				'attribute'	=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [self::GROUP_BY_ITEM_TAG])],
				'tag_name'	=> ['type' => API_STRING_UTF8, 'length' => $this->getMaxLength()]
			]]);
	}

	public function validate(bool $strict = false): array {
		$errors = parent::validate($strict);

		if ($errors) {
			return $errors;
		}

		$group_by = $this->getValue();

		$result = array_filter($group_by, static function(array $row): bool {
			return $row['attribute'] != self::GROUP_BY_ITEM_TAG
				|| $row['tag_name'] !== '';
		});

		if (count($result) < count($group_by)) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Group by'), _('tag cannot be empty'));
		}

		$result = array_map(static function(array $row): string {
			return implode(array_values($row));
		}, $result);

		if (count($result) != count(array_unique($result))) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Group by'), _('rows must be unique'));
		}

		return $errors;
	}

	public function toApi(array &$widget_fields = []): void {
		foreach ($this->getValue() as $index => $value) {
			$widget_fields[] = [
				'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
				'name' => $this->name.'.'.$index.'.'.'attribute',
				'value' => $value['attribute']
			];

			if ($value['attribute'] == self::GROUP_BY_ITEM_TAG) {
				$widget_fields[] = [
					'type' => $this->save_type,
					'name' => $this->name.'.'.$index.'.'.'tag_name',
					'value' => $value['tag_name']
				];
			}
		}
	}
}

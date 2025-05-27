<?php declare(strict_types = 0);

namespace Modules\TableModuleRME;

use Zabbix\Core\CWidget;

class Widget extends CWidget {

	public const DEFAULT_FILL = '#97AAB3';

	public const CELL_HOSTID = 0;
	public const CELL_ITEMID = 1;
	public const CELL_VALUE = 2;
	public const CELL_METADATA = 3;
	public const CELL_SPARKLINE_VALUE = 4;

	public function getDefaultName(): string {
		return _('Table');
	}
}

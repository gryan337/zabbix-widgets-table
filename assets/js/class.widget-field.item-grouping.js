

class CWidgetFieldTableModuleItemGrouping {

	static GROUP_BY_ITEM_TAG_VALUE = 0;

	/**
	 * @type {HTMLTableElement};
	 */
	#table;

	/**
	 * @type {string};
	 */
	#field_name;

	/**
	 * @type {Array};
	 */
	#field_value;

	/**
	 * type {number};
	 */
	#max_rows;

	constructor({
		field_name,
		field_value,
		max_rows
	}) {
		this.#field_name = field_name;
		this.#field_value = field_value;
		this.#max_rows = max_rows;
		this.#table = document.getElementById(`${field_name}-table`);

		this.#initField();
		this.#update();
	}

	#initField() {
		jQuery(this.#table)
			.dynamicRows({
				template: `#${this.#field_name}-row-tmpl`,
				allow_empty: true,
				rows: this.#field_value,
				sortable: true,
				sortable_options: {
					target: 'tbody',
					selector_handle: `.${ZBX_STYLE_DRAG_ICON}`,
					freeze_end: 1
				}
			})
			.on('afteradd.dynamicRows, tableupdate.dynamicRows', () => this.#update());

		this.#table.addEventListener('change', () => this.#update());
	}

	#update() {
		const rows = this.#table.querySelectorAll('.form_row');

		rows.forEach((row, index) => {
			for (const field of row.querySelectorAll(`[name^="${this.#field_name}["]`)) {
				field.name = field.name.replace(/\[\d+]/g, `[${index}]`);
			}

			const attribute_value = row.querySelector('[name$="[attribute]"]').value;

			const is_tag_value = attribute_value == CWidgetFieldTableModuleItemGrouping.GROUP_BY_ITEM_TAG_VALUE;
			const tag_name_input = row.querySelector('input[name$="[tag_name]"]');

			tag_name_input.style.display = is_tag_value ? '' : 'none';
			tag_name_input.disabled = !is_tag_value;
		});

		this.#table.querySelector('#add-row').disabled = rows.length == this.#max_rows;
	}
}

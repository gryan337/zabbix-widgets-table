<?php declare(strict_types = 0);

use Modules\TableModuleRME\Includes\WidgetForm;

?>


window.widget_tablemodulerme_form = new class extends CWidgetForm {

	/**
	 * Widget form.
	 *
	 * @type {HTMLFormElement}
	 */
	#form;

	/**
	 * Template id.
	 *
	 * @type {string}
	 */
	#templateid;

	/**
	 * Column list container.
	 *
	 * @type {HTMLElement}
	 */
	#list_columns;

	/**
	 * Column list entry template.
	 *
	 * @type {Template}
	 */
	#list_column_tmpl;

	init({templateid}) {
		this.#form = this.getForm();
		this.#list_columns = document.getElementById('list_columns');
		this.#list_column_tmpl = new Template(this.#list_columns.querySelector('template').innerHTML);
		this.#templateid = templateid;

		// Initialize form elements accessibility.
		this.#updateForm();

		this.#list_columns.addEventListener('click', (e) => this.#processColumnsAction(e));
		this.#form.querySelectorAll('[name="host_ordering_order_by"], [name="item_ordering_order_by"]')
			.forEach(element => {
				element.addEventListener('change', () => this.#updateForm());
			});

		jQuery(document.getElementById('groupids_')).on('change', () => this.#updateForm());
		jQuery(document.getElementById('hostids_')).on('change', () => this.#updateForm());
		jQuery('[id^=layout_]').on('change', () => this.#updateForm());
		this.#form.addEventListener('form_fields.changed', () => this.#updateForm());

		this.ready();
	}

	/**
	 * Updates widget column configuration form field visibility, enable/disable state and available options.
	 */
	#updateForm() {
		const column_per_pattern = this.#form.querySelector('#layout_3').checked;
		const vertical_layout = this.#form.querySelector('#layout_1').checked;
		const item_grouping_table = this.#form.querySelector('[id=item_group_by-table]');
		const item_grouping_table_rows = item_grouping_table.querySelectorAll(':scope > tbody > tr');

		for (const grouping_field of this.#form.querySelectorAll('.field_item_group_by')) {
			grouping_field.style.display = !column_per_pattern ? 'none' : '';
			for (const input of grouping_field.querySelectorAll('input')) {
				input.disabled = !column_per_pattern;
			}
		}

		for (const grouping_delimiter of this.#form.querySelectorAll('.field_grouping_delimiter')) {
			grouping_delimiter.style.display = !column_per_pattern ? 'none' : '';
			for (const input of grouping_delimiter.querySelectorAll('input')) {
				input.disabled = !column_per_pattern;
				input.setAttribute('data-no-trim', '1');
			}
		}

		for (const aggregate_all_field of this.#form.querySelectorAll('.field_aggregate_all_hosts')) {
			aggregate_all_field.style.display = !column_per_pattern ? 'none' : '';
			for (const input of aggregate_all_field.querySelectorAll('input')) {
				input.disabled = !column_per_pattern;
			}
		}
		
		for (const show_grouping_only_field of this.#form.querySelectorAll('.field_show_grouping_only')) {
			show_grouping_only_field.style.display = !column_per_pattern ? 'none' : '';
			for (const input of show_grouping_only_field.querySelectorAll('input')) {
				input.disabled = !column_per_pattern;
			}
		}

		for (const bc_hostid_field of this.#form.querySelectorAll('.field_no_broadcast_hostid')) {
			bc_hostid_field.style.display = vertical_layout ? 'none' : '';
			for (const input of bc_hostid_field.querySelectorAll('input')) {
				input.disabled = vertical_layout;
			}
		}
		
		const order_by_host =
			this.#form.querySelector('[name=item_ordering_order_by]:checked').value == <?= WidgetForm::ORDERBY_HOST ?>;

		const order_by_item_value = this.#form
			.querySelector('[name=host_ordering_order_by]:checked')
			.value == <?= WidgetForm::ORDERBY_ITEM_VALUE ?>;

		const item_select = document.getElementById('host_ordering_item_');
		if (order_by_item_value) {
			item_select.closest('.form-field').classList.remove('<?= ZBX_STYLE_DISPLAY_NONE ?>')
			jQuery(item_select).multiSelect('enable');
		}
		else {
			item_select.closest('.form-field').classList.add('<?= ZBX_STYLE_DISPLAY_NONE ?>')
			jQuery(item_select).multiSelect('disable');
		}

		const host_select = document.getElementById('item_ordering_host_');
		if (order_by_host) {
			host_select.closest('.form-field').classList.remove('<?= ZBX_STYLE_DISPLAY_NONE ?>')
			jQuery(host_select).multiSelect('enable');
		}
		else {
			host_select.closest('.form-field').classList.add('<?= ZBX_STYLE_DISPLAY_NONE ?>')
			jQuery(host_select).multiSelect('disable');
		}

		// Limit multi select suggestions to selected hosts and groups.
		const url_host = new Curl(jQuery(host_select).multiSelect('getOption', 'url'));
		const url_item = new Curl(jQuery(item_select).multiSelect('getOption', 'url'));
		const form_fields = getFormFields(this.#form);

		if (form_fields.groupids !== undefined) {
			url_host.args.groupids = form_fields.groupids;
			url_item.args.groupids = form_fields.groupids;
		}
		else {
			delete url_host.args.groupids;
			delete url_item.args.groupids;
		}

		if (form_fields.hostids !== undefined) {
			url_item.args.hostids = form_fields.hostids;
		}
		else {
			delete url_item.args.hostids;
		}

		if (form_fields.columns !== undefined) {
			url_item.args.items = [];
			Object.values(form_fields.columns)
				.forEach((column) => url_item.args.items.push(...Object.values(column.items)));
		}
		else {
			delete url_item.args.items;
		}

		jQuery(host_select).multiSelect('modify', {url: url_host.getUrl()});
		jQuery(item_select).multiSelect('modify', {url: url_item.getUrl()});
	}

	#triggerUpdate() {
		this.#form.dispatchEvent(new CustomEvent('form_fields.changed', {detail: {}}));

		this.registerUpdateEvent();
	}

	#processColumnsAction(e) {
		const target = e.target;
		const form_fields = getFormFields(this.#form);

		let column_popup;

		switch (target.getAttribute('name')) {
			case 'add':
				column_popup = PopUp(
					'widget.tablemodulerme.column.edit',
					{
						templateid: this.#templateid,
						groupids: form_fields.groupids,
						hostids: form_fields.hostids
					},
					{
						dialogueid: 'tablemodulerme-column-edit-overlay',
						dialogue_class: 'modal-popup-generic'
					}
				).$dialogue[0];

				column_popup.addEventListener('dialogue.submit', (e) => {
					const last_row = this.#list_columns.querySelector(`tbody > tr:last-child`);
					const index = last_row.previousSibling !== null
						? parseInt(last_row.previousSibling.dataset.index) + 1
						: 0;

					last_row.insertAdjacentElement('beforebegin', this.#makeColumnRow(e.detail, index));
					this.#triggerUpdate();
				});

				break;

			case 'edit':
				const column_index = e.target.closest('tr').dataset.index;
				column_popup = PopUp(
					'widget.tablemodulerme.column.edit',
					{
						...form_fields.columns[column_index],
						edit: 1,
						templateid: this.#templateid,
						groupids: form_fields.groupids,
						hostids: form_fields.hostids
					}, {
						dialogueid: 'tablemodulerme-column-edit-overlay',
						dialogue_class: 'modal-popup-generic'
					}
					).$dialogue[0];

				column_popup.addEventListener('dialogue.submit', (e) => {
					const row = this.#list_columns.querySelector(`tbody > tr[data-index="${column_index}"]`);
					row.replaceWith(this.#makeColumnRow(e.detail, column_index));
					this.#triggerUpdate();
				});

				break;

			case 'remove':
				target.closest('tr').remove();
				this.#triggerUpdate();
				break;
		}
	}

	#makeColumnRow(data, index) {
		const row = this.#list_column_tmpl.evaluateToElement({
			...data,
			rowNum: index,
			items: Object.values(data.items).join(', ')
		});

		row.dataset.index = index;
		const column_data = row.querySelector('.js-column-data');
		for (const [data_key, data_value] of Object.entries(data)) {
			switch (data_key) {
				case 'edit':
					continue;

				case 'thresholds':
					for (const [key, value] of Object.entries(data.thresholds)) {
						column_data.append(this.#makeVar(`columns[${index}][thresholds][${key}][color]`, value.color));
						column_data.append(this.#makeVar(
							`columns[${index}][thresholds][${key}][threshold]`,
							value.threshold
						));
					}

					break;

				case 'highlights':
					for (const [key, value] of Object.entries(data.highlights)) {
						column_data.append(this.#makeVar(`columns[${index}][highlights][${key}][color]`, value.color));
						column_data.append(this.#makeVar(
							`columns[${index}][highlights][${key}][pattern]`,
							value.pattern
						));
					}

					break;

				case 'items':
					for (const [key, value] of Object.entries(data.items)) {
						column_data.append(this.#makeVar(`columns[${index}][items][${key}]`, value));
					}

					break;

				case 'time_period':
					for (const [key, value] of Object.entries(data.time_period)) {
						column_data.append(this.#makeVar(`columns[${index}][time_period][${key}]`, value));
					}

					break;

				case 'sparkline':
					for (const [key, value] of Object.entries(data_value)) {
						if (key === 'time_period') {
							for (const [k, v] of Object.entries(value)) {
								column_data.append(this.#makeVar(`columns[${index}][sparkline][time_period][${k}]`, v));
							}
						}
						else {
							column_data.append(this.#makeVar(`columns[${index}][sparkline][${key}]`, value));
						}
					}
					break;

				case 'item_tags':
					for (const [key, {operator, tag, value}] of Object.entries(data.item_tags)) {
						column_data.append(this.#makeVar(`columns[${index}][item_tags][${key}][operator]`, operator));
						column_data.append(this.#makeVar(`columns[${index}][item_tags][${key}][tag]`, tag));
						column_data.append(this.#makeVar(`columns[${index}][item_tags][${key}][value]`, value));
					}

					break;

				default:
					column_data.append(this.#makeVar(`columns[${index}][${data_key}]`, data_value));

					break;
			}
		}

		return row;
	}

	#makeVar(name, value) {
		const input = document.createElement('input');

		input.setAttribute('type', 'hidden');
		input.setAttribute('name', name);
		input.setAttribute('value', value);

		return input
	}
};

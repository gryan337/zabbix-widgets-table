<?php declare(strict_types = 0);


use Modules\TableModuleRME\Includes\CWidgetFieldColumnsList;

?>

window.tablemodulerme_column_edit_form = new class {

	/**
	 * @type {Overlay}
	 */
	#overlay;

	/**
	 * @type {HTMLElement}
	 */
	#dialogue;

	/**
	 * @type {HTMLFormElement}
	 */
	#form;

	/**
	 * @type {HTMLTableElement}
	 */
	#thresholds_table;

	/**
	 * @type {HTMLTableElement}
	 */
	#highlights_table;

	init({form_id, thresholds, highlights, colors}) {
		this.aggregation_error = false;
		this.#overlay = overlays_stack.getById('tablemodulerme-column-edit-overlay');
		this.#dialogue = this.#overlay.$dialogue[0];
		this.#form = document.getElementById(form_id);

		this.#thresholds_table = document.getElementById('thresholds_table');
		this.#highlights_table = document.getElementById('highlights_table');

		this.#form
			.querySelectorAll(
				'[name="display_value_as"], [name="aggregate_function"], [name="column_agg_method"], [name="display"], [name="history"], [name="url_display_mode"]'
			)
			.forEach(element => {
				element.addEventListener('change', () => this.#updateForm());
			});

		this.#form.addEventListener('change', ({target}) => {
			if (target.matches('[type="text"]')) {
				target.value = target.value.trim();
			}
		});

		colorPalette.setThemeColors(colors);
		
		const parentForm = document.getElementsByClassName('modal-widget-configuration').item(0);
		this.tgt = this.#findRecurse(parentForm, 'field_item_group_by');
		this.all_hosts_aggregated = this.#findRecurse(parentForm, 'field_aggregate_all_hosts');

		// Initialize thresholds table.
		$(this.#thresholds_table)
			.dynamicRows({
				rows: thresholds,
				template: '#thresholds-row-tmpl',
				allow_empty: true,
				dataCallback: (row_data) => {
					if (!('color' in row_data)) {
						const color_pickers = this.#form.querySelectorAll(`.${ZBX_STYLE_COLOR_PICKER}`);
						const used_colors = [];

						for (const color_picker of color_pickers) {
							if (color_picker.color !== '') {
								used_colors.push(color_picker.color);
							}
						}

						row_data.color = colorPalette.getNextColor(used_colors);
					}
				}
			})
			.on('afteradd.dynamicRows', () => this.#updateForm())
			.on('afterremove.dynamicRows', () => this.#updateForm())

		// Initialize highlights table.
		$(this.#highlights_table)
			.dynamicRows({
				rows: highlights,
				template: '#highlights-row-tmpl',
				allow_empty: true,
				dataCallback: (row_data) => {
					if (!('color' in row_data)) {
						const color_pickers = this.#form.querySelectorAll(`.${ZBX_STYLE_COLOR_PICKER}`);
						const used_colors = [];

						for (const color_picker of color_pickers) {
							if (color_picker.color !== '') {
								used_colors.push(color_picker.color);
							}
						}

						row_data.color = colorPalette.getNextColor(used_colors);
					}
				}
			})
			.on('afteradd.dynamicRows', () => this.#updateForm())
			.on('afterremove.dynamicRows', () => this.#updateForm());

		// Initialize Advanced configuration collapsible.
		const collapsible = this.#form.querySelector(`fieldset.<?= ZBX_STYLE_COLLAPSIBLE ?>`);
		new CFormFieldsetCollapsible(collapsible);

		// Field trimming.
		this.#form.querySelectorAll('[name="min"], [name="max"]').forEach(element => {
			element.addEventListener('change', (e) => e.target.value = e.target.value.trim(), {capture: true});
		});

		// Initialize form elements accessibility.
		this.#updateForm();

		this.#form.style.display = '';
		this.#overlay.recoverFocus();

		this.#form.addEventListener('submit', () => this.submit());
	}
	
	#findRecurse(parent, classToSearch) {
		if (parent.classList.contains(classToSearch)) {
			return parent;
		}
		
		for (let i = 0; i < parent.children.length; i++) {
			const child = parent.children[i];
			const result = this.#findRecurse(child, classToSearch);
			if (result) {
				return result;
			}
		}
		
		return null;
	}

	/**
	 * Updates widget column configuration form field visibility, enable/disable state and available options.
	 */
	#updateForm() {
		const display_value_as = this.#form.querySelector('[name=display_value_as]:checked').value;
		const display = this.#form.querySelector('[name=display]:checked').value;
		const url_display_mode = this.#form.querySelector('[name=url_display_mode]:checked').value;

		// Column title	
		for (const element of this.#form.querySelectorAll('.js-column-title')) {
			element.style.display = (this.tgt.style.display == 'none') ? 'none' : '';
		}

		// Broadcast in grouped cell
		for (const element of this.#form.querySelectorAll('.js-broadcast-in-group-cell')) {
			element.style.display = (this.tgt.style.display == 'none') ? 'none' : '';
		}

		// Display.
		const display_show = display_value_as == <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC ?>;
		for (const element of this.#form.querySelectorAll('.js-display-row')) {
			element.style.display = display_show ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !display_show;
			}
		}

		// Sparkline.
		const sparkline_show = display_value_as == <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC ?>
			&& display == <?= CWidgetFieldColumnsList::DISPLAY_SPARKLINE ?>;

		for (const element of this.#form.querySelectorAll('.js-sparkline-row')) {
			element.style.display = sparkline_show ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !sparkline_show;
			}
		}

		this.#form.fields['sparkline[time_period]'].disabled = !sparkline_show;

		// Min/Max.
		const min_max_show = display_value_as == <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC ?>  && [
			'<?= CWidgetFieldColumnsList::DISPLAY_BAR ?>',
			'<?= CWidgetFieldColumnsList::DISPLAY_INDICATORS ?>'
		].includes(display);
		for (const element of this.#form.querySelectorAll('.js-min-max-row')) {
			element.style.display = min_max_show ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !min_max_show;
			}
		}

		// Highlights.
		const highlights_show = display_value_as == <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_TEXT ?>;
		for (const element of this.#form.querySelectorAll('.js-highlights-row')) {
			element.style.display = highlights_show ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !highlights_show;
			}
		}

		// URL display options.
		const display_url = display_value_as == <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_URL ?>;
		const url_override_show = display_value_as == <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_URL ?> &&
			url_display_mode == <?= CWidgetFieldColumnsList::URL_DISPLAY_CUSTOM ?>;

		for (const element of this.#form.querySelectorAll('.js-url-display-mode')) {
			element.style.display = display_url ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !display_url;
			}
		}

		for (const element of this.#form.querySelectorAll('.js-url-display-override')) {
			element.style.display = url_override_show ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !url_override_show;
			}
		}

		for (const element of this.#form.querySelectorAll('.js-url-custom-override')) {
			element.style.display = url_override_show ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !url_override_show;
			}
		}

		for (const element of this.#form.querySelectorAll('.js-url-open-in')) {
			element.style.display = display_url ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !display_url;
			}
		}

		// Thresholds.
		const thresholds_show = display_value_as == <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC ?>;
		for (const element of this.#form.querySelectorAll('.js-thresholds-row')) {
			element.style.display = thresholds_show ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !thresholds_show;
			}
		}

		// Decimal places.
		const decimals_show = display_value_as == <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC ?>;
		for (const element of this.#form.querySelectorAll('.js-decimals-row')) {
			element.style.display = decimals_show ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !decimals_show;
			}
		}

		// Column aggregation.		
		for (const element of this.#form.querySelectorAll('.js-column-agg-row')) {
			element.style.display = (this.tgt.style.display == 'none') ? 'none' : '';
		}

		// Aggregation function.
		const aggregation_function_select = this.#form.querySelector('z-select[name=aggregate_function]');
		[<?= AGGREGATE_MIN ?>, <?= AGGREGATE_MAX ?>, <?= AGGREGATE_AVG ?>, <?= AGGREGATE_SUM ?>].forEach(option => {
			aggregation_function_select.getOptionByValue(option).disabled =
				display_value_as != <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC ?>;
			aggregation_function_select.getOptionByValue(option).hidden =
				display_value_as != <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC ?>;

			if (aggregation_function_select.value == option
					&& display_value_as != <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC ?>) {
				aggregation_function_select.value = <?= AGGREGATE_NONE ?>;
			}
		});

		// Time period.
		const time_period_show = parseInt(document.getElementById('aggregate_function').value) != <?= AGGREGATE_NONE ?>;
		this.#form.fields.time_period.disabled = !time_period_show;
		this.#form.fields.time_period.hidden = !time_period_show;

		// History data.
		const history_show = display_value_as == <?= CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC ?>;
		for (const element of this.#form.querySelectorAll('.js-history-row')) {
			element.style.display = history_show ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !history_show;
			}
		}

		// Override footer.
		for (const element of this.#form.querySelectorAll('.js-override-footer')) {
			element.style.display = (this.tgt.style.display == 'none') ? 'none' : '';
		}

		// Option for including itemids encoding in cell for broadcasting
		const column_pattern_selection = this.#form.querySelector('button[id=column_patterns_aggregation]');
		for (const element of this.#form.querySelectorAll('.js-include-itemids')) {
			if (column_pattern_selection.innerText === 'not used' || this.tgt.style.display == 'none') {
				element.style.display = 'none';
				for (const input of element.querySelectorAll('input')) {
					input.disabled = 1;
				}
			}
			else {
				element.style.display = '';
				for (const input of element.querySelectorAll('input')) {
					input.disabled = 0;
				}
			}
		}
	}

	submit() {
		if (this.all_hosts_aggregated?.nextElementSibling.querySelector('[id="aggregate_all_hosts"]').checked &&
				$('#column_agg_method input[type="hidden"]').val() === '0') {
			if (!this.aggregation_error) {
				const title = 'Form configuration error';
				const messages = ['A \'Column patterns aggregation\' (under Advanced configuration) is required when using \'Aggregate all hosts\' from the main form'];
				const message_box = makeMessageBox('bad', messages, title)[0];
				this.#form.parentNode.insertBefore(message_box, this.#form);
				this.aggregation_error = true;
			}

			this.#overlay.unsetLoading();
			return;
		}

		const curl = new Curl(this.#form.getAttribute('action'));
		const fields = getFormFields(this.#form);

		this.#overlay.setLoading();

		this.#post(curl.getUrl(), fields);
	}

	#post(url, fields) {
		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
			body: urlEncodeData(fields)
		})
			.then(response => response.json())
			.then(response => {
				if ('error' in response) {
					throw {error: response.error};
				}

				overlayDialogueDestroy(this.#overlay.dialogueid);

				this.#dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			})
			.catch((exception) => {
				for (const element of this.#form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				let title, messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [<?= json_encode(_('Unexpected server error.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title)[0];

				this.#form.parentNode.insertBefore(message_box, this.#form);
			})
			.finally(() => {
				this.#overlay.unsetLoading();
			});
	}
};

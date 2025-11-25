
class CWidgetTableModuleRME extends CWidget {

	#seconds_per_day = 86400;
	#seconds_per_hour = 3600;
	#seconds_per_min = 60;

	#Multiplier = new Map([
		['Y', 1000**8],
		['Z', 1000**7],
		['E', 1000**6],
		['P', 1000**5],
		['T', 1000**4],
		['G', 1000**3],
		['M', 1000**2],
		['K', 1000],
		['B', 1]
	]);

	#sMultiplier = new Map([
		['y', 86400 * 365],
		['M', 86400 * 30],
		['d', 86400],
		['h', 3600],
		['m', 60],
		['s', 1],
		['ms', 0.001]
	]);

	#menu_selector = '[data-menu]';
	#theme = null;

	#dataset_item = 'item';
	#dataset_host = 'host';

	#selected_hostid = null;
	#selected_items = [];

	#null_id = '000000';

	#first_td_host_cell = null;
	#first_td_value_cell = null;

	#parent_container;
	#values_table;
	#th;
	#timeout = 0;
	#cssStyleMap = new Map();

	#rowsPerPage = 75;
	#currentPage = 1;
	#totalRows = 0;
	#paginationElement;
	#rowsArray = [];

	#popupId = null;
	#tooltipId = null;
	#filterApplied = false;
	#filterState = {
		type: 'Contains',
		search: '',
		checked: [],
		allSelected: false
	};

	#lastClickedCell = null;
	#clearFiltersClickedWithSelections = false;
	#filterTooltipIds = new Set();

	processUpdateResponse(response) {
		super.processUpdateResponse(response);
	}

	getUpdateRequestData() {
		const request_data = super.getUpdateRequestData();
		if (request_data?.fields?.groupids?.length === 1 && request_data.fields.groupids.includes(this.#null_id)) {
			request_data.fields.groupids = [];
		}
		if (request_data?.fields?.hostids?.length === 1 && request_data.fields.hostids.includes(this.#null_id)) {
			request_data.fields.hostids = [];
		}
		return request_data;
	}

	setContents(response) {
		if (response.body.includes('no-data-message')) {
			this.#broadcast(CWidgetsData.DATA_TYPE_HOST_ID, CWidgetsData.DATA_TYPE_HOST_IDS, this.#null_id);
			this.#broadcast(CWidgetsData.DATA_TYPE_ITEM_ID, CWidgetsData.DATA_TYPE_ITEM_IDS, this.#null_id);
			super.setContents(response);
			this.#removePaginationControls();
			return;
		}

		super.setContents(response)

		this.detachListeners();

		if (this.#theme === null) {
			this.getTheme();
		}

		this.#values_table = this._target.getElementsByClassName('list-table').item(0);
		this.#parent_container = this.#values_table.closest('.dashboard-grid-widget-container');
		const allRows = Array.from(this.#values_table.querySelectorAll('tbody tr'));
		var colIndex = 0;

		const allTds = this.#values_table.querySelectorAll('td');
		this.allThs = this.#values_table.querySelectorAll('th');

		let id = 0;
		allTds.forEach(td => {
			td.setAttribute('id', id);
			let key = td.innerText.trim();
			let element = td.querySelector(this.#menu_selector);
			if (element) {
				if (this._isDoubleSpanColumn(td)) {
					newTd = td.nextElementSibling;
					element = newTd.querySelector(this.#menu_selector);
				}
				const dataset = JSON.parse(element.dataset.menu);
				try {
					if (dataset.itemid) {
						if (this.#first_td_value_cell === null) {
							this.#first_td_value_cell = td;
						}
						key = dataset.itemid;
					}
					else if (dataset.hostid) {
						if (this.#first_td_host_cell === null) {
							this.#first_td_host_cell = td;
						}
						key = dataset.hostid;
					}
				}
				catch (error) {
					console.log('Fail', td)
				}
			}
			key = [key, td.getAttribute('id')].join("_");
			let style = td.getAttribute('style') || '';
			this.#cssStyleMap.set(key, style);
			id++;
		});

		this.allThs.forEach((th) => {
			th.innerHTML += `<span class="new-arrow" id="arrow"></span>`;
			th.setAttribute('style', `color: #4796c4; font-weight: bold;`);
			th.classList.add('cursor-pointer');

			const colspan = th.hasAttribute('colspan') ? parseFloat(th.getAttribute('colspan')) : 1;
			th.id = colIndex + colspan - 1;

			colIndex = parseFloat(th.id) + 1;
		});

		this.#rowsArray = allRows.map(row => {
			return {
				row,
				status: 'display'
			};
		});

		this.#values_table.addEventListener('click', (event) => {
			if (event.target.closest('.filter-icon')) {
				return;
			}

			const target = event.target.closest('th') || event.target.closest('td');

			if (target && target.tagName === 'TH') {
				this.#parent_container.classList.add('is-loading');
				setTimeout(() => {
					this.#th = target;
					const span = this.#getSetSpans(target);
					const ascending = !('sort' in target.dataset) || target.dataset.sort != 'asc';
					this.#sortTable(target, ascending, span);
				}, 0);
			}
			else if (target && target.tagName === 'TD') {
				this.#handleCellClick(target, event);
			}
		});

		this.#values_table.addEventListener('mousedown', (event) => {
			if (event.shiftKey) {
				event.preventDefault();
				event.stopPropagation();
			}
		});

		this.#totalRows = this.#rowsArray.length;
		this.#updateDisplay(false, true, true);

		this.allThs.forEach((th) => {
			if (this.#th !== undefined) {
				if (th.getAttribute('id') === this.#th.getAttribute('id')) {
					setTimeout(() => {
						const span = this.#getSetSpans(th);
						const ascending = this.#th.getAttribute('data-sort') === 'asc' ? true : false;
						this.#sortTable(th, ascending, span, true);
						this.#th = th;
					}, this.#timeout);
				}
			}
		});

		this.#removePaginationControls();
		if (this.#totalRows > this.#rowsPerPage) {
			this.#displayPaginationControls();
			this.#addPaginationCSS();
		}

		this.#addColumnFilterCSS();
		const firstTh = this.#values_table.querySelector('thead th');
		if (firstTh && !firstTh.querySelector('.filter-icon')) {
			let lastCheckedCheckbox = null;
			const filterValues = new Set();
			this.#rowsArray.forEach(rowObj => {
				const tr = rowObj.row;
				if (tr.querySelector('[reset-row]') || tr.querySelector('[footer-row]')) return;
				const td = tr.querySelector('td:first-child');
				if (td) filterValues.add(td.textContent.trim());
			});

			const valuesArray = Array.from(filterValues);

			const isNumeric = val => /^-?\d+(\.\d+)?$/.test(val.trim());
			const isIPv4 = val => /^(\d{1,3}\.){3}\d{1,3}$/.test(val.trim());
			const isAll = (arr, testFn) => arr.every(testFn);

			const ipToNum = ip => {
				const octets = ip.trim().split('.');
				if (octets.length !== 4) return NaN;

				const nums = octets.map(octet => parseInt(octet, 10));
				if (nums.some(num => isNaN(num) || num < 0 || num > 255)) return NaN;

				return (nums[0] * 256 ** 3) + (nums[1] * 256 ** 2) + (nums[2] * 256) + nums[3];
			};

			let sortedValues;
			if (isAll(valuesArray, isNumeric)) {
				sortedValues = valuesArray.sort((a, b) => parseFloat(a) - parseFloat(b));
			}
			else if (isAll(valuesArray, isIPv4)) {
				sortedValues = valuesArray.sort((a, b) => ipToNum(a) - ipToNum(b));
			}
			else if (isAll(valuesArray, val => typeof val === 'string')) {
				sortedValues = valuesArray.sort((a, b) => a.localeCompare(b));
			}
			else {
				sortedValues = valuesArray.sort((a, b) => {
					const getTypeRank = v => isNumeric(v) ? 0 : isIPv4(v) ? 1 : 2;
					const rankA = getTypeRank(a);
					const rankB = getTypeRank(b);
					if (rankA !== rankB) return rankA - rankB;
					if (rankA === 0) return parseFloat(a) - parseFloat(b);
					if (rankA === 1) return ipToNum(a) - ipToNum(b);
					return a.localeCompare(b);
				});
			}
			let filteredValues = sortedValues;

			const filterIcon = document.createElement('span');
			filterIcon.className = 'filter-icon';
			filterIcon.style.cursor = 'pointer';
			filterIcon.style.display = 'inline-flex';
			filterIcon.style.alignItems = 'center';
			filterIcon.style.justifyContent = 'center';
			filterIcon.style.marginLeft = '2px';
			filterIcon.style.width = '18px';
			filterIcon.style.height = '18px';
			filterIcon.style.verticalAlign = 'middle';
			filterIcon.style.position = 'relative';
			filterIcon.style.top = '0';
			filterIcon.title = 'Click to filter this column';

			filterIcon.innerHTML = `
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M3 4h18l-7 8v6l-4 2v-8L3 4z"/>
				</svg>
			`;

			const popup = document.createElement('div');
			popup.style.display = 'none';
			popup.className = 'filter-popup';
			popup.id = this.#values_table.id + '-' + this._widgetid + '-popup';
			
			if (this.#popupId !== null) {
				const oldPopup = document.getElementById(this.#popupId);
				if (oldPopup) {
					oldPopup.remove();
				}
			}
			this.#popupId = popup.id;

			const header = document.createElement('div');
			header.className = 'filter-popup-header';
			const headerTitle = document.createElement('div');
			headerTitle.className = 'filter-popup-header-title';
			headerTitle.textContent = 'Filter column values';

			const filterControls = document.createElement('div');
			filterControls.className = 'filter-popup-controls';

			const filterTypeContainer = document.createElement('div');
			filterTypeContainer.className = 'custom-select';

			const hiddenInput = document.createElement('input');
			hiddenInput.type = 'hidden';
			filterTypeContainer.appendChild(hiddenInput);

			const filterButton = document.createElement('button');
			filterButton.type = 'button';
			filterTypeContainer.appendChild(filterButton);

			const options = [
				{ value: 'contains', label: 'Contains', help: 'Filters items that contain the specified text.' },
				{ value: 'equals', label: 'Equals', help: 'Filters items that exactly match the specified text.' },
				{ value: 'starts with', label: 'Starts with', help: 'Filters items that start with the specified text.' },
				{ value: 'ends with', label: 'Ends with', help: 'Filters items that end with the specified text.' },
				{ value: 'wildcard', label: 'Wildcard', help: 'Filters items that match the specified wildcarded (*) pattern.\nMultiple wildcards (*) are supported.' },
				{ value: 'does not contain', label: 'Does not contain', help: 'Filters items that do not contain the specified text.' },
				{ value: 'regex', label: 'Regex', help: 'Filters items that match the specified regular expression.' },
				{ value: 'boolean', label: 'Boolean Expr', help: 'Filters items that match the specified boolean expression.\nSupports \'AND\', \'OR\', \'AND NOT\', \'OR NOT\', and parentheses\ni.e. (TERMA OR TERMB) AND TERMCD AND NOT TERMCDE' }
			];

			const initialValue = this.#filterState?.type || 'contains';
			const initialLabel = options.find(o => o.value === initialValue)?.label || 'Contains';

			hiddenInput.value = initialValue;
			filterButton.textContent = initialLabel;

			const filterList = document.createElement('ul');
			filterList.className = 'list';
			filterList.style.display = 'none';
			filterList.tabIndex = -1;

			if (this.#filterTooltipIds) {
				this.#filterTooltipIds.forEach(id => {
					const oldTooltip = document.getElementById(id);
					if (oldTooltip) {
						oldTooltip.remove();
					}
				});
			}
			this.#filterTooltipIds = new Set();

			options.forEach(opt => {
				const li = document.createElement('li');
				li.textContent = opt.label;
				li.dataset.value = opt.value;
				li.dataset.label = opt.label;
				li.addEventListener('click', () => {
					event.stopPropagation();
					filterList.style.display = 'none';
					hiddenInput.value = li.dataset.value;
					filterButton.textContent = li.dataset.label;
					searchInput.dispatchEvent(new Event('input'));
					updateWarningIcon();
					updateClearFiltersButton();
					this.#filterState.type = opt;
					setupInputHandler();
				});

				const helpIcon = document.createElement('span');
				helpIcon.className = 'help-icon';
				helpIcon.textContent = '?';
				helpIcon.dataset.help = opt.help;
				li.appendChild(helpIcon);
				filterList.appendChild(li);

				const filterTooltipId = `${this.#values_table.id}-${this._widgetid}-${opt.label}-tooltip`;
				const filterTooltip = document.createElement('div');
				filterTooltip.id = filterTooltipId;
				filterTooltip.className = 'filter-tooltip';
				filterTooltip.textContent = opt.help;
				filterTooltip.style.display = 'none';
				document.body.appendChild(filterTooltip);

				this.#filterTooltipIds.add(filterTooltipId);

				helpIcon.addEventListener('mouseover', (e) => {
					const rect = helpIcon.getBoundingClientRect();
					filterTooltip.style.left = `${rect.left + window.scrollX + rect.width + 10}px`;
					filterTooltip.style.top = `${rect.top + window.scrollY + 15}px`;
					filterTooltip.style.display = 'block';
				});

				helpIcon.addEventListener('mouseout', () => {
					filterTooltip.style.display = 'none';
				});
			});

			filterTypeContainer.appendChild(filterList);

			filterButton.addEventListener('click', () => {
				event.stopPropagation();
				filterList.style.display = filterList.style.display === 'none' ? 'block' : 'none';
			});

			let currentIndex = -1;

			filterButton.setAttribute('aria-haspopup', 'listbox');
			filterButton.setAttribute('aria-expanded', 'false');
			filterList.setAttribute('role', 'listbox');
			filterList.querySelectorAll('li').forEach(li => li.setAttribute('role', 'option'));

			function openList() {
				filterList.style.display = 'block';
				filterButton.setAttribute('aria-expanded', 'true');
				filterList.focus();
			}

			function closeList() {
				filterList.style.display = 'none';
				filterButton.setAttribute('aria-expanded', 'false');
				currentIndex = -1;
			}

			function focusItem(index) {
				const items = filterList.querySelectorAll('li');
				if (items.length === 0) return;
				items.forEach(li => li.classList.remove('focused'));
				if (index >= 0 && index < items.length) {
					currentIndex = index;
					items[currentIndex].classList.add('focused');
					items[currentIndex].scrollIntoView({ block: 'nearest' });
				}
			}

			filterButton.addEventListener('keydown', (e) => {
				const items = filterList.querySelectorAll('li');
				switch (e.key) {
					case ' ':
					case 'Enter':
						e.preventDefault();
						if (filterList.style.display === 'none') {
							openList();
							focusItem(0);
						}
						else {
							closeList();
						}
						break;
					case 'ArrowDown':
						e.preventDefault();
						openList();
						focusItem(0);
						break;
					case 'ArrowUp':
						e.preventDefault();
						openList();
						focusItem(items.length - 1);
						break;
				}
			});

			filterList.addEventListener('keydown', (e) => {
				const items = filterList.querySelectorAll('li');
				if (items.length === 0) return;

				switch (e.key) {
					case 'ArrowDown':
						e.preventDefault();
						focusItem((currentIndex + 1) % items.length);
						break;
					case 'ArrowUp':
						e.preventDefault();
						focusItem((currentIndex - 1 + items.length) % items.length);
						break;
					case 'Enter':
					case ' ':
						e.preventDefault();
						if (currentIndex >= 0) {
							items[currentIndex].click();
							closeList();
							filterButton.focus();
						}
						break;
					case 'Escape':
						e.preventDefault();
						closeList();
						filterButton.focus();
						break;
					case 'Tab':
						closeList();
						break;
				}
			});

			filterList.querySelectorAll('li').forEach((li, i) => {
				li.addEventListener('mouseenter', () => focusItem(i));
			});

			this.dropdownOutsideClickHandler = (e) => {
				if (!filterTypeContainer.contains(e.target)) {
					filterList.style.display = 'none';
				}
			};

			const searchInput = document.createElement('input');
			searchInput.type = 'text';
			searchInput.placeholder = 'Search...';
			searchInput.value = this.#filterState?.search || '';

			const clearBtn = document.createElement('span');
			clearBtn.className = 'clear-btn';
			clearBtn.textContent = '✕ Clear';
			clearBtn.addEventListener('click', () => {
				searchInput.value = '';
				searchInput.dispatchEvent(new Event('input'));

				renderVisibleCheckboxes();
				updateSummary();
				updateWarningIcon();
				updateClearFiltersButton();
				lastCheckedCheckbox = null;
			});

			filterControls.appendChild(filterTypeContainer);
			filterControls.appendChild(searchInput);
			filterControls.appendChild(clearBtn);

			header.appendChild(headerTitle);
			header.appendChild(filterControls);

			const checkboxContainer = document.createElement('div');
			checkboxContainer.className = 'filter-popup-checkboxes';

			const footer = document.createElement('div');
			footer.className = 'filter-popup-footer';

			const sectionContainer = document.createElement('div');
			sectionContainer.className = 'section-container';

			const summary = document.createElement('div');
			summary.className = 'summary';
			summary.textContent = '0 selected';
			sectionContainer.appendChild(summary);

			const toggleRow = document.createElement('div');
			toggleRow.style.marginTop = '6px';
			toggleRow.className = 'toggle-row';

			const toggleButton = document.createElement('button');
			let isAllSelected = this.#filterState?.allSelected || false;
			toggleButton.textContent = isAllSelected ? 'Uncheck All' : 'Select All';
			toggleButton.style.background = '#455a64';
			toggleButton.style.color = '#eee';
			toggleButton.style.fontWeight = 'bold';

			toggleButton.addEventListener('click', () => {
				let valuesToCheck;

				if (filterValues.length !== sortedValues.length || filteredValues.some((v, i) => v !== sortedValues[i])) {
					valuesToCheck = filteredValues;
				}
				else {
					valuesToCheck = sortedValues;
				}

				isAllSelected = valuesToCheck.length > 0 && valuesToCheck.every(value => {
					return this.#filterState.checked?.includes(String(value).toLowerCase());
				});

				if (isAllSelected) {
					this.#filterState.checked = [];
					isAllSelected = false;
					toggleButton.textContent = 'Select All';
				}
				else {
					this.#filterState.checked = valuesToCheck.map(value => String(value).toLowerCase());
					isAllSelected = true;
					toggleButton.textContent = 'Uncheck All';
				}

				renderVisibleCheckboxes();
				updateSummary();
				updateWarningIcon();
				updateClearFiltersButton();
			});

			toggleRow.appendChild(toggleButton);
			sectionContainer.appendChild(toggleRow);
			footer.appendChild(sectionContainer);

			const gap = document.createElement('div');
			gap.style.height = '2px';
			footer.appendChild(gap);

			const buttonsRow = document.createElement('div');
			buttonsRow.style.display = 'flex';
			buttonsRow.style.justifyContent = 'flex-start';
			buttonsRow.style.gap = '8px';
			buttonsRow.style.paddingLeft = '10px';

			const applyButton = document.createElement('button');
			applyButton.textContent = 'Apply';
			applyButton.style.minWidth = '80px';
			applyButton.style.padding = '6px 12px';

			applyButton.addEventListener('click', () => {
				popup.style.display = 'none';
			
				const checkedValues = this.#filterState?.checked || [];
			
				const hasCheckedValues = checkedValues.length > 0;
				const query = searchInput.value.trim();
				const type = hiddenInput.value;
			
				this.#filterState = {
					search: query,
					type,
					checked: hasCheckedValues ? checkedValues : [],
					allSelected: isAllSelected
				};
			
				this.#applyFilter();
				this._resumeUpdating();
			});

			searchInput.addEventListener('keydown', (e) => {
				if (e.key === 'Enter') {
					e.preventDefault();
					applyButton.click();
				}
			});

			const resetButton = document.createElement('button');
			resetButton.textContent = 'Cancel';
			resetButton.className = 'cancel';
			resetButton.style.minWidth = '80px';
			resetButton.style.padding = '6px 12px';
			resetButton.addEventListener('click', () => {
				popup.style.display = 'none';
				this._resumeUpdating();
			});

			const clearFiltersButton = document.createElement('button');
			clearFiltersButton.textContent = 'Clear Filters';
			clearFiltersButton.className = 'clear-filters';
			clearFiltersButton.style.padding = '6px 12px';

			clearFiltersButton.addEventListener('click', () => {
				searchInput.value = '';
				searchInput.dispatchEvent(new Event('input'));

				this.#filterState.checked = [];

				isAllSelected = false;
				toggleButton.textContent = 'Select All';

				renderVisibleCheckboxes();
				updateSummary();
				updateWarningIcon();
				updateClearFiltersButton();
				lastCheckedCheckbox = null;
				if ((this.#selected_items.length > 0 && this.#selected_items[0].itemid !== this.#null_id) ||
						(this.#selected_hostid !== this.#null_id && this.#selected_hostid !== null)) {
					this.#clearFiltersClickedWithSelections = true;
					this.#selected_items = [{ itemid: this.#null_id, name: null }];
					this.#selected_hostid = this.#null_id;
				}
			});

			const warningSvg = `
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none">
					<path d="M12 3C12.3 3 12.6 3.15 12.78 3.44L21.53 18.61C21.95 19.33 21.4 20.25 20.53 20.25H3.47C2.6 20.25 2.05 19.33 2.47 18.61L11.22 3.44C11.4 3.15 11.7 3 12 3Z" fill="#fef08a" stroke="#eab308" stroke-width="1.25"/>
					<circle cx="12" cy="16" r="1.25" fill="#92400e"/>
					<rect x="11.25" y="9" width="1.5" height="4.5" rx="0.75" fill="#92400e"/>
				</svg>
			`;

			const tooltip = document.createElement('div');
			tooltip.id = this.#values_table.id + '-' + this._widgetid + '-tooltip';
			tooltip.className = 'custom-tooltip';
			tooltip.textContent = 'Checkbox selections will take precedence over text entered in the search box after clicking "Apply" button';
			tooltip.style.position = 'absolute';
			tooltip.style.display = 'none';
			tooltip.style.pointerEvents = 'none';
			document.body.appendChild(tooltip);

			if (this.#tooltipId !== null) {
				const oldTooltip = document.getElementById(this.#tooltipId);
				if (oldTooltip) {
					oldTooltip.remove();
				}
			}
			this.#tooltipId = tooltip.id;

			const warningIcon = document.createElement('span');
			warningIcon.className = 'filter-warning-icon';
			warningIcon.style.display = 'none';
			warningIcon.innerHTML = warningSvg;

			buttonsRow.appendChild(applyButton);
			buttonsRow.appendChild(resetButton);
			buttonsRow.appendChild(clearFiltersButton);
			buttonsRow.appendChild(warningIcon);
			footer.appendChild(buttonsRow);

			warningIcon.addEventListener('mouseenter', (e) => {
				const rect = warningIcon.getBoundingClientRect();
				tooltip.style.top = `${rect.top - rect.height - 8}px`;
				tooltip.style.left = `${rect.left + rect.width / 2}px`;
				tooltip.style.transform = 'translateX(-50%)';
				tooltip.style.display = 'block';
			});

			warningIcon.addEventListener('mouseleave', () => {
				tooltip.style.display = 'none';
			});

			const tempSpan = document.createElement('span');
			tempSpan.style.visibility = 'hidden';
			tempSpan.style.position = 'absolute';
			tempSpan.style.whiteSpace = 'nowrap';
			tempSpan.style.font = '13px Sans serif';
			document.body.appendChild(tempSpan);
			let maxWidth = 0;
			for (const value of sortedValues) {
				tempSpan.textContent = value;
				maxWidth = Math.max(maxWidth, tempSpan.offsetWidth);
			}
			document.body.removeChild(tempSpan);
			const calculatedWidth = Math.min(Math.max(maxWidth + 150, 400), 800);
			popup.style.width = `${calculatedWidth}px`;

			const visibleCount = 25;
			let startIndex = 0;
			const scrollContainer = document.createElement('div');
			scrollContainer.style.maxHeight = '300px';
			scrollContainer.style.minHeight = '100px';
			scrollContainer.style.overflowY = 'auto';
			scrollContainer.style.overflowX = 'hidden';
			scrollContainer.style.position = 'relative';
			scrollContainer.style.paddingRight = '6px';
			scrollContainer.style.boxSizing = 'border-box';
			const spacer = document.createElement('div');
			spacer.style.height = `${sortedValues.length * 30}px`;
			spacer.style.position = 'relative';
			checkboxContainer.style.position = 'absolute';
			checkboxContainer.style.width = '100%';
			checkboxContainer.style.top = '0';
			checkboxContainer.style.left = '0';
			checkboxContainer.style.right = '0';
			checkboxContainer.style.display = 'block';
			scrollContainer.appendChild(spacer);
			spacer.appendChild(checkboxContainer);
			popup.appendChild(header);
			popup.appendChild(scrollContainer);
			popup.appendChild(footer);

			this.handleInput = () => {
				scrollContainer.scrollTop = 0;
				const query = searchInput.value.toLowerCase();

				filteredValues = sortedValues.filter(v => {
					const text = String(v).toLowerCase();
					return this.#matchesFilter(text, query, hiddenInput.value);
				});

				const filteredValuesLowerCase = filteredValues.map(v => String(v).toLowerCase());

				this.#filterState.checked = this.#filterState.checked?.filter(v => filteredValuesLowerCase.includes(v));
				this.#filterState.search = query;

				isAllSelected = filteredValues.length > 0 && filteredValues.every(v => this.#filterState.checked?.includes(String(v).toLowerCase()));

				toggleButton.textContent = isAllSelected ? 'Uncheck All' : 'Select All';

				renderVisibleCheckboxes()
				updateSummary();
				updateWarningIcon();
				updateClearFiltersButton();
				spacer.style.height = `${filteredValues.length * 30}px`
			};

			const setupInputHandler = () => {
				const filterMode = hiddenInput.value;

				searchInput.removeEventListener('input', currentInputHandler);

				const newInputHandler = filterMode === 'boolean'
					? this.debounce(this.handleInput, 1000)
					: (sortedValues.length > 250
						? this.debounce(this.handleInput, 300)
						: this.handleInput);

				searchInput.addEventListener('input', newInputHandler);

				currentInputHandler = newInputHandler;
			};

			let currentInputHandler;

			setupInputHandler();

			const renderVisibleCheckboxes = () => {
				const scrollTop = scrollContainer.scrollTop;
				startIndex = Math.floor(scrollTop / 30);
				checkboxContainer.style.top = `${startIndex * 30}px`;
				const endIndex = Math.min(startIndex + visibleCount, filteredValues.length);

				checkboxContainer.innerHTML = '';

				for (let i = startIndex; i < endIndex; i++) {
					const value = filteredValues[i];
					const id = `filter_${String(value).replace(/[^a-zA-Z0-9]/g, '_')}`;
					const label = document.createElement('label');
					label.classList.add('custom-checkbox');
					label.innerHTML = `
						<input type="checkbox" id="${id}" value="${value}">
						<span>${value}</span>
					`;

					const checkbox = label.querySelector('input[type="checkbox"]');

					checkbox.checked = this.#filterState?.checked?.includes(String(value).toLowerCase());

					label.setAttribute('data-index', i);
					checkboxContainer.appendChild(label);
				}
			};

			function getIndexOfCheckbox(checkbox) {
				const value = checkbox.value;
				return filteredValues.indexOf(value);
			}

			function updateFilterState(cb, val, isChecked) {
				if (isChecked) {
					if (!this.#filterState.checked.includes(val)) {
						this.#filterState.checked.push(val);
					}
				}
				else {
					this.#filterState.checked = this.#filterState.checked.filter(v => v !== val);
				}
			}

			scrollContainer.addEventListener('scroll', () => {
				renderVisibleCheckboxes();
			});
			renderVisibleCheckboxes();

			checkboxContainer.addEventListener('change', (event) => {
				const cb = event.target;
				const val = cb.value.toLowerCase();
				const isChecked = cb.checked;
				const label = cb.closest('label');

				updateFilterState.call(this, cb, val, isChecked);

				lastCheckedCheckbox = cb;
				isAllSelected = (this.#filterState.checked.length === sortedValues.length);

				toggleButton.textContent = isAllSelected ? 'Uncheck All' : 'Select All';

				updateSummary();
				updateWarningIcon();
				updateClearFiltersButton();
			});

			checkboxContainer.addEventListener('click', (event) => {
				const cb = event.target;
				if (cb.type !== 'checkbox') return;

				const val = cb.value.toLowerCase();
				const isChecked = cb.checked;
				const label = cb.closest('label');
				const checkboxIndex = parseInt(label.getAttribute('data-index'), 10);

				if (event.shiftKey && lastCheckedCheckbox) {
					event.preventDefault();
					event.stopPropagation();
					const lastCheckedIndex = getIndexOfCheckbox(lastCheckedCheckbox);
					if (lastCheckedIndex !== -1) {
						const [from, to] = [Math.min(lastCheckedIndex, checkboxIndex), Math.max(lastCheckedIndex, checkboxIndex)];

						for (let j = from; j <= to; j++) {
							const value = filteredValues[j];
							const elementVal = String(value).toLowerCase();
							if (!this.#filterState.checked.includes(elementVal)) {
								this.#filterState.checked.push(elementVal);
							}
						}

						renderVisibleCheckboxes();
					}
				}

				isAllSelected = (this.#filterState.checked?.length == filteredValues.length || 0 === sortedValues.length);

				toggleButton.textContent = isAllSelected ? 'Uncheck All' : 'Select All';

				updateSummary();
				updateWarningIcon();
				updateClearFiltersButton();
			});

			const updateSummary = () => {
				const checked = this.#filterState.checked?.length || 0;
				summary.textContent = `${checked} selected`;
			}

			updateSummary();
			document.body.appendChild(popup);

			filterIcon.addEventListener('click', () => {
				if (popup.style.display === 'flex') {
					popup.style.display = 'none';
					return;
				}

				popup.style.visibility = 'hidden';
				popup.style.display = 'flex';
				popup.style.left = '0px';
				popup.style.top = '0px';

				const rect = filterIcon.getBoundingClientRect();
				const popupHeight = popup.offsetHeight;
				const popupWidth = popup.offsetWidth;
				const padding = 10;

				let topPos = rect.bottom + 5;
				if (topPos + popupHeight > window.innerHeight - padding) {
					topPos = rect.top - popupHeight - 5;
					if (topPos < padding) topPos = padding;
				}

				let leftPos = rect.left;
				if (leftPos + popupWidth > window.innerWidth - padding) {
					leftPos = window.innerWidth - popupWidth - padding;
					if (leftPos < padding) leftPos = padding;
				}

				popup.style.left = `${leftPos}px`;
				popup.style.top = `${topPos}px`;

				popup.style.visibility = 'visible';
				popup.style.display = 'flex';

				requestAnimationFrame(() => searchInput.focus());
				this._pauseUpdating();
			});

			const arrowSpan = firstTh.querySelector('span#arrow');
			if (arrowSpan) {
				firstTh.insertBefore(filterIcon, arrowSpan);
			}
			else {
				firstTh.insertBefore(filterIcon, firstTh.firstChild);
			}

			function updateWarningIcon() {
				const checkboxes = popup.querySelectorAll('.filter-popup-checkboxes input[type="checkbox"]');
				const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
				const hasSearchText = searchInput.value.trim() !== '';
				if (anyChecked && hasSearchText) {
					warningIcon.style.display = 'flex';
				}
				else {
					warningIcon.style.display = 'none';
				}
			}

			const updateClearFiltersButton = () => {
				if (this.#filterState.checked.length > 0 || this.#filterState.search !== '') {
					clearFiltersButton.style.visibility = 'visible';
				}
				else {
					clearFiltersButton.style.visibility = 'hidden';
				}
			}

			if (this.#filterApplied) {
				this.#applyFilter();
				updateClearFiltersButton();
			}

			if (searchInput.value !== '') {
				searchInput.dispatchEvent(new Event('input'));
			}

			updateClearFiltersButton();
			this.makeDraggable(popup, header);

		}

		this.closeFilterPopupHandler = this.closeFilterPopup.bind(this);
		this.boundMouseDown = this.handleMouseDownTi.bind(this);
		this.boundMouseMove = this.handleMouseMoveTi.bind(this);
		this.boundMouseUp = this.handleMouseUpTi.bind(this);
		this.attachListeners();

		this.checkAndRemarkSelected();

	}

	getActionsContextMenu({can_copy_widget, can_paste_widget}) {
		const menu = super.getActionsContextMenu({can_copy_widget, can_paste_widget});

		if (this.isEditMode()) {
			return menu;
		}

		let menu_actions = null;

		for (const search_menu_actions of menu) {
			if ('label' in search_menu_actions && search_menu_actions.label === t('Actions')) {
				menu_actions = search_menu_actions;
				break;
			}
		}

		if (menu_actions === null) {
			menu_actions = {
				label: t('Actions'),
				items: []
			};

			menu.unshift(menu_actions);
		}

		menu_actions.items.push({
			label: t('Download as csv'),
			disabled: !this.#rowsArray,
			clickCallback: () => {
				this.exportTableRowsToCSV();
			}
		});

		return menu;
        }

	checkAndRemarkSelected() {
		if (this.#selected_hostid !== null) {
			this.#broadcast(CWidgetsData.DATA_TYPE_HOST_ID, CWidgetsData.DATA_TYPE_HOST_IDS, this.#selected_hostid);
			this._markSelected(this.#dataset_host);
		}
		else if (this.#first_td_host_cell !== null && this._fields.autoselect_first) {
			this.#first_td_host_cell.click();
		}

		if (this.#selected_items.length > 0) {
			this._markSelected(this.#dataset_item, true);
		}
		else if (this.#first_td_value_cell !== null && this._fields.autoselect_first) {
			this.#first_td_value_cell.click();
		}
	}

	onResize() {
		if (this._state === WIDGET_STATE_ACTIVE) {
			this.#recalculateSvgSparklines();
		}
	}

	debounce(fn, delay) {
		let timeoutId;
		return (...args) => {
			clearTimeout(timeoutId);
			timeoutId = setTimeout(() => fn(...args), delay);
		};
	}

	closeFilterPopup(e) {
		const popup = document.getElementById(this.#popupId);
		const filterIcon = this._target.getElementsByClassName('filter-icon').item(0);
		if (popup && filterIcon) {
			if (!popup.contains(e.target) && !filterIcon.contains(e.target)) {
				popup.style.display = 'none';
				this._resumeUpdating();
				if (this.#clearFiltersClickedWithSelections) {
					filterIcon.classList.remove('active');
					filterIcon.classList.remove('filter-error');
					filterIcon.title = 'Click to filter this column';
					this.#filterApplied = false;
					this.checkAndRemarkSelected();
					this.#clearFiltersClickedWithSelections = false;
				}
			}
		}
	}

	attachListeners() {
		document.addEventListener('click', this.closeFilterPopupHandler);
		document.addEventListener('click', this.dropdownOutsideClickHandler);

		if (this.handle && this.popup) {
			this.handle.addEventListener('mousedown', this.boundMouseDown);
			document.addEventListener('mousemove', this.boundMouseMove);
			document.addEventListener('mouseup', this.boundMouseUp);
		}
	}

	detachListeners() {
		document.removeEventListener('click', this.closeFilterPopupHandler);
		document.removeEventListener('click', this.dropdownOutsideClickHandler);

		if (this.handle && this.boundMouseDown) {
			this.handle.removeEventListener('mousedown', this.boundMouseDown);
		}
		document.removeEventListener('mousemove', this.boundMouseMove);
		document.removeEventListener('mouseup', this.boundMouseUp);
	}

	makeDraggable(popup, handle) {
		this.popup = popup;
		this.handle = handle
		this.isDragging = false;
		this.offsetX = 0;
		this.offsetY = 0;

		handle.style.cursor = 'grab';
	}

	handleMouseDownTi(e) {
		if (
			e.target.tagName === 'INPUT' ||
			e.target.tagName === 'SELECT' ||
			e.target.tagName === 'BUTTON' ||
			e.target.classList.contains('clear-btn')
		) {
			return;
		}

		this.isDragging = true;
		this.handle.style.cursor = 'grabbing';
		const rect = this.popup.getBoundingClientRect();
		this.offsetX = e.clientX - rect.left;
		this.offsetY = e.clientY - rect.top;
		document.body.style.userSelect = 'none';
	}

	handleMouseMoveTi(e) {
		if (this.isDragging) {
			this.handle.style.cursor = 'grabbing';
			this.popup.style.left = `${e.clientX - this.offsetX}px`;
			this.popup.style.top = `${e.clientY - this.offsetY}px`;
		}
	}

	handleMouseUpTi(e) {
		this.isDragging = false;
		this.handle.style.cursor = 'grab';
		document.body.style.userSelect = '';
	}

	getTheme() {
		this.#theme = jQuery('html').attr('theme');
		switch(this.#theme) {
			case 'dark-theme':
			case 'hc-dark':
				this.host_bg_color = '#67351d';
				this.bg_color = '#2f280a';
				this.font_color = '#f2f2f2';
				break;
			case 'blue-theme':
			case 'hc-light':
				this.host_bg_color = '#f7ae3e';
				this.bg_color = '#fcf7c2';
				this.font_color = '#1f2c33';
				break;
		}
	}

	#filterSelectedItems() {
		this.#selected_items = this.#selected_items.filter(selectedItem => {
			return this.#rowsArray.some(rowObj => {
				if (rowObj.status === 'display') {
					const menuElements = rowObj.row.querySelectorAll(`td [data-menu]`);
					for (const menuElement of menuElements) {
						const menuData = JSON.parse(menuElement.dataset.menu);
						if (menuData.itemid === selectedItem.item || menuData.name === selectedItem.name) {
							return true;
						}
					}
				}
				return false;
			});
		});
	}

	#applyFilter() {
		function getColumnInfo(td, columns) {
			const indexStr = td.getAttribute('column-id');
			if (indexStr == null) return null;

			const indexNum = parseInt(indexStr, 10);
			if (isNaN(indexNum)) return null;

			const columnDef = columns?.[indexNum];
			if (!columnDef) return null;

			return { indexNum, columnDef };
		}

		function resolveMinMaxSum(columnDef, dynamicStats, indexNum) {
			const staticMin = columnDef.min !== undefined && columnDef.min !== '' ? parseFloat(columnDef.min) : null;
			const staticMax = columnDef.max !== undefined && columnDef.max !== '' ? parseFloat(columnDef.max) : null;

			const min = staticMin !== null ? staticMin : dynamicStats[indexNum]?.min;
			const max = staticMax !== null ? staticMax : dynamicStats[indexNum]?.max;
			const sum = dynamicStats[indexNum]?.sum;

			return { min, max, sum };
		}

		function extractValueFromHintbox(hintboxContent) {
			const match = hintboxContent.match(/<div class="hintbox-wrap">(.*?)<\/div>/);
			if (match && match[1]) {
				const numericValue = parseFloat(match[1]);
				if (!isNaN(numericValue)) {
					return numericValue;
				}
			}
			return null;
		}

		if (!this.#filterState) return;

		this.invalidRegex = false;
		const isCaseSensitive = this.#filterState.caseSensitive || false;

		const checkedValues = this.#filterState.checked || [];
		const allowedValues = checkedValues.length > 0 ? new Set(checkedValues) : null;
		const searchValue = (this.#filterState.search || '').trim().toLowerCase();
		const filterMode = this.#filterState.type || 'contains';

		let columnStats = [];
		const columns = this._fields.columns;

		this.#rowsArray.forEach(rowObj => {
			const tr = rowObj.row
			const isResetRow = tr.querySelector('[reset-row]') !== null;
			const isFooterRow = tr.querySelector('[footer-row]') !== null;
			if (isResetRow || isFooterRow) {
				rowObj.status = 'display';
				return;
			}

			const td = tr.querySelector('td');
			const text = td?.textContent.trim().toLowerCase() || '';

			let showRow = true;
	
			if (allowedValues && allowedValues.size > 0) {
				showRow = allowedValues.has(text);
			}
			else if (searchValue !== '') {
				showRow = this.#matchesFilter(text, searchValue, filterMode, isCaseSensitive);
			}

			rowObj.status = showRow ? 'display' : 'hidden';

			if (this._fields.bar_gauge_layout === 0 || this._fields.layout === 50) {
				if (rowObj.status === 'display') {
					const allTdsInRow = tr.querySelectorAll('td');

					allTdsInRow.forEach((td, index) => {
						if (this._isBarGauge(td) || this._isSparkLine(td)) return;
						let value = null;

						const hintboxContent = td.getAttribute('data-hintbox-contents');
						if (hintboxContent) {
							value = extractValueFromHintbox(hintboxContent);
						}

						if (value === null) return;

						const info = getColumnInfo(td, columns);
						if (!info) return;

						const { indexNum, columnDef } = info;

						const hasStaticMin = columnDef.min !== undefined && columnDef.min !== '';
						const hasStaticMax = columnDef.max !== undefined && columnDef.max !== '';

						if (!this._isNumeric(value)) return;

						if (!columnStats[indexNum]) {
							columnStats[indexNum] = {
								min: hasStaticMin ? columnDef.min : value,
								max: hasStaticMax ? columnDef.max : value,
								sum: value
							};
						}
						else {
							columnStats[indexNum].sum += value;
							if (!hasStaticMin) {
								columnStats[indexNum].min = Math.min(columnStats[indexNum].min, value);
							}

							if (!hasStaticMax) {
								columnStats[indexNum].max = Math.max(columnStats[indexNum].max, value);
							}
						}
					});
				}
			}
		});

		const selectedItemsBefore = [...this.#selected_items];

		const displayedRows = this.#rowsArray.filter(rowObj => rowObj.status === 'display');
		this.#filterSelectedItems();

		if (selectedItemsBefore.length > 0 && this.#selected_items.length === 0) {
			this.#selected_items = [{ itemid: this.#null_id, name: null }];
		}

		if (selectedItemsBefore.length === 1 &&
				this.#selected_items.length === 1 &&
				this.#selected_items[0].itemid === this.#null_id &&
				selectedItemsBefore[0].itemid === this.#null_id &&
				!this.#clearFiltersClickedWithSelections) {
		}
		else {
			this.checkAndRemarkSelected();
		}

		if (this._fields.bar_gauge_layout === 0 || this._fields.layout === 50) {
			displayedRows.forEach(rowObj => {
				const tr = rowObj.row;
				const allTdsInRow = tr.querySelectorAll('td');

				allTdsInRow.forEach((td, index) => {
					const gauge = this._isBarGauge(td);
					if (!gauge) return;

					const info = getColumnInfo(td, columns);
					if (!info) return;

					const { indexNum, columnDef } = info;
					const { min, max, sum } = resolveMinMaxSum(columnDef, columnStats, indexNum);

					if (min === undefined || max === undefined) return;

					const bgValue = parseFloat(gauge.getAttribute('value'));

					let newTooltipValue = null;
					let hintStrValue = '';
					let formatted = newTooltipValue;
					if (this._fields.bar_gauge_tooltip === 0) {
						newTooltipValue = bgValue / max * 100;
						hintStrValue = ' % of column max';
						formatted = `${newTooltipValue.toFixed(3)} ${hintStrValue}`;
						gauge.setAttribute('max', max);
					}
					else if (this._fields.bar_gauge_tooltip === 1) {
						newTooltipValue = bgValue / sum * 100;
						hintStrValue = ' % of column sum';
						formatted = `${newTooltipValue.toFixed(3)} ${hintStrValue}`;
						gauge.setAttribute('max', sum);
					}
					else {
						gauge.setAttribute('max', max);
					}

					if (formatted !== null) {
						const oldHint = td.getAttribute('data-hintbox-contents');
						const newHint = oldHint.replace(/>.*?</, `>${formatted}<`);
						td.setAttribute('data-hintbox-contents', newHint);
					}

					gauge.setAttribute('min', min);
				});
			});
		}

		this.#totalRows = displayedRows.length;
		this.#currentPage = 1;

		this.#removePaginationControls();
		if (this.#totalRows > this.#rowsPerPage) {
			this.#displayPaginationControls();
		}

		this.#updateDisplay(true, true, false);

		const firstTh = this.#values_table.querySelector('thead th');
		if (firstTh) {
			const filterIcon = firstTh.querySelector('.filter-icon');
			if (filterIcon) {
				if (this.invalidRegex) {
					filterIcon.classList.add('filter-error');
					filterIcon.title = 'Invalid Regex pattern!';
					filterIcon.classList.remove('active');
					this.#filterApplied = true;
				}
				else if ((allowedValues && allowedValues.size > 0) || searchValue !== '') {
					filterIcon.classList.add('active');
					filterIcon.classList.remove('filter-error');
					filterIcon.title = 'Filter Applied!';
					this.#filterApplied = true;
				}
				else {
					filterIcon.classList.remove('active');
					filterIcon.classList.remove('filter-error');
					filterIcon.title = 'Click to filter this column';
					this.#filterApplied = false;
				}
			}
		}
	}


	#matchesFilter(text, searchValue, filterMode, caseSensitive = false) {
		text = text.trim();
		searchValue = searchValue.trim();

		if (!caseSensitive) {
			text = text.toLowerCase();
			searchValue = searchValue.toLowerCase();
		}

		switch (filterMode) {
			case 'boolean': {
				if (searchValue === '') return false;
				const tokenize = expr => {
					const tokens = [];
					const regex = /"([^"\\]*(?:\\.[^"\\]*)*)"|'([^'\\]*(?:\\.[^'\\]*)*)'|(\bAND\b|\bOR\b|\bNOT\b)|([()])|([^()\s]+)/gi;
					let match;
					while ((match = regex.exec(expr)) !== null) {
						if (match[1] !== undefined) tokens.push(match[1]);
						else if (match[2] !== undefined) tokens.push(match[2]);
						else if (match[3] !== undefined) tokens.push(match[3].toUpperCase());
						else if (match[4] !== undefined) tokens.push(match[4]);
						else if (match[5] !== undefined) tokens.push(match[5]);
					}
					return tokens;
				};

				const parseExpression = tokens => {
					const parseTerm = () => {
						let node = parseFactor();
						while (tokens[0] === 'AND') {
							tokens.shift();
							node = { op: 'AND', left: node, right: parseFactor() };
						}
						return node;
					};

					const parseFactor = () => {
						const tok = tokens.shift();
						if (tok === '(') {
							const node = parseExpression(tokens);
							if (tokens[0] === ')') tokens.shift();
							return node;
						}
						if (tok === 'NOT') {
							return { op: 'NOT', value: parseFactor() };
						}
						return tok;
					};

					let node = parseTerm();
					while (tokens[0] === 'OR') {
						tokens.shift();
						node = { op: 'OR', left: node, right: parseTerm() };
					}
					return node;
				};

				const evaluateNode = (node, text) => {
					if (node === undefined || (typeof node === 'object' && node.op === undefined)) {
						return false;
					}

					if (typeof node === 'string') {
						const term = caseSensitive ? node : node.toLowerCase();
						const isRegex = term.startsWith('^') || term.endsWith('$') || /[.*+?()[\]|]/.test(term);
						try {
							return isRegex
								? new RegExp(term, caseSensitive ? '' : 'i').test(text)
								: text.includes(term);
						}
						catch {
							return false;
						}
					}
					switch (node.op) {
						case 'AND': return evaluateNode(node.left, text) && evaluateNode(node.right, text);
						case 'OR':  return evaluateNode(node.left, text) || evaluateNode(node.right, text);
						case 'NOT': return !evaluateNode(node.value, text);
					}

					return false;
				};

				try {
					const tokens = tokenize(searchValue);
					const ast = parseExpression(tokens);
					return evaluateNode(ast, text);
				}
				catch (e) {
					console.error('Boolean filter parse error:', e);
					return false;
				}
			}
			case 'equals':
				return text === searchValue;
			case 'starts with':
			case 'startswith':
				return text.startsWith(searchValue);
			case 'ends with':
			case 'endswith':
				return text.endsWith(searchValue);
			case 'wildcard':
				try {
					const escaped = searchValue.replace(/[-[\]{}()+?.\\^$|]/g, '\\$&');
					const pattern = escaped.replace(/\*/g, '.*').replace(/\?/g, '.');
					const regex = new RegExp(`^.*${pattern}.*$`, caseSensitive ? '' : 'i');
					return regex.test(text);
				}
				catch {
					return false;
				}
			case 'does not contain':
				return !text.includes(searchValue);
			case 'regex':
				try {
					const regex = new RegExp(searchValue, caseSensitive ? '' : 'i');
					return regex.test(text);
				}
				catch {
					this.invalidRegex = true;
					return false;
				}
			case 'contains':
			default:
				return text.includes(searchValue);
		}
	}


	#recalculateCanvasSize() {
		const barGauges = this.#values_table.querySelectorAll('tr td z-bar-gauge');
		requestAnimationFrame(() => {
			barGauges.forEach((barGauge) => {
				barGauge._refresh_frame = null;
				barGauge.unregisterEvents();
				barGauge.registerEvents();
			});
		});

	}

	#recalculateSvgSparklines() {
		requestAnimationFrame(() => {
			this.#values_table?.querySelectorAll('z-sparkline').forEach(el => {
				el.attributeChangedCallback('width', null, el.offsetWidth);
			});
		});
	}

	#processItemIds() {
		if (this.#selected_items.length === 1) {
			const item = this.#selected_items[0];
			try {
				const obj = JSON.parse(item.itemid);
				obj[0].tags = item.tags;
				return JSON.stringify(obj);
			}
			catch {
				return item.itemid;
			}
		}

		const uniqueItems = new Set();

		this.#selected_items.forEach(item => {
			if (typeof item.itemid === 'number') {
				const itemKey = `${item.itemid}-`;
				if (!uniqueItems.has(itemKey)) {
					uniqueItems.add(itemKey);
				}
			}
			else if (typeof item.itemid === 'string' && item.itemid.startsWith('[')) {
				const parsedItems = JSON.parse(item.itemid);
				parsedItems.forEach(parsedItem => {
					const itemKey = `${parsedItem.itemid}-${parsedItem.color}`;
					if (!uniqueItems.has(itemKey)) {
						uniqueItems.add(itemKey);
					}
				});
			}
		});

		const resultArray = Array.from(uniqueItems).map(itemKey => {
			const [itemid, color] = itemKey.split('-');
			return { itemid, color: color || '' };
		});

		return JSON.stringify(resultArray);
	}

	#handleCellClick(td, event=false) {
		if (event && event.target?.closest('.menu-btn')) {
			return;
		}

		const extBtn = event.target?.closest('.ext-btn');
		if (extBtn) {
			const href = extBtn.getAttribute('href');
			const target = extBtn.getAttribute('target') || '_self';
			window.open(href, target);
			return;
		}

		let tdClicked = td;
		var nextTd = tdClicked.nextElementSibling;
		var previousTd = tdClicked.previousElementSibling;

		let element = td.querySelector(this.#menu_selector);
		if (this._isDoubleSpanColumn(tdClicked)) {
			element = nextTd.querySelector(this.#menu_selector);
		}

		if (!element) return;

		const dataset = JSON.parse(element.dataset.menu);

		if (dataset?.type === this.#dataset_item) {
			const tags = dataset.tags ? JSON.parse(dataset.tags) : [];

			const selectedItem = { itemid: dataset.itemid, name: dataset.name, tags: tags };

			const key = `${dataset.itemid}__${dataset.name}`;
			const index = this.#selected_items.findIndex(
				item => `${item.itemid}__${item.name}` === key
			);

			if (event && event.ctrlKey) {
				const before = this.#selected_items.length;
				this.#selected_items = this.#selected_items.filter(
					item => `${item.itemid}__${item.name}` !== key
				);

				if (this.#selected_items.length === before) {
					this.#selected_items.push(selectedItem);
				}
			}
			else if (event && event.shiftKey) {
				this.#selectRange(tdClicked);
			}
			else {
				const allMatch = this.#selected_items.length > 0 &&
					this.#selected_items.every(item => `${item.itemid}__${item.name}` === key);

				if (index !== -1 && allMatch) {
					this.#selected_items = [{ itemid: this.#null_id, name: null }];
				}
				else {
					this.#selected_items = [selectedItem];
				}
			}

			this._markSelected(this.#dataset_item);
			const itemsSelected = this.#processItemIds();
			this.#broadcast(CWidgetsData.DATA_TYPE_ITEM_ID, CWidgetsData.DATA_TYPE_ITEM_IDS, itemsSelected);
		}
		else if (dataset?.type === this.#dataset_host) {
			if (this.#selected_hostid === dataset.hostid) {
				this.#selected_hostid = this.#null_id;
			}
			else {
				this.#selected_hostid = dataset.hostid;
			}
			this._markSelected(this.#dataset_host);
			this.#broadcast(CWidgetsData.DATA_TYPE_HOST_ID, CWidgetsData.DATA_TYPE_HOST_IDS, this.#selected_hostid);
		}

		this.#lastClickedCell = tdClicked;
	}

	#selectRange(td) {
		if (!this.#lastClickedCell) return;

		const startIndex = this.#lastClickedCell.parentNode.rowIndex;
		const endIndex = td.parentNode.rowIndex;
		const columnIndex = this.#lastClickedCell.cellIndex;

		const start = Math.min(startIndex, endIndex);
		const end = Math.max(startIndex, endIndex);

		for (let i = start; i <= end; i++) {
			const cell = this.#values_table.rows[i].cells[columnIndex];
			let element = cell.querySelector(this.#menu_selector);
			if (this._isDoubleSpanColumn(cell)) {
				element = cell.nextElementSibling.querySelector(this.#menu_selector);
			}

			if (!element) continue;

			const dataset = JSON.parse(element.dataset.menu);
			const tags = dataset.tags ? JSON.parse(dataset.tags) : [];
			const selectedItem = { itemid: dataset.itemid, name: dataset.name, tags: tags };

			this.#selected_items.push(selectedItem);
		}
	}

	#removePaginationControls() {
		const paginationElements = this.#parent_container
			? this.#parent_container.querySelectorAll('.pagination-controls')
			: [];

		paginationElements.forEach(element => {
			element.remove();
		});
	}

	#displayPaginationControls() {
		this.#paginationElement = document.createElement('div');
		this.#paginationElement.classList.add('pagination-controls');

		const buttons = ['<<', '<'];
		buttons.forEach(label => {
			const button = document.createElement('button');
			button.textContent = label;
			button.addEventListener('click', () => this.#handlePaginationClick(label));
			this.#paginationElement.appendChild(button);
		});

		const pageInfo = document.createElement('span');
		this.#paginationElement.appendChild(pageInfo);
		this.#updatePageInfo(pageInfo);

		const buttons_b = ['>', '>>'];
		buttons_b.forEach(label => {
			const button_b = document.createElement('button');
			button_b.textContent = label;
			button_b.addEventListener('click', () => this.#handlePaginationClick(label));
			this.#paginationElement.appendChild(button_b);
		});

		this.#parent_container.appendChild(this.#paginationElement);
	}

	#handlePaginationClick(label) {
		switch (label) {
			case '<<':
				this.#currentPage = 1;
				break;
			case '<':
				if (this.#currentPage > 1) {
					this.#currentPage--;
				}
				break;
			case '>':
				if (this.#currentPage < Math.ceil(this.#totalRows / this.#rowsPerPage)) {
					this.#currentPage++;
				}
				break;
			case '>>':
				this.#currentPage = Math.ceil(this.#totalRows / this.#rowsPerPage);
				break;
		}
		this.#updateDisplay(true, false, false);
	}

	#updateDisplay(scrollToTop = false, updateFooter = false, firstRun = false) {
		this.#parent_container.classList.add('is-loading');
		if (!this.#parent_container.classList.contains('widget-blur')) {
			this.#parent_container.classList.toggle('widget-blur');
		}

		const visibleRows = this.#rowsArray.filter(({ status }) => status === 'display');
		const maxPages = Math.ceil(visibleRows.length / this.#rowsPerPage);

		if (this.#currentPage > maxPages) {
			this.#currentPage = 1;
		}

		const startIndex = (this.#currentPage - 1) * this.#rowsPerPage;
		const endIndex = Math.min(startIndex + this.#rowsPerPage, visibleRows.length);
		const displayedRows = visibleRows.slice(startIndex, endIndex);

		setTimeout(() => {
			while (this.#values_table.tBodies[0].firstChild) {
				this.#values_table.tBodies[0].removeChild(this.#values_table.tBodies[0].firstChild);
			}

			if (firstRun) {
				this.#rowsArray.forEach(({ row }) => {
					row.classList.remove('display-none');
				});
			}

			displayedRows.forEach(({ row }) => {
				this.#values_table.tBodies[0].appendChild(row);
			});

			this.#recalculateCanvasSize();
			this.#recalculateSvgSparklines();

			if (scrollToTop) {
				this._contents.scrollTop = 0;
			}

			this.#updatePageInfo(this.#paginationElement?.querySelector('span'));
			if (updateFooter) {
				this.updateTableFooter();
			}

			setTimeout(() => {
				if (this.#parent_container.classList.contains('widget-blur')) {
					this.#parent_container.classList.toggle('widget-blur');
				}
				this.#parent_container.classList.remove('is-loading');
			}, 0);
		}, 0);
	}

	#updatePageInfo(pageInfoElement) {
		if (!pageInfoElement) return;
		const totalPages = Math.ceil(this.#totalRows / this.#rowsPerPage);
		pageInfoElement.textContent = `Page ${this.#currentPage} of ${totalPages}`;
	}

	#sortTable(th, ascending, span, preserve = false) {
		this._sortTableRowsByColumn(th.id, ascending);
		th.dataset['sort'] = ascending ? 'asc' : 'desc';
		span.className = ascending ? 'arrow-up' : 'arrow-down';
		if (!preserve) {
			this.#currentPage = 1;
		}
		this.#updateDisplay();
	}

	#getSetSpans(th) {
		const span = th.querySelector('span#arrow');
		const allArrowSpans = this.#values_table.querySelectorAll('thead tr th span#arrow');
		for (let eachSpan of allArrowSpans) {
			eachSpan.className = 'new-arrow';
		}

		return span;
	}


	#broadcast(id_type, id_types, id) {
		this.broadcast({
			[id_type]: [id],
			[id_types]: [id]
		});
	}

	_markSelected(type) {
		const tds = [];

		this.#rowsArray.forEach(rowObj => {
			const tr = rowObj.row;
			tr.querySelectorAll('td').forEach(td => tds.push(td));
		});

		var prevTd = null;
		let hasItemMarking = false;
		let hasHostMarking = false;
		var tdsToMark = [];
		const nameTracking = [];
		const actualsFound = [];

		const isItemSelected = (dataset) => {
			return this.#selected_items.some(item =>
				item.itemid === dataset.itemid && item.name === dataset.name
			);
		};

		const hasName = (dataset) => {
			return this.#selected_items.some(item =>
				item.name === dataset.name
			);
		};

		for (const td of tds) {
			const origStyle = td.style.cssText;
			var element = td.querySelector(this.#menu_selector);

			if (!element) {
				prevTd = td;
				continue;
			}

			const dataset = JSON.parse(element.dataset.menu);
			const cell_key = dataset?.itemid + "_" + td.getAttribute('id');

			if (dataset?.type === this.#dataset_host) {
				if (type === this.#dataset_item) continue;

				if (dataset.hostid === this.#selected_hostid) {
					hasHostMarking = true;
					td.style.backgroundColor = this.host_bg_color;
					td.style.color = this.font_color;
				}
				else {
					td.style.backgroundColor = td.style.color = '';
				}
			}

			else if (dataset?.type === this.#dataset_item) {
				if (type === this.#dataset_host) continue;

				if (isItemSelected(dataset)) {
					hasItemMarking = true;
					const tags = dataset.tags ? JSON.parse(dataset.tags) : [];
					actualsFound.push({ itemid: dataset.itemid, name: dataset.name, tags: tags });

					if (this._isDoubleSpanColumn(prevTd)) {
						td.style.backgroundColor = prevTd.style.backgroundColor = this.bg_color;
						td.style.color = prevTd.style.color = this.font_color;
					}
					else {
						td.style.backgroundColor = this.bg_color;
						td.style.color = this.font_color;
					}
				}
				else {
					if (this._isDoubleSpanColumn(prevTd)) {
						if (this._isBarGauge(prevTd)) {
							td.style = prevTd.style = this.#cssStyleMap.get(cell_key);
						}
						else if (this._isSparkLine(prevTd)) {
							td.style = this.#cssStyleMap.get(cell_key);
							prevTd.style = ''
						}
					}
					else {
						td.style = this.#cssStyleMap.get(cell_key);
					}
				}

				if (hasName(dataset) && !nameTracking.includes(dataset.name)) {
					tdsToMark.push(td);
					nameTracking.push(dataset.name);
				}
			}

			prevTd = td;
		}

		if (!hasHostMarking && type === this.#dataset_host) {
			this.#broadcast(CWidgetsData.DATA_TYPE_HOST_ID, CWidgetsData.DATA_TYPE_HOST_IDS, this.#null_id);
		}

		if (type === this.#dataset_item) {
			if (!hasItemMarking) {
				if (tdsToMark.length > 0) {
					tdsToMark.forEach((td, index) => {
						const ctrlClickEvent = new MouseEvent('click', {
							bubbles: true,
							cancelable: true,
							ctrlKey: index !== 0,
							view: window
						});

						this.#handleCellClick(td, ctrlClickEvent);
					});
				}
				else if (this._fields.autoselect_first &&
						refresh &&
						this.#selected_items[0]['itemid'] !== this.#null_id) {
					this.#first_td_value_cell = null;
					const allTds = this.#values_table.querySelectorAll('td');
					for (const td of allTds) {
						let element = td.querySelector(this.#menu_selector);
						if (element) {
							if (this._isDoubleSpanColumn(td)) {
								const newTd = td.nextElementSibling;
								element = newTd.querySelector(this.#menu_selector);
							}

							const dataset = JSON.parse(element.dataset.menu);

							if (dataset.itemid) {
								this.#first_td_value_cell = td;
								break;
							}
						}
					}

					if (this.#first_td_value_cell !== null) {
						this.#first_td_value_cell.click();
					}
				}
				else {
					this.#broadcast(CWidgetsData.DATA_TYPE_ITEM_ID, CWidgetsData.DATA_TYPE_ITEM_IDS, this.#null_id);
				}
			}
			else {
				this.#selected_items = actualsFound;
				const itemsSelected = this.#processItemIds();
				this.#broadcast(CWidgetsData.DATA_TYPE_ITEM_ID, CWidgetsData.DATA_TYPE_ITEM_IDS, itemsSelected);
			}
		}
	}

	_isDoubleSpanColumn(td) {
		if (td === null) {
			return null;
		}

		return (this._isBarGauge(td) || this._isSparkLine(td));
	}

	_isBarGauge(td) {
		return td.querySelector('z-bar-gauge');
	}

	_isSparkLine(td) {
		return td.querySelector('z-sparkline');
	}

	_handleDateStr(x, regex) {

		var datematch = x.matchAll(regex);
		for (const match of datematch) {
			if (match.length > 0) {
				var epoch = new Date(match.input);
				return String(Math.floor(epoch.getTime() / 1000));
			}
		}

		return x;

	}

	_checkIfDate(x) {

		if (this._isNumeric(x)) {
			return x;
		}

		var uptime_reone = /^([0-9]+)\s*(days?,)\s*([0-9]{2}):([0-9]{2}):([0-9]{2})/g;
		var uptime_retwo = /^([0-9]{2}):([0-9]{2}):([0-9]{2})/g;
		var uptime_matchone = x.matchAll(uptime_reone);
		var uptime_matchtwo = x.matchAll(uptime_retwo);
		for (const match of uptime_matchone) {
			if (match.length > 0) {
				var days = parseInt(match[1]) * this.#seconds_per_day;
				var hours = parseInt(match[3]) * this.#seconds_per_hour;
				var mins = parseInt(match[4]) * this.#seconds_per_min;
				var uptime = days + hours + mins + parseInt(match[5]);
				return String(uptime);
			}
		}

		for (const match of uptime_matchtwo) {
			if (match.length > 0) {
				var hours = parseInt(match[1]) * this.#seconds_per_hour;
				var mins = parseInt(match[2]) * this.#seconds_per_min;
				var uptime = hours + mins + parseInt(match[3]);
				return String(uptime);
			}
		}

		if (x == 'Never') {
			return '0';
		}

		var date_reone = /^(Mon|Tue|Wed|Thus|Fri|Sat|Sun)\s+([0-9]{2})\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s*([0-9]{4})/g;
		x = this._handleDateStr(x, date_reone);
		if (this._isNumeric(x)) {
			return x;
		}

		var date_retwo = /^([0-9]{4})-([0-9]{2})-([0-9]{2})\s{1,}([0-9]{2}):([0-9]{2}):([0-9]{2})\s*(AM|PM)?/g;
		x = this._handleDateStr(x, date_retwo);
		if (this._isNumeric(x)) {
			return x;
		}

		var s_reone = /^(\-?\d*\.?\d*E?\-?\d*)(ms|y|M|d|h|m|s)\s{0,}(\d*\.?\d*)(ms|y|M|d|h|m|s){0,1}\s{0,}(\d*\.?\d*)(ms|y|M|d|h|m|s){0,1}/g;
		var s_retwo = /^< 1 ms/g;
		var s_matchone = x.matchAll(s_reone);
		var s_matchtwo = x.matchAll(s_retwo);
		for (const match of s_matchone) {
			if (match.length > 0) {
				var one = parseFloat(match[1]) * this.#sMultiplier.get(match[2]);
				var two = 0;
				if (match[3] && match[4]) {
					var two = parseFloat(match[3]) * this.#sMultiplier.get(match[4]);
				}

				var three = 0;
				if (match[5] && match[6]) {
					var three = parseFloat(match[5]) * this.#sMultiplier.get(match[6]);
				}

				var s = one + two + three;
				return String(s);
			}
		}

		for (const match of s_matchtwo) {
			if (match.length > 0) {
				return '0';
			}
		}

		return x;

	}

	_isNumeric(x) {

		return !isNaN(parseFloat(x)) && isFinite(x);

	}

	_getNumValue(x, index, convert, allNumeric) {

		try {
			var x = x.cells[index].innerText;
		}
		catch(err) {
			var x = '-1';
		}

		var obj = new Object();

		x = this._checkIfDate(x);

		if (x == '') {
			if (convert) {
				if (allNumeric) {
					obj.type = 'number';
					obj.value = '-1';
				}
				else {
					obj.type = 'text';
					obj.value = x;
				}
			}
			else {
				obj.type = 'empty';
				obj.value = x;
			}
			return obj;
		}

		var splitx = x.split(' ');
		if (splitx.length == 2) {
			var numValue = splitx[0];
			var units_in_display = splitx[1];

			if (this._isNumeric(numValue)) {
				obj.type = 'number';
				if (units_in_display !== undefined) {
					var multiplier = this.#Multiplier.get(units_in_display.charAt(0));

					if (multiplier) {
						obj.value = parseFloat(numValue) * multiplier;
					}
					else {
						obj.value = numValue;
					}
				}
				else {
					obj.value = numValue;
				}
			}
			else {
				obj.type = 'text';
				obj.value = x.toString();
			}
		}
		else {
			if (splitx.length == 1) {
				var numValue = splitx[0];
				if (this._isNumeric(numValue)) {
					obj.type = 'number';
					obj.value = numValue;
				}
				else {
					obj.type = 'text';
					obj.value = x.toString();
				}
			}
			else {
				obj.type = 'text';
				obj.value = x.toString();
			}
		}

		return obj;

	}

	_getTextValue(x, index) {

		try {
			return x.cells[index].innerText;
		}
		catch(err) {
			return '';
		}

	}


	_sortTableRowsByColumn(columnIndex, ascending) {
		const allRows = this.#rowsArray;

		const resetRows = [];
		const footerRows = [];
		const sortableRows = [];

		for (const rowObj of allRows) {
			const row = rowObj.row;
			let isReset = false;
			let isFooter = false;

			const cells = row.getElementsByTagName('td');
			for (let c = 0; c < cells.length; c++) {
				if (cells[c].querySelector('[reset-row]')) {
					isReset = true;
					break;
				}
				if (cells[c].hasAttribute('footer-row')) {
					isFooter = true;
					break;
				}
			}

			if (isReset) {
				resetRows.push(rowObj);
			}
			else if (isFooter) {
				footerRows.push(rowObj);
			}
			else {
				sortableRows.push(rowObj);
			}
		}

		const prelimValues = sortableRows.map(rowObj => this._getNumValue(rowObj.row, columnIndex, false, true));
		const allNumeric = !prelimValues.some(obj => obj.type === 'text');

		const rowsWithValues = sortableRows.map(rowObj => ({
			rowObj,
			valueObj: this._getNumValue(rowObj.row, columnIndex, true, allNumeric),
			textValue: this._getTextValue(rowObj.row, columnIndex)
		}));

		rowsWithValues.sort((a, b) => {
			if (!allNumeric) {
				return ascending
					? a.textValue.localeCompare(b.textValue)
					: b.textValue.localeCompare(a.textValue);
			}

			const aVal = a.valueObj.value;
			const bVal = b.valueObj.value;

			if (a.valueObj.type === 'number' && b.valueObj.type === 'number') {
				return ascending ? aVal - bVal : bVal - aVal;
			}

			return ascending
				? aVal.toString().localeCompare(bVal.toString())
				: bVal.toString().localeCompare(aVal.toString());
		});

		this.#rowsArray = [...resetRows, ...rowsWithValues.map(obj => obj.rowObj), ...footerRows];

	}

	updateTableFooter() {
		const footerRowObj = this.#rowsArray.find(({ row }) =>
			row.querySelector('td[footer-row]')
		);

		const visibleRows = this.#rowsArray.filter(({ status }) => status === 'display');
		
		if (!footerRowObj) {
			return;
		}

		const footerRow = footerRowObj.row;
		const footerCells = Array.from(footerRow.querySelectorAll('td'));

		const defaultLabel = footerCells[0].textContent.trim();
		const defaultMode = defaultLabel === 'Total' ? 'sum' : 'average';

		footerCells.forEach((cell, colIndex) => {
			const label = cell.innerText.trim();

			let overrideIcon = cell.querySelector('.override-icon');
			let mode = defaultMode;
			if (overrideIcon) {
				const title = overrideIcon.getAttribute('title');
				if (title === 'Sum') mode = 'sum';
				else if (title === 'Average') mode = 'average';
			}

			if ((cell.querySelector('span') && cell.querySelector('span').textContent.trim() === '') || cell.querySelector('span') === null) {
				return;
			}

			const values = [];
			const unitsSet = new Set();
			visibleRows.forEach(rowObj => {
				const td = rowObj.row.querySelectorAll('td')[colIndex];
				if (!td) return;
				const rawHtml = td.getAttribute('data-hintbox-contents');
				const unit = td.getAttribute('units') || '';
				if (!rawHtml) return;

				const match = rawHtml.match(/>([\d.\-eE+]+)</);
				if (!match) return;
				let rawValue = parseFloat(match[1]);
				if (isNaN(rawValue)) {
					if (mode === 'sum') rawValue = 0;
					else return;
				}

				values.push(rawValue);
				unitsSet.add(unit);
			});

			let result = 0;
			if (values.length > 0) {
				if (mode === 'sum') {
					result = values.reduce((acc, val) => acc + val, 0);
				}
				else {
					result = values.reduce((acc, val) => acc + val, 0) / values.length;
				}
			}

			const unit = unitsSet.size === 1 ? [...unitsSet][0] : null;
			const formatted = this.formatValuesWithUnit(result, unit);

			const span = cell.querySelector('span');
			if (span) {
				span.textContent = formatted;
			}
			else {
				const content = `<div><span>${formatted}${overrideIcon ? overrideIcon.outerHTML : ''}</span></div>`;
				cell.innerHTML = content;
			}

			const overrideIconSpan = span?.querySelector('.override-icon');
			if (overrideIcon && !overrideIconSpan) {
				span.innerHTML += overrideIcon.outerHTML;
			}
		});
	}

	formatValuesWithUnit(value, unit) {
		if (!unit || unit.startsWith('!')) {
			if (Number.isInteger(value) || Number.isInteger(Math.round((value + Number.EPSILON) * 100) / 100)) {
				return unit ? value.toFixed(0) + ' ' + unit.slice(1) : value.toFixed(0);
			}
			return unit ? value.toFixed(2) + ' ' + unit.slice(1) : value.toFixed(2);
		}

		switch (unit) {
			case 'B':
				return this.formatBytes(value);
			case 's':
				return this.formatSecondsDuration(value);
			case 'uptime':
				return this.formatUptime(value);
			case '%':
				return value.toFixed(2) + " %";
			default:
				return this.formatSIUnit(value, unit);
		}
	}

	formatBytes(bytes) {
		const units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];
		let i = 0;
		while (bytes >= 1024 && i < units.length - 1) {
			bytes /= 1024;
			i++;
		}
		return `${bytes.toFixed(2)} ${units[i]}`;
	}

	formatSIUnit(val, unit) {
		const units = ['', 'K', 'M', 'G', 'T', 'P', 'E'];
		let i = 0;
		while (val >= 1000 && i < units.length - 1) {
			val /= 1000;
			i++;
		}
		return `${val.toFixed(2)} ${units[i]}${unit}`;
	}

	formatSecondsDuration(sec) {
		if (sec < 0.001) return "< 1 ms";
		const d = Math.floor(sec / (3600 * 24));
		const h = Math.floor((sec % (3600 * 24)) / 3600);
		const m = Math.floor((sec % 3600) / 60);
		const s = Math.floor(sec % 60);
		const ms = Math.round((sec % 1) * 1000);

		const parts = [];
		if (d) parts.push(`${d}d`);
		if (h) parts.push(`${h}h`);
		if (m && parts.length < 3) parts.push(`${m}m`);
		if (s && parts.length < 3) parts.push(`${s}s`);
		if (ms && parts.length < 3) parts.push(`${ms}ms`);

		return parts.slice(0, 3).join(" ");
	}

	formatUptime(sec) {
		const days = Math.floor(sec / (3600 * 24));
		const h = String(Math.floor((sec % (3600 * 24)) / 3600)).padStart(2, "0");
		const m = String(Math.floor((sec % 3600) / 60)).padStart(2, "0");
		const s = String(Math.floor(sec % 60)).padStart(2, "0");
		if (days > 0) {
			return `${days} days, ${h}:${m}:${s}`;
		}
		return `${h}:${m}:${s}`;
	}

	#addColumnFilterCSS() {
		if ($('style.column-filter-styles').length === 0) {
			const styleColumnFilters = document.createElement('style');
			styleColumnFilters.classList.add('column-filter-styles');
			styleColumnFilters.innerHTML = `
				.filter-popup {
					position: absolute;
					z-index: 999;
					background: var(--checkbox-bg);
					border: 1px solid #333;
					box-shadow: 0 2px 10px rgba(0, 0, 0, 0.7);
					font-family: Arial, Tahoma, Verdana, sans-serif;
					border-radius: 4px;
					min-width: 400px;
					max-width: 800px;
					max-height: 450px;
					overflow: hidden;
					display: flex;
					flex-direction: column;
					font-size: 13px;
				}
				.filter-popup-header {
					display: flex;
					flex-direction: column;
					align-items: stretch;
					gap: 6px;
					padding: 8px;
					border-bottom: 1px solid #333;
					background: var(--main-bg);
				}
				.filter-popup-header-title {
					font-weight: bold;
					font-size: 14px;
					margin-bottom: 4px;
				}
				.filter-popup-controls {
					display: flex;
					align-items: center;
					gap: 6px;
				}
				.filter-popup-controls input {
					background: var(--input-bg);
					border: 1px solid #555;
					border-radius: 3px;
					padding: 4px 8px;
					font-size: 12px;
				}
				.filter-popup-controls input {
					flex: 1;
				}
				.filter-popup-controls .clear-btn {
					cursor: pointer;
					font-size: 11px;
				}
				.filter-popup-checkboxes {
					padding: 8px;
					overflow-y: auto;
					flex: 1;
				}
				.filter-popup-checkboxes label {
					display: flex;
					align-items: center;
					gap: 6px;
					line-height: 1.2;
					padding: 2px 0;
				}
				.filter-popup-checkboxes input[type="checkbox"] {
					appearance: none;
					-webkit-appearance: none;
					-moz-appearance: none;
					width: 16px;
					height: 16px;
					border: 2px solid var(--checkbox-border);
					border-radius: 4px;
					background-color: white;
					cursor: pointer;
					position: relative;
					flex-shrink: 0;
					transition: all 0.15s ease-in-out;
					margin-top: 1px;
				}
				.filter-popup-checkboxes input[type="checkbox"]:checked {
					background-color: #3b82f6;
					border-color: #3b82f6;
				}
				.filter-popup-checkboxes input[type="checkbox"]:checked::after {
					content: '';
					position: absolute;
					top: 0px;
					left: 4px;
					width: 5px;
					height: 9px;
					border: solid white;
					border-width: 0 2px 2px 0;
					transform: rotate(45deg);
					pointer-events: none;
				}
				.filter-popup-footer {
					border-top: 1px solid #333;
					padding: 8px;
					background: var(--main-bg);
					display: flex;
					flex-direction: column;
					gap: 6px;
					font-size: 12px;
				}
				.filter-popup-footer button {
					background: #2c72d3;
					color: white;
					border: none;
					padding: 6px 12px;
					font-size: 13px;
					border-radius: 3px;
					cursor: pointer;
					display: flex;
					align-items: center;
					justify-content: center;
					height: 30px;
					min-width: 80px;
					top: 1px;
				}
				.filter-popup-footer button.cancel {
					background: #666;
				}
				.filter-popup-footer button.clear-filters {
					min-width: 100px;
					font-weight: bold;
					visibility: hidden;
					background-color: #f06292;
					color: #fff;
				}
				.filter-popup-footer button:hover {
					opacity: 0.9;
				}
				.filter-popup-footer .footer-toggle-row,
				.filter-popup-footer .footer-action-row {
					display: flex;
					justify-content: flex-start;
					gap: 8px;
				}
				.filter-popup-footer .footer-toggle-row {
					margin-top: 4px;
				}
				.filter-popup-footer .footer-action-row {
					margin-top: 10px;
				}
				.filter-icon.active svg path {
					stroke: #4ad380;
				}
				.filter-icon.active {
					border-radius: 2px;
					box-shadow: 0 0 2px rgba(74, 222, 128, 0.5);
				}
				.filter-icon.filter-error svg path {
					stroke: #f44336;
				}
				.filter-icon.filter-error {
					border-radius: 2px;
					box-shadow: 0 0 2px rgba(244, 67, 54, 0.8);
				}
				.filter-warning-icon {
					cursor: help;
					display: flex;
					align-items: center;
					margin-left: 12px;
					position: relative;
					user-select: none;
				}
				.filter-warning-icon svg {
					display: block;
				}
				.custom-tooltip {
					background-color: var(--warning-tooltip-bg);
					border: 1px solid #ccc;
					padding: 4px 8px;
					border-radius: 3px;
					font-size: 12px;
					white-space: nowrap;
					z-index: 10000;
				}
				.section-container {
					border: 0.5px solid #4e4e4e;
					border-radius: 8px;
					padding: 8px;
					background-color: var(--main-bg);
					display: flex;
					flex-direction: column;
					gap: 6px;
				}
				.toggle-row {
					display: flex;
					justify-content: space-between;
					align-items: center;
				}
				.custom-select {
					position: relative;
					display: inline-block;
					font-family: Arial, Tahoma, Verdana, sans-serif;
					font-size: 12px;
					width: 130px;
				}
				.custom-select input[type="hidden"] {
					display: none;
				}
				.custom-select > button {
					position: relative;
					width: 100%;
					text-align: left;
					overflow: hidden;
					text-overflow: ellipsis;
					white-space: pre;
					font-size: 12px;
					line-height: initial;
					color: var(--filter-type-text);
					background-color: var(--filter-type-bg);
					border: 1px solid #4f4f4f;
					cursor: pointer;
					border-radius: 0;
				}
				.custom-select > button:hover,
				.custom-select > button:focus,
				.custom-select > button:active {
					color: var(--filter-type-text);
					background-color: var(--filter-type-bg-selected);
					border-color: #4f4f4f;
					outline: none;
				}
				.custom-select > button::after {
					content: "";
					font-family: 'zabbix-icons';
					font-size: 10px;
					position: absolute;
					right: 4px;
					top: 50%;
					transform: translateY(-50%);
					pointer-events: none;
				}
				.custom-select .list {
					position: absolute;
					top: 100%;
					left: 0;
					right: 0;
					background-color: var(--checkbox-bg);
					border: 1px solid #4f4f4f;
					max-height: 300px;
					overflow-y: auto;
					z-index: 10000;
					margin: 0;
					padding: 0;
					list-style: none;
					display: none;
				}
				.custom-select .list li {
					padding: 4px 8px;
					cursor: pointer;
					color: var(--filter-type-text);
					position: relative;
					display: flex;
					justify-content: space-between;
					align-items: center;
				}
				.custom-select .list li:hover,
				.custom-select .list li.hover {
					background-color: #01579B;
					border-color: #4f4f4f;
					color: #f2f2f2;
				}
				.custom-select .list li.focused {
					background-color: #007bff;
					color: white;
				}
				.custom-select .list::-webkit-scrollbar {
					width: 9px;
				}
				.custom-select .list::-webkit-scrollbar-thumb {
					background-color: #383838;
					border: 1px solid var(--main-bg);
				}
				.custom-select .list::-webkit-scrollbar-track {
					background-color: #1f1f1f;
				}
				.help-icon {
					cursor: pointer;
					color: #fff;
					background-color: #9a6104;
					border-radius: 50%;
					width: 13px;
					height: 13px;
					display: flex;
					justify-content: center;
					align-items: center;
					font-size: 11px;
					line-height: 1;
					margin-left: 8px;
				}
				.filter-tooltip {
					position: absolute;
					background-color: #9a6104;
					color: #fff;
					padding: 5px;
					border-radius: 3px;
					display: none;
					z-index: 10001;
					max-width: 400px;
					white-space: pre-wrap;
					pointer-events: none;
				}
			`;
			document.head.appendChild(styleColumnFilters);

			const root = document.documentElement;
			switch (this.#theme) {
				case 'hc-dark':
					root.style.setProperty('--checkbox-bg', '#000000');
					root.style.setProperty('--input-bg', '#000000');
					root.style.setProperty('--filter-type-bg', '#000000');
					root.style.setProperty('--filter-type-bg-selected', '#0a0a0a');
					root.style.setProperty('--filter-type-text', '#f2f2f2');
					root.style.setProperty('--warning-tooltip-bg', '#383838');
					root.style.setProperty('--checkbox-border', '#9ca3af');
					root.style.setProperty('--main-bg', '#1d1d1d');
					break;
				case 'dark-theme':
					root.style.setProperty('--checkbox-bg', '#1e1e1e');
					root.style.setProperty('--input-bg', '#1e1e1e');
					root.style.setProperty('--filter-type-bg', '#1e1e1e');
					root.style.setProperty('--filter-type-bg-selected', '#383838');
					root.style.setProperty('--filter-type-text', '#f2f2f2');
					root.style.setProperty('--warning-tooltip-bg', '#383838');
					root.style.setProperty('--checkbox-border', '#9ca3af');
					root.style.setProperty('--main-bg', '#2a2a2a');
					break;
				case 'hc-light':
				case 'blue-theme':
					root.style.setProperty('--checkbox-bg', '#ececec');
					root.style.setProperty('--input-bg', '#ffffff');
					root.style.setProperty('--filter-type-bg', '#ffffff');
					root.style.setProperty('--filter-type-bg-selected', '#ffffff');
					root.style.setProperty('--filter-type-text', '#000000');
					root.style.setProperty('--warning-tooltip-bg', '#ffffff');
					root.style.setProperty('--checkbox-border', '#5a616f');
					root.style.setProperty('--main-bg', '#ffffff');
					break;
			}
		}
	}


	#addPaginationCSS() {
		if ($('style.pagination-styles').length === 0) {
			const stylePagination = document.createElement('style');
			stylePagination.classList.add('pagination-styles');
			stylePagination.innerHTML = `
				:root {
					--pagination-bg-dark: #2b2b2b;
					--pagination-bg-hcdark: #000000;
					--pagination-bg-light: #ffffff;
					--page-num-dark: #f2f2f2;
					--page-num-light: #000000;
				}
				.pagination-controls {
					display: flex;
					align-items: center;
					justify-content: center;
					position: relative;
					bottom: 0;
					padding: 10px 0;
					background-color: var(--pagination-bg);
					z-index: 100;
					width: 100%;
					box-sizing: border-box;
				}
				.pagination-controls button {
					margin: 0px 5px 0px;
					padding: 1px 5px;
					cursor: pointer;
					border: 1px solid #d0d0d0;
					border-radius: 5px;
					font-weight: bold;
					transition: background-color 0.3s, border-color 0.3s, color 0.3s;
				}
				.pagination-controls button:hover {
					border-color: #c0c0c0;
				}
				.pagination-controls span {
					margin-left: 10px;
					margin-right: 10px;
					font-weight: bold;
					color: var(--page-num);
				}
				.is-loading.widget-blur::before,
				.is-loading.widget-blur::after {
					background-color: rgba(43, 43, 43, 1.0);
				}
				html[theme="blue-theme"] .is-loading.widget-blur::before,
				html[theme="blue-theme"] .is-loading.widget-blur::after,
				html[theme="hc-light"] .is-loading.widget-blur::before,
				html[theme="hc-light"] .is-loading.widget-blur::after {
					background-color: rgba(255, 255, 255, 1.0);
				}
				html[theme="hc-dark"] .is-loading.widget-blur::before,
				html[theme="hc-dark"] .is-loading.widget-blur::after {
					background-color: rgba(0, 0, 0, 1.0);
				}
				tbody tr {
					transition: visibility 0.2s ease-in-out, display 0.2s ease-in-out;
				}
			`;
			document.head.appendChild(stylePagination);

			const root = document.documentElement;
			switch (this.#theme) {
				case 'dark-theme':
					root.style.setProperty('--pagination-bg', 'var(--pagination-bg-dark)');
					root.style.setProperty('--page-num', 'var(--page-num-dark)');
					break;
				case 'hc-dark':
					root.style.setProperty('--pagination-bg', 'var(--pagination-bg-hcdark)');
					root.style.setProperty('--page-num', 'var(--page-num-dark)');
					break;
				case 'hc-light':
				case 'blue-theme':
					root.style.setProperty('--pagination-bg', 'var(--pagination-bg-light)');
					root.style.setProperty('--page-num', 'var(--page-num-light)');
					break;
				default:
					break;
			}
		}
	}

	exportTableRowsToCSV(filename = 'export.csv') {
		if (!this.#rowsArray || this.#rowsArray.length === 0) {
			return;
		}

		const displayRows = this.#rowsArray
			.filter(item => item.status === 'display')
			.map(item => item.row);

		if (displayRows.length === 0) {
			return;
		}

		const baseHeaders = this.getHeaders();

		const columnUnits = this.collectUnitsPerColumn(displayRows);

		const headers = this.addUnitsToHeaders(baseHeaders, columnUnits);

		const dataRows = displayRows.map(row => this.extractRowData(row).data);

		const allData = [headers, ...dataRows];

		const csvContent = this.arrayToCSV(allData);

		this.downloadCSV(csvContent, filename);
	}

	getHeaders() {
		const headers = [];

		this.allThs.forEach(th => {
			let headerText = '';

			const clonedTh = th.cloneNode(true);

			const filterIcon = clonedTh.querySelector('.filter-icon');
			const arrow = clonedTh.querySelector('.new-arrow');
			if (filterIcon) filterIcon.remove();
			if (arrow) arrow.remove();

			const spanWithTitle = clonedTh.querySelector('span[title]');
			if (spanWithTitle) {
				headerText = spanWithTitle.getAttribute('title');
			}
			else {
				headerText = clonedTh.textContent.trim();
			}

			headers.push(headerText);
		});

		return headers;
	}

	extractRowData(rowElement) {
		const data = [];
		const units = [];
		const cells = rowElement.querySelectorAll('td');

		cells.forEach(cell => {
			if (this._isSparkLine(cell) || this._isBarGauge(cell)) {
				return;
			}

			let unitAttr = cell.getAttribute('units') || '';
			if (unitAttr.startsWith('!')) {
				unitAttr = unitAttr.substring(1);
			}
			units.push(unitAttr);

			let value = '';
			const hintboxContents = cell.getAttribute('data-hintbox-contents');

			if (hintboxContents) {
				const match = hintboxContents.match(/>([^<]+)</);
				if (match && match[1]) {
					value = match[1].trim();
				}
			}

			if (!value) {
				value = cell.textContent.trim();
			}

			data.push(value);
		});

		return { data, units };
	}

	collectUnitsPerColumn(displayRows) {
		const columnUnits = [];

		displayRows.forEach(row => {
			const { units } = this.extractRowData(row);

			units.forEach((unit, colIndex) => {
				if (!columnUnits[colIndex]) {
					columnUnits[colIndex] = new Set();
				}

				if (unit && unit !== '') {
					columnUnits[colIndex].add(unit);
				}
			});
		});

		return columnUnits.map(unitSet => {
			if (!unitSet || unitSet.size === 0) {
				return '';
			}

			if (unitSet.size === 1) {
				return Array.from(unitSet)[0];
			}

			return Array.from(unitSet).join(',');
		});
	}

	addUnitsToHeaders(headers, columnUnits) {
		return headers.map((header, index) => {
			const unit = columnUnits[index];
			if (unit && unit !== '') {
				return `${header} (${unit})`;
			}
			return header;
		});
	}

	downloadCSV(csvContent, filename) {
		const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
		const link = document.createElement('a');

		if (navigator.msSaveBlob) { // IE 10+
			navigator.msSaveBlob(blob, filename);
		}
		else {
			link.href = URL.createObjectURL(blob);
			link.download = filename;
			link.style.display = 'none';
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
		}
	}

	arrayToCSV(data) {
		return data.map(row => {
			return row.map(cell => {
				const cellStr = String(cell);
				if (cellStr.includes(',') || cellStr.includes('"') || cellStr.includes('\n')) {
					return `"${cellStr.replace(/"/g, '""')}"`;
				}
				return cellStr;
			}).join(',');
		}).join('\n');
	}

}

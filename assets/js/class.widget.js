
class CWidgetTableModuleRME extends CWidget {

	#SHADOW_DOM_RENDER_DELAY = 250;

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

	#filterStates = new Map();
	#activeFilters = new Set();
	#columnTypes = new Map();
	#draggableStates = new Map();
	#popupIds = new Set();

	#lastClickedCell = null;
	#clearFiltersClickedWithSelections = false;
	#filterTooltipIds = new Set();

	#scrollPosition = { top: 0, left: 0 };
	#scrollContainer = null;
	#isUpdatingDisplay = false;

	#isUserInitiatedFilterChange = false;

	static #hasManualSelection = false;
	static #sessionStorageInitialized = false;
	#sessionKey = null;

	static {
		// This runs once when the class is first loaded
		if (!CWidgetTableModuleRME.#sessionStorageInitialized) {
			CWidgetTableModuleRME.#clearAllWidgetReferences();
			CWidgetTableModuleRME.#sessionStorageInitialized = true;

			// Clear on page unload/navigation
			window.addEventListener('beforeunload', () => {
				CWidgetTableModuleRME.#clearAllWidgetReferences();
			});

			// Also clear on page hide (for mobile/table)
			window.addEventListener('pagehide', () => {
				CWidgetTableModuleRME.#clearAllWidgetReferences();
			});
		}
	}

	static #clearAllWidgetReferences() {
		try {
			const keys = Object.keys(sessionStorage);
			keys.forEach(key => {
				if (key.startsWith('widget_references_')) {
					sessionStorage.removeItem(key);
				}
			});
			CWidgetTableModuleRME.#hasManualSelection = false;
		}
		catch (e) {
			console.error('Failed to clear session storage:', e);
		}
	}

	// ========== Utility Methods (must be defined first) ========== //

	debounce(fn, delay) {
		let timeoutId;
		return (...args) => {
			clearTimeout(timeoutId);
			timeoutId = setTimeout(() => fn(...args), delay);
		};
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

	_isNumeric(x) {
		return !isNaN(parseFloat(x)) && isFinite(x);
	}

	_isDoubleSpanColumn(td) {
		if (!td) return false;
		return (this._isBarGauge(td) || this._isSparkLine(td));
	}

	_isBarGauge(td) {
		return td.querySelector('z-bar-gauge');
	}

	_isSparkLine(td) {
		return td.querySelector('z-sparkline');
	}

	runwhenSpinnerIsGone (callback) {
		const checkInterval = setInterval(() => {
			if (document.querySelector('.is-loading') === null) {
				clearInterval(checkInterval);
				callback();
			}
		}, 100);
	}

	// ========== Private Helper Methods ========== //

	#broadcast(id_type, id_types, id) {
		this.broadcast({
			[id_type]: [id],
			[id_types]: [id]
		});
	}

	#saveScrollPosition() {
		if (this._contents) {
			this.#scrollPosition = {
				top: this._contents.scrollTop,
				left: this._contents.scrollLeft
			};
		}
	}

	#handleScroll = () => {
		// Don't save scroll position if we're in the middle of updating
		if (!this.#isUpdatingDisplay) {
			this.#saveScrollPosition();
		}
	}

	#setupScrollTracking() {
		if (this._contents) {
			// Remove any existing listener first
			this._contents.removeEventListener('scroll', this.#handleScroll);

			// Add scroll event listener
			this._contents.addEventListener('scroll', this.#handleScroll);
		}
	}

	#cleanupScrollTracking() {
		if (this._contents) {
			this._contents.removeEventListener('scroll', this.#handleScroll);
		}
	}

	#removePaginationControls() {
		const paginationElements = this.#parent_container
			? this.#parent_container.querySelectorAll('.pagination-controls')
			: [];

		paginationElements.forEach(element => {
			element.remove();
		});

		// Also remove standalone hide-button-container (we'll recreate it if needed)
		const hideContainer = this.#parent_container?.querySelector('.hide-button-container');
		if (hideContainer) {
			hideContainer.remove();
		}
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

	#updatePageInfo(pageInfoElement) {
		if (!pageInfoElement) return;
		const totalPages = Math.ceil(this.#totalRows / this.#rowsPerPage);
		pageInfoElement.textContent = `Page ${this.#currentPage} of ${totalPages}`;
	}

	#getSetSpans(th) {
		const span = th.querySelector('span#arrow');
		const allArrowSpans = this.#values_table.querySelectorAll('thead tr th span#arrow');
		for (let eachSpan of allArrowSpans) {
			eachSpan.className = 'new-arrow';
		}

		return span;
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

	// ========== Filter-related Methods ========== //

	#getStableColumnId(th) {
		// First check if we already set a stable ID
		if (th.dataset.stableColumnId) {
			return th.dataset.stableColumnId;
		}

		// Extract the actual column name (same logic as #extractColumnName)
		const clonedTh = th.cloneNode(true);
		const filterIcon = clonedTh.querySelector('.filter-icon');
		const arrow = clonedTh.querySelector('#arrow');
		if (filterIcon) filterIcon.remove();
		if (arrow) arrow.remove();

		const spanWithTitle = clonedTh.querySelector('span[title]');
		let columnName = spanWithTitle
			? spanWithTitle.getAttribute('title').trim()
			: clonedTh.textContent.trim();

		// Fallback if column name is empty
		if (!columnName) {
			columnName = `column-${th.id || Math.random().toString(36).substr(2, 9)}`;
		}

		// Create a stable ID by sanitizing the column name
		// Remove special characters and spaces, convert to lowercase
		const stableId = columnName
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, '-')
			.replace(/^-|-$/g, ''); // Remove leading/trailing hyphens

		// Store it on the element for future reference
		th.dataset.stableColumnId = stableId;

		return stableId;
	}

	#extractColumnName(th) {
		if (!th) return '';

		// Clone the th to avoid modifying the original
		const clonedTh = th.cloneNode(true);

		// Remove filter icon and arrow
		const filterIcon = clonedTh.querySelector('.filter-icon');
		const arrow = clonedTh.querySelector('#arrow');
		if (filterIcon) filterIcon.remove();
		if (arrow) arrow.remove();

		// Check if there is a span with title attribute (tooltip)
		const spanWithTitle = clonedTh.querySelector('span[title]');
		if (spanWithTitle) {
			return spanWithTitle.getAttribute('title').trim();
		}

		// Fall back to text content
		return clonedTh.textContent.trim();
	}

	#getCellByColumnId(row, targetColumnId) {
		const cells = row.querySelectorAll('td');
		let currentColumnId = 0;

		for (let cell of cells) {
			const colspan = cell.hasAttribute('colspan')
				? parseInt(cell.getAttribute('colspan'))
				: 1;

			if (currentColumnId === targetColumnId) {
				return cell;
			}

			currentColumnId += colspan;
		}

		return null;
	}

	#extractCellValue(td) {
		// Check for hintbox content first (numeric value)
		const hintboxContent = td.getAttribute('data-hintbox-contents');
		if (hintboxContent) {
			const match = hintboxContent.match(/<div class="hintbox-wrap">(.*?)<\/div>/);
			if (match && match[1]) {
				return match[1].trim();
			}
		}

		// Fall back to text content
		return td.textContent.trim();
	}

	#detectColumnType(valuesArray) {
		const isNumeric = val => /^-?\d+(\.\d+)?([eE][+-]?\d+)?$/.test(val.trim());
		const isIPv4 = val => /^(\d{1,3}\.){3}\d{1,3}$/.test(val.trim());

		const allNumeric = valuesArray.every(isNumeric);
		const allIP = valuesArray.every(isIPv4);

		if (allNumeric) return 'numeric';
		if (allIP) return 'ip';
		return 'text';
	}

	#sortFilterValues(valuesArray, columnType) {
		const isNumeric = val => /^-?\d+(\.\d+)?([eE][+-]?\d+)?$/.test(val.trim());

		const ipToNum = ip => {
			const octets = ip.trim().split('.');
			if (octets.length !== 4) return NaN;
			const nums = octets.map(octet => parseInt(octet, 10));
			if (nums.some(num => isNaN(num) || num < 0 || num > 255)) return NaN;
			return (nums[0] * 256 ** 3) + (nums[1] * 256 ** 2) + (nums[2] * 256) + nums[3];
		};

		if (columnType === 'numeric') {
			return valuesArray.sort((a, b) => parseFloat(a) - parseFloat(b));
		}
		else if (columnType === 'ip') {
			return valuesArray.sort((a, b) => ipToNum(a) - ipToNum(b));
		}
		else {
			// Mixed content: prioritize numbers, then IPs, then text
			return valuesArray.sort((a, b) => {
				const getTypeRank = v => isNumeric(v) ? 0 : /^(\d{1,3}\.){3}\d{1,3}$/.test(v) ? 1 : 2;
				const rankA = getTypeRank(a);
				const rankB = getTypeRank(b);
				if (rankA !== rankB) return rankA - rankB;
				if (rankA === 0) return parseFloat(a) - parseFloat(b);
				if (rankA === 1) return ipToNum(a) - ipToNum(b);
				return a.localeCompare(b);
			});
		}
	}

	#matchesFilter(text, searchValue, filterMode, caseSensitive = false, columnType = 'text') {
		if (searchValue === '' || searchValue === null || searchValue === undefined) {
			return true;
		}

		text = text.trim();
		searchValue = searchValue.trim();

		// Handle numeric filters
		if (columnType === 'numeric') {
			const numValue = parseFloat(text);
			if (isNaN(numValue)) return false;

			switch (filterMode) {
				case 'equals': {
					const target = parseFloat(searchValue);
					return !isNaN(target) && numValue === target;
				}
				case 'not equals': {
					const target = parseFloat(searchValue);
					return !isNaN(target) && numValue !== target;
				}
				case 'greater than': {
					const target = parseFloat(searchValue);
					return !isNaN(target) && numValue > target;
				}
				case 'less than': {
					const target = parseFloat(searchValue);
					return !isNaN(target) && numValue < target;
				}
				case 'greater or equal': {
					const target = parseFloat(searchValue);
					return !isNaN(target) && numValue >= target;
				}
				case 'less or equal': {
					const target = parseFloat(searchValue);
					return !isNaN(target) && numValue <= target;
				}
				case 'range': {
					// Support formats: "10-20", "10:20", "10 20"
					const rangeParts = searchValue.split(/[-:\s]+/);
					if (rangeParts.length === 2) {
						const min = parseFloat(rangeParts[0]);
						const max = parseFloat(rangeParts[1]);
						if (!isNaN(min) && !isNaN(max)) {
							return numValue >= min && numValue <= max;
						}
					}
					return false;
				}
				case 'top n':
				case 'bottom n':
					// These filters are handled separately in #applyAllFilters()
					// after all other filters have been applied.
					// Return true here so rows aren't filtered out
					return true;
				default:
					return false;
			}
		}

		// Handle IP and text filters
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
			case 'not equals':
				return text !== searchValue;
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
					this.invalidRegex = false;
					return regex.test(text);
				}
				catch (error) {
					this.invalidRegex = true;
					this.invalidRegexError = error.message;
					return false;
				}
			case 'contains':
			default:
				return text.includes(searchValue);
		}
	}

	#getPossibleValuesForColumn(columnId) {
		// Calculate what rows WOULD be visible if this column had no filter
		const otherActiveFilters = Array.from(this.#activeFilters).filter(id => id !== columnId);

		const potentiallyVisibleRows = this.#rowsArray.filter(rowObj => {
			const tr = rowObj.row;
			const isResetRow = tr.querySelector('[reset-row]') !== null;
			const isFooterRow = tr.querySelector('[footer-row]') !== null;

			if (isResetRow || isFooterRow) return true;

			// Check all OTHER active filters (not this column)
			for (const otherColumnId of otherActiveFilters) {
				const otherFilterState = this.#filterStates.get(otherColumnId);
				const otherColumnType = this.#columnTypes.get(otherColumnId) || 'text';
				if (!otherFilterState) continue;

				const otherTh = Array.from(this.allThs).find(t => this.#getStableColumnId(t) === otherColumnId);
				if (!otherTh) continue; // Column no longer exists

				const otherColumnIndex = parseInt(otherTh.id);
				const td = this.#getCellByColumnId(tr, otherColumnIndex);

				if (!td) return false;

				const text = this.#extractCellValue(td).toLowerCase();
				const checkedValues = otherFilterState.checked || [];
				const searchValue = (otherFilterState.search || '').trim().toLowerCase();
				const filterMode = otherFilterState.type || 'contains';

				let matchesOtherColumn = true;

				if (otherColumnType === 'numeric' && (filterMode === 'top n' || filterMode === 'bottom n')) {
					matchesOtherColumn = true;
				}
				else if (checkedValues.length > 0) {
					matchesOtherColumn = checkedValues.includes(text);
				}
				else if (searchValue !== '') {
					matchesOtherColumn = this.#matchesFilter(text, searchValue, filterMode, false, otherColumnType);
				}

				if (!matchesOtherColumn) return false;
			}

			return true;
		});

		const th = Array.from(this.allThs).find(t => this.#getStableColumnId(t) === columnId);

		if (!th) return []; // Column no longer exists

		const columnIndex = parseInt(th.id);

		// Collect values from potentially visible rows
		const visibleValues = new Set();

		potentiallyVisibleRows.forEach(rowObj => {
			const tr = rowObj.row;
			if (tr.querySelector('[reset-row]') || tr.querySelector('[footer-row]')) return;

			const td = this.#getCellByColumnId(tr, columnIndex);
			if (td) {
				const value = this.#extractCellValue(td);
				if (value) visibleValues.add(value);
			}
		});

		const visibleValuesArray = Array.from(visibleValues);
		const columnType = this.#columnTypes.get(columnId) || 'text';
		return this.#sortFilterValues(visibleValuesArray, columnType);
	}

	#showCustomAlert(message, title = 'Alert') {
		// Create backdrop
		const backdrop = document.createElement('div');
		backdrop.className = 'custom-alert-backdrop';
		backdrop.style.cssText = `
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background-color: rgba(0, 0, 0, 0.6);
			z-index: 10000;
			display: flex;
			align-items: center;
			justify-content: center;
		`;

		// Create alert box
		const alertBox = document.createElement('div');
		alertBox.className = 'custom-alert-box';
		alertBox.style.cssText = `
			background: ${this.#theme === 'hc-light' || this.#theme === 'blue-theme' ? '#ffffff' : '#2a2a2a'};
			border: 2px solid #ff6b6b;
			border-radius: 8px;
			box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
			padding: 20px;
			min-width: 300px;
			max-width: 500px;
			text-align: center;
		`;

		// Create title
		const alertTitle = document.createElement('div');
		alertTitle.style.cssText = `
			font-size: 16px;
			font-weight: bold;
			color: #ff6b6b;
			margin-bottom: 10px;
		`;
		alertTitle.textContent = title;

		// Create message
		const alertMessage = document.createElement('div');
		alertMessage.style.cssText = `
			font-size: 14px;
			color: ${this.#theme === 'hc-light' || this.#theme === 'blue-theme' ? '#333' : '#f2f2f2'};
			margin-bottom: 20px;
			line-height: 1.5;
		`;
		alertMessage.textContent = message;

		// Create OK button
		const okButton = document.createElement('button');
		okButton.textContent = 'OK';
		okButton.style.cssText = `
			background: #ff6b6b;
			color: white;
			border: none;
			padding: 2px 24px;
			font-size: 14px;
			font-weight: bold;
			border-radius: 4px;
			cursor: pointer;
			min-width: 80px;
		`;

		okButton.addEventListener('mouseover', () => {
			okButton.style.background = '#ff5252';
		});

		okButton.addEventListener('mouseout', () => {
			okButton.style.background = '#ff6b6b';
		});

		okButton.addEventListener('click', (e) => {
			e.stopPropagation();
			e.preventDefault();
			document.body.removeChild(backdrop);
		});

		// Assemble alert box
		alertBox.appendChild(alertTitle);
		alertBox.appendChild(alertMessage);
		alertBox.appendChild(okButton);
		backdrop.appendChild(alertBox);

		// Add to body
		document.body.appendChild(backdrop);

		// Close on backdrop click
		backdrop.addEventListener('click', (e) => {
			if (e.target === backdrop) {
				e.stopPropagation();
				document.body.removeChild(backdrop);
			}
		});

		// Close on ESC key
		const escHandler = (e) => {
			if (e.key === 'Escape') {
				if (document.body.contains(backdrop)) {
					document.body.removeChild(backdrop);
				}
				document.removeEventListener('keydown', escHandler);
			}
		};
		document.addEventListener('keydown', escHandler);

		// Auto-focus the OK button
		setTimeout(() => okButton.focus(), 100);
	}

	#calculatePopupWidth(sortedValues) {
		const tempSpan = document.createElement('span');
		tempSpan.style.visibility = 'hidden';
		tempSpan.style.position = 'absolute';
		tempSpan.style.whiteSpace = 'nowrap';
		tempSpan.style.font = '13px sans-serif';
		document.body.appendChild(tempSpan);

		let maxWidth = 0;
		for (const value of sortedValues) {
			tempSpan.textContent = value;
			maxWidth = Math.max(maxWidth, tempSpan.offsetWidth);
		}
		document.body.removeChild(tempSpan);

		return Math.min(Math.max(maxWidth + 150, 400), 800);
	}

	#createHelpTooltip(helpIcon, helpText) {
		const tooltipId = `${this._widgetid}-filter-tooltip-${Math.random().toString(36).substr(2, 9)}`;
		const tooltip = document.createElement('div');
		tooltip.id = tooltipId;
		tooltip.className = 'filter-tooltip';
		tooltip.textContent = helpText;
		tooltip.style.display = 'none';
		document.body.appendChild(tooltip);

		this.#filterTooltipIds.add(tooltipId);

		helpIcon.addEventListener('mouseover', (e) => {
			const rect = helpIcon.getBoundingClientRect();
			tooltip.style.left = `${rect.left + window.scrollX + rect.width + 10}px`;
			tooltip.style.top = `${rect.top + window.scrollY + 15}px`;
			tooltip.style.display = 'block';
		});

		helpIcon.addEventListener('mouseout', () => {
			tooltip.style.display = 'none';
		});
	}

	#createWarningIcon() {
		const warningSvg = `
			<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none">
				<path d="M12 3C12.3 3 12.6 3.15 12.78 3.44L21.53 18.61C21.95 19.33 21.4 20.25 20.53 20.25H3.47C2.6 20.25 2.05 19.33 2.47 18.61L11.22 3.44C11.4 3.15 11.7 3 12 3Z" fill="#fef08a" stroke="#eab308" stroke-width="1.25"/>
				<circle cx="12" cy="16" r="1.25" fill="#92400e"/>
				<rect x="11.25" y="9" width="1.5" height="4.5" rx="0.75" fill="#92400e"/>
			</svg>
		`;

		const warningIcon = document.createElement('span');
		warningIcon.className = 'filter-warning-icon';
		warningIcon.style.display = 'none';
		warningIcon.innerHTML = warningSvg;

		// Warning tooltip with widget-specific ID
		const tooltip = document.createElement('div');
		const tooltipId = `${this._widgetid}-warning-tooltip-${Math.random().toString(36).substr(2, 9)}`;
		tooltip.id = tooltipId;
		tooltip.className = 'custom-tooltip';
		tooltip.textContent = 'Checkbox selections will take precedence over text entered in the search box after clicking "Apply" button';
		tooltip.style.position = 'absolute';
		tooltip.style.display = 'none';
		tooltip.style.pointerEvents = 'none';
		document.body.appendChild(tooltip);

		this.#filterTooltipIds.add(tooltipId);

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

		return warningIcon;
	}

	#createCheckboxContainer() {
		const scrollContainer = document.createElement('div');
		scrollContainer.style.maxHeight = '300px';
		scrollContainer.style.minHeight = '100px';
		scrollContainer.style.overflowY = 'auto';
		scrollContainer.style.overflowX = 'hidden';
		scrollContainer.style.position = 'relative';
		scrollContainer.style.paddingRight = '6px';
		scrollContainer.style.boxSizing = 'border-box';

		const spacer = document.createElement('div');
		spacer.style.position = 'relative';

		const checkboxContainer = document.createElement('div');
		checkboxContainer.className = 'filter-popup-checkboxes';
		checkboxContainer.style.position = 'absolute';
		checkboxContainer.style.width = '100%';
		checkboxContainer.style.top = '0';
		checkboxContainer.style.left = '0';
		checkboxContainer.style.right = '0';
		checkboxContainer.style.display = 'block';

		spacer.appendChild(checkboxContainer);
		scrollContainer.appendChild(spacer);

		return { scrollContainer, checkboxContainer, spacer };
	}

	#createPopupFooter(columnId) {
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
		toggleButton.textContent = 'Select All';
		toggleButton.style.background = '#455A64';
		toggleButton.style.color = '#eee';
		toggleButton.style.fontWeight = 'bold';
		toggleButton.type = 'button';
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
		applyButton.type = 'button';
		applyButton.style.minWidth = '80px';
		applyButton.style.padding = '6px 12px';

		const resetButton = document.createElement('button');
		resetButton.textContent = 'Cancel';
		resetButton.className = 'cancel';
		resetButton.type = 'button';
		resetButton.style.minWidth = '80px';
		resetButton.style.padding = '6px 12px';

		const clearFiltersButton = document.createElement('button');
		clearFiltersButton.textContent = 'Clear Filters';
		clearFiltersButton.className = 'clear-filters';
		clearFiltersButton.type = 'button';
		clearFiltersButton.style.padding = '6px 12px';
		clearFiltersButton.style.visibility = 'hidden';

		const warningIcon = this.#createWarningIcon();

		buttonsRow.appendChild(applyButton);
		buttonsRow.appendChild(resetButton);
		buttonsRow.appendChild(clearFiltersButton);
		buttonsRow.appendChild(warningIcon);
		footer.appendChild(buttonsRow);

		// Cancel button handler
		resetButton.addEventListener('click', (e) => {
			e.stopPropagation();
			e.preventDefault();

			const popup = document.getElementById(`${this.#values_table.id}-${this._widgetid}-popup-${columnId}`);

			// Restore original state from popup's dataset
			if (popup && popup.dataset.initialState) {
				const initialState = JSON.parse(popup.dataset.initialState);
				const filterState = this.#filterStates.get(columnId);

				filterState.search = initialState.search;
				filterState.checked = [...initialState.checked];
				filterState.type = initialState.type;

				// Update UI to match restore state
				const searchInput = popup.querySelector('input[type="text"]');
				const filterTypeSelect = popup.querySelector('.custom-select input[type="hidden"]');
				const filterTypeButton = popup.querySelector('.custom-select > button');

				if (searchInput) {
					searchInput.value = initialState.search;
				}

				if (filterTypeSelect) {
					filterTypeSelect.value = initialState.type;
				}

				if (filterTypeButton) {
					const filterTypeList = filterTypeButton.nextElementSibling;
					if (filterTypeList) {
						const matchingOption = Array.from(filterTypeList.querySelectorAll('li'))
							.find(li => li.dataset.value === initialState.type);

						if (matchingOption) {
							filterTypeButton.textContent = matchingOption.dataset.label;
						}
					}
				}
			}

			if (popup) popup.style.display = 'none';
			this._resumeUpdating();
		});

		return { footer, summary, toggleButton, applyButton, resetButton, clearFiltersButton, warningIcon };
	}

	#createDropdownList(options, button, hiddenInput) {
		const filterList = document.createElement('ul');
		filterList.className = 'list';
		filterList.style.display = 'none';
		filterList.tabIndex = -1;
		filterList.setAttribute('role', 'listbox');
		let currentIndex = -1;

		options.forEach((opt, i) => {
			const li = document.createElement('li');
			li.textContent = opt.label;
			li.dataset.value = opt.value;
			li.dataset.label = opt.label;
			li.setAttribute('role', 'option');

			const helpIcon = document.createElement('span');
			helpIcon.className = 'help-icon';
			helpIcon.textContent = '?';
			helpIcon.dataset.help = opt.help;
			li.appendChild(helpIcon);

			// Add click handler for selecting the option
			li.addEventListener('click', (e) => {
				e.stopPropagation();
				hiddenInput.value = opt.value;
				button.textContent = opt.label;
				closeList();

				// Trigger UI update for top/bottom n filters
				const changeEvent = new Event('filterTypeChanged', { bubbles: true });
				changeEvent.filterType = opt.value;
				hiddenInput.dispatchEvent(changeEvent);
			});

			// Mouse hover to focus item
			li.addEventListener('mouseenter', () => focusItem(i));
			filterList.appendChild(li);

			// Help tooltip
			this.#createHelpTooltip(helpIcon, opt.help);
		});

		// Accessibility attributes
		button.setAttribute('aria-haspopup', 'listbox');
		button.setAttribute('aria-expanded', 'false');

		// Helper functions
		const openList = () => {
			filterList.style.display = 'block';
			button.setAttribute('aria-expanded', 'true');
			filterList.focus();
		};

		const closeList = () => {
			filterList.style.display = 'none';
			button.setAttribute('aria-expanded', 'false');
			currentIndex = -1;
			filterList.querySelectorAll('li').forEach(li => li.classList.remove('focused'));
		};

		const focusItem = (index) => {
			const items = filterList.querySelectorAll('li');
			if (items.length === 0) return;

			items.forEach(li => li.classList.remove('focused'));

			if (index >= 0 && index < items.length) {
				currentIndex = index;
				items[currentIndex].classList.add('focused');
				items[currentIndex].scrollIntoView({ block: 'nearest' });
			}
		}

		// Button click toggle
		button.addEventListener('click', (e) => {
			e.stopPropagation();
			e.preventDefault();

			if (filterList.style.display === 'none') {
				openList();
				focusItem(0);
			}
			else {
				closeList();
			}
		});

		// Button keyboard navigation
		button.addEventListener('keydown', (e) => {
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
				case 'Escape':
					e.preventDefault();
					closeList();
					break;
			}
		});

		// List keyboard navigation
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
						button.focus();
					}
					break;
				case 'Escape':
					e.preventDefault();
					closeList();
					button.focus();
					break;
				case 'Tab':
					closeList();
					break;
			}
		});

		// Close dropdown when clicking outside
		const closeDropdownHandler = (e) => {
			if (!button.contains(e.target) && !filterList.contains(e.target)) {
				closeList();
			}
		};

		// Store handler reference on the filterList for cleanup
		filterList._closeDropdownHandler = closeDropdownHandler;
		document.addEventListener('click', closeDropdownHandler);

		return filterList;
	}

	#createFilterTypeDropdown(filterState, columnType) {
		const container = document.createElement('div');
		container.className = 'custom-select';

		const hiddenInput = document.createElement('input');
		hiddenInput.type = 'hidden';
		container.appendChild(hiddenInput);

		const button = document.createElement('button');
		button.type = 'button';
		container.appendChild(button);

		let options;

		if (columnType === 'numeric') {
			options = [
				{ value: 'equals', label: 'Equals', help: 'Value exactly equals the specified number.' },
				{ value: 'not equals', label: 'Not Equals', help: 'Value does not equal the specified number.' },
				{ value: 'greater than', label: 'Greater Than', help: 'Value is greater than the specified number.' },
				{ value: 'less than', label: 'Less Than', help: 'Value is less than the specified number.' },
				{ value: 'greater or equal', label: 'Greater or Equal', help: 'Value is greater than or equal to the specified number.' },
				{ value: 'less or equal', label: 'Less or Equal', help: 'Value is less than or equal to the specified number.' },
				{ value: 'range', label: 'Range', help: 'Value is between two numbers (inclusive).\nFormat: min-max or min:max' },
				{ value: 'top n', label: 'Top N', help: 'Shows the top N highest values.' },
				{ value: 'bottom n', label: 'Bottom N', help: 'Shows the bottom N highest values.' }
			];
		}
		else if (columnType === 'ip') {
			options = [
				{ value: 'equals', label: 'Equals', help: 'IP exactly matches the specified text.' },
				{ value: 'contains', label: 'Contains', help: 'IP contains the specified text.' },
				{ value: 'starts with', label: 'Starts with', help: 'IP starts with the specified text (useful for subnets).' },
				{ value: 'wildcard', label: 'Wildcard', help: 'IP matches the wildcarded (*) pattern.\nExample: 192.168.*.1' },
				{ value: 'regex', label: 'Regex', help: 'IP matches the specified regular expression.' }
			];
		}
		else {
			options = [
				{ value: 'contains', label: 'Contains', help: 'Filters items that contain the specified text.' },
				{ value: 'equals', label: 'Equals', help: 'Filters items that exactly match the specified text.' },
				{ value: 'starts with', label: 'Starts with', help: 'Filters items that start with the specified text.' },
				{ value: 'ends with', label: 'Ends with', help: 'Filters items that end with the specified text.' },
				{ value: 'wildcard', label: 'Wildcard', help: 'Filters items that match the specified wildcarded (*) pattern.\nMultiple wildcards (*) are supported.' },
				{ value: 'does not contain', label: 'Does not contain', help: 'Filters items that do not contain the specified text.' },
				{ value: 'regex', label: 'Regex', help: 'Filters items that match the specified regular expression.' },
				{ value: 'boolean', label: 'Boolean Expr', help: 'Filters items that match the specified boolean expression.\nSupports \'AND\', \'OR\', \'AND NOT\', \'OR NOT\', and parentheses\ni.e. (TERMA OR TERMB) AND TERMCD AND NOT TERMCDE' }
			];
		}

		// Determine initial value - use filterState.type if valid for this column type
		let initialValue = filterState.type;

		// Validate that the current filterState.type is valid for this column type
		const isValidOption = options.some(o => o.value === initialValue);

		if (!isValidOption) {
			// Fall back to the first option if current type isn't valid
			initialValue = options[0].value;
			filterState.type = initialValue; // Update the state too
		}

		const initialLabel = options.find(o => o.value === initialValue)?.label || options[0].label;

		hiddenInput.value = initialValue;
		button.textContent = initialLabel;

		const filterList = this.#createDropdownList(options, button, hiddenInput);
		container.appendChild(filterList);

		return { container, hiddenInput, button };
	}

	#createPopupHeader(columnId, filterState, columnType, columnName) {
		const header = document.createElement('div');
		header.className = 'filter-popup-header';

		const headerTitle = document.createElement('div');
		headerTitle.className = 'filter-popup-header-title';
		headerTitle.textContent = columnName ? `Filter column values - ${columnName}` : 'Filter column values';

		const filterControls = document.createElement('div');
		filterControls.className = 'filter-popup-controls';

		// Filter type dropdown (type-aware)
		const filterTypeContainer = this.#createFilterTypeDropdown(filterState, columnType);

		// Search input
		const searchInput = document.createElement('input');
		searchInput.type = 'text';

		if (columnType === 'numeric') {
			searchInput.placeholder = 'Enter number of range...';
		}
		else if (columnType === 'ip') {
			searchInput.placeholder = 'Enter IP address...';
		}
		else {
			searchInput.placeholder = 'Search...';
		}

		searchInput.value = filterState.search || '';

		// Search input clear button
		const clearBtn = document.createElement('span');
		clearBtn.className = 'clear-btn';
		clearBtn.textContent = '✕ Clear';

		filterControls.appendChild(filterTypeContainer.container);
		filterControls.appendChild(searchInput);
		filterControls.appendChild(clearBtn);

		header.appendChild(headerTitle);
		header.appendChild(filterControls);

		// DONT return the dropdown as toggleButton
		return {
			header,
			searchInput,
			filterTypeSelect: filterTypeContainer.hiddenInput,
			filterTypeButton: filterTypeContainer.button,
			clearBtn
		};
	}

	#setupPopupEventHandlers(config) {
		const {
			popup, columnId, columnType, searchInput, filterTypeSelect, filterTypeButton, clearBtn,
			applyButton, clearFiltersButton, warningIcon, scrollContainer,
			checkboxContainer, spacer, sortedValues, summary, toggleButton
		} = config;

		let filteredValues = [...sortedValues];
		let isAllSelected = this.#filterStates.get(columnId).allSelected;
		let lastCheckedCheckbox = null;

		// Store sortedValues in popup for later updates
		let currentSortedValues = sortedValues;

		// Capture initial state when popup is opened
		const initialState = {
			search: this.#filterStates.get(columnId).search || '',
			checked: [...(this.#filterStates.get(columnId).checked || [])],
			type: this.#filterStates.get(columnId).type
		};

		// Function to toggle checkbox visibility based on filter type
		const updateUIForFilterType = (filterType) => {
			const isTopBottomN = (filterType === 'top n' || filterType === 'bottom n');

			if (isTopBottomN) {
				checkboxContainer.style.display = 'none';
				scrollContainer.style.minHeight = '175px';
				scrollContainer.style.maxHeight = '175px';
				spacer.style.height = '80px';

				let placeholder = scrollContainer.querySelector('.topn-placeholder');
				if (!placeholder) {
					placeholder = document.createElement('div');
					placeholder.className = 'topn-placeholder';
					placeholder.style.cssText = `
						padding: 15px;
						text-align: center;
						color: #888;
						font-style: italic;
						display: flex;
						align-items: center;
						justify-content: center;
						height: 100%;
					`;
					scrollContainer.insertBefore(placeholder, spacer);
				}
				placeholder.textContent = filterType === 'top n'
					? 'Enter N above to show the top N highest values'
					: 'Enter N above to show the bottom N lowest values';
				placeholder.style.display = 'flex';
				scrollContainer.style.visibility = 'visible';

				const sectionContainer = popup.querySelector('.section-container');
				if (sectionContainer) sectionContainer.style.display = 'none';
				searchInput.placeholder = `Enter N (number of rows)`;
			}
			else {
				scrollContainer.style.visibility = 'visible';
				scrollContainer.style.minHeight = '100px';
				scrollContainer.style.maxHeight = '300px';
				checkboxContainer.style.display = 'block';

				const placeholder = scrollContainer.querySelector('.topn-placeholder');
				if (placeholder) placeholder.style.display = 'none';

				const sectionContainer = popup.querySelector('.section-container');
				if (sectionContainer) sectionContainer.style.display = 'flex';

				if (columnType === 'numeric') {
					searchInput.placeholder = 'Enter number or range...';
				}
				else if (columnType === 'ip') {
					searchInput.placeholder = 'Enter IP address...';
				}
				else {
					searchInput.placeholder = 'Search...';
				}
			}
		};

		updateUIForFilterType(filterTypeSelect.value);

		filterTypeSelect.addEventListener('filterTypeChanged', (e) => {
			updateUIForFilterType(e.filterType);
			handleInput();
		});

		const renderVisibleCheckboxes = () => {
			const scrollTop = scrollContainer.scrollTop;
			const visibleCount = 25;
			const startIndex = Math.floor(scrollTop / 30);
			const endIndex = Math.min(startIndex + visibleCount, filteredValues.length);

			checkboxContainer.style.top = `${startIndex * 30}px`;
			checkboxContainer.innerHTML = '';

			const filterState = this.#filterStates.get(columnId);

			for (let i = startIndex; i < endIndex; i++) {
				const value = filteredValues[i];
				const id = `filter_${columnId}_${String(value).replace(/[^a-zA-Z0-9]/g, '_')}`;
				const label = document.createElement('label');
				label.classList.add('custom-checkbox');
				label.innerHTML = `
					<input type="checkbox" id="${id}" value="${value}">
					<span>${value}</span>
				`;

				const checkbox = label.querySelector('input[type="checkbox"]');
				checkbox.checked = filterState.checked.includes(String(value).toLowerCase());
				label.setAttribute('data-index', i);
				checkboxContainer.appendChild(label);
			}
		};

		const updateSummary = () => {
			const filterState = this.#filterStates.get(columnId);
			const checked = filterState.checked.length || 0;
			if (summary) summary.textContent = `${checked} selected`;
		};

		const updateWarningIcon = () => {
			const checkboxes = popup.querySelectorAll('.filter-popup-checkboxes input[type="checkbox"]');
			const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
			const hasSearchText = searchInput.value.trim() !== '';
			warningIcon.style.display = (anyChecked && hasSearchText) ? 'flex' : 'none';
		};

		const updateClearFiltersButton = () => {
			const filterState = this.#filterStates.get(columnId);
			clearFiltersButton.style.visibility = (filterState.checked.length > 0 || filterState.search !== '') ? 'visible' : 'hidden';
		};

		function getIndexOfCheckbox(checkbox) {
			const value = checkbox.value;
			return filteredValues.indexOf(value);
		}

		const handleInput = () => {
			scrollContainer.scrollTop = 0;
			const query = searchInput.value.toLowerCase();
			const filterState = this.#filterStates.get(columnId);

			// Check if sortedValues was updated by #updateFilterPopupValues
			if (popup.dataset.sortedValues) {
				try {
					currentSortedValues = JSON.parse(popup.dataset.sortedValues);
					delete popup.dataset.sortedValues; // Clear after reading
				}
				catch (e) {
					console.error('Failed to parse updated sortedValues', e);
				}
			}

			filteredValues = currentSortedValues.filter(v => {
				const text = String(v).toLowerCase();
				return this.#matchesFilter(text, query, filterTypeSelect.value, false, columnType);
			});

			const filteredValuesLowercase = filteredValues.map(v => String(v).toLowerCase());
			filterState.checked = filterState.checked.filter(v => filteredValuesLowercase.includes(v));
			filterState.search = query;

			isAllSelected = filteredValues.length > 0 &&
				filteredValues.every(v => filterState.checked.includes(String(v).toLowerCase()));

			toggleButton.textContent = isAllSelected ? 'Uncheck All' : 'Select All';

			spacer.style.height = `${filteredValues.length * 30}px`;
			renderVisibleCheckboxes()
			updateSummary();
			updateWarningIcon();
			updateClearFiltersButton();
		};

		// Store handleInput reference so #updateFilterPopupValues can call it
		searchInput._handleInputFunction = handleInput;

		const setupInputHandler = () => {
			const filterMode = filterTypeSelect.value;
			searchInput.removeEventListener('input', currentInputHandler);

			const newInputHandler = filterMode === 'boolean'
				? this.debounce(handleInput, 1000)
				: (sortedValues.length > 250
					? this.debounce(handleInput, 300)
					: handleInput);

			searchInput.addEventListener('input', newInputHandler);
			currentInputHandler = newInputHandler;
		};

		let currentInputHandler;
		setupInputHandler();

		clearBtn.addEventListener('click', () => {
			searchInput.value = '';
			handleInput();
			lastCheckedCheckbox = null;
		});

		toggleButton.addEventListener('click', (e) => {
			e.stopPropagation();
			e.preventDefault();

			const filterState = this.#filterStates.get(columnId);
			const valuesToCheck = filteredValues.length !== sortedValues.length ?
				filteredValues : sortedValues;

			isAllSelected = valuesToCheck.length > 0 &&
				valuesToCheck.every(value => filterState.checked.includes(String(value).toLowerCase()));

			if (isAllSelected) {
				filterState.checked = [];
				isAllSelected = false;
				toggleButton.textContent = 'Select All';
			}
			else {
				filterState.checked = valuesToCheck.map(value => String(value).toLowerCase());
				isAllSelected = true;
				toggleButton.textContent = 'Uncheck All';
			}

			renderVisibleCheckboxes();
			updateSummary();
			updateWarningIcon();
			updateClearFiltersButton();
		});

		checkboxContainer.addEventListener('change', (event) => {
			const cb = event.target;
			if (cb.type !== 'checkbox') return;

			const val = cb.value.toLowerCase();
			const filterState = this.#filterStates.get(columnId);

			if (cb.checked) {
				if (!filterState.checked.includes(val)) {
					filterState.checked.push(val);
				}
			}
			else {
				filterState.checked = filterState.checked.filter(v => v !== val);
			}

			lastCheckedCheckbox = cb;
			isAllSelected = (filterState.checked.length === filteredValues.length);
			toggleButton.textContent = isAllSelected ? 'Uncheck All' : 'Select All';

			updateSummary();
			updateWarningIcon();
			updateClearFiltersButton();
		});

		checkboxContainer.addEventListener('click', (event) => {
			const cb = event.target;
			if (cb.type !== 'checkbox') return;

			const label = cb.closest('label');
			const checkboxIndex = parseInt(label.getAttribute('data-index'), 10);
			const filterState = this.#filterStates.get(columnId);

			if (event.shiftKey && lastCheckedCheckbox) {
				event.preventDefault();
				event.stopPropagation();

				const lastCheckedIndex = getIndexOfCheckbox(lastCheckedCheckbox);
				if (lastCheckedIndex !== -1) {
					const [from, to] = [Math.min(lastCheckedIndex, checkboxIndex), Math.max(lastCheckedIndex, checkboxIndex)];

					for (let j = from; j <= to; j++) {
						const value = filteredValues[j];
						const elementVal = String(value).toLowerCase();
						if (!filterState.checked.includes(elementVal)) {
							filterState.checked.push(elementVal);
						}
					}

					renderVisibleCheckboxes();
					updateSummary();
					updateWarningIcon();
					updateClearFiltersButton();
				}
			}

			isAllSelected = (filterState.checked?.length === filteredValues.length);
			toggleButton.textContent = isAllSelected ? 'Uncheck All' : 'Select All';
		});

		scrollContainer.addEventListener('scroll', () => {
			renderVisibleCheckboxes();
		});

		applyButton.addEventListener('click', (e) => {
			e.stopPropagation();
			e.preventDefault();

			const filterState = this.#filterStates.get(columnId);
			filterState.type = filterTypeSelect.value;
			filterState.search = searchInput.value.trim();

			// Validate Top N / Bottom N input
			if ((filterState.type === 'top n' || filterState.type === 'bottom n') && filterState.search) {
				const n = parseInt(filterState.search);
				if (isNaN(n) || n <= 0) {
					this.#showCustomAlert(
						`Please enter a valid positive number for ${filterState.type}`,
						'Invalid Input'
					);
					return;
				}
			}

			// Validate regex if that's the filter type
			if (filterState.type === 'regex' && filterState.search) {
				try {
					new RegExp(filterState.search);
				}
				catch (error) {
					this.#showCustomAlert(
						`${error.message}`,
						'Regex Error'
					);
					return;
				}
			}

			this.invalidRegex = false;
			popup.style.display = 'none';

			this.#isUserInitiatedFilterChange =true;

			setTimeout(() => {
				this.#applyAllFilters();

				if (this.invalidRegex) {
					this.#showCustomAlert(
						`${this.invalidRegexError || 'Please check your pattern'}`,
						'Regex Error'
					);
					const th = Array.from(this.allThs).find(t => this.#getStableColumnId(t) === columnId);
					if (th) {
						const filterIcon = th.querySelector('.filter-icon');
						if (filterIcon) {
							const popup = document.getElementById(`${this.#values_table.id}-${this._widgetid}-popup-${columnId}`);
							if (popup) {
								popup.style.display = 'flex';
								this._pauseUpdating();
							}
						}
					}
				}
				else {
					this._resumeUpdating();
				}

				this.#isUserInitiatedFilterChange = false;
			}, 0);
		});

		searchInput.addEventListener('keydown', (e) => {
			if (e.key === 'Enter') {
				e.preventDefault();
				applyButton.click();
			}
		});

		clearFiltersButton.addEventListener('click', (e) => {
			e.stopPropagation();
			e.preventDefault();

			const filterState = this.#filterStates.get(columnId);

			searchInput.value = '';
			filterState.checked = [];
			filterState.search = '';

			isAllSelected = false;
			toggleButton.textContent = 'Select All';

			// Get possible values using shared method
			const sortedVisibleValues = this.#getPossibleValuesForColumn(columnId);

			// Update currentSortedValues and trigger re-render
			currentSortedValues = sortedVisibleValues;
			filteredValues = [...currentSortedValues];
			popup.dataset.sortedValues = JSON.stringify(sortedVisibleValues);

			// Update UI to show all possible values
			spacer.style.height = `${filteredValues.length * 30}px`;
			scrollContainer.scrollTop = 0;
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

			this.#isUserInitiatedFilterChange = true;
		});

		// Initial render
		const filterState = this.#filterStates.get(columnId);
		if (filterState.search) {
			searchInput.value = filterState.search;
			handleInput();
		}
		else {
			spacer.style.height = `${sortedValues.length * 30}px`;
			renderVisibleCheckboxes();
			updateSummary();
			updateClearFiltersButton();
		}
	}

	#createFilterPopup(columnId, sortedValues, columnType) {
		const popup = document.createElement('div');
		popup.style.display = 'none';
		popup.className = 'filter-popup';
		popup.id = `${this.#values_table.id}-${this._widgetid}-popup-${columnId}`;
		popup.dataset.columnId = columnId;

		// Clean up old popup for this column if it exists
		const oldPopup = document.getElementById(popup.id);
		if (oldPopup) {
			this.cleanupDraggableState(popup.id);
			oldPopup.remove();
		}

		// Track this popup ID
		this.#popupIds.add(popup.id);

		const filterState = this.#filterStates.get(columnId);
		let filteredValues = sortedValues;
		let isAllSelected = filterState.allSelected;

		// Create header with title and controls
		const th = Array.from(this.allThs).find(t => this.#getStableColumnId(t) === columnId);
		const columnName = th ? this.#extractColumnName(th) : '';

		const { header, searchInput, filterTypeSelect, filterTypeButton, clearBtn } =
			this.#createPopupHeader(columnId, filterState, columnType, columnName);

		// Create checkbox container with virtual scrolling
		const { scrollContainer, checkboxContainer, spacer } =
			this.#createCheckboxContainer();

		// Create footer with buttons
		const { footer, applyButton, warningIcon, clearFiltersButton, summary, toggleButton } =
			this.#createPopupFooter(columnId);

		// Calculate popup width based on content
		const calculatedWidth = this.#calculatePopupWidth(sortedValues);
		popup.style.width = `${calculatedWidth}px`;

		popup.appendChild(header);
		popup.appendChild(scrollContainer);
		popup.appendChild(footer);

		// Setup event handlers
		this.#setupPopupEventHandlers({
			popup,
			columnId,
			columnType,
			searchInput,
			filterTypeSelect,
			filterTypeButton,
			clearBtn,
			applyButton,
			clearFiltersButton,
			warningIcon,
			scrollContainer,
			checkboxContainer,
			spacer,
			sortedValues,
			filteredValues,
			isAllSelected,
			summary,
			toggleButton
		});

		// Make the popup draggable using the header as the handle
		this.makeDraggable(popup, header);

		return popup;
	}

	#createFilterUI(th, columnId, sortedValues, columnType) {
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
		filterIcon.dataset.columnId = columnId;

		filterIcon.innerHTML = `
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<path d="M3 4h18l-7 8v6l-4 2v-8L3 4z"/>
			</svg>
		`;

		const popup = this.#createFilterPopup(columnId, sortedValues, columnType);
		document.body.appendChild(popup);

		filterIcon.addEventListener('click', (e) => {
			e.stopPropagation();
			this.#toggleFilterPopup(popup, filterIcon, columnId);
		});

		const arrowSpan = th.querySelector('span#arrow');
		if (arrowSpan) {
			th.insertBefore(filterIcon, arrowSpan);
		}
		else {
			th.appendChild(filterIcon);
		}

		// Update icon state if filter is already applied
		this.#updateFilterIconState(filterIcon, columnId);
	}

	#createColumnFilters() {
		this.allThs.forEach((th, thIndex) => {
			const columnId = this.#getStableColumnId(th);

			// Initialize filter state for this column
			if (!this.#filterStates.has(columnId)) {
				this.#filterStates.set(columnId, {
					type: 'contains',
					search: '',
					checked: [],
					allSelected: false
				});
			}

			// Collect all values for this column
			const filterValues = new Set();
			const columnIndex = parseInt(th.id);

			this.#rowsArray.forEach(rowObj => {
				const tr = rowObj.row;
				if (tr.querySelector('[reset-row]') || tr.querySelector('[footer-row]')) return;

				const td = this.#getCellByColumnId(tr, columnIndex);
				if (td) {
					const value = this.#extractCellValue(td);
					if (value) filterValues.add(value);
				}
			});

			if (filterValues.size === 0) return; // Skip empty columns

			const valuesArray = Array.from(filterValues);

			// Determine column type
			const columnType = this.#detectColumnType(valuesArray);
			this.#columnTypes.set(columnId, columnType);

			const sortedValues = this.#sortFilterValues(valuesArray, columnType);

			// Set default filter type based on column type (only if not already set)
			const filterState = this.#filterStates.get(columnId);
			if (!filterState.type) {
				if (columnType === 'numeric') {
					filterState.type = 'equals';
				}
				else if (columnType === 'ip') {
					filterState.type = 'contains';
				}
				else {
					filterState.type = 'contains';
				}
			}

			// Create filter icon and popup for this column
			this.#createFilterUI(th, columnId, sortedValues, columnType);
		});
	}

	#toggleFilterPopup(popup, filterIcon, columnId) {
		// Close all other popups
		document.querySelectorAll('.filter-popup').forEach(p => {
			if (p !== popup) p.style.display = 'none';
		});

		if (popup.style.display === 'flex') {
			popup.style.display = 'none';
			this._resumeUpdating();
			return;
		}

		// Get possible values using shared method
		const sortedVisibleValues = this.#getPossibleValuesForColumn(columnId);
		const filterState = this.#filterStates.get(columnId);

		// STORE INITIAL STATE in popup's dataset
		popup.dataset.initialState = JSON.stringify({
			search: filterState.search || '',
			checked: [...(filterState.checked || [])],
			type: filterState.type
		});

		this.#updateFilterPopupValues(popup, columnId, sortedVisibleValues, filterState);

		popup.style.visibility = 'hidden';
		popup.style.display = 'flex';
		popup.style.left = '0px';
		popup.style.top = '0px';

		const scrollContainer = popup.querySelector('[style*="max-height: 300px"]');
		if (scrollContainer) {
			scrollContainer.scrollTop = 0;
		}

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

		requestAnimationFrame(() => {
			const searchInput = popup.querySelector('input[type="text"]');
			if (searchInput) searchInput.focus();
		});

		this._pauseUpdating();
	}

	#updateFilterIconState(filterIcon, columnId) {
		const filterState = this.#filterStates.get(columnId);
		if (!filterState) return;

		const hasChecked = filterState.checked.length > 0;
		const hasSearch = filterState.search !== '';

		if (hasChecked || hasSearch) {
			filterIcon.classList.add('active');
			filterIcon.classList.remove('filter-error');
			filterIcon.title = 'Filter applied!';
			this.#activeFilters.add(columnId);
		}
		else {
			filterIcon.classList.remove('active');
			filterIcon.classList.remove('filter-error');
			filterIcon.title = 'Click to filter this column';
			this.#activeFilters.delete(columnId);
		}
	}

	#updateFilterPopupValues(popup, columnId, sortedValues, filterState) {
		const scrollContainer = popup.querySelector('[style*="max-height: 300px"]');
		const spacer = scrollContainer?.querySelector('[style*="position: relative"]');
		const checkboxContainer = spacer?.querySelector('.filter-popup-checkboxes');

		if (!scrollContainer || !spacer || !checkboxContainer) return;

		// Store the new sortedValues in the popup's dataset so handleInput can access them
		popup.dataset.sortedValues = JSON.stringify(sortedValues);

		// Find the search input and trigger handleInput to re-render with new values
		const searchInput = popup.querySelector('input[type="text"]');

		if (searchInput && searchInput._handleInputFunction) {
			// If handleInput exists, trigger it to recalculate filteredValues with new sortedValues
			searchInput._handleInputFunction();
		}
		else {
			// Fallback: manually update if handleInput isn't available
			spacer.style.height = `${sortedValues.length * 30}px`;
			scrollContainer.scrollTop = 0;

			// Simple render without filtering
			const renderBasic = () => {
				const scrollTop = scrollContainer.scrollTop;
				const visibleCount = 25;
				const startIndex = Math.floor(scrollTop / 30);
				const endIndex = Math.min(startIndex + visibleCount, sortedValues.length);

				checkboxContainer.style.top = `${startIndex * 30}px`;
				checkboxContainer.innerHTML = '';

				for (let i = startIndex; i < endIndex; i++) {
					const value = sortedValues[i];
					const id = `filter_${columnId}_${String(value).replace(/[^a-zA-Z0-9]/g, '_')}`;
					const label = document.createElement('label');
					label.classList.add('custom-checkbox');
					label.innerHTML = `
						<input type="checkbox" id="${id}" value="${value}">
						<span>${value}</span>
					`;

					const checkbox = label.querySelector('input[type="checkbox"]');
					checkbox.checked = filterState.checked.includes(String(value).toLowerCase());
					label.setAttribute('data-index', i);
					checkboxContainer.appendChild(label);
				}
			};

			renderBasic();

			// Update scroll handler
			const oldScrollHandler = scrollContainer._scrollHandler;
			if (oldScrollHandler) {
				scrollContainer.removeEventListener("scroll", oldScrollHandler);
			}

			const newScrollHandler = () => renderBasic();
			scrollContainer._scrollHandler = newScrollHandler;
			scrollContainer.addEventListener('scroll', newScrollHandler);
		}

		// Update summary
		const summary = popup.querySelector('.summary');
		if (summary) {
			summary.textContent = `${filterState.checked.length} selected`;
		}

		// Update toggle button text
		const toggleButton = popup.querySelector('.toggle-row button');
		if (toggleButton) {
			const isAllSelected = sortedValues.length > 0 &&
				sortedValues.every(v => filterState.checked.includes(String(v).toLowerCase()));
			toggleButton.textContent = isAllSelected ? 'Uncheck All': 'Select All';
		}
	}

	#refreshAllFilterPopups() {
		const displayedRows = this.#rowsArray.filter(rowObj => rowObj.status === 'display');

		this.#filterStates.forEach((filterState, columnId) => {
			const popupId = `${this.#values_table.id}-${this._widgetid}-popup-${columnId}`;
			const popup = document.getElementById(popupId);

			if (!popup) return;

			const th = Array.from(this.allThs).find(t => this.#getStableColumnId(t) === columnId);
			if (!th) return; // Column no longer exists

			// Collect values from currently displayed rows for this column
			const visibleValues = new Set();
			const columnIndex = parseInt(th.id);

			displayedRows.forEach(rowObj => {
				const tr = rowObj.row;
				if (tr.querySelector('[reset-row]') || tr.querySelector('[footer-row]')) return;

				const td = this.#getCellByColumnId(tr, columnIndex);
				if (td) {
					const value = this.#extractCellValue(td);
					if (value) visibleValues.add(value);
				}
			});

			const visibleValuesArray = Array.from(visibleValues);
			const columnType = this.#columnTypes.get(columnId) || 'text';
			const sortedVisibleValues = this.#sortFilterValues(visibleValuesArray, columnType);

			// Update the popup's checkbox list
			this.#updateFilterPopupValues(popup, columnId, sortedVisibleValues, filterState);
		});
	}

	#applyAllFilters(updateDisplayNow = true) {
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

		this.invalidRegex = false;
		let columnStats = [];
		const columns = this._fields.columns;

		// Rebuild active filters set based on current filter states
		this.#activeFilters.clear();
		this.#filterStates.forEach((filterState, columnId) => {
			const hasChecked = filterState.checked && filterState.checked.length > 0;
			const hasSearch = filterState.search && filterState.search.trim() !== '';

			if (hasChecked || hasSearch) {
				this.#activeFilters.add(columnId);
			}
		});

		// Apply filters to all rows
		this.#rowsArray.forEach(rowObj => {
			const tr = rowObj.row;
			const isResetRow = tr.querySelector('[reset-row]') !== null;
			const isFooterRow = tr.querySelector('[footer-row]') !== null;

			if (isResetRow || isFooterRow) {
				rowObj.status = 'display';
				return;
			}

			let showRow = true;

			// Check all active filters
			for (const columnId of this.#activeFilters) {
				const filterState = this.#filterStates.get(columnId);
				const columnType = this.#columnTypes.get(columnId) || 'text';

				if (!filterState) continue;

				const th = Array.from(this.allThs).find(t => this.#getStableColumnId(t) === columnId);
				if (!th) {
					// Column no longer exists, skip this filter
					continue;
				}

				const columnIndex = parseInt(th.id); // Get current position
				const td = this.#getCellByColumnId(tr, columnIndex);

				if (!td) {
					showRow = false;
					break;
				}

				const text = this.#extractCellValue(td).toLowerCase();
				const checkedValues = filterState.checked || [];
				const searchValue = (filterState.search || '').trim().toLowerCase();
				const filterMode = filterState.type || 'contains';

				let matchesThisColumn = true;

				// Handle Top N / Bottom N specially.
				if (columnType === 'numeric' && (filterMode === 'top n' || filterMode === 'bottom n')) {
					// Mark for later processing don't filter out yet
					matchesThisColumn = true;
				}
				else if (checkedValues.length > 0) {
					matchesThisColumn = checkedValues.includes(text);
				}
				else if (searchValue !== '') {
					matchesThisColumn = this.#matchesFilter(text, searchValue, filterMode, false, columnType);
				}

				if (!matchesThisColumn) {
					showRow = false;
					break;
				}
			}

			rowObj.status = showRow ? 'display' : 'hidden';
		});

		// Collect stats for bar gauges/sparklines if row is displayed
		if (this._fields.bar_gauge_layout === 0 || this._fields.layout === 50) {
			const displayedRows = this.#rowsArray.filter(rowObj => rowObj.status === 'display');

			displayedRows.forEach(rowObj => {
				const tr = rowObj.row;
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
			});
		}

		// Filter selected items based on visible rows
		const selectedItemsBefore = [...this.#selected_items];
		const displayedRows = this.#rowsArray.filter(rowObj => rowObj.status === 'display');
		this.#filterSelectedItems();

		// Handle the case where clear filters was clicked with selections
		if (this.#clearFiltersClickedWithSelections) {
			this.#selected_items = [{ itemid: this.#null_id, name: null }];
			this.#selected_hostid = this.#null_id;
			this.#clearFiltersClickedWithSelections = false;
		}
		else if (selectedItemsBefore.length > 0 && this.#selected_items.length === 0) {
			this.#selected_items = [{ itemid: this.#null_id, name: null }];
		}

		// Update bar gauges if needed
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

		// Handle Top N/Bottom N filters
		for (const columnId of this.#activeFilters) {
			const filterState = this.#filterStates.get(columnId);
			const columnType = this.#columnTypes.get(columnId);

			if (columnType === 'numeric' && (filterState.type === 'top n' || filterState.type === 'bottom n')) {
				const n = parseInt(filterState.search);
				if (isNaN(n) || n <= 0) {
					console.warn(`Invalid N value for ${filterState.type}: "${filterState.search}"`);
					continue;
				}

				const th = Array.from(this.allThs).find(t => this.#getStableColumnId(t) === columnId);
				if (!th) continue; // Column no longer exists

				const columnIndex = parseInt(th.id);

				// Get all currently displayed rows with their values
				const displayedWithValues = this.#rowsArray
					.filter(rowObj => {
						// Only consider rows that passed all other filters
						if (rowObj.status !== 'display') return false;

						// Skip reset and footer rows
						const tr = rowObj.row;
						if (tr.querySelector('[reset-row]') || tr.querySelector('[footer-row]')) return false;

						return true;
					})
					.map(rowObj => {
						const td = this.#getCellByColumnId(rowObj.row, columnIndex);
						const valueStr = this.#extractCellValue(td);
						const value = parseFloat(valueStr);
						return { rowObj, value, valueStr };
					})
					.filter(item => !isNaN(item.value)); // Only keep valid numeric values

				if (displayedWithValues.length === 0) {
					console.warn(`No valid numeric values found in column ${columnId} for ${filterState.type}`);
					continue;
				}

				// Sort based on filter type
				displayedWithValues.sort((a, b) => {
					return filterState.type === 'top n' ? b.value - a.value : a.value - b.value;
				});

				// Take top/bottom N
				const keepCount = Math.min(n, displayedWithValues.length);
				const keepSet = new Set(displayedWithValues.slice(0, keepCount).map(item => item.rowObj));

				// Hide rows not in top/bottom N
				this.#rowsArray.forEach(rowObj => {
					// Only hide rows that were previously set to display
					if (rowObj.status === 'display' && !keepSet.has(rowObj)) {
						const tr = rowObj.row;
						// Don't hide reset or footer rows

						if (!tr.querySelector('[reset-row]') && !tr.querySelector('[footer-row]')) {
							rowObj.status = 'hidden';
						}
					}
				});
			}
		}

		const finalDisplayedRows = this.#rowsArray.filter(rowObj => rowObj.status === 'display');
		this.#totalRows = finalDisplayedRows.length;
		this.#currentPage = 1;

		// Update pagination if needed
		this.#removePaginationControls();
		if (this.#totalRows > this.#rowsPerPage) {
			this.#displayPaginationControls();
		}

		if (updateDisplayNow) {
			// Decide whether to scroll to top based on flag
			if (this.#isUserInitiatedFilterChange) {
				// User clicked Apply or Clear Filters scroll to top
				this._contents.scrollTop = 0;
			}
			this.#updateDisplay(false, true, false);
		}

		// Update all filter icon states
		this.allThs.forEach(th => {
			const filterIcon = th.querySelector('.filter-icon');
			if (filterIcon) {
				const columnId = this.#getStableColumnId(th);
				this.#updateFilterIconState(filterIcon, columnId);
			}
		});

		// Refresh all filter popups to show only values from visible rows
		this.#refreshAllFilterPopups();

		this.checkAndRemarkSelected();

		this.#isUserInitiatedFilterChange = false;
	}

	cleanupDraggableState(popupId) {
		this.#draggableStates.delete(popupId);
		this.#popupIds.delete(popupId);
	}

	#cleanupAllPopups() {
		// Clean up all popups belonging to this widget instance
		this.#popupIds.forEach(popupId => {
			const popup = document.getElementById(popupId);
			if (popup) {
				// Clean up dropdown handlers before removing popup
				const filterLists = popup.querySelectorAll('.custom-select .list');
				filterLists.forEach(filterList => {
					if (filterList._closeDropdownHandler) {
						document.removeEventListener('click', filterList._closeDropdownHandler);
						delete filterList._closeDropdownHandler;
					}
				});

				popup.remove();
			}
			this.#draggableStates.delete(popupId);
		});
		this.#popupIds.clear();

		// Clean up all filter tooltips belonging to this widget instance
		this.#filterTooltipIds.forEach(tooltipId => {
			const tooltip = document.getElementById(tooltipId);
			if (tooltip) {
				tooltip.remove();
			}
		});
		this.#filterTooltipIds.clear();
	}

	closeFilterPopup(e) {
		const clickedPopup = e.target.closest('.filter-popup');
		const clickedFilterIcon = e.target.closest('.filter-icon');

		if (!clickedPopup && !clickedFilterIcon) {
			// Restore initial state for any open popups before closing
			document.querySelectorAll('.filter-popup').forEach(popup => {
				if (popup.style.display === 'flex') {
					const columnId = popup.dataset.columnId;
					const filterState = this.#filterStates.get(columnId);

					// Only process if filterState exists
					if (filterState) {
						// Get initial state from popup's data attribute if it exists
						if (popup.dataset.initialState) {
							try {
								const initialState = JSON.parse(popup.dataset.initialState);
								filterState.search = initialState.search;
								filterState.checked = [...initialState.checked];
								filterState.type = initialState.type;

								// Update UI
								const searchInput = popup.querySelector('input[type="text"]');
								if (searchInput) {
									searchInput.value = initialState.search;
								}
							}
							catch (e) {
								console.error('Failed to parse initial state:', e);
							}
						}
					}
				}
				popup.style.display = 'none';
			});
			this._resumeUpdating();
		}
	}

	// ========== Draggable Handlers ========== //

	makeDraggable(popup, handle) {
		const popupId = popup.id;

		// Initialize draggable state for this popup
		this.#draggableStates.set(popupId, {
			isDragging: false,
			offsetX: 0,
			offsetY: 0
		});

		handle.style.cursor = 'grab';

		// Create bound handlers specific to this popup
		const boundMouseDown = (e) => this.handleMouseDownTi(e, popupId, popup, handle);

		// Store handlers so we can remove them later if needed
		handle._dragHandlers = {
			mouseDown: boundMouseDown
		};

		handle.addEventListener('mousedown', boundMouseDown);
	}

	handleMouseDownTi(e, popupId, popup, handle) {
		if (
			e.target.tagName === 'INPUT' ||
			e.target.tagName === 'SELECT' ||
			e.target.tagName === 'BUTTON' ||
			e.target.classList.contains('clear-btn') ||
			e.target.closest('.custom-select')
		) {
			return;
		}

		const state = this.#draggableStates.get(popupId);
		if (!state) return;

		state.isDragging = true;
		handle.style.cursor = 'grabbing';

		const rect = popup.getBoundingClientRect();
		state.offsetX = e.clientX - rect.left;
		state.offsetY = e.clientY - rect.top;

		document.body.style.userSelect = 'none';

		// Create bound handlers for this drag session
		const boundMouseMove = (e) => this.handleMouseMoveTi(e, popupId, popup);
		const boundMouseUp = (e) => this.handleMouseUpTi(e, popupId, handle, boundMouseMove, boundMouseUp);

		// Attach to document so they fire even when cursor leaves the popup
		document.addEventListener('mousemove', boundMouseMove);
		document.addEventListener('mouseup', boundMouseUp);
	}

	handleMouseMoveTi(e, popupId, popup) {
		const state = this.#draggableStates.get(popupId);
		if (!state || !state.isDragging) return;

		popup.style.left = `${e.clientX - state.offsetX}px`;
		popup.style.top = `${e.clientY - state.offsetY}px`;
	}

	handleMouseUpTi(e, popupId, handle, mouseMoveHandler, mouseUpHandler) {
		const state = this.#draggableStates.get(popupId);
		if (!state) return;

		state.isDragging = false;
		handle.style.cursor = 'grab';
		document.body.style.userSelect = '';

		// Remove the document listeners
		document.removeEventListener('mousemove', mouseMoveHandler);
		document.removeEventListener('mouseup', mouseUpHandler);
	}

	// ========== Cell Click and Selection Methods ========== //

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
			if (this._fields.use_host_storage) {
				this.setReferenceInSession(this.#sessionKey, "hostids", this.#selected_hostid);
			}
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

	_markSelected(type, refresh = false) {
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
			let element = td.querySelector(this.#menu_selector);

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
					else {
						this.#broadcast(CWidgetsData.DATA_TYPE_ITEM_ID, CWidgetsData.DATA_TYPE_ITEM_IDS, this.#null_id);
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

	// ========== Sorting Methods ========== //


	#sortTable(th, ascending, span, preserve = false) {
		this._sortTableRowsByColumn(th.id, ascending);
		th.dataset['sort'] = ascending ? 'asc' : 'desc';
		span.className = ascending ? 'arrow-up' : 'arrow-down';
		if (!preserve) {
			this.#currentPage = 1;
		}
		this.#updateDisplay();
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

	// ========== Display and Update Methods ========== //

	#updateDisplay(scrollToTop = false, updateFooter = false, firstRun = false, restoreScrollTop = null) {
		// Don't save on first run from setContents
		if (!scrollToTop && !firstRun) {
			this.#saveScrollPosition();
		}

		const visibleRows = this.#rowsArray.filter(({ status }) => status === 'display');
		const maxPages = Math.ceil(visibleRows.length / this.#rowsPerPage);

		if (this.#currentPage > maxPages) {
			this.#currentPage = 1;
		}

		const startIndex = (this.#currentPage - 1) * this.#rowsPerPage;
		const endIndex = Math.min(startIndex + this.#rowsPerPage, visibleRows.length);
		const displayedRows = visibleRows.slice(startIndex, endIndex);

		let blurAdded = false;
		if (!updateFooter && maxPages > 1 &&
				this.#values_table.querySelectorAll('z-bar-gauge').length > 0) {
			blurAdded = true;
			this.#parent_container.classList.add('is-loading', 'widget-blur');
		}

		setTimeout(() => {
			this.#values_table.tBodies[0].innerHTML = '';

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
				this.#scrollPosition = { top: 0, left: 0 };
			}

			this.#updatePageInfo(this.#paginationElement?.querySelector('span'));
			if (updateFooter) {
				this.updateTableFooter();
			}

			// Restore scroll position
			if (restoreScrollTop !== null) {
				this._contents.scrollTop = restoreScrollTop;
				this._contents.scrollLeft = this.#scrollPosition.left;
			}

			// Re-enable scroll tracking after everything is done
			if (firstRun) {
				this.#isUpdatingDisplay = false;
			}

			// Only restore for non-firstRun, non-scrollToTop calls
			if (!scrollToTop && !firstRun) {
				requestAnimationFrame(() => {
					requestAnimationFrame(() => {
						this._contents.scrollTop = this.#scrollPosition.top;
						this._contents.scrollLeft = this.#scrollPosition.left;
					});
				});
			}

			// Wait for custom elements to be ready
			const startTime = Date.now();
			if (blurAdded) {
				requestAnimationFrame(() => {
					const elapsed = Date.now() - startTime;
					if (elapsed < this.#SHADOW_DOM_RENDER_DELAY) {
						setTimeout(() => {
							this.#parent_container.classList.remove('is-loading', 'widget-blur');
						}, this.#SHADOW_DOM_RENDER_DELAY - elapsed);
					}
					else {
						this.#parent_container.classList.remove('is-loading', 'widget-blur');
					}
				});
			}
		}, 0);
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

	// ========== Session Storage Methods ========== //

	getReferenceFromSession(key) {
		try {
			return sessionStorage.getItem(key);
		}
		catch (e) {
			console.error('Session storage access failed:', e);
			return null;
		}
	}

	setReferenceInSession(key, id_type, id) {
		try {
			CWidgetTableModuleRME.#hasManualSelection = true;

			let existing = this.getReferenceFromSession(key);
			let reference;

			if (existing) {
				reference = JSON.parse(existing);
				reference[id_type] = [id];
			}
			else {
				reference = {
					hostids: [],
					hostgroupids: [],
					itemids: []
				};
				reference[id_type] = [id];
			}

			sessionStorage.setItem(key, JSON.stringify(reference));
		}
		catch (e) {
			console.error('Session storage write failed:', e);
		}
	}

	// ========== Event Listener Management ========== //

	attachListeners() {
		document.addEventListener('click', this.closeFilterPopupHandler);
	}

	detachListeners() {
		document.removeEventListener('click', this.closeFilterPopupHandler);
	}

	// ========== Widget Lifecycle Methods ========== //

	_showPreloader() {
		const container = this._target.querySelector('.dashboard-grid-widget-container');

		if (this._hide_preloader_animation_frame !== null) {
			cancelAnimationFrame(this._hide_preloader_animation_frame);
			this._hide_preloader_animation_frame = null;
		}

		container.classList.add('is-loading');
		container.classList.remove('is-loading-fadein', 'delayed-15s');
	}

	_hidePreloader() {
		const container = this._target.querySelector('.dashboard-grid-widget-container');

		if (this._hide_preloader_animation_frame !== null) {
			return;
		}

		this._hide_preloader_animation_frame = requestAnimationFrame(() => {
			container.classList.remove('is-loading', 'widget-blur');
			this._hide_preloader_animation_frame = null;
		});
	}

	_schedulePreloader() {
		const container = this._target.querySelector(".dashboard-grid-widget-container");

		if (this._hide_preloader_animation_frame !== null) {
			cancelAnimationFrame(this._hide_preloader_animation_frame);
			this._hide_preloader_animation_frame = null;
		}

		container.classList.add('is-loading', 'widget-blur');
	}

	async promiseUpdate() {
		// Let the parent fetch the data
		await super.promiseUpdate();

		// At this point, core wants to hide the preloader, but we need to keep it visible
		// The preloader will be hidden automatically by core after this promise resolves

		// Add a small delay to ensure setContents processing completes
		// This prevents the preloader from hiding before filters/sorts are applied
		await new Promise(resolve => {
			// Wait for the next animation frame after setContents completes
			requestAnimationFrame(() => {
				requestAnimationFrame(() => {
					requestAnimationFrame(() => {
						resolve();
					});
				});
			});
		});
	}

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
		// Set session storage key for this dashboard
		if (!this.#sessionKey) {
			const dashboardId = this._dashboard?.dashboardid || 'default';
			this.#sessionKey = `widget_references_${dashboardId}`;
		}

		// Set flag to prevent scroll handler from interfering
		this.#isUpdatingDisplay = true;

		// Save scroll position BEFORE any processing
		this.#saveScrollPosition();

		this.#cleanupAllPopups();

		if (this.#theme === null) {
			this.getTheme();
		}

		if (response.body.includes('no-data-message')) {
			setTimeout(() => {
				this.#broadcast(CWidgetsData.DATA_TYPE_HOST_ID, CWidgetsData.DATA_TYPE_HOST_IDS, this.#null_id);
				this.#broadcast(CWidgetsData.DATA_TYPE_ITEM_ID, CWidgetsData.DATA_TYPE_ITEM_IDS, this.#null_id);
				super.setContents(response);
				this.#removePaginationControls();
			}, 0);
			return;
		}

		super.setContents(response);

		this.detachListeners();

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
					let newTd = td.nextElementSibling;
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
			// FIRST: Add the arrow span to the existing HTML
			th.innerHTML += `<span class="new-arrow" id="arrow"></span>`;
			th.setAttribute('style', `color: #4796c4; font-weight: bold;`);
			th.classList.remove('cursor-pointer'); // Remove this we'll add it to span only

			const colspan = th.hasAttribute('colspan') ? parseFloat(th.getAttribute('colspan')) : 1;
			th.id = colIndex + colspan - 1;

			colIndex = parseFloat(th.id) + 1;
		});

		// Wrap the text content before filter icons are added
		this.allThs.forEach((th) => {
			// Check if we already wrapped it (in case of multiple updates)
			if (th.querySelector('.sortable-header-text')) {
				return;
			}

			// Collect all text nodes and elements that aren't filter icons or arrows
			const nodesToWrap = [];
			Array.from(th.childNodes).forEach(node => {
				// Skip filter icons and arrow spans
				if (node.nodeType === Node.ELEMENT_NODE) {
					if (node.classList.contains('filter-icon') ||
							node.classList.contains('new-arrow') ||
							node.id === 'arrow') {
						return; // Don't wrap these
					}
				}
				nodesToWrap.push(node);
			});

			if (nodesToWrap.length === 0) {
				return;
			}

			// Create wrapper span
			const textSpan = document.createElement('span');
			textSpan.className = 'sortable-header-text';

			// Move nodes into the wrapper
			nodesToWrap.forEach(node => {
				textSpan.appendChild(node.cloneNode(true));
				node.remove();
			});

			// Insert wrapper at the beginning of th
			th.insertBefore(textSpan, th.firstChild);
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
				// Only sort if the click was on the sortable text span
				const clickedTextSpan = event.target.closest('.sortable-header-text');
				if (!clickedTextSpan) {
					return; // Click was on th but not on the text
				}

				this.#th = target;
				const span = this.#getSetSpans(target);
				const ascending = !('sort' in target.dataset) || target.dataset.sort != 'asc';
				this.#sortTable(target, ascending, span);
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


		// Store existing filter states before recreating filters
		const existingFilterStates = new Map(this.#filterStates);

		this.#createColumnFilters();

		// Restore filter states after recreation
		existingFilterStates.forEach((filterState, columnId) => {
			if (this.#filterStates.has(columnId)) {
				this.#filterStates.set(columnId, filterState);
			}
		});

		// Update the popup UI elements to reflect the restored state
		existingFilterStates.forEach((filterState, columnId) => {
			const popupId = `${this.#values_table.id}-${this._widgetid}-popup-${columnId}`;
			const popup = document.getElementById(popupId);
			if (popup) {
				const searchInput = popup.querySelector('input[type="text"]');
				if (searchInput) {
					searchInput.value = filterState.search || '';
				}

				const filterTypeSelect = popup.querySelector('.custom-select input[type="hidden"]');
				const filterTypeButton = popup.querySelector('.custom-select > button');
				if (filterTypeSelect && filterTypeButton) {
					filterTypeSelect.value = filterState.type;
					const filterTypeList = filterTypeButton.nextElementSibling;
					if (filterTypeList) {
						const matchingOption = Array.from(filterTypeList.querySelectorAll('li'))
							.find(li => li.dataset.value === filterState.type);
						if (matchingOption) {
							filterTypeButton.textContent = matchingOption.dataset.label;
						}
					}
				}
			}
		});

		// Validate and clean up filter states based on current data
		this.#filterStates.forEach((filterState, columnId) => {
			// Skip if no checkbox selections
			if (!filterState.checked || filterState.checked.length === 0) {
				return;
			}

			// Find the column
			const th = Array.from(this.allThs).find(t => this.#getStableColumnId(t) === columnId);
			if (!th) {
				// Column doesn't exist anymore, clear the filter
				this.#filterStates.delete(columnId);
				this.#activeFilters.delete(columnId);
				return;
			}

			// Collect all available values in this column
			const availableValues = new Set();
			const columnIndex = parseInt(th.id);

			this.#rowsArray.forEach(rowObj => {
				const tr = rowObj.row;
				if (tr.querySelector('[reset-row]') || tr.querySelector('[footer-row]')) return;

				const td = this.#getCellByColumnId(tr, columnIndex);
				if (td) {
					const value = this.#extractCellValue(td);
					if (value) {
						availableValues.add(value.toLowerCase());
					}
				}
			});

			// Check which of the selected values still exist
			const validChecked = filterState.checked.filter(checkedValue =>
				availableValues.has(checkedValue)
			);

			// If none of the checked values exist anymore, clear the filter
			if (validChecked.length === 0) {
				filterState.checked = [];
				filterState.search = '';
				this.#activeFilters.delete(columnId);

				// Update the filter icon to reflect cleared state
				const filterIcon = th.querySelector('.filter-icon');
				if (filterIcon) {
					this.#updateFilterIconState(filterIcon, columnId);
				}
			}
			else if (validChecked.length !== filterState.checked.length) {
				// Some values still exist, keep only those
				filterState.checked = validChecked;
			}
		});

		// Check if we have active filters
		const hasActiveFilters = existingFilterStates.size > 0 &&
			Array.from(existingFilterStates.values()).some(
				state => state.checked.length > 0 || state.search !== ''
			);

		// If we have active filters, apply them BEFORE showing the table
		if (hasActiveFilters) {
			this.#applyAllFilters(false); // <-- This applies filters SYNCHRONOUSLY
		}

		// Apply sort BEFORE displaying (if a sort was previously active)
		if (this.#th !== undefined) {
			const previousSortedTh = Array.from(this.allThs).find(
				th => th.getAttribute('id') === this.#th.getAttribute('id')
			);

			if (previousSortedTh) {
				const span = this.#getSetSpans(previousSortedTh);
				const ascending = this.#th.getAttribute('data-sort') === 'asc' ? true : false;
				// Sort the rowsArray directly without calling #sortTable to avoid the display update
				this._sortTableRowsByColumn(previousSortedTh.id, ascending);
				previousSortedTh.dataset['sort'] = ascending ? 'asc' : 'desc';
				span.className = ascending ? 'arrow-up' : 'arrow-down';
				this.#th = previousSortedTh;
			}
		}

		// NOW set up display AFTER filters AND sort are applied
		this.#totalRows = this.#rowsArray.filter(r => r.status === 'display').length;
		this.#updateDisplay(false, true, true, this.#scrollPosition.top);

		this.#removePaginationControls();
		if (this.#totalRows > this.#rowsPerPage) {
			this.#displayPaginationControls();
		}

		this.closeFilterPopupHandler = this.closeFilterPopup.bind(this);
		this.boundMouseDown = this.handleMouseDownTi.bind(this);
		this.boundMouseMove = this.handleMouseMoveTi.bind(this);
		this.boundMouseUp = this.handleMouseUpTi.bind(this);
		this.attachListeners();

		if (this._fields.use_host_storage && CWidgetTableModuleRME.#hasManualSelection) {
			let references = this.getReferenceFromSession(this.#sessionKey);
			if (references) {
				let reference_session = JSON.parse(references);
				if (reference_session['hostids']) {
					this.#selected_hostid = reference_session['hostids'][0];
				}
			}
		}

		this.checkAndRemarkSelected();

		// Setup scroll tracking and restore position
		this.#setupScrollTracking();
	}

	onResize() {
		if (this._state === WIDGET_STATE_ACTIVE) {
			this.#recalculateSvgSparklines();
		}
	}

	onDestroy() {
		// Clean up scroll tracking
		this.#cleanupScrollTracking();

		// Clean up all popups and tooltips for this widget instance
		this.#cleanupAllPopups();

		// Detach event listeners
		this.detachListeners();

		super.onDestroy();
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

	// ========== CSV Export Methods ========== //

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

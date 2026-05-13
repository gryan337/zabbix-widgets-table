# 📊 RME Table Widget for Zabbix

A powerful, feature-rich table widget for Zabbix dashboards — built to fill the gap left by native visualizations and designed for both operators and end-users.

> 📺 [Training & Demo Videos](https://www.youtube.com/playlist?list=PLVBaXiAwXB7GKKJ_4eoQuOkan9SPfzEVs)

---

## 🖼️ Preview

<img src="screenshots/TableDemo.png" width="750" />

---

## 📦 Requirements

| Zabbix Version | Branch |
|----------------|--------|
| 7.0 | `7.0` |
| 7.2 | `7.2` |
| 7.4 | `7.4` |

> ⚠️ Clone the branch that matches your Zabbix version.

---

## 🛠️ Installation

```bash
# Clone into Zabbix modules directory
git clone -b 7.2 https://github.com/yourrepo/rme-table-widget \
  /usr/share/zabbix/ui/modules/rme-table-widget
```

Then in Zabbix UI:  
**Administration → General → Modules → Scan directory → Enable "RME Table"**

---

## ⚡ Features

<details>
<summary><strong>📐 Layout Modes</strong></summary>

<br>

Two new **`Layout`** modes give you flexibility for different use cases:

**3 Column (Top/Bottom N)**  
Classic `Metric | Host | Value` layout. Best for ranked views or true Top N / Bottom N displays of values across all hosts.

<img src="screenshots/3-Column-Layout.png" width="600" />

**Column-per-Pattern**  
One column per configured item pattern. This is the most flexible layout and the recommended choice for any metrics created by Low-Level Discovery. Each item added in the **`Items`** section creates a new column.

Supports **`Item grouping`** by:
- **Item tags**
- **Host name** — produces a layout similar to a **`Horizontal`** layout table
- **Host tags**
- **Host groups**

> 💡 Heavy use of item tags unlocks the full power of this mode.

#### Item Grouping: Item Tags

When grouping by **Item tags**, each row's grouping cell displays the value of a specific tag attached to the item. This is the most powerful grouping mode and works especially well with Low-Level Discovery, where tag values naturally identify what each discovered item represents (e.g. interface name, mount point, application tier).

- The cell displays the tag value for the configured tag key. If the tag value is absent on an item, the cell is empty.
- Multiple item tag groupings can be stacked — each configured tag key extends the grouping. When multiple item tag keys are used, the values are combined into a single cell separated by the configured **`Delimiter`**, or split into individual columns when the **`split groupings`** checkbox is selected.
- Item tag cells are **clickable** (rendered in blue with underline) when the row has associated item IDs (added when at least one item pattern has the **`Broadcast from grouped collumn`** checked. Clicking selects the row and **broadcasts the item ID(s)** to connected widgets. If the **`Split groupings`** checkbox is selected the item tag values will no longer be clickable and a separate column on the far left will be added with an orange icon that can be clicked instead.
- **Multi-select is supported**: `Ctrl+Click` adds individual rows to the selection; `Shift+Click` selects a contiguous range. All selected item IDs are broadcast together, and their time periods are automatically merged (see [Time Period Broadcasting](#-time-period-broadcasting)).
- Clicking an already-selected cell deselects it and clears the broadcast.

> 💡 Pairing item tag grouping with **`Show item grouping only`** turns the widget into a pure tag-based filter that drives other widgets on the dashboard without displaying any metric values itself.

#### Item Grouping: Host Name

Each row's grouping cell shows the hostname of the row's host. Clicking a hostname cell selects it and **broadcasts the host ID** to connected widgets. Only one host can be selected at a time — `Ctrl+Click` and `Shift+Click` are not supported for host name cells. Clicking an already-selected cell deselects it.

#### Item Grouping: Host Tags

When grouping by **Host tags**, each row's grouping cell displays the value of a specific tag attached to the host rather than the item. Multiple host tag groupings can be stacked in the same way as item tags, and values are combined using the configured **`Delimiter`** when using multiple host tag groupings.

Host tag cells are **display-only** — they carry no `data-menu` and produce no broadcast on click. They are purely informational, useful for visually organizing rows by host metadata (e.g. environment, region, team) without interfering with item or host selection elsewhere in the row.

#### Item Grouping: Host Groups

When grouping by **Host groups**, each row's grouping cell displays the host groups that the row's host belongs to. Because a single host can belong to many groups, the cell is designed to stay compact while keeping all groups accessible:

- The **first host group** is always shown inline in the cell. If the name exceeds ~38 characters it is truncated with an ellipsis; hovering over it reveals the full name in an instant tooltip.
- When a host belongs to **more than one group**, a **`+N` badge** appears next to the visible group name, where `N` is the number of additional groups. Clicking or pressing `Enter`/`Space` on the badge opens a **popover menu** listing all remaining groups.
- **Selecting a host group** (either the inline name or an item from the popover) highlights it with a gradient and **broadcasts the host group ID** to any connected widgets that listen for host group broadcasts. Clicking the same group again deselects it and clears the broadcast.
- When a group from the popover is selected it is **promoted to the front** of the cell so it remains visible without reopening the popover on subsequent interactions.
- Only **one host group can be selected at a time** — multi-select is not supported for host group grouping.

> 💡 Host group selection integrates with the same [Dashboard Communication Framework](#-dashboard-communication-framework) as host and item cell clicks. Connect a map widget, another table, or any host-group-aware widget to react to the selection in real time.

<img src="screenshots/ColumnPerPattern.png" width="600" />

</details>

---

<details>
<summary><strong>🔗 Dashboard Communication Framework</strong></summary>

<br>

This widget is fully integrated with the [Zabbix dynamic parameters framework](https://www.zabbix.com/documentation/current/en/manual/web_interface/frontend_sections/dashboards/widgets#dynamic-parameters).

**Receives broadcasts of:**
- Host groups
- Hosts
- Items
- Time period

**Broadcasts on cell click:**
- Host IDs
- Item IDs
- Host Group IDs *(when grouping by Host groups)*
- Time period (see [Time Period Broadcasting](#-time-period-broadcasting) below)
- Multi-cell selection via `Ctrl+Click` or `Shift+Click` (item cells only) → broadcasts all selected item IDs and a merged time period

> 💡 Broadcasting multiple items is only compatible with other [RME Widgets](https://www.github.com/gryan337).

**Additional controls:**
- **`Auto select first cell`** — Will find the first clickable host and item cells and automatically select them when the widget loads. Otherwise, nothing is selected.
- **`Reset row`** *(deprecated)* — broadcasts `hostid: 000000` to reset all hosts broadcasted to linked widgets.
  - You can click an already-selected host cell again to achieve the same result.
- **`Show item grouping only`** — transforms this widget into a pure dashboard filter. Selecting a grouping broadcasts to all connected [RME Widgets](https://www.github.com/gryan337), including other table widgets.

</details>

---

<details>
<summary><strong>🕐 Time Period Broadcasting</strong></summary>

<br>

Each column can be configured with its own **time period**, enabling powerful cross-widget time range control. When a cell is clicked, the widget broadcasts both the item data and the corresponding column's time period to any listening widgets (e.g. a graph panel).

This is particularly useful for **week-over-week or period comparison layouts** — for example, configuring three columns for "this week", "last week", and "two weeks ago" so that clicking any cell also updates the receiving graph's time axis to match the selected period.

#### Time Period Sources

Each column's time period can come from one of three sources, configured independently per column:

| Source | Description |
|--------|-------------|
| **Custom** | An explicit fixed range set directly in the column configuration (e.g. `2026-01-09` to `2026-01-16`, or relative values like `now-3M` to `now`). |
| **Dashboard time picker** | The column uses `_reference: DASHBOARD._timeperiod`. The broadcasted time period will always reflect the dashboard's current time picker selection. |
| **Another widget** | The column uses `_reference: <WIDGET_ID>._timeperiod`. The broadcasted time period is sourced from whatever that widget is currently broadcasting. |

#### Multi-Cell Selection & Time Period Merging

When multiple cells are selected using `Ctrl+Click` or `Shift+Click` across columns with different time periods, the widget **automatically merges** the time periods by taking the **earliest `from`** and **latest `to`** across all selected columns. The receiving graph will then display a time axis spanning the full combined range.

- Columns using `_reference`-based time periods are resolved at the time of the click and included in the merge.
- Columns whose time period has not yet been received (e.g. a foreign widget that hasn't broadcast yet) are excluded from the merge gracefully.

#### Grouped / Action Column Behavior

When **`Broadcast from grouped column`** is enabled for a column, that column's data-menu information is encoded into the grouped/action column at the start of each row. Clicking the grouped column broadcasts the item without overriding the time period — the receiving widget retains its existing or dashboard time period. This is intentional: the grouped column represents the row's identity, not a specific time window.

To broadcast a specific time period, click a **column cell** rather than the grouped column.

#### Dynamic Time Period Updates

If a receiving table widget has one or more columns referencing a foreign widget's time period, and that foreign widget broadcasts a new time period (e.g. because a user clicked a different cell in the source table), the receiving table will automatically re-broadcast its currently selected cell's time period using the updated time period — without requiring the user to re-click.

#### Example: Period Comparison Layout

1. Create a table widget with three columns, all querying the same item pattern (e.g. `net.if.in[*]`).
2. Set each column's time period to a different week:
   - Column 0: `2026-01-09 00:00:00` → `2026-01-16 00:00:00`
   - Column 1: `2026-01-16 00:00:00` → `2026-01-23 00:00:00`
   - Column 2: `2026-01-23 00:00:00` → `2026-02-06 00:00:00`
3. Link a graph panel to receive `_timeperiod` and `_itemid` broadcasts from the table.
4. Clicking any cell broadcasts that row's item IDs and the column's specific time window to the graph.
5. `Shift+Click` or `Ctrl+Click` across multiple columns broadcasts the merged time range, with each series colored per its column configuration.

</details>

---

<details>
<summary><strong>🗂️ Column Management</strong></summary>

<br>

Each item pattern is fully customizable from the configuration form and interactively within the dashboard.

**Configuration:**

<img src="screenshots/ColumnConfigForm.png" width="525" />

**Interactive column controls (right-click any column header):**
- **Freeze column** — pin it to the left while scrolling horizontally
- **Hide column** — remove it from view without deleting its configuration
- **Column visibility menu** — also accessible via the ⋮ actions menu in the top-right of the widget panel

<img src="screenshots/Column-header-menu.png" width="300" />
<img src="screenshots/Column-visibility-popup.png" width="300" />

> 💡 Hidden columns retain their column filters, but those filters are suspended until the column is unhidden.

</details>

---

<details>
<summary><strong>🔍 Column Filtering</strong></summary>

<br>

Every column supports independent filtering. A **green funnel icon** indicates an active filter on that column.

<img src="screenshots/ColumnFiltering1.png" width="500" />

<img src="screenshots/ColumnFiltering2.png" width="500" />

**Available filter types:**

| Type | Description |
|------|-------------|
| Contains / Not Contains | Substring match |
| Equals | Exact match |
| Starts With / Ends With | Prefix/suffix match |
| Wildcard | `*` patterns |
| Regex | Full regular expression |
| Boolean Expression | Logical combinations |
| Numeric filters | `=`, `>`, `<`, `>=`, `<=`, range — for fully numeric columns, regex |

**Column filter popup controls:**

| Control | Behavior |
|---------|----------|
| Filter type dropdown | Sets the filter mode (Contains, Regex, Numeric, etc.) |
| Search input box | Reduces the value list as you type based on the selected filter type. No checkbox required — matching values are applied on `Apply` |
| `Clear` (top right) | Clears the search input and unchecks all checkboxes in one click |
| Checkbox list | Select specific values to filter. **Checkbox selections take precedence over search input text** |
| Select/Uncheck all | Toggles all currently visible checkboxes at once |
| `Apply` | Applies the combined state of the filter type, search input, and any checked boxes |
| `Cancel` | Discards all changes and restores the previous filter state |
| `Clear filters` | Visible when a search string or checkbox is active. Removes all filters; if a filter is currently active in the table, also clears it after clicking `Apply` |
| ⚠️ warning icon | Appears when both a search string and checkboxes are active — reminds you that checkboxes take precedence |

> 💡 Multiple column filters can be active simultaneously. Since filtering is entirely client-side (no server round-trips), it's worth setting **`Item ordering`** and **`Host ordering`** generously so all data is available in the browser for filtering.

> ⚠️ While a column filter popup is open, the widget will not refresh until the popup is closed.

</details>

---

<details>
<summary><strong>📊 Aggregations & Footer Row</strong></summary>

<br>

- **`Show footer row`** displays **Sum** or **Average** across all visible rows.
  - When column filters are applied, the footer recalculates based on visible rows only.
  - When there are multiple table pages, the footer is visible on all pages and reflects the **Sum** or **Average** across all pages.
- Per-column aggregation overrides: **Sum**, **Avg**, **Min**, **Max** — applied per table cell.
- **`Aggregate all hosts`** — merges values across multiple hosts/items into a single cell, enabling arbitrary aggregations of metric values.
  - All item IDs are encoded in these cells. Clicking one broadcasts all item IDs to connected widgets.

</details>

---

<details>
<summary><strong>📈 Bar Gauge enhancements</strong></summary>

<br>

Rows or columns can display inline **bar gauges** showing proportional values. A few configuration settings control how proportions are displayed:

- **`Bar gauge layout`** — proportions can be calculated within each **`Column`** or by **`Row`**.
- **`Bar gauge tooltip`** — when hovering over a bar gauge, shows the percentage relative to other bar gauges in the same column or row (based on **`Bar gauge layout`**). The percentage can be relative to the maximum value or the sum of values in the **`Column`** or **`Row`**.

<img src="screenshots/Bar-gauge-proportions.png" width="300" />

</details>

---

<details>
<summary><strong>🔗 URL Display Mode</strong></summary>

<br>

Columns can be configured to render item values as **clickable URLs**.

In the column form, set **`Display as`** to **`URL`**, then configure **`URL display mode`**:

- **`As-is`** — use this when the metric value itself is already a properly formatted URL.
- **`Custom`** — allows you to transform or fully customize the URL.
  - To keep the metric value as the URL but override the display text, enter a value in **`URL display override`**.
  - To build a fully custom URL, use the **`URL customization`** field — supports built-in and user macros.

</details>

---

<details>
<summary><strong>🧰 Additional Features</strong></summary>

<br>

| Feature | Details |
|---------|---------|
| **Sortable columns** | Correctly handles all custom Zabbix units: `B`, `bps`, `s`, `unixtime`, etc. Sorting tens of thousands of table rows takes sub-100ms to complete allowing for enterprise level performance |
| **Pagination** | Automatic after 75 rows |
| **Customize item names** | Available in all layouts except **`Column per pattern`**. Supports Zabbix built-in macros. Set via the **`Metric label`** field |
| **Select host from storage** | Allows a host selected on one dashboard tab to be automatically selected on another dashboard tab when cycling through dashboard tabs - MUST be set in each RME Table that you want to participate |
| **Value map display** | Configure how mapped values are rendered per column/item pattern |
| **Add link for history** | Allows for one-click navigation to the historical values - this is the same page that `Latest data` takes you to when clicking `History` on the far right |
| **CSV export** | Download table data from the actions menu |
| **Lazy loading** | Configurable toggle to show/hide data on demand. Found in the main configuration form under **`Advanced configuration`** → **`Display on click`** |
| **Background gradiency** | Improve contrast and visual aesthetics by enabling gradient color backgrounds |
| **Font color** | Adds ability to choose font color |
| **Topitems** | Maintains all capabilities from the [Topitems](https://www.zabbix.com/documentation/current/en/manual/web_interface/frontend_sections/dashboards/widgets/top_items) widget, including custom sort ordering when the widget first loads, bar gauges, sparkline graphs, dynamic cell shading based on thresholds, automatic cell highlight when a trigger/alert is active

</details>

---

## ⚙️ Configuration

<details>
<summary><strong>Main Configuration Form</strong></summary>

<br>

<img src="screenshots/MainConfigForm.png" width="525" />

<!--
TODO: Describe key fields here — host groups, item patterns, layout selection, etc.
-->

</details>

<details>
<summary><strong>Column Configuration Form</strong></summary>

<br>

<img src="screenshots/ColumnConfigForm.png" width="525" />

<!--
TODO: Describe column-level options — display type, aggregation, header macros, URL mode, etc.
-->

</details>

---

## 🗺️ Roadmap

<details>
<summary><strong>View roadmap</strong></summary>

<br>

### ✅ Q1 2026
- [x] Draggable columns in configuration UI
- [x] Style improvements
- [x] Performance improvements — scales to 10,000s of rows / 100,000s of cells with minimal lag
- [x] Migrated client-side logic to PHP backend

### 🔜 Q2 2026
- [x] Broadcast time period to listening widgets
- [x] Add ability to group by Host tags and Host groups
- [x] Remove `{HOST.HOST}` in favor of an explicit option to group by `Hostname`
- [ ] Add more aggregation functions for `Column patterns aggregation` and for the `Show footer row`
- [ ] Crowd-sourced feature requests & community feedback (continuous)

### 📋 Q3 2026
- [ ] Add a right-click context menu to metric/item cells (copy to clipboard, item configuration context menu, click to item history (would replace history link), expand value on click for truncated string values)
- [ ] Crowd-sourced feature requests & community feedback (continuous)

</details>

---

## 🐛 Known Issues

- Ongoing code cleanup (reducing JS reliance; moving logic to PHP + CSS)
- Documentation expansion in progress

---

## ✍️ Background

Zabbix is a powerful monitoring platform, but end-users have historically struggled with its UI.  
This widget was originally cloned from the native [TopItems](https://www.zabbix.com/documentation/current/en/manual/web_interface/frontend_sections/dashboards/widgets/top_items) widget and significantly expanded to provide a flexible, production-grade table experience — and to push Zabbix's dashboard capabilities forward.

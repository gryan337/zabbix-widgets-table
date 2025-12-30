# 📊 Zabbix Custom Widget – Table
A **powerful table widget** with advanced functionality for the Zabbix dashboard.

---

## 🌐 Overview

<img src="screenshots/TableDemo.png" width="750" height="450" />

---

## ⚙️ Main Configuration Form

<img src="screenshots/MainConfigForm.png" width="525" height="450" />

---

## 🧩 Column Configuration Form

<img src="screenshots/ColumnConfigForm.png" width="525" height="450" />

---

## 🔍 Column Filtering

<img src="screenshots/ColumnFiltering.png" width="500" height="500" />

---

## 📦 Required Zabbix Version

- The branches of this widget correspond to the matching Zabbix branch.  
  Please clone the branch version that matches your Zabbix version.
- **Available Versions**:
  - `7.0`
  - `7.2`
  - `7.4`

---

## 🎯 Purpose

Zabbix is an incredibly powerful monitoring and observability platform, but one of the biggest complaints has historically been the **usability of the UI and visualizations**.  

While the UI works well for developers and administrators of Zabbix, end-users often struggle to extract meaningful metrics/data.  

- **Zabbix 6.4** introduced custom modules and widgets.  
- **Zabbix 7.0** added the communication framework for dashboards — a crucial step forward.  

This widget was created to **fill the gap** in a flexible, powerful table visualizations for dashboards.  

Even though Zabbix introduced [TopItems](https://www.zabbix.com/documentation/current/en/manual/web_interface/frontend_sections/dashboards/widgets/top_items), many features were missing to create a truly powerful experience. That’s why this widget exists.

---

## ✍️ Author’s Notes

- Originally cloned from the [TopItems](https://www.zabbix.com/documentation/current/en/manual/web_interface/frontend_sections/dashboards/widgets/top_items) widget for its solid foundation.
- Significantly expanded with **new features** and enhancements.
- Goal:  
  - Provide immediate community value.  
  - Spark interest in further improvement.  
  - Possibly gain Zabbix’s attention to integrate this natively.

---

## 🔮 Future Development

- Continue polishing bugs and usability issues.  
- Ongoing code cleanup (move logic from JavaScript → PHP + CSS).  
- Continue addressing community feedback in order to make this the premier Zabbix dashboard table visualization.  

---

## ⚡ Functionality & Features

✔️ **Layouts**
- **3 Column**: True Top/Bottom N table (metric name | host | value).  
- **Column per pattern**: One column per configured item pattern. Supports grouping by item tags as well as `{HOST.HOST}` to enable a similar layout to `Horizontal`.
  - As such, it is very important now to make heavy use of tags on your items to make maximum use of this widget and truly unlock its power.

✔️ **Communication Framework**
- Receives **broadcasted inputs** of host groups, hosts, and as of recently, items.  
- Broadcasts hosts and items by clicking cells.  
- Fully leverages Zabbix [dynamic parameters framework](https://www.zabbix.com/documentation/current/en/manual/web_interface/frontend_sections/dashboards/widgets#dynamic-parameters).

✔️ **Advanced Table Behavior**
- Sortable columns (correctly handles `B`, `bps`, `s`, `unixtime`, etc.).  
- Automatic pagination after **75 rows**.  
- Column filtering (All columns):  
  - Filter types: Contains, Equals, Starts/Ends with, Wildcard, Not Contains, Regex, Boolean Expression and a variety of numeric filters for fully numeric columns.  
  - Green funnel = filter active in that column.  
- Footer row with **Sum** or **Average**.  
- Column-specific overrides for aggregations (Sum, Avg, Min, Max) that is applied per table cell.
- Ability to customize how value mapped values are displayed.  
- Column display option of URL added to allow for items represented as URLs to be clickable.  
- URL display option allows for complete customization of the display text and generating fully customized URLs.  
- Customizable item headers using Zabbix built-in macros.  
- Configuration control of autoselection of first host and item table cell for instant broadcasting.  
- Reset row capability (broadcasts hostid `000000` to reset linked widgets).  
- Support for **aggregations across multiple hosts/items** within a single table cell using **Aggregate all hosts** option.
- Multi-cell selection (`Ctrl` + click, `Shift` + click) → broadcasts all selected itemids.  
- **Groupings-only mode**: Use the widget as a **dashboard filter** for other tables by checkboxing `Show groupings only`.  
- **Bar gauge proportions** in rows/columns as well as tooltip showing ratio to other table cells as a percentage.  
- **Aggregate by item groupings** (not just hosts) for high-level overviews → drill down by connecting a table panel to another table panel.  
- Ability to download table data into CSV format from the actions context menu in the widget header.  
- Configurable and toggleable lazy loading button to allow users to hide and show data dynamically.

---

## 🛠️ Installation

1. Clone this repo into `ui/modules/`  
   _(default: `/usr/share/zabbix/ui/modules/` for RPM installations)_  
2. Navigate: **Zabbix → Administration → General → Modules**  
3. Click **Scan directory**  
4. Enable **Table widget**

---

## 🐞 Known Issues / Polish List

- Code cleanup (reduce JS reliance, move logic to PHP + CSS).  
- Expand documentation.  

---

# 🚀 Project Roadmap

High-level milestones and upcoming goals.

---

## 📍 Q1 2026

- [ ] Draggable columns in configuration UI.  
- [ ] Style improvements.  
- [ ] Performance improvements allowing to scale the table to 10's of thousands of rows with 100s of thousands of table cells with minimal lag.  
- [ ] Performance improvements: migrate client-side code to PHP backend.

---

## 🛠️ Upcoming (Q1 2026)

| Milestone | Status | Target |
|-----------|--------|--------|
| Crowd-sourced feature requests & feedback | 🔜 Upcoming | Continuous |

---

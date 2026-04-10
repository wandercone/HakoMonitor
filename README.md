# HakoMonitor

An Unraid plugin that monitors [Hako Foundry](https://github.com/HakoForge/HakoFoundry). Displays fan wall RPM and PWM readings alongside per-shunt and total power wattage from connected HakoForge Power Boards.

## Features

- Real-time fan wall status: profile, mode (Auto / Manual / Override), PWM %, and RPM
- Per-board power readings: shunt wattage, section totals, and grand total
- Main dashboard tile showing fan wall RPM and power board totals at a glance
- Configurable poll interval and API key authentication
- Live connection status indicator

## Requirements

- Unraid 7.1.0 or later
- [Hako Foundry](https://github.com/HakoForge/HakoFoundry)
- **NOTE: This plugin requires a version of Hako Foundry not yet available for public use.**

## Installation

**Via Community Applications (recommended)**

Search for **HakoMonitor** in the Apps tab and install from there.

**Via Plugin URL**

1. In the Unraid UI go to **Plugins > Install Plugin**
2. Paste the `.plg` URL from the [latest release](https://raw.githubusercontent.com/wandercone/HakoMonitor/main/plugin/hakomonitor.plg)
3. Click **Install**

After installation the plugin is available at **Settings > HakoMonitor**.

## Usage

1. Open **Settings > HakoMonitor**
2. Set the **API Host** to the address of your Hako Foundry instance
3. Enter your **API Key** 
4. Adjust the **Poll Interval** as needed and click **Save Settings**

Fan and power data will begin updating automatically.

## Configuration

| Setting | Default | Description |
|---|---|---|
| API Host | `http://127.0.0.1:8080` | URL of the Hako Foundry instance |
| API Key | _(empty)_ | `X-API-Key` value |
| Poll Interval | `5` | How often (in seconds) to refresh data [2-60] |
| Dashboard Tile | `enable` | Show the HakoMonitor tile on the Main dashboard (`enable` / `disable`) |

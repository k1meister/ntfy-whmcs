# WHMCS ntfy Notification Module

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Send real-time WHMCS event notifications directly to your [ntfy.sh](https://ntfy.sh/) topic (or your self-hosted ntfy instance).

This module integrates with the WHMCS Notifications system, allowing you to configure rules that trigger push notifications via ntfy for events like new tickets, orders, paid invoices, client replies, and more.

## Features

*   Seamless integration with the WHMCS Notification Provider system.
*   Configure connection to any ntfy server (public ntfy.sh or self-hosted).
*   Specify the target ntfy topic for notifications.
*   Set default ntfy message priority (Min, Low, Default, High, Max).
*   Add default ntfy tags to messages.
*   Supports ntfy authentication methods:
    *   No Authentication
    *   Access Token (Bearer Token)
    *   Username/Password (Basic Auth)
*   "Test Connection" feature in WHMCS admin to verify settings.
*   Uses standard WHMCS module logging for troubleshooting.

## Requirements

*   WHMCS version 8.x or later (tested lightly, may work on older versions supporting the Notification Provider interface).
*   Access to your WHMCS installation directory (specifically `/modules/notifications/`).
*   An ntfy server (e.g., `https://ntfy.sh` or your own instance) and a topic name.
*   PHP `curl` extension enabled on your WHMCS server.

## Installation

1.  **Extract/Upload:** Extract the downloaded archive. You should have a directory named `ntfy`. Upload this entire `ntfy` directory to your WHMCS installation under the `/modules/notifications/` directory.
    *   The final path should look like: `<your_whmcs_root>/modules/notifications/ntfy/`
    *   Inside this `ntfy` directory, you should see `ntfy.php`, `logo.png`, and the `lang/` subdirectory.
2.  **Activate:**
    *   Log in to your WHMCS Admin Area.
    *   Navigate to **Setup > System Settings > Notifications** (or **Configuration () > System Settings > Notifications** in newer WHMCS themes).
    *   Click the **Configure** button next to the list of "Notification Providers".
    *   Find **"NW ntfy Notifications"** in the list.
    *   Click the **Activate** button next to it.

## Configuration

1.  **Module Settings:**
    *   After activating, stay on the **Setup > System Settings > Notifications** page.
    *   You will now see "NW ntfy Notifications" listed as an active provider.
    *   Click the **Configure** button *for the NW ntfy Notifications provider itself*.
    *   Fill in the required details:
        *   **ntfy Server URL:** The base URL of your ntfy server (e.g., `https://ntfy.sh` or `https://ntfy.yourdomain.com`). **Do not include the topic here.**
        *   **ntfy Topic Name:** The specific topic you want to publish notifications to (e.g., `whmcs_critical_alerts`).
        *   **Default Message Priority:** Choose the default priority for messages sent via this provider.
        *   **Authentication Method:** Select 'None', 'Access Token', or 'Username/Password' based on your ntfy server/topic setup.
        *   **(Conditional) Access Token / Username / Password:** Fill these in if you selected an authentication method other than 'None'.
        *   **Default Tags:** Optionally add comma-separated tags (e.g., `whmcs,production,billing`).
    *   Click **Save Changes**.
2.  **Test Connection:**
    *   After saving, click the **Test Connection** button.
    *   You should receive a test notification on any client subscribed to your configured ntfy topic.
    *   If it fails, check your settings and consult the WHMCS Module Log (**Utilities > Logs > Module Log**) for detailed error messages from the ntfy module.

## Usage

1.  Go to **Setup > System Settings > Notifications**.
2.  Click **Create New Notification Rule**.
3.  Configure the Rule Name, Event, and Conditions as desired.
4.  Under **Notification Provider Settings**:
    *   Select **"NW ntfy Notifications"** from the "Choose Provider" dropdown.
    *   Configure the **Title** and **Message** using available WHMCS merge fields. The module will send these directly to ntfy.
    *   *(Note: This module currently does not support overriding priority or tags on a per-rule basis; it uses the defaults set in the main module configuration).*
5.  Click **Create**.

Now, whenever the conditions for your rule are met, WHMCS will trigger this module to send a notification to your configured ntfy topic.

## Disclaimer

**This software is provided "AS IS", without warranty of any kind, express or implied, including but not limited to the warranties of merchantability, fitness for a particular purpose, and noninfringement.**

This is a free and open-source module provided under the terms of the MIT License. **It is NOT intended for resale as a standalone licensed product or service.** You are free to use, modify, and distribute it according to the license terms, including incorporating it into services you offer, but you may not repackage and sell licenses specifically for this module itself.

In no event shall the authors or copyright holders be liable for any claim, damages, or other liability, whether in an action of contract, tort, or otherwise, arising from, out of, or in connection with the software or the use or other dealings in the software.

Support is provided on a best-effort basis through GitHub Issues and is not guaranteed. Use at your own risk.
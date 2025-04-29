<?php
/**
 * WHMCS ntfy Notification Module Language File - English
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Module Configuration Settings
$_LANG['ntfy_config_title'] = 'ntfy Notification Settings'; // Not directly used by module structure, but good practice

$_LANG['ntfyServerUrl_FriendlyName'] = 'ntfy Server URL';
$_LANG['ntfyServerUrl_Description'] = 'The base URL of your ntfy server (e.g., https://ntfy.sh or https://ntfy.yourdomain.com). Do NOT include the topic name here.';

$_LANG['ntfyTopic_FriendlyName'] = 'ntfy Topic Name';
$_LANG['ntfyTopic_Description'] = 'The ntfy topic to publish notifications to (e.g., whmcs_alerts).';

$_LANG['ntfyPriority_FriendlyName'] = 'Default Message Priority';
$_LANG['ntfyPriority_Description'] = 'Default priority for notifications (1-min, 2-low, 3-default, 4-high, 5-max). Can be overridden per rule if the rule allows.';
$_LANG['ntfyPriority_Options'] = '3,Default|1,Min|2,Low|4,High|5,Max'; // Format: Value,Display|Value,Display

$_LANG['ntfyAuthMethod_FriendlyName'] = 'Authentication Method';
$_LANG['ntfyAuthMethod_Description'] = 'Select the authentication method required by your ntfy topic/server.';
$_LANG['ntfyAuthMethod_Options'] = 'None,None|Token,Access Token|Basic,Username/Password'; // Format: Value,Display

$_LANG['ntfyAuthToken_FriendlyName'] = 'Access Token';
$_LANG['ntfyAuthToken_Description'] = 'Your ntfy access token (if using Token authentication).';

$_LANG['ntfyUsername_FriendlyName'] = 'Username';
$_LANG['ntfyUsername_Description'] = 'Username for Basic Authentication (if used).';

$_LANG['ntfyPassword_FriendlyName'] = 'Password';
$_LANG['ntfyPassword_Description'] = 'Password for Basic Authentication (if used).';

$_LANG['ntfyDefaultTags_FriendlyName'] = 'Default Tags';
$_LANG['ntfyDefaultTags_Description'] = 'Optional: Comma-separated default tags to add to notifications (e.g., whmcs,billing).';

// Error messages
$_LANG['ntfy_test_connection_error'] = 'Failed to send test notification. Please check your settings and ntfy server status. Error: ';
$_LANG['ntfy_curl_error'] = 'Curl Error: ';
$_LANG['ntfy_http_error'] = 'ntfy Server HTTP Error: Status ';
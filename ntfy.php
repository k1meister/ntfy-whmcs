<?php

// Define the namespace EXACTLY as WHMCS seems to expect based on the directory structure
namespace WHMCS\Module\Notification\ntfy; // Lowercase 'ntfy' matching the directory

// Import necessary WHMCS classes and interfaces using their correct namespaces from the sample
use WHMCS\Module\Contracts\NotificationModuleInterface;
use WHMCS\Module\Notification\DescriptionTrait;
use WHMCS\Notification\Contracts\NotificationInterface;

// Make sure the file cannot be accessed directly
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * NW ntfy Notification Module for WHMCS
 *
 * Class name and namespace adjusted to match WHMCS autoloader expectations.
 */
// Class name changed to lowercase 'ntfy' to match the expected class name from the error
class ntfy implements NotificationModuleInterface
{
    // Use the trait provided by WHMCS for handling Display Name and Logo
    use DescriptionTrait;

    /**
     * Module constructor.
     * Set the display name and logo file using the DescriptionTrait.
     */
    public function __construct()
    {
        // DisplayName can still be user-friendly CamelCase
        $this->setDisplayName('NW ntfy Notifications')
             ->setLogoFileName('logo.png');
    }

    /**
     * Defines the configuration fields required for the module. (Corresponds to settings())
     *
     * @return array Configuration fields array.
     */
    public function settings(): array
    {
        global $_LANG;
        $lang = $_LANG ?? [];

        return [
            'ntfyServerUrl' => [
                'FriendlyName' => $lang['ntfyServerUrl_FriendlyName'] ?? 'ntfy Server URL',
                'Type' => 'text',
                'Description' => $lang['ntfyServerUrl_Description'] ?? 'Base URL (e.g., https://ntfy.sh). No topic.',
                'Required' => true,
            ],
            'ntfyTopic' => [
                'FriendlyName' => $lang['ntfyTopic_FriendlyName'] ?? 'ntfy Topic Name',
                'Type' => 'text',
                'Description' => $lang['ntfyTopic_Description'] ?? 'Topic name (e.g., whmcs_alerts).',
                'Required' => true,
            ],
            'ntfyPriority' => [
                'FriendlyName' => $lang['ntfyPriority_FriendlyName'] ?? 'Default Priority',
                'Type' => 'dropdown',
                'Options' => $lang['ntfyPriority_Options'] ?? '3,Default|1,Min|2,Low|4,High|5,Max',
                'Default' => '3',
                'Description' => $lang['ntfyPriority_Description'] ?? 'Default priority (1-min to 5-max).',
            ],
            'ntfyAuthMethod' => [
                'FriendlyName' => $lang['ntfyAuthMethod_FriendlyName'] ?? 'Authentication',
                'Type' => 'dropdown',
                'Options' => $lang['ntfyAuthMethod_Options'] ?? 'None,None|Token,Access Token|Basic,Username/Password',
                'Default' => 'None',
                'Description' => $lang['ntfyAuthMethod_Description'] ?? 'Authentication method.',
            ],
            'ntfyAuthToken' => [
                'FriendlyName' => $lang['ntfyAuthToken_FriendlyName'] ?? 'Access Token',
                'Type' => 'password',
                'Description' => $lang['ntfyAuthToken_Description'] ?? 'Token (if using Token auth).',
            ],
            'ntfyUsername' => [
                'FriendlyName' => $lang['ntfyUsername_FriendlyName'] ?? 'Username',
                'Type' => 'text',
                'Description' => $lang['ntfyUsername_Description'] ?? 'Username (if using Basic auth).',
            ],
            'ntfyPassword' => [
                'FriendlyName' => $lang['ntfyPassword_FriendlyName'] ?? 'Password',
                'Type' => 'password',
                'Description' => $lang['ntfyPassword_Description'] ?? 'Password (if using Basic auth).',
            ],
            'ntfyDefaultTags' => [
                'FriendlyName' => $lang['ntfyDefaultTags_FriendlyName'] ?? 'Default Tags',
                'Type' => 'text',
                'Description' => $lang['ntfyDefaultTags_Description'] ?? 'Optional comma-separated tags.',
            ],
        ];
    }

    /**
     * Validate settings and test the connection. (Corresponds to testConnection())
     *
     * @param array $settings Current module configuration settings.
     * @throws \Exception On connection failure or validation error.
     */
    public function testConnection($settings): void
    {
        global $_LANG;
        $lang = $_LANG ?? [];
        $testTitle = "WHMCS Test Connection";
        $testMessage = "This is a test notification from your WHMCS ntfy module configuration.";
        $testPriority = $settings['ntfyPriority'] ?? '3';
        $testTags = $settings['ntfyDefaultTags'] ?? '';
        $testUrl = '';

        $result = $this->sendToNtfy(
            $settings,
            $testTitle,
            $testMessage,
            (string) $testPriority,
            (string) $testTags,
            $testUrl
        );

        if (!$result['success']) {
            $errorMessage = ($lang['ntfy_test_connection_error'] ?? 'Failed to send test notification. Error: ') . $result['error'];
            throw new \Exception($errorMessage);
        }
    }

    /**
     * Defines settings specific to an individual notification rule. (Corresponds to notificationSettings())
     *
     * @return array An empty array as no rule-specific settings are used.
     */
    public function notificationSettings(): array
    {
        return [];
    }

    /**
     * Provides options for 'dynamic' fields defined in notificationSettings(). (Corresponds to getDynamicField())
     *
     * @param string $fieldName Notification setting field name.
     * @param array $settings Settings for the module.
     * @return array Empty array as no dynamic fields are used.
     */
    public function getDynamicField($fieldName, $settings): array
    {
        return [];
    }

    /**
     * Delivers the notification. (Corresponds to sendNotification())
     *
     * @param NotificationInterface $notification The notification object.
     * @param array $moduleSettings The global module configuration.
     * @param array $notificationSettings Configured settings from the rule (empty here).
     *
     * @throws \Exception On failure to send the notification.
     */
    public function sendNotification(NotificationInterface $notification, $moduleSettings, $notificationSettings): void
    {
        $title = $notification->getTitle();
        $message = $notification->getMessage();
        $url = $notification->getUrl();
        $priority = $moduleSettings['ntfyPriority'] ?? '3';
        if (empty($priority)) { $priority = '3'; }
        $tags = $moduleSettings['ntfyDefaultTags'] ?? '';

        $result = $this->sendToNtfy(
            $moduleSettings,
            $title,
            $message,
            (string) $priority,
            (string) $tags,
            $url
        );

        if (!$result['success']) {
            $logMessage = "Failed to send ntfy notification '{$title}'. Error: {$result['error']}";
            $this->logActivity($logMessage);
            // Throw exception to signal failure to WHMCS
            throw new \Exception($logMessage);
        } else {
            $this->logActivity("Successfully sent ntfy notification: '{$title}'");
        }
    }


    // --- Helper Functions (Internal logic) ---

    /**
     * Helper: Performs the HTTP POST request to the ntfy server.
     *
     * @param array $settings Module configuration.
     * @param string $title Notification title.
     * @param string $message Notification message body.
     * @param string $priority Notification priority ('1'-'5').
     * @param string $tags Comma-separated tags string.
     * @param string|null $clickUrl Optional URL for 'Click' action.
     *
     * @return array ['success' => bool, 'error' => ?string].
     */
    protected function sendToNtfy(array $settings, string $title, string $message, string $priority, string $tags, ?string $clickUrl): array
    {
        global $_LANG;
        $lang = $_LANG ?? [];

        $serverUrl = rtrim($settings['ntfyServerUrl'] ?? '', '/');
        $topic = $settings['ntfyTopic'] ?? '';

        if (empty($serverUrl) || empty($topic)) {
            return ['success' => false, 'error' => ($lang['ntfy_config_error'] ?? 'ntfy Server URL or Topic is not configured.')];
        }

        $fullUrl = $serverUrl . '/' . $topic;
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $headers = [
            'User-Agent: WHMCS NW ntfy Module v1.0',
            'Title: ' . $this->encodeHeaderString($title),
        ];

        if ($priority !== '3' && $priority !== '') $headers[] = 'Priority: ' . $priority;
        if (!empty($tags)) $headers[] = 'Tags: ' . $this->encodeHeaderString($tags);
        if (!empty($clickUrl)) $headers[] = 'Click: ' . $clickUrl;

        $authMethod = $settings['ntfyAuthMethod'] ?? 'None';
        if ($authMethod === 'Token') {
            $token = $settings['ntfyAuthToken'] ?? '';
            if (!empty($token)) $headers[] = 'Authorization: Bearer ' . $token;
        } elseif ($authMethod === 'Basic') {
            $username = $settings['ntfyUsername'] ?? '';
            $password = $settings['ntfyPassword'] ?? '';
            if (!empty($username)) $headers[] = 'Authorization: Basic ' . base64_encode($username . ':' . $password);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrorNum = curl_errno($ch);
        $curlErrorMsg = curl_error($ch);
        curl_close($ch);

        if ($curlErrorNum !== CURLE_OK) {
            $errorMsg = ($lang['ntfy_curl_error'] ?? 'Curl Error: ') . $curlErrorNum . ' - ' . $curlErrorMsg;
            return ['success' => false, 'error' => $errorMsg];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
             return ['success' => true, 'error' => null];
        } else {
            $errorMsg = ($lang['ntfy_http_error'] ?? 'ntfy Server HTTP Error: Status ') . $httpCode;
            $this->logActivity("ntfy HTTP Error {$httpCode} for URL {$fullUrl}. Response: " . substr((string)$responseBody, 0, 500));
            return ['success' => false, 'error' => $errorMsg . " (see module log)"];
        }
    }

    /**
     * Helper: Basic sanitization for HTTP header strings.
     * @param string $value Raw string.
     * @return string Sanitized string.
     */
    protected function encodeHeaderString(string $value): string
    {
        return str_replace(["\r", "\n"], '', $value);
    }

    /**
     * Helper: Logs activity using WHMCS's logModuleCall.
     * @param string $message Log message.
     * @param array $data Optional data array.
     */
    protected function logActivity(string $message, array $data = []): void
    {
        try {
            logModuleCall(
                'ntfy', // Module directory name - MUST BE LOWERCASE 'ntfy'
                debug_backtrace()[1]['function'] ?? __FUNCTION__,
                $message, $data, null,
                ['ntfyAuthToken', 'ntfyPassword'] // Mask sensitive fields
            );
        } catch (\Exception $e) {
            error_log("WHMCS ntfy Module - logActivity failed: " . $e->getMessage() . " | Original message: " . $message);
        }
    }
}
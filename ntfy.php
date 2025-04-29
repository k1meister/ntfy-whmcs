<?php

// Define the namespace matching WHMCS autoloader expectation
namespace WHMCS\Module\Notification\ntfy;

// Import necessary WHMCS classes and interfaces
use WHMCS\Module\Contracts\NotificationModuleInterface;
use WHMCS\Module\Notification\DescriptionTrait;
use WHMCS\Notification\Contracts\NotificationInterface;

// Make sure the file cannot be accessed directly
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * NW ntfy Notification Module for WHMCS (Token Authentication Only)
 *
 * Implements NotificationModuleInterface. Sends WHMCS notifications via ntfy.
 */
class ntfy implements NotificationModuleInterface
{
    use DescriptionTrait;

    /**
     * Constructor: Set display name and logo.
     */
    public function __construct()
    {
        $this->setDisplayName('NW ntfy Notifications')
             ->setLogoFileName('logo.png');
    }

    /**
     * Defines the configuration fields required for the module.
     * Removed Username/Password fields. Updated Auth Method options.
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
                'Description' => $lang['ntfyServerUrl_Description'] ?? 'HTTPS Base URL (e.g., https://ntfy.sh). No topic.',
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
            // --- Updated Auth Section ---
            'ntfyAuthMethod' => [
                'FriendlyName' => $lang['ntfyAuthMethod_FriendlyName'] ?? 'Authentication',
                'Type' => 'dropdown',
                'Options' => $lang['ntfyAuthMethod_Options'] ?? 'None,None|Token,Access Token', // Only None or Token
                'Default' => 'None',
                'Description' => $lang['ntfyAuthMethod_Description'] ?? 'Use Token if auth required.',
            ],
            'ntfyAuthToken' => [
                'FriendlyName' => $lang['ntfyAuthToken_FriendlyName'] ?? 'Access Token',
                'Type' => 'password', // Keep as password type for masking
                // Description now uses the detailed HTML from lang file
                'Description' => $lang['ntfyAuthToken_Description'] ?? 'Enter Access Token if required.',
            ],
            // Username/Password fields removed
            'ntfyDefaultTags' => [
                'FriendlyName' => $lang['ntfyDefaultTags_FriendlyName'] ?? 'Default Tags',
                'Type' => 'text',
                'Description' => $lang['ntfyDefaultTags_Description'] ?? 'Optional comma-separated tags.',
            ],
        ];
    }

    /**
     * Validate settings and test the connection.
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

        // Call the internal send helper
        $result = $this->sendToNtfy(
            $settings,
            $testTitle,
            $testMessage,
            (string) $testPriority,
            (string) $testTags,
            $testUrl
        );

        // Throw exception on failure
        if (!$result['success']) {
            $errorMessage = ($lang['ntfy_test_connection_error'] ?? 'Failed to send test notification. Error: ') . $result['error'];
            throw new \Exception($errorMessage);
        }
        // Success if no exception
    }

    /**
     * Defines settings specific to an individual notification rule (None used).
     * @return array An empty array.
     */
    public function notificationSettings(): array
    {
        return [];
    }

    /**
     * Provides options for 'dynamic' fields (None used).
     * @param string $fieldName Notification setting field name.
     * @param array $settings Settings for the module.
     * @return array Empty array.
     */
    public function getDynamicField($fieldName, $settings): array
    {
        return [];
    }

    /**
     * Delivers the notification via ntfy.
     *
     * @param NotificationInterface $notification The notification object.
     * @param array $moduleSettings The global module configuration.
     * @param array $notificationSettings Configured settings from the rule (empty here).
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

        // Call the internal send helper
        $result = $this->sendToNtfy(
            $moduleSettings,
            $title,
            $message,
            (string) $priority,
            (string) $tags,
            $url
        );

        // Handle result: Log and throw exception on failure
        if (!$result['success']) {
            $logMessage = "Failed to send ntfy notification '{$title}'. Error: {$result['error']}";
            $this->logActivity($logMessage);
            throw new \Exception($logMessage);
        } else {
            $this->logActivity("Successfully sent ntfy notification: '{$title}'");
        }
    }


    /**
     * Helper: Performs the HTTP POST request to the ntfy server using file_get_contents.
     * Rewritten based on ntfy PHP documentation example.
     *
     * @param array $settings Module configuration.
     * @param string $title Notification title.
     * @param string $message Notification message body.
     * @param string $priority Notification priority ('1'-'5').
     * @param string $tags Comma-separated tags string.
     * @param string|null $clickUrl Optional URL for 'Click' action.
     * @return array ['success' => bool, 'error' => ?string].
     */
    protected function sendToNtfy(array $settings, string $title, string $message, string $priority, string $tags, ?string $clickUrl): array
{
    global $_LANG;
    $lang = $_LANG ?? [];

    // ---> NEW DEBUGGING <---
    $this->logActivity("ntfy Debug: Settings received by sendToNtfy: " . print_r($settings, true));
    $debugAuthMethod = $settings['ntfyAuthMethod'] ?? '!! NOT FOUND !!';
    $debugAuthToken = $settings['ntfyAuthToken'] ?? '!! NOT FOUND !!';
    $this->logActivity("ntfy Debug: Value of ntfyAuthMethod from settings: '" . $debugAuthMethod . "'");
    $this->logActivity("ntfy Debug: Value of ntfyAuthToken from settings: '" . $debugAuthToken . "'");
    // ---> END NEW DEBUGGING <---

    // --- Basic Config Validation ---
    $serverUrl = rtrim($settings['ntfyServerUrl'] ?? '', '/');
        $topic = $settings['ntfyTopic'] ?? '';

        if (empty($serverUrl) || empty($topic)) {
            return ['success' => false, 'error' => ($lang['ntfy_config_error'] ?? 'ntfy Server URL or Topic is not configured.')];
        }
        // Ensure HTTPS
        if (strpos($serverUrl, 'http://') === 0) {
             $this->logActivity("Warning: ntfy Server URL uses HTTP. HTTPS is strongly recommended.");
        } elseif (strpos($serverUrl, 'https://') !== 0) {
             $serverUrl = 'https://' . $serverUrl;
             $this->logActivity("Assuming HTTPS for ntfy Server URL.");
        }
        $fullUrl = $serverUrl . '/' . $topic;

        // --- Build Header String ---
        // Use \r\n line endings for headers as per HTTP spec and examples
        $headerString = "Content-Type: text/plain\r\n"; // Set content type
        $headerString .= "Title: " . $this->encodeHeaderString($title) . "\r\n";
        if ($priority !== '3' && $priority !== '') {
            $headerString .= "Priority: " . $priority . "\r\n";
        }
        if (!empty($tags)) {
            $headerString .= "Tags: " . $this->encodeHeaderString($tags) . "\r\n";
        }
        if (!empty($clickUrl)) {
            $headerString .= "Click: " . $clickUrl . "\r\n";
        }
        // Add User-Agent
        $headerString .= "User-Agent: WHMCS NW ntfy Module v1.2\r\n"; // Increment version

        // --- Authentication ---
    $authMethod = $settings['ntfyAuthMethod'] ?? 'None'; // Get the value

    // ---> NEW: Log the raw value before comparison <---
    $this->logActivity("ntfy Debug: Raw authMethod value for comparison: '" . $authMethod . "'");

    // ---> MODIFIED: Use trim() and case-insensitive compare just in case <---
    $shouldUseTokenAuth = (strcasecmp(trim($authMethod), 'Token') == 0 || strcasecmp(trim($authMethod), 'Access Token') == 0);

    // ---> NEW: Log the comparison result <---
    $this->logActivity("ntfy Debug: Result of evaluating if Token Auth should be used: " . ($shouldUseTokenAuth ? 'TRUE' : 'FALSE'));


    if ($shouldUseTokenAuth) { // Use the boolean variable now
        $token = trim($settings['ntfyAuthToken'] ?? '');
        if (!empty($token)) {
            $headerString .= "Authorization: Bearer " . $token . "\r\n";
            // ---> Make sure this log is INSIDE the if(!empty($token)) block <---
             $this->logActivity("ntfy Debug (file_get_contents): Adding Authorization header for token.");
        } else {
            $this->logActivity("ntfy Config Error: Auth method is Token, but Access Token field is empty.");
            return ['success' => false, 'error' => ($lang['ntfy_config_error'] ?? 'Access Token required but not provided.')];
        }
    } else {
        // ---> NEW: Log if token auth block is skipped <---
         $this->logActivity("ntfy Debug: Skipping Token Authentication block because authMethod condition was false.");
    }

    // Remove trailing \r\n from the final header string
    $headerString = rtrim($headerString, "\r\n");
    $this->logActivity("ntfy Debug (file_get_contents): Compiled headers: " . str_replace("\r\n", " | ", $headerString)); // Log headers

        // --- Prepare Stream Context ---
        $contextOptions = [
            'http' => [
                'method' => 'POST',
                'header' => $headerString,
                'content' => $message,
                'ignore_errors' => true, // Crucial to get response body on errors (like 403)
                'timeout' => 15, // Set a timeout
            ],
            // Add SSL context options if needed for specific verification (usually not necessary with defaults)
            // 'ssl' => [
            //     'verify_peer' => true,
            //     'verify_peer_name' => true,
            // ]
        ];
        $context = stream_context_create($contextOptions);

        // --- Make the Request ---
        // Use error suppression (@) as file_get_contents can emit warnings on failure
        $responseBody = @file_get_contents($fullUrl, false, $context);

        // --- Process Response ---
        // $http_response_header is a magic variable populated by the request
        $statusCode = 0;
        if (isset($http_response_header) && is_array($http_response_header) && count($http_response_header) > 0) {
            // Parse status code from the first header line (e.g., "HTTP/1.1 200 OK")
            if (preg_match('{HTTP/\d\.\d\s+(\d+)\s+}', $http_response_header[0], $matches)) {
                $statusCode = (int)$matches[1];
            }
             $this->logActivity("ntfy Debug (file_get_contents): Response Headers: " . print_r($http_response_header, true));
        }

        if ($responseBody === false) {
            // Could be DNS error, connection timeout, SSL verification failure etc.
            $error = error_get_last();
            $errorMsg = ($lang['ntfy_http_error'] ?? 'ntfy Server Request Error: ') . ($error['message'] ?? 'Unknown error during file_get_contents');
             $this->logActivity("ntfy Error (file_get_contents): Request failed. Last PHP error: " . print_r($error, true));
            return ['success' => false, 'error' => $errorMsg];
        } elseif ($statusCode >= 200 && $statusCode < 300) {
             // Success
             $this->logActivity("ntfy Debug (file_get_contents): Request successful (HTTP {$statusCode}).");
             return ['success' => true, 'error' => null];
        } else {
            // HTTP error code (like 403, 401, 500)
            $errorMsg = ($lang['ntfy_http_error'] ?? 'ntfy Server HTTP Error: Status ') . $statusCode;
            $this->logActivity("ntfy HTTP Error {$statusCode} using file_get_contents for URL {$fullUrl}. Response Body: " . substr((string)$responseBody, 0, 500));
            return ['success' => false, 'error' => $errorMsg . " - Check module log for details."];
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
                'ntfy', // Module directory name
                debug_backtrace()[1]['function'] ?? __FUNCTION__,
                $message, $data, null,
                // Only mask token now
                ['ntfyAuthToken']
            );
        } catch (\Exception $e) {
            error_log("WHMCS ntfy Module - logActivity failed: " . $e->getMessage() . " | Original message: " . $message);
        }
    }
}
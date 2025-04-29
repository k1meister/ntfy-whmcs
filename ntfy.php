<?php

// Ensure this file is loaded within the WHMCS environment
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Import necessary WHMCS classes (adjust namespaces based on your WHMCS version if needed)
use WHMCS\Notification\Contracts\NotificationModuleInterface;
use WHMCS\Notification\Domain\Notification;
// Note: WHMCS might use different classes/namespaces for NotificationSetting, check docs
// use WHMCS\Notification\Domain\NotificationSetting;
use WHMCS\Config\Setting; // Used for logging function lookup (optional)
use WHMCS\Exception\Module\InvalidConfiguration;
// use WHMCS\Exception\HttpClient\HttpRequestException; // Could be used if WHMCS has its own HTTP client abstraction

/**
 * NW ntfy Notification Module for WHMCS
 *
 * Sends WHMCS notification events to a specified ntfy.sh server and topic.
 */
class NtfyNotification implements NotificationModuleInterface
{
    /**
     * Module constructor.
     * Currently no specific setup actions are needed here.
     */
    public function __construct()
    {
        // Constructor body - intentionally empty for now
    }

    /**
     * Provides metadata about the notification module.
     * DisplayName, APIVersion, Logo requirements, and configuration fields.
     *
     * @return array Metadata array describing the module.
     */
    public function getMetaData(): array
    {
        return [
            'DisplayName' => 'NW ntfy Notifications', // Updated as requested
            'APIVersion' => '1.1',
            'RequiresLogo' => true,
            'LogoFileName' => 'logo.png',
            'Configuration' => $this->getConfigurationFields(),
        ];
    }

    /**
     * Defines the configuration fields shown in the WHMCS admin area.
     * Uses language strings defined in lang/english.php for user-friendliness.
     *
     * @return array An array defining the configuration fields.
     */
    public function getConfigurationFields(): array
    {
        global $_LANG; // Make language variables available

        // Ensure LANG vars are loaded, provide fallbacks just in case
        $lang = $_LANG ?? [];

        return [
            'ntfyServerUrl' => [
                'FriendlyName' => $lang['ntfyServerUrl_FriendlyName'] ?? 'ntfy Server URL',
                'Type' => 'text',
                'Description' => $lang['ntfyServerUrl_Description'] ?? 'The base URL of your ntfy server (e.g., https://ntfy.sh). Do NOT include the topic name.',
                'Required' => true,
            ],
            'ntfyTopic' => [
                'FriendlyName' => $lang['ntfyTopic_FriendlyName'] ?? 'ntfy Topic Name',
                'Type' => 'text',
                'Description' => $lang['ntfyTopic_Description'] ?? 'The ntfy topic to publish notifications to (e.g., whmcs_alerts).',
                'Required' => true,
            ],
            'ntfyPriority' => [
                'FriendlyName' => $lang['ntfyPriority_FriendlyName'] ?? 'Default Message Priority',
                'Type' => 'dropdown',
                'Options' => $lang['ntfyPriority_Options'] ?? '3,Default|1,Min|2,Low|4,High|5,Max',
                'Default' => '3',
                'Description' => $lang['ntfyPriority_Description'] ?? 'Default priority for notifications (1-min to 5-max).',
            ],
            'ntfyAuthMethod' => [
                'FriendlyName' => $lang['ntfyAuthMethod_FriendlyName'] ?? 'Authentication Method',
                'Type' => 'dropdown',
                'Options' => $lang['ntfyAuthMethod_Options'] ?? 'None,None|Token,Access Token|Basic,Username/Password',
                'Default' => 'None',
                'Description' => $lang['ntfyAuthMethod_Description'] ?? 'Select the authentication method required by your ntfy topic/server.',
            ],
            'ntfyAuthToken' => [
                'FriendlyName' => $lang['ntfyAuthToken_FriendlyName'] ?? 'Access Token',
                'Type' => 'password', // Use password type to obscure input
                'Description' => $lang['ntfyAuthToken_Description'] ?? 'Your ntfy access token (if using Token authentication).',
            ],
            'ntfyUsername' => [
                'FriendlyName' => $lang['ntfyUsername_FriendlyName'] ?? 'Username',
                'Type' => 'text',
                'Description' => $lang['ntfyUsername_Description'] ?? 'Username for Basic Authentication (if used).',
            ],
            'ntfyPassword' => [
                'FriendlyName' => $lang['ntfyPassword_FriendlyName'] ?? 'Password',
                'Type' => 'password',
                'Description' => $lang['ntfyPassword_Description'] ?? 'Password for Basic Authentication (if used).',
            ],
            'ntfyDefaultTags' => [
                'FriendlyName' => $lang['ntfyDefaultTags_FriendlyName'] ?? 'Default Tags',
                'Type' => 'text',
                'Description' => $lang['ntfyDefaultTags_Description'] ?? 'Optional: Comma-separated default tags (e.g., whmcs,billing).',
            ],
        ];
    }

    /**
     * Defines settings specific to an individual notification rule.
     * Currently, no rule-specific settings are implemented for ntfy.
     * All configuration is managed at the module level.
     *
     * @param Notification|null $notification Contextual notification object (unused here).
     * @return array An empty array as no rule-specific settings are defined.
     */
    public function getRuleSettings(?Notification $notification = null): array
    {
        // Return an empty array to indicate no specific settings for individual rules.
        // All configuration (server, topic, auth, defaults) is global for this provider.
        return [];
    }


    /**
     * Tests the connection to the ntfy server using the provided settings.
     * Triggered by the "Test Connection" button in the WHMCS admin area.
     *
     * @param array $settings Current module configuration settings.
     * @return array Result array with 'success' (bool) or 'error' (string) key.
     */
    public function testConnection(array $settings): array
    {
        global $_LANG;
        $lang = $_LANG ?? [];
        $testTitle = "WHMCS Test Connection";
        $testMessage = "This is a test notification from your WHMCS ntfy module configuration.";
        // Use configured default priority for the test, falling back to '3'
        $testPriority = $settings['ntfyPriority'] ?? '3';
        $testTags = $settings['ntfyDefaultTags'] ?? '';
        $testUrl = ''; // No specific URL context for a test connection

        try {
            $result = $this->sendToNtfy(
                $settings,
                $testTitle,
                $testMessage,
                (string) $testPriority, // Ensure priority is string for header
                (string) $testTags,
                $testUrl
            );

            if ($result['success']) {
                // Connection test successful
                return ['success' => true];
            } else {
                // Failed to send, provide error message
                $errorMessage = ($lang['ntfy_test_connection_error'] ?? 'Failed to send test notification. Error: ') . $result['error'];
                return ['error' => $errorMessage];
            }
        } catch (\Exception $e) {
            // Catch any unexpected exceptions during the test
            $this->logActivity("ntfy Test Connection Exception: " . $e->getMessage());
            $errorMessage = ($lang['ntfy_test_connection_error'] ?? 'Failed to send test notification. Error: ') . $e->getMessage();
            return ['error' => $errorMessage];
        }
    }

    /**
     * Sends the notification based on a triggered WHMCS event rule.
     * This method orchestrates gathering details and calling the sendToNtfy helper.
     *
     * @param Notification $notification The notification object (contains title, message, URL, etc.).
     * @param array $moduleSettings The global module configuration saved by the admin.
     * @param array $ruleSettings Settings specific to the rule (empty in this implementation).
     *
     * @throws \Exception Can re-throw exceptions from sendToNtfy if needed, but logging is often preferred.
     */
    public function sendNotification(Notification $notification, array $moduleSettings, array $ruleSettings): void // Return type is void as per typical interface
    {
        // Extract core details from the notification object
        $title = $notification->getTitle();
        $message = $notification->getMessage();
        // Attempt to get a relevant URL, might be null
        $url = $notification->getUrl();

        // Determine priority: Module default setting takes precedence, fallback to '3' (ntfy default)
        // $ruleSettings is ignored here as getRuleSettings returns empty
        $priority = $moduleSettings['ntfyPriority'] ?? '3';
        if (empty($priority)) { // Ensure we don't send an empty priority header
             $priority = '3';
        }

        // Get default tags from module settings
        // $ruleSettings is ignored here
        $tags = $moduleSettings['ntfyDefaultTags'] ?? '';

        try {
            // Call the helper function to perform the actual HTTP request
            $result = $this->sendToNtfy(
                $moduleSettings, // Pass global settings for connection details
                $title,
                $message,
                (string) $priority, // Ensure type consistency
                (string) $tags,
                $url // Pass the URL if available
            );

            // Log the outcome
            if ($result['success']) {
                 $this->logActivity("Successfully sent ntfy notification: '{$title}'");
            } else {
                // Log the error if sending failed, allowing WHMCS to continue
                $this->logActivity("Failed to send ntfy notification '{$title}'. Error: {$result['error']}");
                // Depending on desired behavior, you might throw an exception here,
                // but this could halt processing of other notifications. Logging is safer.
                // throw new \Exception("ntfy Send Error: " . $result['error']);
            }

        } catch (\Exception $e) {
            // Log any unexpected exceptions during the sending process
            $this->logActivity("Exception during ntfy sendNotification for '{$title}': " . $e->getMessage());
            // Potentially re-throw if this failure should be considered critical
            // throw $e;
        }
    }

    /**
     * Performs the actual HTTP POST request to the ntfy server using cURL.
     *
     * @param array $settings Module configuration (URL, topic, auth details).
     * @param string $title Notification title.
     * @param string $message Notification message body.
     * @param string $priority Notification priority ('1' through '5').
     * @param string $tags Comma-separated string of tags.
     * @param string|null $clickUrl Optional URL for the 'Click' action header.
     *
     * @return array Associative array: ['success' => bool, 'error' => ?string].
     */
    protected function sendToNtfy(array $settings, string $title, string $message, string $priority, string $tags, ?string $clickUrl): array
    {
        global $_LANG;
        $lang = $_LANG ?? [];

        // Validate essential settings
        $serverUrl = rtrim($settings['ntfyServerUrl'] ?? '', '/'); // Clean trailing slash
        $topic = $settings['ntfyTopic'] ?? '';

        if (empty($serverUrl) || empty($topic)) {
            return ['success' => false, 'error' => 'ntfy Server URL or Topic is not configured.'];
        }

        // Construct the full endpoint URL
        $fullUrl = $serverUrl . '/' . $topic;

        // --- Prepare cURL Request ---
        $ch = curl_init();

        // Basic options
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $message); // Send the message as the raw request body
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Connection/execution timeout in seconds
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Connection timeout
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Standard security practice
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);   // Verify hostname matches certificate

        // Prepare HTTP Headers
        $headers = [
            // ntfy primarily uses headers for metadata when the body is plain text
            'User-Agent: WHMCS ntfy Notification Module', // Identify the client
            'Title: ' . $this->encodeHeaderString($title), // Use helper to sanitize title
            // 'Content-Type: text/plain', // Explicitly set if needed, often inferred by ntfy
        ];

        // Add priority header if not default (ntfy assumes '3' if omitted)
        if ($priority !== '3' && $priority !== '') {
             $headers[] = 'Priority: ' . $priority;
        }
        // Add tags header if tags are provided
        if (!empty($tags)) {
            $headers[] = 'Tags: ' . $this->encodeHeaderString($tags); // Sanitize tags
        }
        // Add click URL header if provided
        if (!empty($clickUrl)) {
            $headers[] = 'Click: ' . $clickUrl; // Assume URL is safe, no extra encoding needed here
        }

        // Handle Authentication
        $authMethod = $settings['ntfyAuthMethod'] ?? 'None';

        if ($authMethod === 'Token') {
            $token = $settings['ntfyAuthToken'] ?? '';
            if (!empty($token)) {
                $headers[] = 'Authorization: Bearer ' . $token;
            } else {
                 $this->logActivity("ntfy Warning: Auth method is Token, but no token provided in settings.");
            }
        } elseif ($authMethod === 'Basic') {
            $username = $settings['ntfyUsername'] ?? '';
            // WHMCS should handle secure retrieval of password fields
            $password = $settings['ntfyPassword'] ?? '';
            if (!empty($username)) { // Password can technically be empty in basic auth
                 $headers[] = 'Authorization: Basic ' . base64_encode($username . ':' . $password);
            } else {
                 $this->logActivity("ntfy Warning: Auth method is Basic, but Username is missing in settings.");
            }
        }

        // Apply headers to cURL request
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Execute cURL request
        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrorNum = curl_errno($ch);
        $curlErrorMsg = curl_error($ch);
        curl_close($ch);
        // --- End cURL Request ---


        // --- Process Response ---
        if ($curlErrorNum !== CURLE_OK) {
            // cURL library error occurred (network issue, DNS, SSL, etc.)
            $errorMsg = ($lang['ntfy_curl_error'] ?? 'Curl Error: ') . $curlErrorNum . ' - ' . $curlErrorMsg;
            return ['success' => false, 'error' => $errorMsg];
        }

        // Check HTTP status code for success (2xx range indicates success for ntfy POST)
        if ($httpCode >= 200 && $httpCode < 300) {
             // Successful POST to ntfy
             return ['success' => true, 'error' => null];
        } else {
            // ntfy server returned an error (4xx, 5xx)
            $errorMsg = ($lang['ntfy_http_error'] ?? 'ntfy Server HTTP Error: Status ') . $httpCode;
            // Log server response for debugging, truncate long responses
            $this->logActivity("ntfy HTTP Error {$httpCode} for URL {$fullUrl}. Response: " . substr((string)$responseBody, 0, 500));
            return ['success' => false, 'error' => $errorMsg];
        }
    }

    /**
     * Basic sanitization for strings intended for HTTP headers.
     * Primarily removes newline characters which can break headers or cause injection.
     *
     * @param string $value The raw string.
     * @return string The sanitized string safe for header use.
     */
    protected function encodeHeaderString(string $value): string
    {
        // Replace CR and LF characters with empty string
        return str_replace(["\r", "\n"], '', $value);
        // For more complex international character support in headers, RFC 2047 encoding might be needed,
        // but ntfy seems robust with UTF-8 directly in headers. This basic sanitization is crucial.
    }

    /**
     * Logs activity using WHMCS's built-in module logging system.
     * Sensitive data specified in the last parameter will be automatically masked.
     *
     * @param string $message The primary log message.
     * @param array $data Optional additional data array (e.g., request details).
     */
    protected function logActivity(string $message, array $data = []): void
    {
        try {
            // Use WHMCS's logModuleCall function for standardized logging
            logModuleCall(
                'ntfy', // Module directory name (must match)
                debug_backtrace()[1]['function'] ?? __FUNCTION__, // Attempt to get the calling function name
                $message, // Log message (e.g., request string or description)
                $data,    // Request/context data (can be empty)
                null,     // Response data (can be null if not applicable)
                // Array of keys whose values should be masked in the log (matches config field names)
                ['ntfyAuthToken', 'ntfyPassword']
            );
        } catch (\Exception $e) {
            // Fallback logging if logModuleCall fails for some reason
            error_log("WHMCS ntfy Module - logActivity failed: " . $e->getMessage() . " | Original message: " . $message);
        }
    }
}
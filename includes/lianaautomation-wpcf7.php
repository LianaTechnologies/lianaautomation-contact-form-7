<?php
/**
 * LianaAutomation Contact Form 7 handler
 *
 * PHP Version 7.4
 *
 * @category Components
 * @package  WordPress
 * @author   Liana Technologies <websites@lianatech.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL-3.0-or-later
 * @link     https://www.lianatech.com
 */

/**
 * WPCF7 Function
 *
 * WPCF7 functionality for the plugin. Sends the information to Automation API
 *
 * @param $contact_form The actual form data from Contact Form 7
 *
 * @return null
 */
function Liana_Automation_wpcf7($contact_form)
{
    // Gets liana_t tracking cookie if set
    if (isset($_COOKIE['liana_t'])) {
        $liana_t = $_COOKIE['liana_t'];
    } else {
        // We shall send the form even without tracking cookie data
        $liana_t = null;
    }

    $submission = WPCF7_Submission::get_instance();

    if (!$submission) {
        return false;
    }

    /** 
    * Retrieve values from WPCF7 form submission:
    */
    $posted_data = $submission->get_posted_data();

    // Make some additional customization to data for Automation to work properly
    $posted_data['formtitle'] = $contact_form->title();
    $posted_data['formid'] = $contact_form->id();

    // If the WPCF7 default value for email not found, search entire submission
    // for partial match
    $email = null;
    if (empty($posted_data['your-email'])) {
        $iterator  = new RecursiveArrayIterator($posted_data);
        $recursive = new RecursiveIteratorIterator(
            $iterator,
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($recursive as $emailkey => $emailvalue) {
            if (preg_match("/email/i", $emailkey) && !empty($emailvalue)) {
                $email = $emailvalue;
                break;
            }
        }
    } else {
        // your-email was not empty, can assign here
        $email = $posted_data['your-email'];
    }

    // WPCF7 value for sms (tel-); search entire submission for partial match
    $sms = null;
    $iterator  = new RecursiveArrayIterator($posted_data);
    $recursive = new RecursiveIteratorIterator(
        $iterator,
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($recursive as $smskey => $smsvalue) {
        if (preg_match("/tel/i", $smskey) && !empty($smsvalue)) {
            $sms = $smsvalue;
            break;
        }
    }

    /** 
    * Retrieve Liana Options values (Array of All Options)
    */
    $lianaautomation_contactform7_options
        = get_option('lianaautomation_contactform7_options');

    if (empty($lianaautomation_contactform7_options)) {
        error_log("lianaautomation_contactform7_options was empty");
        return false;
    }

    // The user id, integer
    if (empty($lianaautomation_contactform7_options['lianaautomation_user'])) {
        error_log("lianaautomation_options lianaautomation_user was empty");
        return false;
    }
    $user   = $lianaautomation_contactform7_options['lianaautomation_user'];

    // Hexadecimal secret string
    if (empty($lianaautomation_contactform7_options['lianaautomation_key'])) {
        error_log(
            "lianaautomation_contactform7_options lianaautomation_key was empty!"
        );
        return false;
    }
    $secret = $lianaautomation_contactform7_options['lianaautomation_key'];

    // The base url for our API installation
    if (empty($lianaautomation_contactform7_options['lianaautomation_url'])) {
        error_log(
            "lianaautomation_contactform7_options lianaautomation_url was empty!"
        );
        return false;
    }
    $url    = $lianaautomation_contactform7_options['lianaautomation_url'];

    // The realm of our API installation, all caps alphanumeric string
    if (empty($lianaautomation_contactform7_options['lianaautomation_realm'])) {
        error_log(
            "lianaautomation_contactform7_options lianaautomation_realm was empty!"
        );
        return false;
    }
    $realm  = $lianaautomation_contactform7_options['lianaautomation_realm'];

    // The channel ID of our automation
    if (empty($lianaautomation_contactform7_options['lianaautomation_channel'])) {
        error_log(
            "lianaautomation_contactform7_options lianaautomation_channel was empty!"
        );
        return false;
    }
    $channel  = $lianaautomation_contactform7_options['lianaautomation_channel'];

    /**
    * General variables
    */
    $basePath    = 'rest';             // Base path of the api end points
    $contentType = 'application/json'; // Content will be send as json
    $method      = 'POST';             // Method is always POST

    // Build the identity array
    $identity = array();
    if (!empty($email)) {
        $identity["email"] = $email;
    }
    if (!empty($liana_t)) {
        $identity["token"] = $liana_t;
    }
    if (!empty($sms)) {
        $identity["sms"] = $sms;
    }

    // Bail out if no identities found
    if (empty($identity)) {
        return false;
    }

    // Import Data
    $path = 'v1/import';

    $data = array(
        "channel" => $channel,
        "no_duplicates" => false,
        "data" => [
            [
                "identity" => $identity,
                "events" => [
                    [
                        "verb" => "formsend",
                        "items" => $posted_data,
                    ],
                ]
            ]
        ]
    );
    
    // Encode our body content data
    $data = json_encode($data);
    // Get the current datetime in ISO 8601
    $date = date('c');
    // md5 hash our body content
    $contentMd5 = md5($data);
    // Create our signature
    $signatureContent = implode(
        "\n",
        [
            $method,
            $contentMd5,
            $contentType,
            $date,
            $data,
            "/{$basePath}/{$path}"
        ],
    );
    $signature = hash_hmac('sha256', $signatureContent, $secret);
    // Create the authorization header value
    $auth = "{$realm} {$user}:" . $signature;

    // Create our full stream context with all required headers
    $ctx = stream_context_create(
        [
        'http' => [
            'method' => $method,
            'header' => implode(
                "\r\n",
                [
                "Authorization: {$auth}",
                "Date: {$date}",
                "Content-md5: {$contentMd5}",
                "Content-Type: {$contentType}"
                ]
            ),
            'content' => $data
        ]
        ]
    );

    // Build full path, open a data stream, and decode the json response
    $fullPath = "{$url}/{$basePath}/{$path}";
    $fp = fopen($fullPath, 'rb', false, $ctx);
    $response = stream_get_contents($fp);
    $response = json_decode($response, true);
    
    //if (!empty($response)) {
    //    error_log(print_r($response, true));
    //}
}

add_action('wpcf7_mail_sent', 'Liana_Automation_wpcf7', 10, 2);

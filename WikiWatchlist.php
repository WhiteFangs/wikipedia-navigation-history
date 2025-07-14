<?php
// Database connection parameters
include('dbinfo.php');
// Your Wikipedia login credentials (username and password)
include('userinfo.php');

// Establish a database connection
$conn = new mysqli($dbhost, $dbAppLogin, $dbAppPassword, $dbAppName);

// Check for connection errors
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to send POST requests
function sendPostRequest($url, $data, $cookieFile, $headers)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

// Function to check if the article is a Talk page based on language
function isTalkArticle($title, $lang)
{
    // Define a list of Talk prefixes for each language
    $talkPrefixes = [
        'en' => 'Talk:',        // English
        'fr' => 'Discussion:',  // French
        // Add more languages and their Talk prefixes as needed
    ];

    // Check if the title starts with the corresponding Talk prefix for the given language
    if (isset($talkPrefixes[$lang])) {
        return strpos($title, $talkPrefixes[$lang]) === 0;
    }

    // If no prefix is defined for the language, assume it's not a Talk article
    return false;
}

// Function to remove an article from the watchlist using the Wikipedia API
function removeFromWatchlist($articlesToRemove, $lang, $csrfToken, $cookieFile, $headers)
{
    // Define the API URL based on language
    $apiUrl = "https://" . $lang . ".wikipedia.org/w/api.php";

    // Prepare the POST data to remove the article from the watchlist
    $data = [
        'action' => 'watch',
        'titles' => implode("|", $articlesToRemove),
        'unwatch' => '',  // This tells the API to remove the article from the watchlist
        'token' => $csrfToken,
        'format' => 'json'
    ];

    // Send the POST request to remove the article
    $response = sendPostRequest($apiUrl, $data, $cookieFile, $headers);
    $responseData = json_decode($response, true);

    // Check if the request was successful
    if (isset($responseData['batchcomplete'])) {
        echo "Articles removed from watchlist:\n";
        echo implode("\n", $articlesToRemove);
    } else {
        echo "Error removing articles from watchlist.\n";
        echo implode("\n", $articlesToRemove);
    }
}


// Define the languages you want to handle (English and French)
$languages = ['en', 'fr'];  // Array containing 'en' for English and 'fr' for French

// Loop through both languages
foreach ($languages as $lang) {
    // Define the Wikipedia API URL
    $apiUrl = "https://" . $lang . ".wikipedia.org/w/api.php";

    // Start a session to store cookies (necessary for maintaining login session)
    $cookieFile = tempnam(sys_get_temp_dir(), "wiki_session");
    $headers = [
        "User-Agent: PHP script",
    ];

    // Get the login token
    $data = [
        'action' => 'query',
        'meta' => 'tokens',
        'type' => '*',
        'format' => 'json'
    ];
    $response = sendPostRequest($apiUrl, $data, $cookieFile, $headers);
    $responseData = json_decode($response, true);
    $loginToken = $responseData['query']['tokens']['logintoken'];

    // Log in with the username and password to get a CSRF token
    $data = [
        'action' => 'login',
        'lgname' => $username,
        'lgpassword' => $password,
        'lgtoken' => $loginToken,
        'format' => 'json'
    ];
    $response = sendPostRequest($apiUrl, $data, $cookieFile, $headers);
    $responseData = json_decode($response, true);

    // Check if the login was successful
    if (isset($responseData['login']['result']) && $responseData['login']['result'] == 'Success') {
        echo "Login successful!\n";

        // Retrieve the raw watchlist (only titles)
        $data = [
            'action' => 'query',
            'list' => 'watchlistraw',
            'format' => 'json',
            'wrlimit' => 'max', // Fetch the maximum number of pages from the watchlist
        ];

        $response = sendPostRequest($apiUrl, $data, $cookieFile, $headers);
        $responseData = json_decode($response, true);

        // Check if the watchlist data is returned
        if (isset($responseData['watchlistraw'])) {
            echo "Fetching raw watchlist...\n";
            $currentWatchlist = [];
            $currentTimestamp = time(); // Use the current time as the timestamp for new entries

            // Get the CSRF token for watch actions
            $data = [
                'action' => 'query',
                'meta' => 'tokens',
                'type' => 'watch',
                'format' => 'json'
            ];
            $tokenResponse = sendPostRequest($apiUrl, $data, $cookieFile, $headers);
            $tokenResponseData = json_decode($tokenResponse, true);
            $csrfToken = $tokenResponseData['query']['tokens']['watchtoken'];

            $articlesToRemove = array();
            $watchlistraw = array_reverse($responseData['watchlistraw']);
            // Insert the new articles into the database and store their addition time
            foreach ($watchlistraw as $page) {
                $pageTitle = $page['title'];
                if (!isTalkArticle($pageTitle, $lang)) {

                    $insertSql = "INSERT INTO watchlist (title, language, added_at) VALUES (?, ?, ?)";
                    $insertStmt = $conn->prepare($insertSql);
                    $insertStmt->bind_param("ssi", $pageTitle, $lang, $currentTimestamp);

                    if ($insertStmt->execute()) {
                        echo "New article added to DB: $pageTitle\n";
                    } else {
                        echo "Error adding article to DB: $pageTitle\n";
                    }
                    $articlesToRemove[] = $pageTitle;
                }
            }
            if (count($articlesToRemove) > 0) {
                // Remove the articles from the watchlist
                removeFromWatchlist($articlesToRemove, $lang, $csrfToken, $cookieFile, $headers);
            }
        } else {
            echo "Failed to retrieve the raw watchlist.\n";
        }

    } else {
        echo "Login failed.\n";
    }
}

$conn->close();

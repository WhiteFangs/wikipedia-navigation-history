<?php
// Database connection parameters
include('dbinfo.php');
include('mastodonCredentials.php');
require_once('MastodonAPIExchange.php');
header('Content-Type: text/html; charset=utf-8');

$APIsettings = array(
    'oauth_access_token' => $oauth_access_token,
);
$mastodon = new MastodonAPIExchange($APIsettings);


$characterLimit = 500;

// Establish a database connection
$conn = new mysqli($dbhost, $dbAppLogin, $dbAppPassword, $dbAppName);

// Check for connection errors
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function getArticlesAddedInLast24Hours($conn)
{
    $currentTimestamp = time();
    $last24Hours = $currentTimestamp - 86400; // 24 hours ago

    // Retrieve articles added in the last 24 hours
    $sql = "SELECT title, language, added_at FROM watchlist WHERE added_at >= ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $last24Hours);
    $stmt->execute();
    $result = $stmt->get_result();

    $articles = [];
    while ($row = $result->fetch_assoc()) {
        if (!in_array($row['title'], array_column($articles, 'title'))) {
            $articles[] = $row;
        }
    }

    return $articles;
}

function sanitizeTitleToUrl($title) {
    // Capitalize first letter
    $title = ucfirst($title);
    
    // Replace spaces with underscores
    $title = str_replace(' ', '_', $title);
    
    // Percent-encode special characters
    $title = urlencode($title);
    
    // Convert underscores back (since urlencode also encodes them)
    $title = str_replace('%2F', '/', $title); // Preserve slashes
    $title = str_replace('%3A', ':', $title); // Preserve colons
    $title = str_replace('%23', '#', $title); // Preserve hash for sections
    $title = str_replace('%2B', '+', $title); // Keep plus signs as they are in some cases
    $title = str_replace('%40', '@', $title); // Keep @ signs

    return $title;
}

// Function to retrieve articles added in the last 24 hours
// Example of using the function to retrieve articles added in the last 24 hours
$articlesAddedToday = getArticlesAddedInLast24Hours($conn);

if (count($articlesAddedToday) > 0) {
    $posts = array();
    $text = "Articles Wikipédia consultés ces dernières 24h:\n\n";
    foreach ($articlesAddedToday as $article) {
        $url = "https://" . $article['language'] . ".wikipedia.org/wiki/" . sanitizeTitleToUrl($article['title']); // Construct the URL
        $line = "- " . date('H\hi', $article['added_at']) . ": " . $article['title'] . " " . $url . "\n";
        if (strlen($text . $line) > 500) {
            $posts[] = $text;
            $text = $line;
        } else {
            $text = $text . $line;
        }
    }
    $posts[] = $text;
    $replyId = null;
    foreach ($posts as $post) {
        $postfields = array(
            'status' =>  $post,
            'visibility' => 'public',
            'in_reply_to_id' => $replyId
        );
        $url = $instanceUrl . "/api/v1/statuses";
        $requestMethod = "POST";
        $response = $mastodon->resetFields()
            ->buildOauth($url, $requestMethod)
            ->setPostfields($postfields)
            ->performRequest();
        $response = json_decode($response);
        if (isset($response->id))
            $replyId = $response->id;
        var_dump($response);
    }
}

$conn->close();

# Wikipedia Navigation History

This repo present two PHP scripts:

- `WikiWatchlist.php` : Saves the articles in your Wikipedia Watchlist into a DB and clears your Watchlist
- `PostLastArticles.php` : Retrieves articles added to the DB in the last 24 hours and posts them to Mastodon

## Requirements

- Wikipedia account (where you do not care about the Watchlist)
- SQL database
- PHP server
- Cron job service
- Mastodon account

## How it works

Once you're logged in to your Wikipedia account, anytime you visit an article that you want to save to your DB, add it to your Watchlist (using the star icon in the UI).

The `WikiWatchlist.php` script connects to your Wikipedia account, takes the list of Watched articles and stores them in the SQL DB, then removes the articles from the Watchlist (to avoid duplicates next time it's run).

Then, the `PostLastArticles.php` script takes the articles added to your DB in the last 24 hours and posts them to Mastodon.

You can fork this repo to adapt the scripts for another use like receiving a weekly digest of your navigation history by email.

The current script works for French and English Wikipedia, but you can easily add or changes the languages by tweaking the scripts.

## Setup

### SQL DB

Create the SQL DB using this script:

```SQL
CREATE TABLE watchlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    language VARCHAR(10) NOT NULL,  -- New column for language (e.g., 'en' or 'fr')
    added_at INT NOT NULL
);
```

Create a `dbinfo.php` file with the relevant information that looks like this:

```php
<?php

$dbhost = '';
$dbAppName = '';
$dbAppLogin = '';
$dbAppPassword = '';
```

### Wikipedia User info

Create a `userinfo.php` file with the relevant information that looks like this:

```php
<?php

$username = "";
$password = "";
```

### Mastodon credentials

Create a `mastodonCredentials.php` file with the relevant information that looks like this:

```php
<?php

$oauth_access_token = "";
$instanceUrl = "";

```

### Cron jobs

Create two cron jobs:
- One that runs the `WikiWatchlist.php` script with the time granularity you want to have in the timestamps stored in your DB (I use 10 minutes)
- One that runs the `PostLastArticles.php` every day once a day (like Agent Cooper said, to give yourself a present)

## License

MIT - Bilgé Kimyonok
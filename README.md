# GitHub to Discord Webhook Relay

This simple application receives webhooks from GitHub, modifies the payload to replace GitHub user mentions with Discord user mentions, and relays the payload to a Discord channel via a webhook.

## Installation

### Prerequisites

- PHP >= 8.1
- Composer
- Laravel 10.x or later

### Steps

1. **Clone the Repository**

   ```bash
   git clone https://github.com/phpfour/github-discord-relay.git
   cd github-discord-relay
   ```
2. **Install Dependencies**

    ```bash
    composer install
    ```

3. **Configure Environment**

   Copy the .env.example file to .env and update the `DISCORD_WEBHOOK_URL` variable with your Discord webhook URL:

    ```bash
    cp .env.example .env
    ```
4. Generate Application Key

    ```
    php artisan key:generate
    ```
5. Update the GitHub User to Discord User mapping in the `config/github-discord.php` file.

    ```php
   'github_to_discord_map' => [
        // GitHub username => Discord user ID
        'phpfour' => '<@538057585698537506>',
    ],
    ```

6. Host the application on a server and point your GitHub webhook to the `/github/webhook` endpoint.

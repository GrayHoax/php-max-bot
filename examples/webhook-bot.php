<?php
/**
 * Webhook Bot Example (production-ready)
 *
 * Demonstrates how to run a MAX bot in webhook mode and how to register the
 * subscription with the MAX API.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * Requirements (effective 2026-05-25):
 *   • The webhook URL MUST be HTTPS.
 *   • The TLS certificate MUST be issued by a trusted CA (Let's Encrypt, a
 *     commercial CA, etc.). Self-signed certificates are no longer accepted.
 *   • HTTP webhooks and self-signed certificates are rejected by MAX.
 *
 * Long Polling is rate-limited and events have a limited server-side lifetime.
 * Do NOT use Long Polling in production — use Webhook on every environment
 * past local development.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Usage
 *
 *   1. Deploy this file to a public HTTPS endpoint, e.g.
 *      https://example.com/webhook.php
 *
 *   2. Register the subscription ONCE (from CLI or a one-shot script):
 *
 *        export BOT_TOKEN=your_token
 *        export WEBHOOK_URL=https://example.com/webhook.php
 *        php examples/webhook-bot.php --register
 *
 *   3. To change the event list or URL, just re-run --register with a new URL
 *      or event list. Re-registering with the same URL replaces its settings.
 *
 *   4. To remove the subscription:
 *
 *        php examples/webhook-bot.php --unregister
 *
 *   5. To inspect active subscriptions:
 *
 *        php examples/webhook-bot.php --list
 *
 * When invoked by the web server (POST from MAX), the same file processes
 * incoming updates.
 */

require_once __DIR__ . '/../src/PHPMaxBot.php';

$token = getenv('BOT_TOKEN');
if (!$token) {
    fwrite(STDERR, "BOT_TOKEN environment variable is required.\n");
    exit(1);
}

// In production, enable strict SSL verification for outgoing requests to the
// MAX API. The library disables it by default for development convenience.
$bot = new PHPMaxBot($token, [
    'curlOptions' => [
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ],
]);

// ── Subscription management (CLI subcommands) ────────────────────────────────

if (php_sapi_name() === 'cli') {
    $args = array_slice($argv, 1);

    if (in_array('--register', $args, true)) {
        $url = getenv('WEBHOOK_URL');
        if (!$url) {
            fwrite(STDERR, "WEBHOOK_URL environment variable is required for --register.\n");
            exit(1);
        }
        if (strpos($url, 'https://') !== 0) {
            fwrite(STDERR, "WEBHOOK_URL must use HTTPS. HTTP webhooks are no longer supported.\n");
            exit(1);
        }

        $events = [
            'message_created',
            'message_callback',
            'message_edited',
            'message_removed',
            'bot_started',
            'bot_added',
            'bot_removed',
            'user_added',
            'user_removed',
        ];

        $result = Bot::createSubscription($url, $events);
        echo "Subscription registered for $url\n";
        echo "Events: " . implode(', ', $events) . "\n";
        echo "Response: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
        exit(0);
    }

    if (in_array('--unregister', $args, true)) {
        $url = getenv('WEBHOOK_URL');
        if (!$url) {
            fwrite(STDERR, "WEBHOOK_URL environment variable is required for --unregister.\n");
            exit(1);
        }
        $result = Bot::deleteSubscription($url);
        echo "Subscription removed for $url\n";
        echo "Response: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
        exit(0);
    }

    if (in_array('--list', $args, true)) {
        $subs = Bot::getSubscriptions();
        echo json_encode($subs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        exit(0);
    }

    // No subcommand → fall through and run long polling (development only).
    fwrite(STDERR, "[warn] Running in Long Polling mode — not suitable for production.\n");
    fwrite(STDERR, "       Use --register / --unregister / --list to manage the webhook.\n");
}

// ── Handlers (shared between webhook and long polling) ───────────────────────

$bot->command('start', function () {
    return Bot::sendMessage('Webhook bot is up. Try /ping.');
});

$bot->command('ping', function () {
    return Bot::sendMessage('pong');
});

$bot->on('bot_started', function () {
    $name = PHPMaxBot::$currentUpdate['user']['first_name'] ?? 'there';
    return Bot::sendMessage("Hi, $name! This bot runs in webhook mode.");
});

// ── Entry point ──────────────────────────────────────────────────────────────
//
// PHPMaxBot::start() auto-detects the mode:
//   • CLI invocation  → Long Polling (development only)
//   • HTTP POST       → Webhook (production)

$bot->start();

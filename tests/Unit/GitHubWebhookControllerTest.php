<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\GitHubWebhookController;
use ReflectionMethod;
use Tests\TestCase;

class GitHubWebhookControllerTest extends TestCase
{
    public function test_modify_payload_replaces_review_comment_user_and_mentions(): void
    {
        config()->set('user_mapping.github', [
            'octocat' => '<@1234567890>',
        ]);

        $payload = [
            'action' => 'submitted',
            'review' => [
                'body' => 'Looks good to me @octocat.',
                'user' => [
                    'login' => 'octocat',
                ],
            ],
        ];

        $controller = new GitHubWebhookController();
        $modified = $this->invokeModifyPayload($controller, $payload);

        $this->assertSame('<@1234567890>', $modified['review']['user']['login']);
        $this->assertSame('Looks good to me <@1234567890>.', $modified['review']['body']);
    }

    public function test_modify_payload_replaces_issue_comment_mentions(): void
    {
        config()->set('user_mapping.github', [
            'octocat' => '<@1234567890>',
        ]);

        $payload = [
            'action' => 'created',
            'comment' => [
                'body' => 'Thanks @octocat!',
            ],
        ];

        $modified = $this->invokeModifyPayload(new GitHubWebhookController(), $payload);

        $this->assertSame('Thanks <@1234567890>!', $modified['comment']['body']);
    }

    public function test_modify_payload_replaces_pr_body_mentions(): void
    {
        config()->set('user_mapping.github', [
            'octocat' => '<@1234567890>',
        ]);

        $payload = [
            'action' => 'opened',
            'pull_request' => [
                'body' => 'Please review this, @octocat.',
            ],
        ];

        $modified = $this->invokeModifyPayload(new GitHubWebhookController(), $payload);

        $this->assertSame('Please review this, <@1234567890>.', $modified['pull_request']['body']);
    }

    public function test_modify_payload_does_not_replace_substrings_or_unrelated_handles(): void
    {
        config()->set('user_mapping.github', [
            'octo' => '<@1111111111>',
        ]);

        $payload = [
            'comment' => [
                'body' => 'Email octo@example.com. Thanks @octo-cat!',
            ],
        ];

        $modified = $this->invokeModifyPayload(new GitHubWebhookController(), $payload);

        $this->assertSame('Email octo@example.com. Thanks @octo-cat!', $modified['comment']['body']);
    }

    public function test_modify_payload_replaces_multiple_users_in_one_string(): void
    {
        config()->set('user_mapping.github', [
            'octocat' => '<@1234567890>',
            'hubot' => '<@2222222222>',
        ]);

        $payload = [
            'comment' => [
                'body' => 'Ping @octocat and @hubot.',
            ],
        ];

        $modified = $this->invokeModifyPayload(new GitHubWebhookController(), $payload);

        $this->assertSame('Ping <@1234567890> and <@2222222222>.', $modified['comment']['body']);
    }

    private function invokeModifyPayload(GitHubWebhookController $controller, array $payload): array
    {
        $method = new ReflectionMethod($controller, 'modifyPayload');
        $method->setAccessible(true);

        return $method->invoke($controller, $payload);
    }
}

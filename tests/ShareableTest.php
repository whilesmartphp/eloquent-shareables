<?php

namespace Whilesmart\Shareables\Tests;

use Whilesmart\Shareables\Models\Share;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

class ShareableTest extends TestCase
{
    public function test_it_can_create_a_share_for_a_model(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $post = Post::create([
            'title' => 'Test Post',
        ]);

        $share = $post->share('read', $user, 'team_123');

        $this->assertInstanceOf(Share::class, $share);
        $this->assertEquals('read', $share->access);
        $this->assertEquals('team_123', $share->audience);
        $this->assertNotNull($share->token);
        $this->assertEquals(User::class, $share->owner_type);
        $this->assertEquals($user->id, $share->owner_id);

        $this->assertDatabaseHas('shares', [
            'shareable_type' => Post::class,
            'shareable_id' => $post->id,
            'audience' => 'team_123',
            'access' => 'read',
        ]);
    }

    public function test_it_generates_token_if_none_provided(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $post = Post::create([
            'title' => 'Test Post',
        ]);

        $share = $post->share('view', $user);

        $this->assertNotNull($share->token);
        $this->assertEquals(40, strlen($share->token));
    }

    public function test_it_can_check_if_shared_with_audience(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $post = Post::create([
            'title' => 'Test Post',
        ]);

        $this->assertFalse($post->isSharedWith('team_123'));

        $post->share('read', $user, 'team_123');

        $this->assertTrue($post->isSharedWith('team_123'));
        $this->assertFalse($post->isSharedWith('team_456'));
    }

    public function test_it_does_not_count_revoked_shares_as_shared(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $post = Post::create([
            'title' => 'Test Post',
        ]);

        $post->share('read', $user, 'team_123');

        $this->assertTrue($post->isSharedWith('team_123'));

        $post->revokeShare('team_123');

        $this->assertFalse($post->isSharedWith('team_123'));
    }

    public function test_it_can_check_if_shared_via_token(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $post = Post::create([
            'title' => 'Test Post',
        ]);

        $share = $post->share('view', $user, null, 'secret-token-xyz');

        $this->assertTrue($post->isSharedViaToken('secret-token-xyz'));
        $this->assertFalse($post->isSharedViaToken('other-token'));
    }

    public function test_it_can_revoke_share_for_audience(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $post = Post::create([
            'title' => 'Test Post',
        ]);

        $post->share('read', $user, 'team_123');
        $post->share('read', $user, 'team_456');

        $post->revokeShare('team_123');

        $this->assertFalse($post->isSharedWith('team_123'));
        $this->assertTrue($post->isSharedWith('team_456'));
    }

    public function test_it_can_revoke_all_shares(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $post = Post::create([
            'title' => 'Test Post',
        ]);

        $post->share('read', $user, 'team_123');
        $post->share('read', $user, 'team_456');

        $post->revokeAllShares();

        $this->assertFalse($post->isSharedWith('team_123'));
        $this->assertFalse($post->isSharedWith('team_456'));
    }

    public function test_it_can_scope_query_to_shared_with_audience(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $post1 = Post::create(['title' => 'Post 1']);
        $post2 = Post::create(['title' => 'Post 2']);

        $post1->share('read', $user, 'team_123');

        $sharedPosts = Post::sharedWith('team_123')->get();

        $this->assertCount(1, $sharedPosts);
        $this->assertEquals($post1->id, $sharedPosts->first()->id);
    }

    public function test_owner_and_shareable_relationships_on_share_model(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $post = Post::create([
            'title' => 'Test Post',
        ]);

        $share = $post->share('read', $user, 'team_123');

        $this->assertInstanceOf(User::class, $share->owner);
        $this->assertEquals($user->id, $share->owner->id);

        $this->assertInstanceOf(Post::class, $share->shareable);
        $this->assertEquals($post->id, $share->shareable->id);
    }
}

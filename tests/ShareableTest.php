<?php

namespace Whilesmart\Shareables\Tests;

use Illuminate\Database\QueryException;
use InvalidArgumentException;
use Whilesmart\Shareables\Models\Audience;
use Whilesmart\Shareables\Models\Share;
use Workbench\App\Models\Post;
use Workbench\App\Models\SoftPost;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;

class ShareableTest extends TestCase
{
    private function user(string $name): User
    {
        return User::create(['name' => $name, 'email' => strtolower($name) . '@example.test', 'password' => 'test-password']);
    }

    public function test_one_link_can_target_users_and_teams_with_different_abilities(): void
    {
        $alice = $this->user('Alice');
        $bob = $this->user('Bob');
        $outsider = $this->user('Outsider');
        $team = Team::create(['name' => 'Household']);
        $team->members()->attach($bob);
        $post = Post::create(['title' => 'Shared plan']);
        $share = $post->share([$alice, $team], 'read', createdBy: $alice);
        $share->setAudience($alice, 'edit');
        $this->assertSame(['edit'], Share::resolveToken($share->token, $alice));
        $this->assertSame(['read'], Share::resolveToken($share->token, $bob));
        $this->assertSame([], Share::resolveToken($share->token, $outsider));
        $this->assertSame([], Share::resolveToken($share->token));
        $this->assertSame($alice->id, $share->createdBy->id);
        $this->assertSame($post->id, $share->shareable->id);
        $this->assertCount(2, $share->audiences);
    }

    public function test_a_viewer_receives_every_matching_ability_without_package_ranking(): void
    {
        $user = $this->user('Alice');
        $team = Team::create(['name' => 'Household']);
        $team->members()->attach($user);
        $share = Post::create(['title' => 'Plan'])->share([$user, $team], 'reader');
        $share->setAudience($team, 'reviewer');
        $share->setAudience(null, 'guest');
        $this->assertEqualsCanonicalizing(['reader', 'reviewer', 'guest'], $share->abilitiesFor($user));
        $this->assertSame(['guest'], $share->abilitiesFor(null));
    }

    public function test_no_audiences_is_private_and_public_access_must_be_explicit(): void
    {
        $post = Post::create(['title' => 'Plan']);
        $share = $post->share([], 'read');
        $this->assertFalse($post->isSharedWith(null));
        $this->assertSame([], $share->abilitiesFor(null));
        $share->setAudience(null, 'read');
        $share->setAudience(null, 'view');
        $this->assertCount(1, $share->audiences);
        $this->assertSame(['view'], $share->abilitiesFor(null));
        $share->removeAudience(null);
        $this->assertSame([], $share->abilitiesFor(null));
        $this->assertFalse($post->isSharedViaToken($share->token));
    }

    public function test_recipient_ids_do_not_match_other_model_types(): void
    {
        $user = $this->user('Alice');
        $team = Team::create(['name' => 'Household']);
        $this->assertSame($user->id, $team->id);
        $post = Post::create(['title' => 'Plan']);
        $post->share($team, 'read');
        $this->assertTrue($post->isSharedWith($team, 'read'));
        $this->assertFalse($post->isSharedWith($user));
        $this->assertSame(1, Post::sharedWith($team, 'read')->count());
        $this->assertSame(0, Post::sharedWith($user)->count());
        $this->assertSame(0, Post::sharedWith($team, 'edit')->count());
    }

    public function test_expired_and_revoked_links_are_rejected_by_every_read_path(): void
    {
        $user = $this->user('Alice');
        $post = Post::create(['title' => 'Plan']);
        $expired = $post->share($user, 'read', constraints: ['expires_at' => now()->subSecond()]);
        $this->assertSame([], Share::resolveToken($expired->token, $user));
        $this->assertFalse($post->isSharedViaToken($expired->token, $user));
        $this->assertFalse($post->isSharedWith($user));
        $this->assertSame(0, Post::sharedWith($user)->count());
        $active = $post->share($user, 'read', constraints: ['expires_at' => now()->addHour()]);
        $this->assertSame(['read'], $active->abilitiesFor($user));
        $post->revokeAllShares();
        $this->assertSame([], $active->abilitiesFor($user));
        $this->assertFalse($post->isSharedWith($user));
    }

    public function test_revoking_one_audience_keeps_other_recipients_on_the_same_link(): void
    {
        $alice = $this->user('Alice');
        $bob = $this->user('Bob');
        $post = Post::create(['title' => 'Plan']);
        $share = $post->share([$alice, $bob], 'read');
        $this->assertSame(['read'], $share->abilitiesFor($alice));
        $post->revokeShare($alice);
        $this->assertSame([], $share->abilitiesFor($alice));
        $this->assertSame(['read'], $share->abilitiesFor($bob));
    }

    public function test_membership_removal_changes_admission_immediately(): void
    {
        $user = $this->user('Alice');
        $team = Team::create(['name' => 'Household']);
        $team->members()->attach($user);
        $share = Post::create(['title' => 'Plan'])->share($team, 'read');
        $this->assertSame(['read'], $share->abilitiesFor($user));
        $team->members()->detach($user);
        $this->assertSame([], $share->abilitiesFor($user));
    }

    public function test_passwords_are_hashed_hidden_and_required_for_admission(): void
    {
        $post = Post::create(['title' => 'Plan']);
        $share = $post->share(null, 'read', constraints: ['password' => 'test-passphrase']);
        $this->assertNotSame('test-passphrase', $share->password);
        $this->assertArrayNotHasKey('password', $share->toArray());
        $this->assertSame([], Share::resolveToken($share->token));
        $this->assertSame([], Share::resolveToken($share->token, password: 'wrong'));
        $this->assertSame(['read'], Share::resolveToken($share->token, password: 'test-passphrase'));
        $this->assertSame(0, Post::sharedWith(null)->count());
    }

    public function test_successful_token_resolution_consumes_the_use_allowance(): void
    {
        $user = $this->user('Alice');
        $post = Post::create(['title' => 'Plan']);
        $share = $post->share($user, 'read', constraints: ['max_uses' => 1]);
        $this->assertSame([], Share::resolveToken($share->token));
        $this->assertSame(0, $share->fresh()->uses);
        $this->assertSame(['read'], Share::resolveToken($share->token, $user));
        $this->assertSame([], Share::resolveToken($share->token, $user));
        $this->assertSame(1, $share->fresh()->uses);
        $this->assertFalse($post->isSharedWith($user));
    }

    public function test_duplicate_tokens_are_rejected_by_the_database(): void
    {
        $post = Post::create(['title' => 'Plan']);
        $post->share(null, 'read', constraints: ['token' => 'test-only-token']);
        $this->expectException(QueryException::class);
        $post->share(null, 'read', constraints: ['token' => 'test-only-token']);
    }

    public function test_invalid_audiences_roll_back_the_entire_share(): void
    {
        $user = $this->user('Alice');
        $post = Post::create(['title' => 'Plan']);
        try {
            $post->share([$user, $post], 'read');
            $this->fail('A resource without an audience admission contract must not become a recipient.');
        } catch (InvalidArgumentException) {
            $this->assertSame(0, Share::count());
            $this->assertSame(0, Audience::count());
        }
    }

    public function test_hard_deleting_a_resource_removes_links_and_audiences(): void
    {
        $user = $this->user('Alice');
        $post = Post::create(['title' => 'Plan']);
        $post->share($user, 'read');
        $post->delete();
        $this->assertSame(0, Share::withTrashed()->count());
        $this->assertSame(0, Audience::count());
    }

    public function test_soft_deletion_revokes_links_and_restore_does_not_regrant_them(): void
    {
        $user = $this->user('Alice');
        $post = SoftPost::create(['title' => 'Plan']);
        $share = $post->share($user, 'read');
        $post->delete();
        $this->assertSame([], $share->abilitiesFor($user));
        $post->restore();
        $this->assertFalse($post->isSharedWith($user));
        $post->forceDelete();
        $this->assertSame(0, Share::withTrashed()->count());
        $this->assertSame(0, Audience::count());
    }

    public function test_deleting_the_last_recipient_does_not_make_a_link_public(): void
    {
        $user = $this->user('Alice');
        $share = Post::create(['title' => 'Plan'])->share($user, 'read');
        $user->delete();
        $this->assertSame(0, Audience::count());
        $this->assertSame([], $share->abilitiesFor(null));
    }

    public function test_query_scopes_support_creator_and_resource_filters(): void
    {
        $user = $this->user('Alice');
        $post = Post::create(['title' => 'Plan']);
        $share = $post->share($user, 'read', createdBy: $user);
        $this->assertSame([$share->id], Share::byCreator($user)->forResource($post)->pluck('id')->all());
    }
    public function test_a_stale_model_cannot_publish_a_share_after_resource_deletion(): void
    {
        $user = $this->user('Alice');
        $post = Post::create(['title' => 'Plan']);
        $stale = Post::findOrFail($post->id);
        $post->delete();
        try {
            $stale->share($user, 'read');
            $this->fail('A deleted resource must not gain a new sharing grant.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->assertDatabaseCount('shares', 0);
            $this->assertDatabaseCount('share_audiences', 0);
        }
    }

    public function test_configured_model_subclasses_are_used_by_relations(): void
    {
        config(['shareables.models.share' => CustomShare::class, 'shareables.models.audience' => CustomAudience::class]);
        $user = $this->user('Alice');
        $share = Post::create(['title' => 'Plan'])->share($user, 'read');
        $this->assertInstanceOf(CustomShare::class, $share);
        $this->assertInstanceOf(CustomAudience::class, $share->audiences->first());
        $this->assertSame(['read'], CustomShare::resolveToken($share->token, $user));
    }

    public function test_invalid_model_configuration_fails_with_a_clear_error(): void
    {
        config(['shareables.models.share' => Post::class]);
        $this->expectException(InvalidArgumentException::class);
        (new \Whilesmart\Shareables\ShareablesServiceProvider($this->app))->boot();
    }

    public function test_morph_aliases_are_preserved_for_resources_recipients_and_creators(): void
    {
        $previous = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap(['post' => Post::class, 'person' => User::class], false);
        try {
            $user = $this->user('Alice');
            $post = Post::create(['title' => 'Plan']);
            $share = $post->share($user, 'read', createdBy: $user);
            $this->assertDatabaseHas('shares', ['id' => $share->id, 'shareable_type' => 'post', 'created_by_type' => 'person']);
            $this->assertDatabaseHas('share_audiences', ['share_id' => $share->id, 'audience_type' => 'person']);
            $this->assertTrue($post->isSharedWith($user, 'read'));
            $this->assertSame(['read'], $share->abilitiesFor($user));
        } finally {
            \Illuminate\Database\Eloquent\Relations\Relation::morphMap($previous, false);
        }
    }

}

class CustomShare extends Share
{
    protected $table = 'shares';
}

class CustomAudience extends Audience
{
}

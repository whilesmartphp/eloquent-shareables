# Usage Guide

**Eloquent Shareables** provides a clean polymorphic trait and model relationship to manage sharing database records with other users, teams, or any custom audience. It supports tracking access levels, owner information, and unique share tokens.

## Preparing Your Models

To make an Eloquent model shareable, import and use the `Shareable` trait:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Whilesmart\Shareables\Traits\Shareable;

class Post extends Model
{
    use Shareable;
}
```

## How It Works

### 1. Creating a Share

You can share a model by calling `share()`. This requires an access level (e.g. `'read'`, `'write'`, `'admin'`) and an owner Model (e.g., the User sharing the record):

```php
$user = User::find(1);

// Share a post with a team with 'read' access
$share = $post->share('read', $user, 'team_123');

// Share a post publicly (generating a random unique token automatically)
$publicShare = $post->share('view', $user);
```

The parameters of the `share` method are:
1. `string $access` — The access level/permission string.
2. `Model $owner` — The Eloquent model representing the owner/creator of the share.
3. `?string $audience` (optional) — The target audience (e.g., a team ID, department name, or user reference).
4. `?string $token` (optional) — A specific token to use. If not provided, a random 40-character string will be generated.

### 2. Checking Share Status

You can verify whether a model is shared with a specific audience or via a specific token using helper methods:

```php
// Check if the post is shared with 'team_123'
if ($post->isSharedWith('team_123')) {
    // Has access!
}

// Check if the post is shared via a specific secret link token
if ($post->isSharedViaToken($token)) {
    // Has access!
}
```

> [!NOTE]
> These methods only look for active shares (where `revoked_at` is `null`).

### 3. Revoking Share Access

To revoke access, set a revocation timestamp on the share records:

```php
// Revoke sharing for a specific audience
$post->revokeShare('team_123');

// Revoke all shares (e.g. public links and team shares) for this model
$post->revokeAllShares();
```

### 4. Querying Shared Models

If you want to retrieve all models that have been shared with a particular audience, use the `sharedWith` query scope:

```php
$sharedPosts = Post::sharedWith('team_123')->get();
```

### 5. Accessing Relationships Directly

You can access the relationship or the share properties directly:

```php
// Get all shares for the model
$shares = $post->shares;

foreach ($shares as $share) {
    // Get the owner of the share
    $owner = $share->owner; // e.g. User model

    // Get the shared model itself
    $sharedModel = $share->shareable; // e.g. Post model

    // Check properties
    $access = $share->access;
    $token = $share->token;
    $revokedAt = $share->revoked_at;
}
```

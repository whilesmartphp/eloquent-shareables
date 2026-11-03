# Recipient admission

Recipient models implement `ShareAudience`. Add `ReceivesShares` to remove their audience rows when they are permanently deleted.

```php
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Whilesmart\Shareables\Contracts\ShareAudience;
use Whilesmart\Shareables\Traits\ReceivesShares;

class User extends AuthenticatableUser implements ShareAudience
{
    use ReceivesShares;

    public function admits(?Authenticatable $viewer): bool
    {
        return $viewer instanceof self && $this->is($viewer);
    }
}
```

A team or workspace implements the same contract using its current membership rules. The package does not assume a membership table or role hierarchy.

## Resource sharing

Add `Whilesmart\Shareables\Traits\Shareable` to the resource model, then create a link:

```php
$share = $document->share([$alice, $team], 'reader', createdBy: $alice);
$share->setAudience($alice, 'editor');

$abilities = Share::resolveToken($share->token, $viewer);
```

`resolveToken` returns every distinct ability admitted by matching audiences. It does not rank abilities. A viewer matching both a user and team audience receives both abilities.

An empty audience list grants nobody access. Public access requires an explicit null audience:

```php
$private = $document->share([], 'reader');
$public = $document->share(null, 'reader');
```

Removing the final audience makes a link private. It never makes it public.

## Link constraints

```php
$share = $document->share($team, 'reader', createdBy: $alice, constraints: [
    'expires_at' => now()->addDay(),
    'password' => 'a-passphrase-chosen-by-the-user',
    'max_uses' => 10,
]);

$abilities = Share::resolveToken($share->token, $viewer, $providedPassword);
```

Passwords are hashed and hidden from serialization. Token resolution checks expiry, revocation, password and current recipient admission. A successful resolution consumes one use; rejected resolutions consume none. The use limit is checked while locking the link inside a database transaction.

## Queries and revocation

```php
$documents = Document::sharedWith($team, 'reader')->get();
$shared = $document->isSharedWith($team, 'reader');
$sharedViaLink = $document->isSharedViaToken($token, $viewer, 'reader', $password);

$document->revokeShare($team);
$document->revokeAllShares();
```

Audience queries include active, unprotected links only. They select grants for an audience; the application must authorize the caller's access to that audience. Use token resolution when checking password-protected links or consuming a use allowance.

Revoking an audience keeps other recipients on the same link. Soft-deleting a resource revokes its links, and restoring it does not restore access. Permanently deleting it removes links and their audience rows. Enable foreign keys when using SQLite so audience cleanup cascades.

## Host model customization

`config/shareables.php` allows subclasses of `Share` and `Audience`:

```php
'models' => [
    'share' => App\Models\Share::class,
    'audience' => App\Models\ShareAudience::class,
],
```

The configured models use the package's columns and relations. Application-specific membership, authorization, response formatting and financial calculations remain with the host application.

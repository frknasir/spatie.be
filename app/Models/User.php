<?php

namespace App\Models;

use App\Enums\PurchasableType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Paddle\Billable;
use Spatie\Mailcoach\Models\EmailList;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;
    use Billable;
    use Notifiable;

    protected $hidden = [
        'password', 'remember_token',
    ];

    public $casts = [
        'is_admin' => 'bool',
    ];

    public function getPayLinkForProductId(string $paddleProductId)
    {
        /*
        $purchasable = Purchasable::findForPaddleProductId($paddleProductId);

        [$amountInCents, $currency] = $purchasable->getPriceForIp(request()->ip());

        $amountWithDecimals = $amountInCents / 100;

        $prices[] = "{$currency}:{$amountWithDecimals}";
        if ($currency !== 'USD') {
            $priceInDollars = $purchasable->price_in_usd_cents / 100;
            $prices[] = "USD:{$priceInDollars}";
        }
        */

        return $this->chargeProduct($paddleProductId, [
            'quantity_variable' => false,
            'customer_email' => auth()->user()->email,
            'marketing_consent' => true,
            //'prices' => $prices,
        ]);
    }

    public function isSponsoring(): bool
    {
        if ($this->isSpatieMember()) {
            return true;
        }

        return (bool)$this->is_sponsor;
    }

    public function isSubscribedToNewsletter(): bool
    {
        if (! $this->email) {
            return false;
        }

        /** @var EmailList $emailList */
        $emailList = EmailList::firstWhere('name', 'Spatie');

        if (! $emailList) {
            return false;
        }

        return $emailList->isSubscribed($this->email);
    }

    public function isSpatieMember(): bool
    {
        return Member::where('github', $this->github_username)->exists();
    }

    public function owns(Purchasable $purchasable): bool
    {
        return $this->purchases()->where('purchasable_id', $purchasable->id)->exists();
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    public function licensesWithoutRenewals(): HasMany
    {
        return $this->hasMany(License::class)->whereHas('purchasable', function (Builder $query): void {
            $query->whereNotIn('type', [
                PurchasableType::TYPE_STANDARD_RENEWAL,
                PurchasableType::TYPE_UNLIMITED_DOMAINS_RENEWAL,
            ]);
        });
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class)->with('purchasable.product');
    }

    public function purchasesWithoutRenewals(): HasMany
    {
        return $this->hasMany(Purchase::class)->whereHas('purchasable', function (Builder $query): void {
            $query->whereNotIn('type', [
                PurchasableType::TYPE_STANDARD_RENEWAL,
                PurchasableType::TYPE_UNLIMITED_DOMAINS_RENEWAL,
            ]);
        });
    }

    public function completedVideos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class, 'video_completions')->withTimestamps();
    }

    public function hasAccessToUnreleasedPurchasables(): bool
    {
        return $this->isSpatieMember();
    }
}

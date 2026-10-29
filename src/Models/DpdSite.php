<?php

declare(strict_types=1);

namespace WeBRTeu\Dpd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * @property int $id
 * @property int $site_id
 * @property string $name
 * @property string|null $municipality
 * @property string|null $region
 * @property string|null $post_code
 * @property string $name_key
 * @property string $region_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DpdSite extends Model
{
    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Config::string('dpd.tables.sites', 'dpd_sites');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['site_id' => 'integer'];
    }
}

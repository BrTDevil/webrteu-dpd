<?php

declare(strict_types=1);

namespace WeBRTeu\Dpd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * @property int $id
 * @property int $office_id
 * @property string $type
 * @property string $name
 * @property string|null $county
 * @property string $city
 * @property string $address
 * @property string|null $postal_code
 * @property float|null $lat
 * @property float|null $lng
 * @property bool $supports_cod
 * @property bool $active
 * @property array<array-key, mixed>|null $raw
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DpdOffice extends Model
{
    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Config::string('dpd.tables.offices', 'dpd_offices');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'office_id' => 'integer',
            'lat' => 'float',
            'lng' => 'float',
            'supports_cod' => 'boolean',
            'active' => 'boolean',
            'raw' => 'array',
        ];
    }
}

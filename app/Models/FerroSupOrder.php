<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sup_order_id
 * @property int $bitrix_contact_id
 * @property string $contact_sup_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static Builder|FerroSupOrder newModelQuery()
 * @method static Builder|FerroSupOrder newQuery()
 * @method static Builder|FerroSupOrder query()
 * @method static Builder|FerroSupOrder whereBitrixContactId($value)
 * @method static Builder|FerroSupOrder whereContactSupId($value)
 * @method static Builder|FerroSupOrder whereCreatedAt($value)
 * @method static Builder|FerroSupOrder whereId($value)
 * @method static Builder|FerroSupOrder whereSupOrderId($value)
 * @method static Builder|FerroSupOrder whereUpdatedAt($value)
 * @mixin Eloquent
 */
class FerroSupOrder extends Model
{
    use HasFactory;
}

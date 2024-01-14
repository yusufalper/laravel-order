<?php

namespace Alper\LaravelOrder\Traits;

use Alper\LaravelOrder\Services\OrderService;
use Exception;
use Illuminate\Support\Facades\DB;
use Throwable;

/*
 * Requires 'order' (integer and nullable) attribute from Model Migration
 * Gets optional $orderUnificationAttributes property from Model Class.
 */
trait HasOrder
{
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            DB::beginTransaction();
            try {
                $model->order = (new OrderService($model))->saveNew($model->order);
            } catch (Exception|Throwable $e) {
                DB::rollBack();
                throw $e;
            }
            DB::commit();
        });
        static::updating(function ($model) {
            if ($model->order !== $model->getOriginal('order')) {
                DB::beginTransaction();
                try {
                    $model->order = (new OrderService($model))->updateOrder();
                } catch (Exception|Throwable $e) {
                    DB::rollBack();
                    throw $e;
                }
                DB::commit();
            }
        });
        static::deleting(function ($model) {
            DB::beginTransaction();
            try {
                (new OrderService($model))->safeDeleteWithOrder();
            } catch (Exception|Throwable $e) {
                DB::rollBack();
                throw $e;
            }
            DB::commit();
        });
        static::updated(function ($model) {
            DB::beginTransaction();
            try {
                if ($model->order !== $model->getOriginal('order')) {
                    OrderService::arrangeAllOrders($model);
                }
            } catch (Exception|Throwable $e) {
                DB::rollBack();
                throw $e;
            }
            DB::commit();
        });
    }
}
